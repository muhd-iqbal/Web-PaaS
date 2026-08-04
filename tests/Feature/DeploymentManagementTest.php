<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use App\Enums\ProjectStatus;
use App\Jobs\DestroyProjectContainer;
use App\Jobs\RunProjectDeployment;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\User;
use App\Services\DeploymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Fakes\FakeContainerRuntime;
use Tests\TestCase;

class DeploymentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hosting.deployment.base_domain', 'sites.example.test');
        Storage::fake('project_files');
    }

    public function test_an_owner_can_queue_an_initial_deployment(): void
    {
        Queue::fake();
        $project = Project::factory()->create(['file_count' => 2]);

        $this->actingAs($project->user)
            ->post(route('projects.deploy', $project))
            ->assertRedirect(route('projects.show', $project));

        $deployment = Deployment::query()->sole();
        $this->assertSame(DeploymentType::Deploy, $deployment->type);
        $this->assertSame(DeploymentStatus::Pending, $deployment->status);
        $this->assertSame('https://'.$project->slug.'.sites.example.test', $deployment->url);
        $this->assertSame(ProjectStatus::Deploying, $project->refresh()->status);
        $this->assertNull($project->url);
        Queue::assertPushed(RunProjectDeployment::class);
    }

    public function test_deployment_requires_files_and_project_ownership(): void
    {
        Queue::fake();
        $project = Project::factory()->create(['file_count' => 0]);

        $this->actingAs($project->user)
            ->post(route('projects.deploy', $project))
            ->assertSessionHasErrors('deployment');

        $this->actingAs(User::factory()->create())
            ->post(route('projects.deploy', $project))
            ->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_a_successful_deployment_activates_the_project(): void
    {
        Queue::fake();
        $runtime = new FakeContainerRuntime;
        $manager = new DeploymentManager($runtime);
        $project = Project::factory()->create(['file_count' => 1]);
        $deployment = $manager->queueDeploy($project, $project->user);

        $manager->perform($deployment);

        $deployment->refresh();
        $project->refresh();
        $this->assertSame(DeploymentStatus::Succeeded, $deployment->status);
        $this->assertSame(ProjectStatus::Active, $project->status);
        $this->assertSame("hosting-project-{$project->id}", $project->container_name);
        $this->assertNotNull($project->deployed_at);
        $this->assertSame($deployment->url, $project->url);
        $this->assertSame([['project_id' => $project->id, 'hostname' => $deployment->hostname]], $runtime->deployments);
    }

    public function test_a_failed_deployment_records_a_safe_failure_state(): void
    {
        Queue::fake();
        $runtime = new FakeContainerRuntime;
        $runtime->failure = 'Docker daemon unavailable';
        $manager = new DeploymentManager($runtime);
        $project = Project::factory()->create(['file_count' => 1]);
        $deployment = $manager->queueDeploy($project, $project->user);

        try {
            $manager->perform($deployment);
            $this->fail('The deployment exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Docker daemon unavailable', $exception->getMessage());
        }

        $this->assertSame(DeploymentStatus::Failed, $deployment->refresh()->status);
        $this->assertSame(ProjectStatus::Failed, $project->refresh()->status);
        $this->assertSame('Docker daemon unavailable', $project->last_deployment_error);
    }

    public function test_restart_and_suspension_are_recorded_as_lifecycle_operations(): void
    {
        Queue::fake();
        $runtime = new FakeContainerRuntime;
        $manager = new DeploymentManager($runtime);
        $project = Project::factory()->create([
            'status' => ProjectStatus::Active,
            'container_name' => 'hosting-project-42',
            'hostname' => 'site.sites.example.test',
            'url' => 'https://site.sites.example.test',
            'deployed_at' => now(),
        ]);

        $restart = $manager->queueRestart($project, $project->user);
        $manager->perform($restart);
        $this->assertSame([$project->id], $runtime->restarts);
        $this->assertSame(ProjectStatus::Active, $project->refresh()->status);

        $suspend = $manager->queueSuspend($project, $project->user);
        $manager->perform($suspend);
        $this->assertSame([$project->id], $runtime->stops);
        $this->assertSame(ProjectStatus::Suspended, $project->refresh()->status);
        $this->assertSame(DeploymentType::Suspend, $suspend->type);
    }

    public function test_deleting_a_deployed_project_queues_container_cleanup(): void
    {
        Queue::fake();
        $project = Project::factory()->create(['container_name' => 'hosting-project-1']);

        $project->delete();

        Queue::assertPushed(DestroyProjectContainer::class, fn (DestroyProjectContainer $job): bool => $job->projectId === $project->id);
    }

    public function test_a_project_cannot_be_changed_or_deleted_while_deploying(): void
    {
        $project = Project::factory()->create([
            'status' => ProjectStatus::Deploying,
            'file_count' => 1,
        ]);

        $this->actingAs($project->user)
            ->put(route('projects.update', $project), [
                'name' => 'Changed during deployment',
                'slug' => $project->slug,
                'runtime' => $project->runtime->value,
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($project->user)
            ->delete(route('projects.destroy', $project))
            ->assertSessionHasErrors('deployment');

        $this->assertSame($project->name, $project->refresh()->name);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }
}
