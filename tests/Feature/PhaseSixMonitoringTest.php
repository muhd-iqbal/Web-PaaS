<?php

namespace Tests\Feature;

use App\Contracts\ContainerRuntime;
use App\Enums\AlertSeverity;
use App\Enums\ProjectStatus;
use App\Jobs\RunProjectDeployment;
use App\Models\AdminAlert;
use App\Models\BandwidthUsage;
use App\Models\Project;
use App\Models\User;
use App\Services\AdminAlertManager;
use App\Services\BandwidthQuotaManager;
use App\Services\DeploymentManager;
use App\Services\ResourceMonitoringCollector;
use App\Services\TraefikAccessLogImporter;
use App\ValueObjects\ContainerMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeContainerRuntime;
use Tests\TestCase;

class PhaseSixMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private string $accessLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessLog = storage_path('framework/testing/traefik-access.json');
        @unlink($this->accessLog);
        config()->set('hosting.monitoring.traefik_access_log', $this->accessLog);
    }

    protected function tearDown(): void
    {
        @unlink($this->accessLog);
        parent::tearDown();
    }

    public function test_traefik_requests_are_imported_incrementally_and_only_for_known_projects(): void
    {
        $project = Project::factory()->create();
        file_put_contents($this->accessLog, implode("\n", [
            json_encode($this->logEntry($project->id, 4096, 128)),
            json_encode($this->logEntry(999999, 8000, 200)),
            '{bad-json',
        ])."\n");
        $importer = app(TraefikAccessLogImporter::class);

        $first = $importer->import();
        $second = $importer->import();

        $this->assertSame(3, $first->linesRead);
        $this->assertSame(1, $first->requestsImported);
        $this->assertSame(0, $second->linesRead);
        $usage = BandwidthUsage::query()->sole();
        $this->assertSame(4096, $usage->bytes_sent);
        $this->assertSame(128, $usage->bytes_received);
        $this->assertSame(1, $usage->request_count);

        file_put_contents($this->accessLog, json_encode($this->logEntry($project->id, 2048, 64)), FILE_APPEND);
        $this->assertSame(0, $importer->import()->requestsImported);
        file_put_contents($this->accessLog, "\n", FILE_APPEND);
        $this->assertSame(1, $importer->import()->requestsImported);
        $this->assertSame(6144, $usage->refresh()->bytes_sent);
        $this->assertSame(2, $usage->request_count);
    }

    public function test_bandwidth_limit_raises_an_alert_and_queues_active_projects_for_suspension(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $user->plan->update(['bandwidth_mb' => 1]);
        $project = Project::factory()->for($user)->create([
            'status' => ProjectStatus::Active,
            'container_name' => 'hosting-project-1',
        ]);
        BandwidthUsage::query()->create([
            'project_id' => $project->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'bytes_sent' => 1_048_577,
            'request_count' => 1,
        ]);
        $manager = new BandwidthQuotaManager(app(AdminAlertManager::class), app(DeploymentManager::class));

        $manager->enforce($user);

        $this->assertDatabaseHas('admin_alerts', [
            'fingerprint' => "bandwidth:user:{$user->id}",
            'severity' => AlertSeverity::Critical->value,
            'resolved_at' => null,
        ]);
        $this->assertSame(ProjectStatus::Deploying, $project->refresh()->status);
        Queue::assertPushed(RunProjectDeployment::class);
    }

    public function test_resource_collection_records_metrics_and_resolves_recovered_alerts(): void
    {
        $runtime = new FakeContainerRuntime;
        $runtime->containerMetrics = new ContainerMetrics(true, 'healthy', 95, 25, 25, 100, 4, 1);
        $project = Project::factory()->create([
            'status' => ProjectStatus::Active,
            'container_name' => 'hosting-project-1',
        ]);
        $collector = new ResourceMonitoringCollector($runtime, app(AdminAlertManager::class));

        $snapshot = $collector->collect($project);
        $this->assertSame(95.0, $snapshot->cpu_percent);
        $this->assertDatabaseHas('admin_alerts', ['fingerprint' => "cpu-high:project:{$project->id}", 'resolved_at' => null]);

        $runtime->containerMetrics = new ContainerMetrics(true, 'healthy', 5, 25, 25, 100, 4, 1);
        $collector->collect($project);

        $this->assertNotNull(AdminAlert::query()->where('fingerprint', "cpu-high:project:{$project->id}")->value('resolved_at'));
    }

    public function test_container_logs_are_owner_only_bounded_and_html_escaped(): void
    {
        $runtime = new class extends FakeContainerRuntime
        {
            public function logs(Project $project, int $lines): string
            {
                return "<script>alert(1)</script> {$lines}";
            }
        };
        $this->app->instance(ContainerRuntime::class, $runtime);
        $project = Project::factory()->create(['container_name' => 'hosting-project-1']);

        $this->actingAs($project->user)
            ->get(route('projects.logs', [$project, 'lines' => 99999]))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; 500', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.logs', $project))
            ->assertForbidden();
    }

    public function test_administrators_can_view_monitoring_alerts_in_filament(): void
    {
        $alert = app(AdminAlertManager::class)->raise(
            'test-system-alert',
            'monitoring_test',
            AlertSeverity::Warning,
            'Test monitoring alert',
            'A monitoring condition needs attention.',
        );
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/admin-alerts')
            ->assertOk()
            ->assertSee('Test monitoring alert');
        $this->get('/admin/admin-alerts/'.$alert->id)
            ->assertOk()
            ->assertSee('A monitoring condition needs attention.');
    }

    /** @return array<string, mixed> */
    private function logEntry(int $projectId, int $sent, int $received): array
    {
        return [
            'RouterName' => "project-{$projectId}@docker",
            'StartUTC' => now()->utc()->toIso8601String(),
            'DownstreamContentSize' => $sent,
            'RequestContentSize' => $received,
        ];
    }
}
