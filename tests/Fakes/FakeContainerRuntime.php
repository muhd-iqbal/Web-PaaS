<?php

namespace Tests\Fakes;

use App\Contracts\ContainerRuntime;
use App\Models\Project;
use App\ValueObjects\ContainerInstance;
use App\ValueObjects\ContainerMetrics;
use RuntimeException;

class FakeContainerRuntime implements ContainerRuntime
{
    /** @var list<array{project_id: int, hostname: string}> */
    public array $deployments = [];

    /** @var list<int> */
    public array $restarts = [];

    /** @var list<int> */
    public array $stops = [];

    /** @var list<int> */
    public array $destroyed = [];

    public ?string $failure = null;

    public ContainerMetrics $containerMetrics;

    public function __construct()
    {
        $this->containerMetrics = new ContainerMetrics(true, 'healthy', 1.5, 10, 10_485_760, 104_857_600, 3);
    }

    public function deploy(Project $project, string $hostname): ContainerInstance
    {
        $this->failWhenConfigured();
        $this->deployments[] = ['project_id' => $project->id, 'hostname' => $hostname];

        return new ContainerInstance("hosting-project-{$project->id}", str_repeat('a', 64));
    }

    public function restart(Project $project): ContainerInstance
    {
        $this->failWhenConfigured();
        $this->restarts[] = $project->id;

        return new ContainerInstance($project->container_name, str_repeat('b', 64));
    }

    public function stop(Project $project): void
    {
        $this->failWhenConfigured();
        $this->stops[] = $project->id;
    }

    public function destroy(int $projectId): void
    {
        $this->failWhenConfigured();
        $this->destroyed[] = $projectId;
    }

    public function logs(Project $project, int $lines): string
    {
        $this->failWhenConfigured();

        return "Last {$lines} lines for {$project->id}";
    }

    public function metrics(Project $project): ContainerMetrics
    {
        $this->failWhenConfigured();

        return $this->containerMetrics;
    }

    private function failWhenConfigured(): void
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }
    }
}
