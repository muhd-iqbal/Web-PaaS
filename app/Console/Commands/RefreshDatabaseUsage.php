<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProjectDatabaseManager;
use Illuminate\Console\Command;

class RefreshDatabaseUsage extends Command
{
    protected $signature = 'databases:refresh-usage {--user= : Refresh one user ID only}';

    protected $description = 'Refresh managed database sizes and enforce account database quotas';

    public function handle(ProjectDatabaseManager $manager): int
    {
        $query = User::query()->whereHas('projects.hostedDatabase');

        if ($userId = $this->option('user')) {
            $query->whereKey($userId);
        }

        $failures = 0;
        $query->eachById(function (User $user) use ($manager, &$failures): void {
            try {
                $manager->refreshUsageForUser($user);
            } catch (\Throwable $exception) {
                $failures++;
                report($exception);
                $this->error("User {$user->id}: database usage refresh failed.");
            }
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
