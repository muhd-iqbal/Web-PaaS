<?php

namespace Tests\Feature;

use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_update_and_delete_their_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Portfolio Site',
            'slug' => 'My Portfolio',
            'runtime' => ProjectRuntime::Static->value,
        ])->assertRedirect();

        $project = Project::query()->sole();
        $this->assertSame($user->id, $project->user_id);
        $this->assertSame('my-portfolio', $project->slug);
        $this->assertSame(ProjectStatus::Draft, $project->status);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'Updated Portfolio',
            'slug' => 'updated-portfolio',
            'runtime' => ProjectRuntime::Php->value,
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Portfolio',
            'runtime' => ProjectRuntime::Php->value,
        ]);

        $this->actingAs($user)->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_a_user_cannot_access_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($otherUser)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($otherUser)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($otherUser)->delete(route('projects.destroy', $project))->assertForbidden();
    }

    public function test_the_plan_website_limit_is_enforced(): void
    {
        $plan = Plan::factory()->create(['website_limit' => 1]);
        $user = User::factory()->for($plan)->create();
        Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Second Site',
            'slug' => 'second-site',
            'runtime' => ProjectRuntime::Static->value,
        ])->assertSessionHasErrors('plan');

        $this->assertSame(1, $user->projects()->count());
    }
}
