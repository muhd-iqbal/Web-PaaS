<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free Trial', 'slug' => 'free-trial',
                'description' => 'Try simple website hosting before choosing a paid plan.',
                'monthly_price' => 0, 'website_limit' => 1, 'storage_mb' => 250,
                'bandwidth_mb' => 2048, 'database_mb' => 0, 'max_upload_mb' => 25,
                'max_extracted_mb' => 100, 'max_file_count' => 1000,
                'trial_days' => 7, 'currency' => 'myr',
                'sort_order' => 10,
            ],
            [
                'name' => 'Student', 'slug' => 'student',
                'description' => 'Everything a student needs for assignments and small projects.',
                'monthly_price' => 5, 'website_limit' => 1, 'storage_mb' => 2048,
                'bandwidth_mb' => 20480, 'database_mb' => 200, 'max_upload_mb' => 100,
                'max_extracted_mb' => 500, 'max_file_count' => 5000,
                'currency' => 'myr',
                'sort_order' => 20,
            ],
            [
                'name' => 'Fresh Grad', 'slug' => 'developer',
                'description' => 'Multiple sites and higher limits for active development.',
                'monthly_price' => 15, 'website_limit' => 5, 'storage_mb' => 10240,
                'bandwidth_mb' => 102400, 'database_mb' => 1024, 'max_upload_mb' => 250,
                'max_extracted_mb' => 1024, 'max_file_count' => 15000,
                'currency' => 'myr',
                'sort_order' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan + ['is_active' => true]);
        }
    }
}
