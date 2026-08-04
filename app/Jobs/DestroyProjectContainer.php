<?php

namespace App\Jobs;

use App\Contracts\ContainerRuntime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DestroyProjectContainer implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $projectId) {}

    /**
     * Execute the job.
     */
    public function handle(ContainerRuntime $runtime): void
    {
        $runtime->destroy($this->projectId);
    }
}
