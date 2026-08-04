<?php

namespace Tests\Feature;

use App\Enums\ProjectRuntime;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ProjectFileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('project_files');
    }

    public function test_a_user_can_upload_and_replace_a_wrapped_website_zip(): void
    {
        $project = $this->project();
        $archive = $this->zip([
            'my-site/index.html' => '<!doctype html><title>Hello</title>',
            'my-site/assets/app.js' => 'console.log("hello");',
        ]);

        $this->actingAs($project->user)
            ->post(route('projects.files.store', $project), ['archive' => $archive])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(2, $project->file_count);
        $this->assertGreaterThan(0, $project->storage_bytes);
        $this->assertNotNull($project->files_updated_at);
        $this->assertDatabaseHas('project_files', ['project_id' => $project->id, 'path' => 'index.html']);
        $this->assertDatabaseHas('project_uploads', ['project_id' => $project->id, 'file_count' => 2]);
        Storage::disk('project_files')->assertExists($project->storageDirectory().'/index.html');
        Storage::disk('project_files')->assertExists($project->storageDirectory().'/assets/app.js');
        $this->actingAs($project->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('assets/app.js');

        $replacement = $this->zip(['index.html' => '<!doctype html><title>New</title>']);
        $this->actingAs($project->user)
            ->post(route('projects.files.store', $project), ['archive' => $replacement])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(1, $project->file_count);
        $this->assertSame(2, $project->uploads()->count());
        Storage::disk('project_files')->assertMissing($project->storageDirectory().'/assets/app.js');
    }

    public function test_zip_path_traversal_is_rejected_without_replacing_existing_files(): void
    {
        $project = $this->project();
        $valid = $this->zip(['index.html' => '<!doctype html><title>Safe</title>']);
        $this->actingAs($project->user)->post(route('projects.files.store', $project), ['archive' => $valid]);

        $malicious = $this->zip([
            'index.html' => '<!doctype html><title>Unsafe</title>',
            '../outside.txt' => 'escaped',
        ]);

        $this->actingAs($project->user)
            ->post(route('projects.files.store', $project), ['archive' => $malicious])
            ->assertSessionHasErrors('archive');

        $this->assertSame(
            '<!doctype html><title>Safe</title>',
            Storage::disk('project_files')->get($project->storageDirectory().'/index.html'),
        );
        Storage::disk('project_files')->assertMissing('outside.txt');
        $this->assertSame(1, $project->uploads()->count());
    }

    public function test_symlinks_and_disallowed_runtime_files_are_rejected(): void
    {
        $project = $this->project(['runtime' => ProjectRuntime::Static]);
        $symlink = $this->zip(
            ['index.html' => '<!doctype html>', 'linked.html' => 'index.html'],
            function (ZipArchive $zip): void {
                $zip->setExternalAttributesName('linked.html', ZipArchive::OPSYS_UNIX, 0120777 << 16);
            },
        );

        $this->actingAs($project->user)
            ->post(route('projects.files.store', $project), ['archive' => $symlink])
            ->assertSessionHasErrors('archive');

        $phpArchive = $this->zip([
            'index.html' => '<!doctype html>',
            'shell.php' => '<?php echo "no";',
        ]);

        $this->actingAs($project->user)
            ->post(route('projects.files.store', $project), ['archive' => $phpArchive])
            ->assertSessionHasErrors('archive');

        $this->assertSame(0, $project->uploads()->count());
    }

    public function test_file_count_extracted_size_and_account_storage_limits_are_enforced(): void
    {
        $plan = Plan::factory()->create([
            'storage_mb' => 1,
            'max_upload_mb' => 5,
            'max_extracted_mb' => 1,
            'max_file_count' => 1,
        ]);
        $user = User::factory()->for($plan)->create();
        $project = Project::factory()->for($user)->create();

        $tooMany = $this->zip(['index.html' => '<!doctype html>', 'app.js' => 'alert(1)']);
        $this->actingAs($user)->post(route('projects.files.store', $project), ['archive' => $tooMany])
            ->assertSessionHasErrors('archive');

        $plan->update(['max_file_count' => 10]);
        $tooLarge = $this->zip(['index.html' => str_repeat('a', 1_048_577)]);
        $this->actingAs($user)->post(route('projects.files.store', $project), ['archive' => $tooLarge])
            ->assertSessionHasErrors('archive');

        $plan->update(['max_extracted_mb' => 2]);
        Project::factory()->for($user)->create(['storage_bytes' => 900_000]);
        $overAccountQuota = $this->zip(['index.html' => str_repeat('a', 200_000)]);
        $this->actingAs($user)->post(route('projects.files.store', $project), ['archive' => $overAccountQuota])
            ->assertSessionHasErrors('archive');

        $this->assertSame(0, $project->uploads()->count());
    }

    public function test_an_index_file_is_required_at_the_extracted_root(): void
    {
        $project = $this->project();
        $archive = $this->zip(['pages/home.html' => '<!doctype html>']);

        $this->actingAs($project->user)
            ->post(route('projects.files.store', $project), ['archive' => $archive])
            ->assertSessionHasErrors('archive');
    }

    public function test_a_file_with_a_spoofed_image_extension_is_rejected(): void
    {
        $project = $this->project();
        $archive = $this->zip([
            'index.html' => '<!doctype html>',
            'logo.png' => '<?php echo "not an image";',
        ]);

        $this->actingAs($project->user)
            ->post(route('projects.files.store', $project), ['archive' => $archive])
            ->assertSessionHasErrors('archive');

        $this->assertSame(0, $project->files()->count());
    }

    public function test_an_owner_can_download_and_delete_a_project_file(): void
    {
        $project = $this->project();
        $archive = $this->zip(['index.html' => '<!doctype html><title>Site</title>']);
        $this->actingAs($project->user)->post(route('projects.files.store', $project), ['archive' => $archive]);
        $file = $project->files()->firstOrFail();

        $this->actingAs($project->user)
            ->get(route('projects.files.download', [$project, $file]))
            ->assertDownload('index.html');

        $this->actingAs($project->user)
            ->delete(route('projects.files.destroy', [$project, $file]))
            ->assertRedirect(route('projects.show', $project));

        $project->refresh();
        $this->assertSame(0, $project->file_count);
        $this->assertSame(0, $project->storage_bytes);
        $this->assertDatabaseMissing('project_files', ['id' => $file->id]);
        Storage::disk('project_files')->assertMissing($project->storageDirectory().'/index.html');
    }

    public function test_users_cannot_manage_files_from_another_project(): void
    {
        $project = $this->project();
        $archive = $this->zip(['index.html' => '<!doctype html>']);
        $this->actingAs($project->user)->post(route('projects.files.store', $project), ['archive' => $archive]);
        $file = $project->files()->firstOrFail();
        $otherUser = User::factory()->create();

        $otherArchive = $this->zip(['index.html' => '<!doctype html><title>Other</title>']);
        $this->actingAs($otherUser)
            ->post(route('projects.files.store', $project), ['archive' => $otherArchive])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->get(route('projects.files.download', [$project, $file]))
            ->assertForbidden();
        $this->actingAs($otherUser)
            ->delete(route('projects.files.destroy', [$project, $file]))
            ->assertForbidden();
    }

    public function test_deleting_a_project_removes_its_private_files(): void
    {
        $project = $this->project();
        $archive = $this->zip(['index.html' => '<!doctype html>']);
        $this->actingAs($project->user)->post(route('projects.files.store', $project), ['archive' => $archive]);

        $this->actingAs($project->user)->delete(route('projects.destroy', $project));

        Storage::disk('project_files')->assertMissing($project->storageDirectory());
    }

    private function project(array $attributes = []): Project
    {
        $plan = Plan::factory()->create([
            'storage_mb' => 100,
            'max_upload_mb' => 10,
            'max_extracted_mb' => 20,
            'max_file_count' => 100,
        ]);
        $user = User::factory()->for($plan)->create();

        return Project::factory()->for($user)->create($attributes);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files, ?callable $configure = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'website-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $configure?->__invoke($zip);
        $zip->close();

        return new UploadedFile($path, 'website.zip', 'application/zip', null, true);
    }
}
