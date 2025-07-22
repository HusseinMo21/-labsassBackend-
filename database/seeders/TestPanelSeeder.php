<?php

namespace Database\Seeders;

use App\Models\TestPanel;
use App\Models\LabTest;
use Illuminate\Database\Seeder;

class TestPanelSeeder extends Seeder
{
    public function run(): void
    {
        $this->createTumorMarkerPanel();
        $this->createLiverFunctionPanel();
        $this->createKidneyFunctionPanel();
        $this->createCompleteBloodCountPanel();
        $this->createElectrolytePanel();
    }

    private function createTumorMarkerPanel()
    {
        $panel = TestPanel::create([
            'name' => 'Tumor Marker Panel',
            'code' => 'TMP001',
            'description' => 'Comprehensive tumor marker panel for cancer screening and monitoring',
            'price' => 150.00,
            'is_active' => true,
        ]);

        $tumorMarkers = [
            'PSA (Prostate Specific Antigen)',
            'CEA (Carcinoembryonic Antigen)',
            'AFP (Alpha-Fetoprotein)',
            'CA-125 (Cancer Antigen 125)',
            'CA-19-9 (Cancer Antigen 19-9)',
        ];

        foreach ($tumorMarkers as $index => $markerName) {
            $test = LabTest::where('name', 'like', "%{$markerName}%")->first();
            if ($test) {
                $panel->addTest($test->id, $index + 1, true);
            }
        }
    }

    private function createLiverFunctionPanel()
    {
        $panel = TestPanel::create([
            'name' => 'Liver Function Panel',
            'code' => 'LFP001',
            'description' => 'Comprehensive liver function tests for hepatic assessment',
            'price' => 85.00,
            'is_active' => true,
        ]);

        $liverTests = [
            'ALT (Alanine Aminotransferase)',
            'AST (Aspartate Aminotransferase)',
            'Alkaline Phosphatase',
            'Total Bilirubin',
            'Direct Bilirubin',
            'Total Protein',
            'Albumin',
        ];

        foreach ($liverTests as $index => $testName) {
            $test = LabTest::where('name', 'like', "%{$testName}%")->first();
            if ($test) {
                $panel->addTest($test->id, $index + 1, true);
            }
        }
    }

    private function createKidneyFunctionPanel()
    {
        $panel = TestPanel::create([
            'name' => 'Kidney Function Panel',
            'code' => 'KFP001',
            'description' => 'Comprehensive kidney function tests for renal assessment',
            'price' => 75.00,
            'is_active' => true,
        ]);

        $kidneyTests = [
            'Creatinine',
            'Blood Urea Nitrogen (BUN)',
            'Uric Acid',
            'Calcium',
            'Phosphorus',
        ];

        foreach ($kidneyTests as $index => $testName) {
            $test = LabTest::where('name', 'like', "%{$testName}%")->first();
            if ($test) {
                $panel->addTest($test->id, $index + 1, true);
            }
        }
    }

    private function createCompleteBloodCountPanel()
    {
        $panel = TestPanel::create([
            'name' => 'Complete Blood Count (CBC)',
            'code' => 'CBC001',
            'description' => 'Complete blood count with differential for hematological assessment',
            'price' => 45.00,
            'is_active' => true,
        ]);

        $cbcTests = [
            'Complete Blood Count (CBC)',
            'Hemoglobin',
            'Hematocrit',
            'White Blood Cell Count',
            'Platelet Count',
        ];

        foreach ($cbcTests as $index => $testName) {
            $test = LabTest::where('name', 'like', "%{$testName}%")->first();
            if ($test) {
                $panel->addTest($test->id, $index + 1, true);
            }
        }
    }

    private function createElectrolytePanel()
    {
        $panel = TestPanel::create([
            'name' => 'Electrolyte Panel',
            'code' => 'EP001',
            'description' => 'Comprehensive electrolyte panel for metabolic assessment',
            'price' => 65.00,
            'is_active' => true,
        ]);

        $electrolyteTests = [
            'Sodium',
            'Potassium',
            'Chloride',
            'Carbon Dioxide (CO2)',
            'Glucose',
        ];

        foreach ($electrolyteTests as $index => $testName) {
            $test = LabTest::where('name', 'like', "%{$testName}%")->first();
            if ($test) {
                $panel->addTest($test->id, $index + 1, true);
            }
        }
    }
} 