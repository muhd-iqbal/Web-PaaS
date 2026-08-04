<?php

namespace App\Console\Commands;

use App\Enums\AlertSeverity;
use App\Models\User;
use App\Services\AdminAlertManager;
use App\Services\BandwidthQuotaManager;
use App\Services\TraefikAccessLogImporter;
use Illuminate\Console\Command;

class ImportTraefikUsage extends Command
{
    protected $signature = 'usage:import-traefik';

    protected $description = 'Import public request bandwidth from the Traefik JSON access log';

    public function handle(TraefikAccessLogImporter $importer, BandwidthQuotaManager $quotas, AdminAlertManager $alerts): int
    {
        $result = $importer->import();

        if (! $result->fileFound) {
            $alerts->raise(
                'traefik-access-log-unavailable',
                'access_log_unavailable',
                AlertSeverity::Critical,
                'Traefik access log unavailable',
                'Bandwidth usage cannot be imported because the configured Traefik access log is not readable.',
            );
            $this->warn('Traefik access log not found or unreadable.');

            return self::FAILURE;
        }

        $alerts->resolve('traefik-access-log-unavailable');
        User::query()
            ->whereHas('projects', fn ($query) => $query->whereIn('id', $result->projectIds))
            ->eachById(fn (User $user) => $quotas->enforce($user));
        $this->info("Imported {$result->requestsImported} requests from {$result->linesRead} log lines.");

        return self::SUCCESS;
    }
}
