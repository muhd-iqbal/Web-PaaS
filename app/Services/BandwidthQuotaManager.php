<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\ProjectStatus;
use App\Models\BandwidthUsage;
use App\Models\User;

class BandwidthQuotaManager
{
    public function __construct(
        private readonly AdminAlertManager $alerts,
        private readonly DeploymentManager $deployments,
    ) {}

    public function enforce(User $user): int
    {
        $user->loadMissing('plan');
        $limit = (int) ($user->plan?->bandwidth_mb ?? 0) * 1_048_576;

        if ($limit <= 0) {
            return 0;
        }

        $used = (int) BandwidthUsage::query()
            ->whereIn('project_id', $user->projects()->select('id'))
            ->whereDate('period_start', now()->startOfMonth()->toDateString())
            ->sum('bytes_sent');
        $percent = ($used / $limit) * 100;
        $fingerprint = "bandwidth:user:{$user->id}";

        if ($percent < (float) config('hosting.monitoring.bandwidth_warning_percent')) {
            $this->alerts->resolve($fingerprint);

            return $used;
        }

        $project = $user->projects()->oldest('id')->first();
        $exceeded = $used >= $limit;
        $this->alerts->raise(
            $fingerprint,
            $exceeded ? 'bandwidth_exceeded' : 'bandwidth_warning',
            $exceeded ? AlertSeverity::Critical : AlertSeverity::Warning,
            $exceeded ? 'Account bandwidth quota exceeded' : 'Account bandwidth quota nearing limit',
            sprintf('Account %d has used %.2f MB of its %d MB monthly bandwidth allowance.', $user->id, $used / 1_048_576, (int) $user->plan->bandwidth_mb),
            $project,
            ['used_bytes' => $used, 'limit_bytes' => $limit],
        );

        if ($exceeded) {
            $user->projects()
                ->where('status', ProjectStatus::Active->value)
                ->whereNotNull('container_name')
                ->each(fn ($activeProject) => $this->deployments->queueSuspend($activeProject, $user));
        }

        return $used;
    }
}
