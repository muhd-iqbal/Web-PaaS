<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['plan_id' => null]),
            'plan_id' => Plan::factory(),
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_'.fake()->unique()->regexify('[A-Za-z0-9]{20}'),
            'provider_price_id' => 'price_'.fake()->regexify('[A-Za-z0-9]{20}'),
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ];
    }
}
