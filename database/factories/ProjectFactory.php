<?php

namespace Database\Factories;

use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'runtime' => fake()->randomElement(ProjectRuntime::cases()),
            'status' => ProjectStatus::Draft,
        ];
    }
}
