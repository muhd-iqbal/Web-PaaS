<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 0, 30),
            'website_limit' => fake()->numberBetween(1, 5),
            'storage_mb' => fake()->randomElement([500, 2048, 5120]),
            'bandwidth_mb' => fake()->randomElement([5120, 20480, 51200]),
            'database_mb' => fake()->randomElement([0, 200, 500]),
            'max_upload_mb' => fake()->randomElement([50, 100, 200]),
            'max_extracted_mb' => fake()->randomElement([100, 500, 1024]),
            'max_file_count' => fake()->randomElement([1000, 5000, 15000]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
