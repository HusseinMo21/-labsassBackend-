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
            PlatformMasterClinicalCatalogSeeder::class,
            SyncLabCatalogFromMasterTestsSeeder::class,
            MultiTenantWorkflowSeeder::class,
        ]);
    }
} 