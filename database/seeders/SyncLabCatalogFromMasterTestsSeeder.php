<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\LabTest;
use App\Models\LabTestOffering;
use Illuminate\Database\Seeder;

class SyncLabCatalogFromMasterTestsSeeder extends Seeder
{
    /**
     * Create lab_test_offerings from master lab_tests for every lab (default catalog).
     */
    public function run(): void
    {
        $tests = LabTest::where('is_active', true)->get();
        if ($tests->isEmpty()) {
            $this->command->warn('No lab_tests found. Run LabTestSeeder first.');
            return;
        }

        foreach (Lab::all() as $lab) {
            foreach ($tests as $test) {
                LabTestOffering::firstOrCreate(
                    [
                        'lab_id' => $lab->id,
                        'lab_test_id' => $test->id,
                    ],
                    [
                        'price' => $test->price,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Lab catalog offerings synced for all labs.');
    }
}
