<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabRequest;
use App\Models\LabSequence;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitTest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MultiTenantWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $labs = Lab::orderBy('id')->get();
        $labTestId = \App\Models\LabTest::first()?->id;
        $testCategoryId = \App\Models\TestCategory::first()?->id;

        if (!$labTestId || !$testCategoryId) {
            $this->command->warn('Run LabTestSeeder and TestCategorySeeder first.');
            return;
        }

        foreach ($labs as $lab) {
            $this->seedLabWorkflow($lab, $labTestId, $testCategoryId);
        }

        $this->command->info('Multi-tenant workflow data seeded for ' . $labs->count() . ' labs.');
    }

    private function seedLabWorkflow(Lab $lab, int $labTestId, int $testCategoryId): void
    {
        $year = now()->year;

        // Doctors and Organizations per lab
        $doctors = [
            Doctor::firstOrCreate(
                ['lab_id' => $lab->id, 'name' => "Dr. Ahmed - {$lab->name}"],
                ['lab_id' => $lab->id, 'name' => "Dr. Ahmed - {$lab->name}"]
            ),
            Doctor::firstOrCreate(
                ['lab_id' => $lab->id, 'name' => "Dr. Fatma - {$lab->name}"],
                ['lab_id' => $lab->id, 'name' => "Dr. Fatma - {$lab->name}"]
            ),
        ];

        $orgs = [
            Organization::firstOrCreate(
                ['lab_id' => $lab->id, 'name' => "Clinic A - {$lab->name}"],
                ['lab_id' => $lab->id, 'name' => "Clinic A - {$lab->name}"]
            ),
            Organization::firstOrCreate(
                ['lab_id' => $lab->id, 'name' => "Hospital B - {$lab->name}"],
                ['lab_id' => $lab->id, 'name' => "Hospital B - {$lab->name}"]
            ),
        ];

        // Create 5-8 patients per lab
        $patientCount = rand(5, 8);
        $patients = [];

        for ($i = 0; $i < $patientCount; $i++) {
            $seq = LabSequence::getNextSequence($year, $lab->id);
            $labNo = "{$seq}-{$year}";
            $doctor = $doctors[$i % count($doctors)];
            $org = $orgs[$i % count($orgs)];

            $patient = Patient::create([
                'lab_id' => $lab->id,
                'name' => "Patient " . ($i + 1) . " - {$lab->name}",
                'gender' => ['male', 'female'][$i % 2],
                'birth_date' => now()->subYears(rand(20, 60)),
                'phone' => '+201' . str_pad(rand(100000000, 999999999), 9, '0'),
                'address' => "Address {$i}, {$lab->name}",
                'lab' => $labNo,
                'doctor_id' => $doctor->name,
                'organization_id' => $org->name,
            ]);
            $patients[] = $patient;
        }

        // Create lab_request (1 per patient), visit, visit_tests for each patient
        $visitCounter = 0;
        foreach ($patients as $idx => $patient) {
            $seq = LabSequence::getNextSequence($year, $lab->id);
            $labNo = "{$seq}-{$year}";

            $labRequest = LabRequest::create([
                    'lab_id' => $lab->id,
                    'patient_id' => $patient->id,
                    'lab_no' => $labNo,
                    'suffix' => null,
                    'status' => ['pending', 'in_progress', 'completed'][rand(0, 2)],
                ]);

                $visitCounter++;
                $visitNumber = 'VIS' . now()->format('Ymd') . str_pad($visitCounter, 4, '0', STR_PAD_LEFT);
                $totalAmount = rand(100, 500);
                $discount = rand(0, 2) ? 0 : rand(10, 50);
                $finalAmount = $totalAmount - $discount;

                $visitDate = now()->subDays(rand(0, 30));
                $visit = Visit::create([
                    'lab_id' => $lab->id,
                    'patient_id' => $patient->id,
                    'lab_request_id' => $labRequest->id,
                    'visit_number' => $visitNumber,
                    'visit_date' => $visitDate,
                    'visit_time' => $visitDate->setTime(rand(8, 16), rand(0, 59))->format('H:i:s'),
                    'total_amount' => $totalAmount,
                    'discount_amount' => $discount,
                    'final_amount' => $finalAmount,
                    'upfront_payment' => rand(0, 1) ? $finalAmount : rand(0, (int)$finalAmount),
                    'remaining_balance' => 0,
                    'status' => ['pending', 'in_progress', 'completed'][rand(0, 2)],
                    'billing_status' => ['pending', 'partial', 'paid'][rand(0, 2)],
                ]);

                // Visit tests (1-3 per visit)
                $numTests = rand(1, 3);
                for ($t = 0; $t < $numTests; $t++) {
                    $price = rand(50, 150);
                    VisitTest::create([
                        'visit_id' => $visit->id,
                        'lab_id' => $lab->id,
                        'lab_test_id' => $labTestId,
                        'test_category_id' => $testCategoryId,
                        'price' => $price,
                        'final_price' => $price,
                        'status' => ['pending', 'under_review', 'completed'][rand(0, 2)],
                        'barcode_uid' => Str::uuid()->toString(),
                    ]);
                }
        }
    }
}
