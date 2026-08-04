<?php

namespace Database\Factories;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentType;
use App\Models\Deployment;
use App\Models\Deployment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
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
            'triggered_by' => fn (array $attributes): int => Project::query()->findOrFail($attributes['project_id'])->user_id,
            'type' => DeploymentType::Deploy,
            'status' => DeploymentStatus::Pending,
            'runtime' => 'static',
            'hostname' => fake()->unique()->domainName(),
            'url' => fn (array $attributes): string => 'https://'.$attributes['hostname'],
        ];
    }
}
