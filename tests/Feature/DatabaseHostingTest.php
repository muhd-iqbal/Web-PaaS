<?php

namespace Tests\Feature;

use App\Contracts\DatabaseServer;
use App\Enums\ProjectDatabaseStatus;
use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use App\Jobs\DropProjectDatabase;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectDatabase;
use App\Models\User;
use App\Services\ProjectDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeDatabaseServer;
use Tests\TestCase;

class DatabaseHostingTest extends TestCase
{
    use RefreshDatabase;

    private FakeDatabaseServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = new FakeDatabaseServer;
        $this->app->instance(DatabaseServer::class, $this->server);
    }

    public function test_a_php_project_can_provision_encrypted_database_credentials(): void
    {
        $project = $this->project([
            'status' => ProjectStatus::Active,
            'deployed_at' => now(),
        ]);

        $this->actingAs($project->user)
            ->post(route('projects.database.store', $project))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasNoErrors();

        $database = ProjectDatabase::query()->sole();
        $this->assertSame(ProjectDatabaseStatus::Active, $database->status);
        $this->assertSame("hosting_project_{$project->id}", $database->database_name);
        $this->assertSame("hp_{$project->id}", $database->username);
        $this->assertSame($database->password, $this->server->provisioned[0]['password']);
        $this->assertNotSame($database->password, DB::table('project_databases')->value('password'));
        $this->assertSame(ProjectStatus::ChangesPending, $project->refresh()->status);
    }

    public function test_a_provisioning_failure_is_recorded_without_exposing_server_details(): void
    {
        $project = $this->project();
        $this->server->failure = 'root password and internal host leaked here';

        $this->actingAs($project->user)
            ->post(route('projects.database.store', $project))
            ->assertSessionHasErrors('database');

        $database = ProjectDatabase::query()->sole();
        $this->assertSame(ProjectDatabaseStatus::Failed, $database->status);
        $this->assertStringNotContainsString('root password', $database->last_error);
    }

    public function test_database_provisioning_requires_ownership_php_and_a_plan_allowance(): void
    {
        $project = $this->project(['runtime' => ProjectRuntime::Static]);

        $this->actingAs($project->user)
            ->post(route('projects.database.store', $project))
            ->assertSessionHasErrors('database');

        $project->update(['runtime' => ProjectRuntime::Php]);
        $project->user->plan->update(['database_mb' => 0]);
        $this->actingAs($project->user)
            ->post(route('projects.database.store', $project))
            ->assertSessionHasErrors('database');

        $project->user->plan->update(['database_mb' => 100]);
        $this->actingAs(User::factory()->create())
            ->post(route('projects.database.store', $project))
            ->assertForbidden();

        $this->assertDatabaseCount('project_databases', 0);
    }

    public function test_a_password_can_be_rotated_and_database_deleted(): void
    {
        $project = $this->project();
        $database = ProjectDatabase::factory()->for($project)->create();
        $oldPassword = $database->password;

        $this->actingAs($project->user)
            ->post(route('projects.database.rotate', $project))
            ->assertSessionHasNoErrors();

        $this->assertNotSame($oldPassword, $database->refresh()->password);
        $this->assertSame($database->password, $this->server->rotated[0]['password']);

        $this->actingAs($project->user)
            ->delete(route('projects.database.destroy', $project))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('project_databases', 0);
        $this->assertSame($database->database_name, $this->server->dropped[0]['database']);
    }

    public function test_account_database_quota_switches_all_databases_to_read_only_and_back(): void
    {
        $plan = Plan::factory()->create(['database_mb' => 1, 'website_limit' => 2]);
        $user = User::factory()->for($plan)->create();
        $first = ProjectDatabase::factory()->for(Project::factory()->for($user)->state(['runtime' => ProjectRuntime::Php]))->create();
        $second = ProjectDatabase::factory()->for(Project::factory()->for($user)->state(['runtime' => ProjectRuntime::Php]))->create();
        $this->server->sizes = [$first->database_name => 700_000, $second->database_name => 700_000];
        $manager = app(ProjectDatabaseManager::class);

        $this->assertSame(1_400_000, $manager->refreshUsageForUser($user));
        $this->assertSame(ProjectDatabaseStatus::QuotaExceeded, $first->refresh()->status);
        $this->assertSame(ProjectDatabaseStatus::QuotaExceeded, $second->refresh()->status);
        $this->assertCount(2, array_filter($this->server->accessChanges, fn (array $change): bool => $change['read_only']));

        $this->server->sizes = [$first->database_name => 100_000, $second->database_name => 100_000];
        $manager->refreshUsageForUser($user);
        $this->assertSame(ProjectDatabaseStatus::Active, $first->refresh()->status);
        $this->assertSame(ProjectDatabaseStatus::Active, $second->refresh()->status);
        $this->assertCount(2, array_filter($this->server->accessChanges, fn (array $change): bool => ! $change['read_only']));
    }

    public function test_deleting_a_project_queues_database_cleanup(): void
    {
        Queue::fake();
        $project = $this->project();
        $database = ProjectDatabase::factory()->for($project)->create();

        $project->delete();

        Queue::assertPushed(DropProjectDatabase::class, fn (DropProjectDatabase $job): bool => $job->databaseName === $database->database_name && $job->username === $database->username);
    }

    public function test_a_project_with_a_database_cannot_switch_to_static_runtime(): void
    {
        $project = $this->project();
        ProjectDatabase::factory()->for($project)->create();

        $this->actingAs($project->user)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'slug' => $project->slug,
                'runtime' => ProjectRuntime::Static->value,
            ])
            ->assertSessionHasErrors('runtime');

        $this->assertSame(ProjectRuntime::Php, $project->refresh()->runtime);
    }

    private function project(array $attributes = []): Project
    {
        $plan = Plan::factory()->create(['database_mb' => 200]);
        $user = User::factory()->for($plan)->create();

        return Project::factory()->for($user)->create($attributes + ['runtime' => ProjectRuntime::Php]);
    }
}
