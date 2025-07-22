<?php

namespace Database\Seeders;

use App\Models\LabTest;
use App\Models\TestCategory;
use Illuminate\Database\Seeder;

class LabTestSeeder extends Seeder
{
    public function run(): void
    {
        $categories = TestCategory::all()->keyBy('name');

        $tests = [
            // Blood Tests
            [
                'name' => 'Complete Blood Count (CBC)',
                'code' => 'CBC001',
                'description' => 'Complete blood count including RBC, WBC, and platelets',
                'price' => 25.00,
                'unit' => 'cells/μL',
                'reference_range' => 'RBC: 4.5-5.5M, WBC: 4.5-11K, Platelets: 150-450K',
                'preparation_instructions' => 'Fasting not required',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Blood Tests']->id,
                'is_active' => true,
            ],
            [
                'name' => 'Hemoglobin A1c',
                'code' => 'HBA1C001',
                'description' => 'Glycated hemoglobin test for diabetes monitoring',
                'price' => 35.00,
                'unit' => '%',
                'reference_range' => '4.0-5.6%',
                'preparation_instructions' => 'Fasting not required',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Blood Tests']->id,
                'is_active' => true,
            ],

            // Biochemistry
            [
                'name' => 'Glucose (Fasting)',
                'code' => 'GLU001',
                'description' => 'Fasting blood glucose test',
                'price' => 15.00,
                'unit' => 'mg/dL',
                'reference_range' => '70-100 mg/dL',
                'preparation_instructions' => '8-12 hours fasting required',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Biochemistry']->id,
                'is_active' => true,
            ],
            [
                'name' => 'Cholesterol Panel',
                'code' => 'CHOL001',
                'description' => 'Total cholesterol, HDL, LDL, and triglycerides',
                'price' => 45.00,
                'unit' => 'mg/dL',
                'reference_range' => 'Total: <200, HDL: >40, LDL: <100, Trig: <150',
                'preparation_instructions' => '12-14 hours fasting required',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Biochemistry']->id,
                'is_active' => true,
            ],
            [
                'name' => 'Creatinine',
                'code' => 'CRE001',
                'description' => 'Kidney function test',
                'price' => 20.00,
                'unit' => 'mg/dL',
                'reference_range' => '0.7-1.3 mg/dL',
                'preparation_instructions' => 'Fasting not required',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Biochemistry']->id,
                'is_active' => true,
            ],

            // Urine Tests
            [
                'name' => 'Urinalysis',
                'code' => 'UA001',
                'description' => 'Complete urinalysis with microscopic examination',
                'price' => 30.00,
                'unit' => 'N/A',
                'reference_range' => 'Normal appearance, no protein, no glucose',
                'preparation_instructions' => 'Clean catch midstream urine sample',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Urine Tests']->id,
                'is_active' => true,
            ],
            [
                'name' => 'Urine Culture',
                'code' => 'UC001',
                'description' => 'Bacterial culture and sensitivity',
                'price' => 55.00,
                'unit' => 'CFU/mL',
                'reference_range' => '<10,000 CFU/mL',
                'preparation_instructions' => 'Clean catch midstream urine sample',
                'turnaround_time_hours' => 72,
                'category_id' => $categories['Urine Tests']->id,
                'is_active' => true,
            ],

            // Immunology
            [
                'name' => 'HIV Antibody Test',
                'code' => 'HIV001',
                'description' => 'HIV antibody screening test',
                'price' => 40.00,
                'unit' => 'N/A',
                'reference_range' => 'Non-reactive',
                'preparation_instructions' => 'Fasting not required',
                'turnaround_time_hours' => 48,
                'category_id' => $categories['Immunology']->id,
                'is_active' => true,
            ],
            [
                'name' => 'Hepatitis B Surface Antigen',
                'code' => 'HBSAG001',
                'description' => 'Hepatitis B surface antigen test',
                'price' => 35.00,
                'unit' => 'N/A',
                'reference_range' => 'Negative',
                'preparation_instructions' => 'Fasting not required',
                'turnaround_time_hours' => 48,
                'category_id' => $categories['Immunology']->id,
                'is_active' => true,
            ],

            // Hormone Tests
            [
                'name' => 'Thyroid Stimulating Hormone (TSH)',
                'code' => 'TSH001',
                'description' => 'Thyroid function test',
                'price' => 40.00,
                'unit' => 'μIU/mL',
                'reference_range' => '0.4-4.0 μIU/mL',
                'preparation_instructions' => 'Fasting not required',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Hormone Tests']->id,
                'is_active' => true,
            ],
            [
                'name' => 'Testosterone (Total)',
                'code' => 'TEST001',
                'description' => 'Total testosterone level',
                'price' => 50.00,
                'unit' => 'ng/dL',
                'reference_range' => '300-1000 ng/dL',
                'preparation_instructions' => 'Morning sample preferred',
                'turnaround_time_hours' => 48,
                'category_id' => $categories['Hormone Tests']->id,
                'is_active' => true,
            ],

            // Cardiac Markers
            [
                'name' => 'Troponin I',
                'code' => 'TROP001',
                'description' => 'Cardiac troponin I for heart attack detection',
                'price' => 60.00,
                'unit' => 'ng/mL',
                'reference_range' => '<0.04 ng/mL',
                'preparation_instructions' => 'Fasting not required',
                'turnaround_time_hours' => 24,
                'category_id' => $categories['Cardiac Markers']->id,
                'is_active' => true,
            ],

            // Tumor Markers
            [
                'name' => 'PSA (Prostate Specific Antigen)',
                'code' => 'PSA001',
                'description' => 'Prostate cancer screening test',
                'price' => 45.00,
                'unit' => 'ng/mL',
                'reference_range' => '<4.0 ng/mL',
                'preparation_instructions' => 'No ejaculation 48 hours prior',
                'turnaround_time_hours' => 48,
                'category_id' => $categories['Tumor Markers']->id,
                'is_active' => true,
            ],
        ];

        foreach ($tests as $testData) {
            LabTest::updateOrCreate(
                ['code' => $testData['code']],
                $testData
            );
        }
    }
} 