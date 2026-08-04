<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Exceptions\ArchiveValidationException;
use App\Http\Requests\UploadProjectArchiveRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\ProjectArchiveManager;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectFileController extends Controller
{
    public function store(UploadProjectArchiveRequest $request, Project $project, ProjectArchiveManager $manager): RedirectResponse
    {
        try {
            $manager->replace($project, $request->file('archive'));
        } catch (ArchiveValidationException $exception) {
            throw ValidationException::withMessages(['archive' => $exception->getMessage()]);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['archive' => 'Another file operation is still running. Please try again.']);
        }

        return redirect()->route('projects.show', $project)->with('status', 'Website files uploaded and validated successfully.');
    }

    public function download(Project $project, ProjectFile $projectFile, ProjectArchiveManager $manager): BinaryFileResponse
    {
        $this->authorize('view', $project);

        return response()->download($manager->downloadPath($project, $projectFile), basename($projectFile->path));
    }

    public function destroy(Project $project, ProjectFile $projectFile, ProjectArchiveManager $manager): RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($project->status === ProjectStatus::Deploying) {
            throw ValidationException::withMessages(['file' => 'Wait for the current deployment to finish before deleting files.']);
        }

        try {
            $manager->deleteFile($project, $projectFile);
        } catch (ArchiveValidationException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['file' => 'Another file operation is still running. Please try again.']);
        }

        return redirect()->route('projects.show', $project)->with('status', 'File deleted.');
    }
}
