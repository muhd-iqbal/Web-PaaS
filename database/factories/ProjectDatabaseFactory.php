<?php

namespace Database\Factories;

use App\Enums\ProjectDatabaseStatus;
use App\Models\Project;
use App\Models\ProjectDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectDatabase> */
class ProjectDatabaseFactory extends Factory
{
    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1, 999999);

        return [
            'project_id' => Project::factory(),
            'status' => ProjectDatabaseStatus::Active,
            'database_name' => "hosting_project_{$suffix}",
            'username' => "hp_{$suffix}",
            'password' => fake()->regexify('[A-Za-z0-9]{48}'),
            'host' => 'hosting-database',
            'port' => 3306,
            'provisioned_at' => now(),
        ];
    }
}
