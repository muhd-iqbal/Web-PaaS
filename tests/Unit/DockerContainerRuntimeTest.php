<?php

namespace Tests\Unit;

use App\Enums\ProjectRuntime;
use App\Models\Project;
use App\Models\ProjectDatabase;
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

    public function test_php_deployment_receives_managed_database_credentials_and_private_network(): void
    {
        Storage::fake('project_files');
        $project = Project::factory()->create(['runtime' => ProjectRuntime::Php, 'file_count' => 1]);
        $database = ProjectDatabase::factory()->for($project)->create(['password' => 'database-secret']);
        Storage::disk('project_files')->put($project->storageDirectory().'/index.php', '<?php echo "ok";');
        $runner = new RecordingCommandRunner;

        (new DockerContainerRuntime($runner))->deploy($project, 'php.sites.example.test');

        $runCommand = collect($runner->commands)->first(fn (array $command): bool => in_array('run', $command, true));
        $this->assertContains('DB_HOST='.$database->host, $runCommand);
        $this->assertContains('DB_DATABASE='.$database->database_name, $runCommand);
        $this->assertContains('DB_USERNAME='.$database->username, $runCommand);
        $this->assertContains('DB_PASSWORD=database-secret', $runCommand);
        $this->assertTrue(collect($runner->commands)->contains(
            fn (array $command): bool => in_array('connect', $command, true) && in_array('hosting_database', $command, true),
        ));
    }

    public function test_metrics_parse_bounded_docker_inspect_and_stats_output(): void
    {
        $project = Project::factory()->create(['container_name' => 'hosting-project-99']);
        $runner = new RecordingCommandRunner;

        $metrics = (new DockerContainerRuntime($runner))->metrics($project);

        $this->assertTrue($metrics->isRunning);
        $this->assertSame('healthy', $metrics->health);
        $this->assertSame(1.25, $metrics->cpuPercent);
        $this->assertSame(5.0, $metrics->memoryPercent);
        $this->assertSame(33_554_432, $metrics->memoryUsageBytes);
        $this->assertSame(1_073_741_824, $metrics->memoryLimitBytes);
        $this->assertSame(4, $metrics->processCount);
        $this->assertSame(2, $metrics->restartCount);
        $this->assertTrue(collect($runner->commands)->contains(
            fn (array $command): bool => in_array('stats', $command, true) && in_array('--no-stream', $command, true),
        ));
    }
}
