<?php

namespace App\Console\Commands;

use App\Models\AdminAlert;
use App\Models\ProjectResourceSnapshot;
use Illuminate\Console\Command;

class PruneMonitoringData extends Command
{
    protected $signature = 'monitoring:prune';

    protected $description = 'Remove expired resource snapshots and old resolved alerts';

    public function handle(): int
    {
        $days = max(1, (int) config('hosting.monitoring.snapshot_retention_days'));
        $snapshots = ProjectResourceSnapshot::query()->where('sampled_at', '<', now()->subDays($days))->delete();
        $alerts = AdminAlert::query()->whereNotNull('resolved_at')->where('resolved_at', '<', now()->subDays($days))->delete();
        $this->info("Pruned {$snapshots} snapshots and {$alerts} resolved alerts.");

        return self::SUCCESS;
    }
}
