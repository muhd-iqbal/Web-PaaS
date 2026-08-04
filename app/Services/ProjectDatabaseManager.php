<?php

namespace App\Services;

use App\Contracts\DatabaseServer;
use App\Enums\ProjectDatabaseStatus;
use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use App\Exceptions\DatabaseHostingException;
use App\Models\Project;
use App\Models\ProjectDatabase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProjectDatabaseManager
{
    public function __construct(private readonly DatabaseServer $server) {}

    public function provision(Project $project): ProjectDatabase
    {
        $failure = null;
        $database = DB::transaction(function () use ($project, &$failure): ProjectDatabase {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
            $lockedProject->load('user.plan');
            $this->assertMutable($lockedProject);

            if ($lockedProject->runtime !== ProjectRuntime::Php) {
                throw new DatabaseHostingException('Databases are available only for PHP projects.');
            }

            if (($lockedProject->user->plan?->database_mb ?? 0) < 1) {
                throw new DatabaseHostingException('Your current plan does not include database hosting.');
            }

            if ($lockedProject->hostedDatabase()->exists()) {
                throw new DatabaseHostingException('This project already has a managed database.');
            }

            $database = $lockedProject->hostedDatabase()->create([
                'status' => ProjectDatabaseStatus::Provisioning,
                'database_name' => $this->databaseName($lockedProject->id),
                'username' => $this->username($lockedProject->id),
                'password' => Str::random(48),
                'host' => config('hosting.database.container_host'),
                'port' => config('hosting.database.container_port'),
            ]);

            try {
                $this->server->provision($database->database_name, $database->username, $database->password);
                $database->update([
                    'status' => ProjectDatabaseStatus::Active,
                    'provisioned_at' => now(),
                    'last_error' => null,
                ]);
                $this->markProjectForRedeployment($lockedProject);
            } catch (Throwable $exception) {
                $failure = $exception;
                $database->update([
                    'status' => ProjectDatabaseStatus::Failed,
                    'last_error' => 'Provisioning failed. Check the managed database service and try again.',
                ]);
            }

            return $database;
        });

        if ($failure) {
            if ($failure instanceof DatabaseHostingException) {
                throw $failure;
            }

            throw new DatabaseHostingException('The project database could not be provisioned.', previous: $failure);
        }

        return $database->refresh();
    }

    public function rotatePassword(ProjectDatabase $database): ProjectDatabase
    {
        return DB::transaction(function () use ($database): ProjectDatabase {
            $project = Project::query()->lockForUpdate()->findOrFail($database->project_id);
            $this->assertMutable($project);
            $database = ProjectDatabase::query()->findOrFail($database->id);

            if (! in_array($database->status, [ProjectDatabaseStatus::Active, ProjectDatabaseStatus::QuotaExceeded], true)) {
                throw new DatabaseHostingException('Only an active managed database can rotate its password.');
            }

            $password = Str::random(48);
            $this->server->rotatePassword($database->username, $password);
            $database->update(['password' => $password, 'last_error' => null]);
            $this->markProjectForRedeployment($project);

            return $database->refresh();
        });
    }

    public function destroy(ProjectDatabase $database): void
    {
        DB::transaction(function () use ($database): void {
            $project = Project::query()->lockForUpdate()->findOrFail($database->project_id);
            $this->assertMutable($project);
            $database = ProjectDatabase::query()->findOrFail($database->id);
            $this->server->drop($database->database_name, $database->username);
            $database->delete();
            $this->markProjectForRedeployment($project);
        });
    }

    public function refreshUsageForUser(User $user): int
    {
        $user->load('plan');
        $databases = ProjectDatabase::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->whereIn('status', [ProjectDatabaseStatus::Active->value, ProjectDatabaseStatus::QuotaExceeded->value])
            ->get();

        foreach ($databases as $database) {
            $database->update([
                'size_bytes' => $this->server->sizeBytes($database->database_name),
                'usage_checked_at' => now(),
            ]);
        }

        $totalBytes = (int) $databases->sum('size_bytes');
        $quotaBytes = max(0, (int) ($user->plan?->database_mb ?? 0)) * 1_048_576;
        $quotaExceeded = $totalBytes > $quotaBytes;

        foreach ($databases as $database) {
            $targetStatus = $quotaExceeded ? ProjectDatabaseStatus::QuotaExceeded : ProjectDatabaseStatus::Active;

            if ($database->status !== $targetStatus) {
                $this->server->setReadOnly($database->database_name, $database->username, $quotaExceeded);
                $database->update(['status' => $targetStatus, 'last_error' => null]);
            }
        }

        return $totalBytes;
    }

    private function markProjectForRedeployment(Project $project): void
    {
        $project->update(['status' => $project->statusAfterFileChange()]);
    }

    private function assertMutable(Project $project): void
    {
        if ($project->status === ProjectStatus::Deploying) {
            throw new DatabaseHostingException('Wait for the current deployment to finish before changing database access.');
        }
    }

    private function databaseName(int $projectId): string
    {
        return "hosting_project_{$projectId}";
    }

    private function username(int $projectId): string
    {
        return "hp_{$projectId}";
    }
}
