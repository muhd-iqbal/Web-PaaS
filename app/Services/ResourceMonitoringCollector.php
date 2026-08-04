<?php

namespace App\Services;

use App\Contracts\ContainerRuntime;
use App\Enums\AlertSeverity;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectResourceSnapshot;
use App\ValueObjects\ContainerMetrics;
use Illuminate\Support\Str;

class ResourceMonitoringCollector
{
    public function __construct(
        private readonly ContainerRuntime $runtime,
        private readonly AdminAlertManager $alerts,
    ) {}

    public function collect(Project $project): ProjectResourceSnapshot
    {
        try {
            $metrics = $this->runtime->metrics($project);
            $snapshot = $project->resourceSnapshots()->create($this->attributes($metrics));
            $this->evaluate($project, $metrics);
            $this->alerts->resolve("monitoring-unavailable:project:{$project->id}");

            return $snapshot;
        } catch (\Throwable $exception) {
            $message = Str::limit($exception->getMessage() ?: 'Container monitoring failed.', 1000, '');
            $snapshot = $project->resourceSnapshots()->create([
                'sampled_at' => now(),
                'is_running' => false,
                'error_message' => $message,
            ]);
            $this->alerts->raise(
                "monitoring-unavailable:project:{$project->id}",
                'monitoring_unavailable',
                AlertSeverity::Critical,
                'Container monitoring unavailable',
                "Metrics could not be collected for {$project->name}: {$message}",
                $project,
            );

            return $snapshot;
        }
    }

    public function collectAll(): int
    {
        $count = 0;
        Project::query()
            ->whereNotNull('container_name')
            ->where('status', '!=', ProjectStatus::Suspended->value)
            ->eachById(function (Project $project) use (&$count): void {
                $this->collect($project);
                $count++;
            });

        return $count;
    }

    /** @return array<string, mixed> */
    private function attributes(ContainerMetrics $metrics): array
    {
        return [
            'sampled_at' => now(),
            'is_running' => $metrics->isRunning,
            'health' => $metrics->health,
            'cpu_percent' => $metrics->cpuPercent,
            'memory_percent' => $metrics->memoryPercent,
            'memory_usage_bytes' => $metrics->memoryUsageBytes,
            'memory_limit_bytes' => $metrics->memoryLimitBytes,
            'process_count' => $metrics->processCount,
            'restart_count' => $metrics->restartCount,
            'oom_killed' => $metrics->oomKilled,
        ];
    }

    private function evaluate(Project $project, ContainerMetrics $metrics): void
    {
        $this->condition(
            $project,
            'container-down',
            ! $metrics->isRunning || ($metrics->health !== null && $metrics->health !== 'healthy'),
            'container_unhealthy',
            AlertSeverity::Critical,
            'Website container is not healthy',
            "{$project->name} is stopped or unhealthy.",
        );
        $this->condition(
            $project,
            'cpu-high',
            $metrics->cpuPercent !== null && $metrics->cpuPercent >= (float) config('hosting.monitoring.cpu_warning_percent'),
            'cpu_high',
            AlertSeverity::Warning,
            'High container CPU usage',
            "{$project->name} is using {$metrics->cpuPercent}% CPU.",
        );
        $this->condition(
            $project,
            'memory-high',
            $metrics->memoryPercent !== null && $metrics->memoryPercent >= (float) config('hosting.monitoring.memory_warning_percent'),
            'memory_high',
            AlertSeverity::Warning,
            'High container memory usage',
            "{$project->name} is using {$metrics->memoryPercent}% of its reported memory limit.",
        );
        $this->condition(
            $project,
            'oom-killed',
            $metrics->oomKilled,
            'container_oom_killed',
            AlertSeverity::Critical,
            'Website container ran out of memory',
            "{$project->name} was terminated by the out-of-memory handler.",
        );
    }

    private function condition(Project $project, string $key, bool $active, string $type, AlertSeverity $severity, string $title, string $message): void
    {
        $fingerprint = "{$key}:project:{$project->id}";

        if ($active) {
            $this->alerts->raise($fingerprint, $type, $severity, $title, $message, $project);
        } else {
            $this->alerts->resolve($fingerprint);
        }
    }
}
