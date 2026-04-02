<?php

namespace Database\Seeders;

use App\Models\LabTest;
use App\Models\TestCategory;
use Illuminate\Database\Seeder;

/**
 * @deprecated Legacy minimal seed. Fresh installs use {@see PlatformMasterClinicalCatalogSeeder} from DatabaseSeeder.
 */
class LabTestSeeder extends Seeder
{
    public function run(): void
    {
        $category = TestCategory::where('code', 'path')->first()
            ?? TestCategory::first();

        if (!$category) {
            $this->command->warn('No test category found. Run TestCategorySeeder first.');
            return;
        }

        $tests = [
            ['name' => 'General Pathology', 'code' => 'PATH001', 'price' => 100],
            ['name' => 'CBC - Complete Blood Count', 'code' => 'CBC001', 'price' => 80],
            ['name' => 'HbA1c - Glycated Hemoglobin', 'code' => 'HBA1C01', 'price' => 120],
            ['name' => 'Liver Function Test', 'code' => 'LFT001', 'price' => 150],
            ['name' => 'Kidney Function Test', 'code' => 'KFT001', 'price' => 130],
        ];

        foreach ($tests as $test) {
            LabTest::firstOrCreate(
                ['code' => $test['code']],
                [
                    'name' => $test['name'],
                    'description' => $test['name'] . ' - Laboratory test',
                    'price' => $test['price'],
                    'category_id' => $category->id,
                    'turnaround_time_hours' => 24,
                    'is_active' => true,
                ]
            );
        }
    }
}
