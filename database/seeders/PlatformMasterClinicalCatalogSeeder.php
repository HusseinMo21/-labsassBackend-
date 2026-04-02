<?php

namespace Database\Seeders;

use App\Models\LabTest;
use App\Models\TestCategory;
use Database\Seeders\MasterCatalog\MasterClinicalTestsCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds platform (lab_id = null) test categories and a large master lab test list with report_template JSON
 * for each test. Shown under Platform → Master catalog (Tests & categories).
 */
class PlatformMasterClinicalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MasterClinicalTestsCatalog::categories() as $cat) {
            TestCategory::updateOrCreate(
                [
                    'code' => $cat['code'],
                    'lab_id' => $cat['lab_id'],
                ],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'is_active' => $cat['is_active'],
                    'sort_order' => $cat['sort_order'] ?? null,
                    'report_type' => $cat['report_type'] ?? 'numeric',
                ]
            );
        }

        $categoryIds = TestCategory::query()
            ->whereNull('lab_id')
            ->pluck('id', 'code')
            ->all();

        $count = 0;
        foreach (MasterClinicalTestsCatalog::tests() as $test) {
            $code = $test['category_code'];
            $categoryId = $categoryIds[$code] ?? null;
            if ($categoryId === null) {
                $this->command->warn("Skip test {$test['code']}: category code [{$code}] not found.");

                continue;
            }

            LabTest::updateOrCreate(
                [
                    'code' => $test['code'],
                    'lab_id' => null,
                ],
                [
                    'name' => $test['name'],
                    'description' => $test['description'] ?? $test['name'],
                    'price' => $test['price'],
                    'category_id' => $categoryId,
                    'turnaround_time_hours' => $test['turnaround_time_hours'] ?? 24,
                    'is_active' => true,
                    'report_template' => $test['report_template'],
                    'unit' => null,
                    'reference_range' => null,
                ]
            );
            $count++;
        }

        $this->command->info("Platform master clinical catalog: {$count} lab tests upserted.");
    }
}
