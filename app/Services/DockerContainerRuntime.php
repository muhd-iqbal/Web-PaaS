<?php

namespace App\Services;

use App\Contracts\CommandRunner;
use App\Contracts\ContainerRuntime;
use App\Enums\DeploymentStatus;
use App\Enums\ProjectDatabaseStatus;
use App\Enums\ProjectRuntime;
use App\Exceptions\ContainerRuntimeException;
use App\Models\Project;
use App\ValueObjects\CommandResult;
use App\ValueObjects\ContainerInstance;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class DockerContainerRuntime implements ContainerRuntime
{
    public function __construct(private readonly CommandRunner $runner) {}

    public function deploy(Project $project, string $hostname): ContainerInstance
    {
        $this->assertDockerAvailable();
        $image = $this->imageFor($project->runtime);
        $this->ensureImage($project->runtime, $image);
        [$filesPath, $backupPath] = $this->prepareRelease($project);
        $rollbackHostname = $project->hostname ?: $hostname;
        $rollbackRuntime = $this->lastSuccessfulRuntime($project) ?? $project->runtime;
        $containerReplaced = false;

        try {
            $this->ensureProjectNetwork($project->id);
            $this->ensureTraefikConnection($project->id);
            $this->removeContainerIfPresent($project->id);
            $containerReplaced = true;
            $instance = $this->startContainer($project, $hostname, $image, $filesPath);
            $this->commitRelease($backupPath);

            return $instance;
        } catch (\Throwable $exception) {
            $this->rollbackRelease($filesPath, $backupPath);

            if ($containerReplaced && $backupPath !== null) {
                try {
                    $this->removeContainerIfPresent($project->id);
                    $rollbackProject = clone $project;
                    $rollbackProject->runtime = $rollbackRuntime;
                    $this->startContainer($rollbackProject, $rollbackHostname, $this->imageFor($rollbackRuntime), $filesPath);
                } catch (\Throwable) {
                    // Preserve the original deployment error; recovery can be retried by an operator.
                }
            }

            throw $exception;
        }
    }

    public function restart(Project $project): ContainerInstance
    {
        $containerName = $project->container_name ?: $this->containerName($project->id);
        $this->command(['container', 'restart', '--time', '10', $containerName], true);
        $this->assertContainerRunning($containerName);
        $containerId = trim($this->command(['container', 'inspect', '--format', '{{.Id}}', $containerName], true)->output);

        return new ContainerInstance($containerName, $containerId);
    }

    public function stop(Project $project): void
    {
        $containerName = $project->container_name ?: $this->containerName($project->id);
        $this->command(['container', 'stop', '--time', '10', $containerName], true);
    }

    public function destroy(int $projectId): void
    {
        $this->assertDockerAvailable();
        File::deleteDirectory($this->releaseRoot($projectId));
        $this->removeContainerIfPresent($projectId);
        $networkName = $this->networkName($projectId);

        if (! $this->command(['network', 'inspect', $networkName])->successful()) {
            return;
        }

        $traefik = config('hosting.deployment.traefik_container');
        $this->command(['network', 'disconnect', '--force', $networkName, $traefik]);
        $this->command(['network', 'rm', $networkName], true);
    }

    public function logs(Project $project, int $lines): string
    {
        $containerName = $project->container_name ?: $this->containerName($project->id);
        $result = $this->command(['container', 'logs', '--tail', (string) max(1, min($lines, 1000)), $containerName]);

        if (! $result->successful()) {
            throw $this->exceptionFor($result, 'Container logs could not be read.');
        }

        return trim($result->output."\n".$result->errorOutput);
    }

    private function assertDockerAvailable(): void
    {
        $this->command(['info', '--format', '{{.ServerVersion}}'], true);
    }

    private function ensureImage(ProjectRuntime $runtime, string $image): void
    {
        if ($this->command(['image', 'inspect', $image])->successful()) {
            return;
        }

        $context = rtrim(config('hosting.deployment.runtime_root'), '/').'/'.$runtime->value;

        if (! is_file($context.'/Dockerfile')) {
            throw new ContainerRuntimeException("The {$runtime->label()} runtime Dockerfile is missing.");
        }

        $this->command(['image', 'build', '--pull', '--tag', $image, $context], true);
    }

    private function ensureProjectNetwork(int $projectId): void
    {
        $networkName = $this->networkName($projectId);

        if ($this->command(['network', 'inspect', $networkName])->successful()) {
            return;
        }

        $this->command(['network', 'create', '--driver', 'bridge', '--label', 'hosting.managed=true', '--label', "hosting.project_id={$projectId}", $networkName], true);
    }

    private function startContainer(Project $project, string $hostname, string $image, string $filesPath): ContainerInstance
    {
        $containerName = $this->containerName($project->id);
        $networkName = $this->networkName($project->id);
        $routerName = "project-{$project->id}";
        $arguments = [
            'container', 'run', '--detach',
            '--name', $containerName,
            '--network', $networkName,
            '--restart', 'unless-stopped',
            '--read-only',
            '--cap-drop', 'ALL',
            '--security-opt', 'no-new-privileges:true',
            '--pids-limit', '128',
            '--tmpfs', '/tmp:rw,noexec,nosuid,size=32m',
            '--mount', "type=bind,source={$filesPath},target=/var/www/html,readonly",
            '--label', 'hosting.managed=true',
            '--label', "hosting.project_id={$project->id}",
            '--label', 'traefik.enable=true',
            '--label', "traefik.docker.network={$networkName}",
            '--label', "traefik.http.routers.{$routerName}.rule=Host(`{$hostname}`)",
            '--label', "traefik.http.routers.{$routerName}.entrypoints=websecure",
            '--label', "traefik.http.routers.{$routerName}.tls=true",
            '--label', "traefik.http.routers.{$routerName}.tls.certresolver=".config('hosting.deployment.certificate_resolver'),
            '--label', "traefik.http.routers.{$routerName}.service={$routerName}",
            '--label', "traefik.http.services.{$routerName}.loadbalancer.server.port=".config('hosting.deployment.container_port'),
        ];
        $database = $project->hostedDatabase()
            ->whereIn('status', [ProjectDatabaseStatus::Active->value, ProjectDatabaseStatus::QuotaExceeded->value])
            ->first();

        if ($database) {
            array_push(
                $arguments,
                '--env', 'DB_CONNECTION=mysql',
                '--env', "DB_HOST={$database->host}",
                '--env', "DB_PORT={$database->port}",
                '--env', "DB_DATABASE={$database->database_name}",
                '--env', "DB_USERNAME={$database->username}",
                '--env', "DB_PASSWORD={$database->password}",
            );
        }

        $arguments[] = $image;
        $result = $this->command($arguments, true);
        $containerId = trim($result->output);

        if ($containerId === '') {
            throw new ContainerRuntimeException('Docker did not return a container identifier.');
        }

        if ($database) {
            $this->command(['network', 'connect', config('hosting.database.docker_network'), $containerName], true);
        }

        $this->assertContainerRunning($containerName);

        return new ContainerInstance($containerName, $containerId);
    }

    private function ensureTraefikConnection(int $projectId): void
    {
        $traefik = config('hosting.deployment.traefik_container');
        $networkName = $this->networkName($projectId);
        $result = $this->command(['container', 'inspect', '--format', '{{json .NetworkSettings.Networks}}', $traefik], true);

        try {
            $networks = json_decode($result->output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ContainerRuntimeException('Traefik returned invalid network information.');
        }

        if (! is_array($networks) || ! array_key_exists($networkName, $networks)) {
            $this->command(['network', 'connect', $networkName, $traefik], true);
        }
    }

    private function removeContainerIfPresent(int $projectId): void
    {
        $containerName = $this->containerName($projectId);

        if ($this->command(['container', 'inspect', $containerName])->successful()) {
            $this->command(['container', 'rm', '--force', $containerName], true);
        }
    }

    /** @return array{string, string|null} */
    private function prepareRelease(Project $project): array
    {
        $disk = Storage::disk(config('hosting.project_disk'));
        $source = realpath($disk->path($project->storageDirectory()));

        if ($source === false || ! is_dir($source)) {
            throw new ContainerRuntimeException('The validated project files are missing from local storage.');
        }

        $root = $this->releaseRoot($project->id);
        $staging = $root.'/staging-'.Str::uuid();
        $current = $root.'/current';
        $backup = is_dir($current) ? $root.'/previous-'.Str::uuid() : null;
        File::ensureDirectoryExists($root, 0750, true);

        if (! File::copyDirectory($source, $staging)) {
            File::deleteDirectory($staging);
            throw new ContainerRuntimeException('The deployment release could not be prepared.');
        }

        if ($backup !== null && ! rename($current, $backup)) {
            File::deleteDirectory($staging);
            throw new ContainerRuntimeException('The previous deployment release could not be preserved.');
        }

        if (! rename($staging, $current)) {
            if ($backup !== null) {
                rename($backup, $current);
            }

            throw new ContainerRuntimeException('The deployment release could not be activated.');
        }

        return [$current, $backup];
    }

    private function commitRelease(?string $backupPath): void
    {
        if ($backupPath !== null) {
            File::deleteDirectory($backupPath);
        }
    }

    private function rollbackRelease(string $currentPath, ?string $backupPath): void
    {
        File::deleteDirectory($currentPath);

        if ($backupPath !== null && is_dir($backupPath)) {
            rename($backupPath, $currentPath);
        }
    }

    private function releaseRoot(int $projectId): string
    {
        return Storage::disk(config('hosting.project_disk'))->path(".deployments/project-{$projectId}");
    }

    private function assertContainerRunning(string $containerName): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $result = $this->command([
                'container', 'inspect', '--format',
                '{{.State.Running}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}',
                $containerName,
            ], true);
            [$running, $health] = array_pad(explode(' ', trim($result->output), 2), 2, 'none');

            if ($running !== 'true') {
                throw new ContainerRuntimeException('The website container exited during startup.');
            }

            if (in_array($health, ['healthy', 'none'], true)) {
                return;
            }

            if ($health === 'unhealthy') {
                throw new ContainerRuntimeException('The website container failed its startup health check.');
            }

            usleep(1_000_000);
        }

        throw new ContainerRuntimeException('The website container did not become healthy in time.');
    }

    private function imageFor(ProjectRuntime $runtime): string
    {
        return match ($runtime) {
            ProjectRuntime::Static => config('hosting.deployment.static_image'),
            ProjectRuntime::Php => config('hosting.deployment.php_image'),
        };
    }

    private function lastSuccessfulRuntime(Project $project): ?ProjectRuntime
    {
        $runtime = $project->deployments()
            ->where('status', DeploymentStatus::Succeeded->value)
            ->latest('id')
            ->value('runtime');

        return is_string($runtime) ? ProjectRuntime::tryFrom($runtime) : null;
    }

    private function containerName(int $projectId): string
    {
        return "hosting-project-{$projectId}";
    }

    private function networkName(int $projectId): string
    {
        return "hosting_project_{$projectId}";
    }

    /** @param list<string> $arguments */
    private function command(array $arguments, bool $mustSucceed = false): CommandResult
    {
        $result = $this->runner->run(
            array_merge([config('hosting.deployment.docker_binary')], $arguments),
            config('hosting.deployment.command_timeout'),
        );

        if ($mustSucceed && ! $result->successful()) {
            throw $this->exceptionFor($result, 'Docker could not complete the container operation.');
        }

        return $result;
    }

    private function exceptionFor(CommandResult $result, string $fallback): ContainerRuntimeException
    {
        $message = trim($result->errorOutput) ?: trim($result->output) ?: $fallback;

        return new ContainerRuntimeException(mb_substr($message, 0, 2000));
    }
}
