<?php

namespace App\Services;

use App\Contracts\ContainerRuntime;
use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use App\Enums\ProjectStatus;
use App\Exceptions\DeploymentException;
use App\Jobs\RunProjectDeployment;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DeploymentManager
{
    public function __construct(private readonly ContainerRuntime $runtime) {}

    public function queueDeploy(Project $project, User $user): Deployment
    {
        return $this->queue($project, DeploymentType::Deploy, $user);
    }

    public function queueRestart(Project $project, User $user): Deployment
    {
        return $this->queue($project, DeploymentType::Restart, $user);
    }

    public function queueSuspend(Project $project, User $user): Deployment
    {
        return $this->queue($project, DeploymentType::Suspend, $user);
    }

    public function perform(Deployment $deployment): void
    {
        $deployment = Deployment::query()->with('project')->findOrFail($deployment->id);
        $project = $deployment->project;
        $runtimeProject = clone $project;
        $runtimeProject->runtime = $deployment->runtime;
        $deployment->update([
            'status' => DeploymentStatus::Running,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $instance = match ($deployment->type) {
                DeploymentType::Deploy, DeploymentType::Redeploy => $this->runtime->deploy($runtimeProject, $deployment->hostname),
                DeploymentType::Restart => $this->runtime->restart($project),
                DeploymentType::Suspend => null,
            };

            if ($deployment->type === DeploymentType::Suspend) {
                $this->runtime->stop($project);
            }

            DB::transaction(function () use ($deployment, $project, $instance): void {
                $deployment->update([
                    'status' => DeploymentStatus::Succeeded,
                    'container_name' => $instance?->name ?? $project->container_name,
                    'container_id' => $instance?->id,
                    'completed_at' => now(),
                ]);
                $project->update([
                    'status' => $deployment->type === DeploymentType::Suspend ? ProjectStatus::Suspended : ProjectStatus::Active,
                    'hostname' => $deployment->hostname,
                    'url' => $deployment->url,
                    'container_name' => $instance?->name ?? $project->container_name,
                    'deployed_at' => in_array($deployment->type, [DeploymentType::Deploy, DeploymentType::Redeploy], true) ? now() : $project->deployed_at,
                    'last_deployment_error' => null,
                ]);
            });

            $project->refresh()->load('user.plan');

            if ($deployment->type !== DeploymentType::Suspend && ! $project->user->canUseProject($project)) {
                $this->runtime->stop($project);
                $project->update(['status' => ProjectStatus::Suspended]);
            }
        } catch (Throwable $exception) {
            $message = Str::limit($exception->getMessage() ?: 'The deployment failed unexpectedly.', 2000, '');

            DB::transaction(function () use ($deployment, $project, $message): void {
                $deployment->update([
                    'status' => DeploymentStatus::Failed,
                    'error_message' => $message,
                    'completed_at' => now(),
                ]);
                $project->update([
                    'status' => ProjectStatus::Failed,
                    'last_deployment_error' => $message,
                ]);
            });

            throw $exception;
        }
    }

    private function queue(Project $project, DeploymentType $type, User $user): Deployment
    {
        $deployment = DB::transaction(function () use ($project, $type, $user): Deployment {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($project->status === ProjectStatus::Deploying) {
                throw new DeploymentException('A deployment operation is already pending for this project.');
            }

            if (in_array($type, [DeploymentType::Deploy, DeploymentType::Redeploy], true)) {
                $type = $project->deployed_at ? DeploymentType::Redeploy : DeploymentType::Deploy;
            }

            if (in_array($type, [DeploymentType::Deploy, DeploymentType::Redeploy], true) && $project->file_count < 1) {
                throw new DeploymentException('Upload and validate website files before deploying.');
            }

            if (in_array($type, [DeploymentType::Restart, DeploymentType::Suspend], true) && ! $project->container_name) {
                throw new DeploymentException('This project does not have a deployed container yet.');
            }

            [$hostname, $url] = $this->addressFor($project);

            $deployment = $project->deployments()->create([
                'triggered_by' => $user->id,
                'type' => $type,
                'status' => DeploymentStatus::Pending,
                'runtime' => $project->runtime->value,
                'hostname' => $hostname,
                'url' => $url,
                'container_name' => $project->container_name,
            ]);

            $project->update([
                'status' => ProjectStatus::Deploying,
                'last_deployment_error' => null,
            ]);

            return $deployment;
        });

        RunProjectDeployment::dispatch($deployment);

        return $deployment;
    }

    /** @return array{string, string} */
    private function addressFor(Project $project): array
    {
        $baseDomain = strtolower(trim((string) config('hosting.deployment.base_domain'), '. '));
        $scheme = strtolower((string) config('hosting.deployment.scheme'));
        $hostname = strtolower($project->slug.'.'.$baseDomain);

        if ($baseDomain === '' || filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new DeploymentException('HOSTING_BASE_DOMAIN is not configured with a valid domain.');
        }

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new DeploymentException('HOSTING_URL_SCHEME must be http or https.');
        }

        return [$hostname, "{$scheme}://{$hostname}"];
    }
}
