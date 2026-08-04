<?php

namespace App\Console\Commands;

use App\Services\ResourceMonitoringCollector;
use Illuminate\Console\Command;

class CollectResourceMetrics extends Command
{
    protected $signature = 'monitoring:collect';

    protected $description = 'Collect health and resource snapshots for deployed website containers';

    public function handle(ResourceMonitoringCollector $collector): int
    {
        $count = $collector->collectAll();
        $this->info("Collected {$count} container resource snapshots.");

        return self::SUCCESS;
    }
}
