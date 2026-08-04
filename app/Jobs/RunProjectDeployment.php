<?php

namespace App\Jobs;

use App\Enums\DeploymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Deployment;
use App\Services\DeploymentManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class RunProjectDeployment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public Deployment $deployment) {}

    /**
     * Execute the job.
     */
    public function handle(DeploymentManager $manager): void
    {
        $manager->perform($this->deployment);
    }

    public function failed(?Throwable $exception): void
    {
        $deployment = Deployment::query()->with('project')->find($this->deployment->id);

        if (! $deployment || $deployment->status === DeploymentStatus::Succeeded) {
            return;
        }

        $message = Str::limit($exception?->getMessage() ?: 'The deployment job failed or timed out.', 2000, '');
        $deployment->update([
            'status' => DeploymentStatus::Failed,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
        $deployment->project?->update([
            'status' => ProjectStatus::Failed,
            'last_deployment_error' => $message,
        ]);
    }
}
