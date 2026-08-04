<?php

namespace Tests\Unit;

use App\Enums\ProjectRuntime;
use App\Models\Project;
use App\Services\DockerContainerRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\RecordingCommandRunner;
use Tests\TestCase;

class DockerContainerRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_builds_a_hardened_isolated_container_command(): void
    {
        Storage::fake('project_files');
        config()->set('hosting.deployment.base_domain', 'sites.example.test');
        $project = Project::factory()->create(['runtime' => ProjectRuntime::Static, 'file_count' => 1]);
        Storage::disk('project_files')->put($project->storageDirectory().'/index.html', '<!doctype html>');
        $runner = new RecordingCommandRunner;
        $runtime = new DockerContainerRuntime($runner);

        $instance = $runtime->deploy($project, $project->slug.'.sites.example.test');

        $runCommand = collect($runner->commands)->first(fn (array $command): bool => in_array('run', $command, true));
        $this->assertNotNull($runCommand);
        $this->assertContains('--read-only', $runCommand);
        $this->assertContains('ALL', $runCommand);
        $this->assertContains('no-new-privileges:true', $runCommand);
        $this->assertNotContains('--privileged', $runCommand);
        $this->assertContains('hosting_project_'.$project->id, $runCommand);
        $this->assertContains('traefik.http.services.project-'.$project->id.'.loadbalancer.server.port=8080', $runCommand);
        $this->assertTrue(collect($runCommand)->contains(fn (string $argument): bool => str_contains($argument, "Host(`{$project->slug}.sites.example.test`)")));
        $this->assertTrue(collect($runCommand)->contains(fn (string $argument): bool => str_contains($argument, 'target=/var/www/html,readonly')));
        $this->assertSame('hosting-project-'.$project->id, $instance->name);
    }
}
