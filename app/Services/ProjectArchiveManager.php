<?php

namespace App\Services;

use App\Exceptions\ArchiveValidationException;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class ProjectArchiveManager
{
    private const BYTES_PER_MB = 1_048_576;

    public function replace(Project $project, UploadedFile $archive): ProjectUpload
    {
        return Cache::lock("project-files:user:{$project->user_id}", 300)->block(10, function () use ($project, $archive): ProjectUpload {
            $project = Project::query()->with('user.plan')->findOrFail($project->id);
            $plan = $project->user->plan;

            if (! $plan) {
                throw new ArchiveValidationException('Choose a hosting plan before uploading website files.');
            }

            $archivePath = $archive->getRealPath();
            $archiveSize = $archive->getSize();

            if (! $archivePath || $archiveSize === false) {
                throw new ArchiveValidationException('The uploaded ZIP could not be read.');
            }

            if ($archiveSize > $plan->max_upload_mb * self::BYTES_PER_MB) {
                throw new ArchiveValidationException("The ZIP may not be larger than {$plan->max_upload_mb} MB.");
            }

            $zip = new ZipArchive;
            $openResult = $zip->open($archivePath, ZipArchive::RDONLY);

            if ($openResult !== true) {
                throw new ArchiveValidationException('The uploaded file is not a valid ZIP archive.');
            }

            $disk = Storage::disk(config('hosting.project_disk'));
            $stagingRelative = '.staging/'.Str::uuid();
            $stagingPath = $disk->path($stagingRelative);

            try {
                $entries = $this->inspect($zip, $project);
                $otherUsage = (int) $project->user->projects()->whereKeyNot($project->id)->sum('storage_bytes');
                $storageLimit = $plan->storage_mb * self::BYTES_PER_MB;

                if ($otherUsage + array_sum(array_column($entries, 'declared_size')) > $storageLimit) {
                    throw new ArchiveValidationException('Extracting this ZIP would exceed your plan storage limit.');
                }

                File::ensureDirectoryExists($stagingPath, 0750, true);
                [$files, $extractedSize] = $this->extract($zip, $entries, $stagingPath, $plan->max_extracted_mb * self::BYTES_PER_MB);

                if ($otherUsage + $extractedSize > $storageLimit) {
                    throw new ArchiveValidationException('Extracting this ZIP would exceed your plan storage limit.');
                }

                $this->assertEntrypointExists($project, $files);

                return $this->swapIntoPlace(
                    project: $project,
                    archive: $archive,
                    archivePath: $archivePath,
                    archiveSize: $archiveSize,
                    stagingPath: $stagingPath,
                    files: $files,
                    extractedSize: $extractedSize,
                );
            } finally {
                $zip->close();

                if (File::isDirectory($stagingPath)) {
                    File::deleteDirectory($stagingPath);
                }
            }
        });
    }

    public function deleteFile(Project $project, ProjectFile $file): void
    {
        Cache::lock("project-files:user:{$project->user_id}", 300)->block(10, function () use ($project, $file): void {
            $project->refresh();
            $file = ProjectFile::query()->findOrFail($file->id);

            if ($file->project_id !== $project->id) {
                abort(404);
            }

            $disk = Storage::disk(config('hosting.project_disk'));
            $relativePath = $project->storageDirectory().'/'.$file->path;
            $absolutePath = $this->assertPathBelongsToProject($project, $file);
            $trashPath = $disk->path('.trash/'.Str::uuid());
            File::ensureDirectoryExists(dirname($trashPath), 0750, true);

            if (! rename($absolutePath, $trashPath)) {
                throw new ArchiveValidationException('The file could not be prepared for deletion.');
            }

            try {
                DB::transaction(function () use ($project, $file): void {
                    $file->delete();
                    $project->update([
                        'storage_bytes' => max(0, $project->storage_bytes - $file->size_bytes),
                        'file_count' => max(0, $project->file_count - 1),
                        'files_updated_at' => now(),
                    ]);
                });
            } catch (Throwable $exception) {
                rename($trashPath, $absolutePath);

                throw $exception;
            }

            File::delete($trashPath);

            $this->removeEmptyParentDirectories($disk->path($relativePath), $disk->path($project->storageDirectory()));
        });
    }

    public function downloadPath(Project $project, ProjectFile $file): string
    {
        if ($file->project_id !== $project->id) {
            abort(404);
        }

        return $this->assertPathBelongsToProject($project, $file);
    }

    /**
     * @return list<array{index: int, path: string, declared_size: int}>
     */
    private function inspect(ZipArchive $zip, Project $project): array
    {
        $plan = $project->user->plan;

        if ($zip->numFiles === 0) {
            throw new ArchiveValidationException('The ZIP archive is empty.');
        }

        if ($zip->numFiles > $plan->max_file_count * 2) {
            throw new ArchiveValidationException("The ZIP contains too many entries. Your plan allows {$plan->max_file_count} files.");
        }

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);

            if ($stat === false || ! isset($stat['name'])) {
                throw new ArchiveValidationException('The ZIP contains an unreadable entry.');
            }

            $path = $this->normalizePath($stat['name']);
            $isDirectory = str_ends_with(str_replace('\\', '/', $stat['name']), '/');
            $this->assertRegularEntry($zip, $index, $isDirectory, $path);

            if ($isDirectory) {
                continue;
            }

            if (count($entries) >= $plan->max_file_count) {
                throw new ArchiveValidationException("The ZIP contains too many files. Your plan allows {$plan->max_file_count} files.");
            }

            $size = (int) ($stat['size'] ?? -1);

            if ($size < 0) {
                throw new ArchiveValidationException("The size of {$path} could not be verified.");
            }

            if (($stat['encryption_method'] ?? 0) !== 0) {
                throw new ArchiveValidationException('Password-protected ZIP archives are not supported.');
            }

            $this->assertAllowedFileName($path, $project);
            $entries[] = ['index' => $index, 'path' => $path, 'declared_size' => $size];
        }

        $entries = $this->stripSingleRootDirectory($entries);
        $paths = array_column($entries, 'path');

        if (count($paths) !== count(array_unique(array_map('strtolower', $paths)))) {
            throw new ArchiveValidationException('The ZIP contains duplicate or conflicting file paths.');
        }

        if (array_sum(array_column($entries, 'declared_size')) > $plan->max_extracted_mb * self::BYTES_PER_MB) {
            throw new ArchiveValidationException("The extracted website may not exceed {$plan->max_extracted_mb} MB.");
        }

        return $entries;
    }

    private function normalizePath(string $path): string
    {
        if (str_contains($path, "\0") || preg_match('//u', $path) !== 1 || preg_match('/[\x00-\x1F\x7F]/u', $path) === 1) {
            throw new ArchiveValidationException('The ZIP contains an invalid file name.');
        }

        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\//', $path) === 1) {
            throw new ArchiveValidationException('Absolute paths are not allowed inside the ZIP.');
        }

        $segments = explode('/', rtrim($path, '/'));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new ArchiveValidationException('The ZIP contains an unsafe path.');
            }

            if (strlen($segment) > 255) {
                throw new ArchiveValidationException('A file name inside the ZIP is too long.');
            }

            if (in_array(strtolower($segment), config('hosting.blocked_names'), true)) {
                throw new ArchiveValidationException("The file name {$segment} is not allowed.");
            }
        }

        $normalized = implode('/', $segments);

        if (strlen($normalized) > 500) {
            throw new ArchiveValidationException('A path inside the ZIP is too long.');
        }

        return $normalized;
    }

    private function assertRegularEntry(ZipArchive $zip, int $index, bool $isDirectory, string $path): void
    {
        if (! $zip->getExternalAttributesIndex($index, $operationsSystem, $attributes)) {
            return;
        }

        $type = ($attributes >> 16) & 0170000;
        $expectedType = $isDirectory ? 0040000 : 0100000;

        if ($type !== 0 && $type !== $expectedType) {
            throw new ArchiveValidationException("The ZIP entry {$path} is not a regular file or directory.");
        }
    }

    private function assertAllowedFileName(string $path, Project $project): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = config("hosting.allowed_extensions.{$project->runtime->value}", []);

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            throw new ArchiveValidationException("The file type for {$path} is not allowed for this website.");
        }
    }

    /**
     * @param  list<array{index: int, path: string, declared_size: int}>  $entries
     * @return list<array{index: int, path: string, declared_size: int}>
     */
    private function stripSingleRootDirectory(array $entries): array
    {
        $roots = [];

        foreach ($entries as $entry) {
            if (! str_contains($entry['path'], '/')) {
                return $entries;
            }

            $roots[] = Str::before($entry['path'], '/');
        }

        if (count(array_unique($roots)) !== 1) {
            return $entries;
        }

        $prefixLength = strlen($roots[0]) + 1;

        return array_map(function (array $entry) use ($prefixLength): array {
            $entry['path'] = substr($entry['path'], $prefixLength);

            return $entry;
        }, $entries);
    }

    /**
     * @param  list<array{index: int, path: string, declared_size: int}>  $entries
     * @return array{list<array{path: string, size_bytes: int, mime_type: string}>, int}
     */
    private function extract(ZipArchive $zip, array $entries, string $stagingPath, int $extractedLimit): array
    {
        $files = [];
        $total = 0;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($entries as $entry) {
            $destination = $stagingPath.'/'.$entry['path'];
            File::ensureDirectoryExists(dirname($destination), 0750, true);
            $input = $zip->getStreamIndex($entry['index'], ZipArchive::FL_UNCHANGED);
            $output = fopen($destination, 'xb');

            if ($input === false || $output === false) {
                if (is_resource($input)) {
                    fclose($input);
                }

                throw new ArchiveValidationException("The file {$entry['path']} could not be extracted.");
            }

            $fileSize = 0;

            try {
                while (! feof($input)) {
                    $chunk = fread($input, 8192);

                    if ($chunk === false) {
                        throw new ArchiveValidationException("The file {$entry['path']} could not be read.");
                    }

                    $length = strlen($chunk);
                    $fileSize += $length;
                    $total += $length;

                    if ($total > $extractedLimit) {
                        throw new ArchiveValidationException('The extracted website exceeds your plan limit.');
                    }

                    if ($length > 0 && fwrite($output, $chunk) !== $length) {
                        throw new ArchiveValidationException("The file {$entry['path']} could not be written.");
                    }
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            $mimeType = $finfo->file($destination) ?: 'application/octet-stream';
            $this->assertAllowedMimeType($entry['path'], $mimeType);
            $files[] = ['path' => $entry['path'], 'size_bytes' => $fileSize, 'mime_type' => $mimeType];
        }

        return [$files, $total];
    }

    private function assertAllowedMimeType(string $path, string $mimeType): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedMimes = match ($extension) {
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'avif' => ['image/avif', 'application/octet-stream'],
            'svg' => ['image/svg+xml', 'application/xml', 'text/xml', 'text/plain'],
            'pdf' => ['application/pdf'],
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon', 'application/octet-stream'],
            'woff' => ['font/woff', 'application/font-woff', 'application/octet-stream'],
            'woff2' => ['font/woff2', 'application/octet-stream'],
            'ttf' => ['font/ttf', 'application/x-font-ttf', 'application/octet-stream'],
            'otf' => ['font/otf', 'application/x-font-opentype', 'application/octet-stream'],
            'eot' => ['application/vnd.ms-fontobject', 'application/octet-stream'],
            'json', 'map', 'webmanifest' => ['application/json', 'text/plain', 'application/x-empty'],
            'xml' => ['application/xml', 'text/xml', 'text/plain', 'application/x-empty'],
            'php' => ['text/x-php', 'application/x-httpd-php', 'text/plain', 'application/x-empty'],
            'html', 'htm' => ['text/html', 'text/plain', 'application/x-empty'],
            'css' => ['text/css', 'text/plain', 'application/x-empty'],
            'js', 'mjs' => ['text/javascript', 'application/javascript', 'application/x-javascript', 'text/plain', 'application/x-empty'],
            'txt' => ['text/plain', 'application/x-empty'],
            default => [],
        };

        if (! in_array($mimeType, $allowedMimes, true)) {
            throw new ArchiveValidationException("The content type of {$path} does not match an allowed website file.");
        }
    }

    /** @param list<array{path: string, size_bytes: int, mime_type: string}> $files */
    private function assertEntrypointExists(Project $project, array $files): void
    {
        $paths = array_map(fn (array $file): string => strtolower($file['path']), $files);
        $entrypoints = $project->runtime->value === 'php' ? ['index.php', 'index.html', 'index.htm'] : ['index.html', 'index.htm'];

        if (count(array_intersect($entrypoints, $paths)) === 0) {
            throw new ArchiveValidationException('The website must contain an index file at its root.');
        }
    }

    /**
     * @param  list<array{path: string, size_bytes: int, mime_type: string}>  $files
     */
    private function swapIntoPlace(Project $project, UploadedFile $archive, string $archivePath, int $archiveSize, string $stagingPath, array $files, int $extractedSize): ProjectUpload
    {
        $disk = Storage::disk(config('hosting.project_disk'));
        $finalPath = $disk->path($project->storageDirectory());
        $backupPath = $disk->path('.backups/'.Str::uuid());
        $hadExistingFiles = File::isDirectory($finalPath);

        File::ensureDirectoryExists(dirname($finalPath), 0750, true);

        if ($hadExistingFiles) {
            File::ensureDirectoryExists(dirname($backupPath), 0750, true);

            if (! rename($finalPath, $backupPath)) {
                throw new ArchiveValidationException('The existing website files could not be prepared for replacement.');
            }
        }

        if (! rename($stagingPath, $finalPath)) {
            if ($hadExistingFiles) {
                rename($backupPath, $finalPath);
            }

            throw new ArchiveValidationException('The validated website files could not be saved.');
        }

        try {
            $upload = DB::transaction(function () use ($project, $archive, $archivePath, $archiveSize, $files, $extractedSize): ProjectUpload {
                $project->files()->delete();

                foreach (array_chunk($files, 500) as $chunk) {
                    $project->files()->createMany($chunk);
                }

                $project->update([
                    'storage_bytes' => $extractedSize,
                    'file_count' => count($files),
                    'files_updated_at' => now(),
                ]);

                return $project->uploads()->create([
                    'user_id' => $project->user_id,
                    'original_name' => Str::limit(basename(str_replace('\\', '/', $archive->getClientOriginalName())), 255, ''),
                    'archive_size_bytes' => $archiveSize,
                    'extracted_size_bytes' => $extractedSize,
                    'file_count' => count($files),
                    'sha256' => hash_file('sha256', $archivePath),
                ]);
            });
        } catch (Throwable $exception) {
            File::deleteDirectory($finalPath);

            if ($hadExistingFiles) {
                rename($backupPath, $finalPath);
            }

            throw $exception;
        }

        if ($hadExistingFiles) {
            File::deleteDirectory($backupPath);
        }

        return $upload;
    }

    private function removeEmptyParentDirectories(string $filePath, string $projectRoot): void
    {
        $directory = dirname($filePath);

        while ($directory !== $projectRoot && str_starts_with($directory, $projectRoot.'/')) {
            if (! File::isDirectory($directory) || count(File::files($directory)) + count(File::directories($directory)) > 0) {
                break;
            }

            rmdir($directory);
            $directory = dirname($directory);
        }
    }

    private function assertPathBelongsToProject(Project $project, ProjectFile $file): string
    {
        $disk = Storage::disk(config('hosting.project_disk'));
        $root = realpath($disk->path($project->storageDirectory()));
        $path = realpath($disk->path($project->storageDirectory().'/'.$file->path));

        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            abort(404);
        }

        return $path;
    }
}
