<?php

namespace App\Contracts;

use App\Models\Project;
use App\ValueObjects\ContainerInstance;
use App\ValueObjects\ContainerMetrics;

interface ContainerRuntime
{
    public function deploy(Project $project, string $hostname): ContainerInstance;

    public function restart(Project $project): ContainerInstance;

    public function stop(Project $project): void;

    public function destroy(int $projectId): void;

    public function logs(Project $project, int $lines): string;

    public function metrics(Project $project): ContainerMetrics;
}
