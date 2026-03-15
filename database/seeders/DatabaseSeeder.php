<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LabSeeder::class,
            PlanSeeder::class,
            DemoCredentialsSeeder::class,
            TestCategorySeeder::class,
            LabTestSeeder::class,
            MultiTenantWorkflowSeeder::class,
        ]);
    }
} 