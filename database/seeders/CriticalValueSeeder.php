<?php

namespace Database\Seeders;

use App\Models\CriticalValue;
use App\Models\LabTest;
use Illuminate\Database\Seeder;

class CriticalValueSeeder extends Seeder
{
    public function run(): void
    {
        $tests = LabTest::all();

        foreach ($tests as $test) {
            // Set critical values based on test name
            $criticalValue = $this->getCriticalValueForTest($test);
            
            if ($criticalValue) {
                CriticalValue::create([
                    'lab_test_id' => $test->id,
                    'critical_low' => $criticalValue['low'] ?? null,
                    'critical_high' => $criticalValue['high'] ?? null,
                    'unit' => $criticalValue['unit'] ?? $test->unit,
                    'notification_message' => $criticalValue['message'] ?? null,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function getCriticalValueForTest($test)
    {
        $testName = strtolower($test->name);
        
        // Common critical values for oncology lab tests
        $criticalValues = [
            'hemoglobin' => [
                'low' => 7.0,
                'high' => 20.0,
                'unit' => 'g/dL',
                'message' => 'CRITICAL: Hemoglobin level is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'platelet' => [
                'low' => 20000,
                'high' => 1000000,
                'unit' => 'cells/μL',
                'message' => 'CRITICAL: Platelet count is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'white blood cell' => [
                'low' => 1000,
                'high' => 50000,
                'unit' => 'cells/μL',
                'message' => 'CRITICAL: WBC count is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'sodium' => [
                'low' => 120,
                'high' => 160,
                'unit' => 'mEq/L',
                'message' => 'CRITICAL: Sodium level is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'potassium' => [
                'low' => 2.5,
                'high' => 6.5,
                'unit' => 'mEq/L',
                'message' => 'CRITICAL: Potassium level is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'creatinine' => [
                'low' => 0.3,
                'high' => 5.0,
                'unit' => 'mg/dL',
                'message' => 'CRITICAL: Creatinine level is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'glucose' => [
                'low' => 40,
                'high' => 400,
                'unit' => 'mg/dL',
                'message' => 'CRITICAL: Glucose level is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'calcium' => [
                'low' => 6.0,
                'high' => 13.0,
                'unit' => 'mg/dL',
                'message' => 'CRITICAL: Calcium level is {type} for patient {patient}. Value: {value} {unit}'
            ],
            'psa' => [
                'high' => 10.0,
                'unit' => 'ng/mL',
                'message' => 'CRITICAL: PSA level is elevated for patient {patient}. Value: {value} {unit}'
            ],
            'cea' => [
                'high' => 10.0,
                'unit' => 'ng/mL',
                'message' => 'CRITICAL: CEA level is elevated for patient {patient}. Value: {value} {unit}'
            ],
            'afp' => [
                'high' => 400,
                'unit' => 'ng/mL',
                'message' => 'CRITICAL: AFP level is elevated for patient {patient}. Value: {value} {unit}'
            ],
            'ca-125' => [
                'high' => 200,
                'unit' => 'U/mL',
                'message' => 'CRITICAL: CA-125 level is elevated for patient {patient}. Value: {value} {unit}'
            ],
            'ca-19-9' => [
                'high' => 1000,
                'unit' => 'U/mL',
                'message' => 'CRITICAL: CA-19-9 level is elevated for patient {patient}. Value: {value} {unit}'
            ],
        ];

        foreach ($criticalValues as $keyword => $values) {
            if (str_contains($testName, $keyword)) {
                return $values;
            }
        }

        return null;
    }
} 