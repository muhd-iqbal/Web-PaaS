<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Models\AdminAlert;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminAlertManager
{
    /** @param array<string, scalar|null> $context */
    public function raise(
        string $fingerprint,
        string $type,
        AlertSeverity $severity,
        string $title,
        string $message,
        ?Project $project = null,
        array $context = [],
    ): AdminAlert {
        $alert = DB::transaction(function () use ($fingerprint, $type, $severity, $title, $message, $project, $context): AdminAlert {
            $alert = AdminAlert::query()->lockForUpdate()->where('fingerprint', $fingerprint)->first();

            if (! $alert) {
                return AdminAlert::query()->create([
                    'project_id' => $project?->id,
                    'user_id' => $project?->user_id,
                    'fingerprint' => $fingerprint,
                    'type' => $type,
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'context' => $context,
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                ]);
            }

            $alert->update([
                'project_id' => $project?->id ?? $alert->project_id,
                'user_id' => $project?->user_id ?? $alert->user_id,
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'context' => $context,
                'occurrences' => $alert->occurrences + 1,
                'last_detected_at' => now(),
                'resolved_at' => null,
            ]);

            return $alert->refresh();
        });

        if ($alert->wasRecentlyCreated || $alert->occurrences === 1) {
            Log::warning('Hosting monitoring alert raised.', [
                'alert_id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity->value,
                'project_id' => $alert->project_id,
            ]);
        }

        return $alert;
    }

    public function resolve(string $fingerprint): void
    {
        AdminAlert::query()
            ->where('fingerprint', $fingerprint)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }
}
