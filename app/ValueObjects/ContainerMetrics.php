<?php

namespace App\ValueObjects;

final readonly class ContainerMetrics
{
    public function __construct(
        public bool $isRunning,
        public ?string $health,
        public ?float $cpuPercent = null,
        public ?float $memoryPercent = null,
        public ?int $memoryUsageBytes = null,
        public ?int $memoryLimitBytes = null,
        public ?int $processCount = null,
        public int $restartCount = 0,
        public bool $oomKilled = false,
    ) {}
}
