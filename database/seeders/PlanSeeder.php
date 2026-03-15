<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'price_period' => 'monthly',
                'max_users' => 2,
                'max_tests_per_month' => 50,
                'features' => ['basic_reports'],
                'is_active' => true,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 299,
                'price_period' => 'monthly',
                'max_users' => 5,
                'max_tests_per_month' => 500,
                'features' => ['basic_reports', 'patient_portal'],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 599,
                'price_period' => 'monthly',
                'max_users' => 15,
                'max_tests_per_month' => 2000,
                'features' => ['basic_reports', 'patient_portal', 'advanced_reports', 'api_access'],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 1499,
                'price_period' => 'monthly',
                'max_users' => null,
                'max_tests_per_month' => null,
                'features' => ['basic_reports', 'patient_portal', 'advanced_reports', 'api_access', 'priority_support'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
