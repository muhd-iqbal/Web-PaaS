<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectUpload>
 */
class ProjectUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $attributes): int => Project::query()->findOrFail($attributes['project_id'])->user_id,
            'original_name' => 'website.zip',
            'archive_size_bytes' => 1024,
            'extracted_size_bytes' => 2048,
            'file_count' => 2,
            'sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
