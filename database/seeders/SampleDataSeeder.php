<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\LabTest;
use App\Models\Visit;
use App\Models\VisitTest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample users
        $admin = User::firstOrCreate(
            ['email' => 'admin@lab.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $labTech = User::firstOrCreate(
            ['email' => 'tech@lab.com'],
            [
                'name' => 'Lab Technician',
                'password' => Hash::make('password'),
                'role' => 'lab_tech',
                'email_verified_at' => now(),
            ]
        );

        // Create sample patients
        $patients = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '1234567890',
                'whatsapp_number' => '1234567890',
                'birth_date' => '1990-01-15',
                'gender' => 'male',
                'address' => '123 Main St, City',
                'national_id' => '12345678901234',
                'username' => 'john_doe',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '0987654321',
                'whatsapp_number' => '0987654321',
                'birth_date' => '1985-05-20',
                'gender' => 'female',
                'address' => '456 Oak Ave, Town',
                'national_id' => '98765432109876',
                'username' => 'jane_smith',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'phone' => '5555555555',
                'whatsapp_number' => '5555555555',
                'birth_date' => '1978-12-10',
                'gender' => 'male',
                'address' => '789 Pine Rd, Village',
                'national_id' => '11223344556677',
                'username' => 'bob_johnson',
                'password' => Hash::make('password'),
            ],
        ];

        $createdPatients = [];
        foreach ($patients as $patientData) {
            $patient = Patient::firstOrCreate(
                ['email' => $patientData['email']],
                $patientData
            );
            $createdPatients[] = $patient;
        }

        // Create sample lab tests
        $labTests = [
            [
                'name' => 'Complete Blood Count (CBC)',
                'description' => 'A blood test that measures different components of blood',
                'price' => 25.00,
                'turnaround_time_hours' => 2,
                'normal_range' => 'Varies by component',
                'unit' => 'Various',
            ],
            [
                'name' => 'Blood Glucose',
                'description' => 'Measures blood sugar levels',
                'price' => 15.00,
                'turnaround_time_hours' => 1,
                'normal_range' => '70-100 mg/dL',
                'unit' => 'mg/dL',
            ],
            [
                'name' => 'Cholesterol Panel',
                'description' => 'Measures cholesterol levels in blood',
                'price' => 35.00,
                'turnaround_time_hours' => 4,
                'normal_range' => 'Total: <200 mg/dL',
                'unit' => 'mg/dL',
            ],
            [
                'name' => 'Thyroid Function Test',
                'description' => 'Measures thyroid hormone levels',
                'price' => 45.00,
                'turnaround_time_hours' => 6,
                'normal_range' => 'TSH: 0.4-4.0 mIU/L',
                'unit' => 'mIU/L',
            ],
        ];

        $createdLabTests = [];
        foreach ($labTests as $testData) {
            $test = LabTest::firstOrCreate(
                ['name' => $testData['name']],
                $testData
            );
            $createdLabTests[] = $test;
        }

        // Create sample completed visits
        $visitData = [
            [
                'patient' => $createdPatients[0],
                'tests' => [$createdLabTests[0], $createdLabTests[1]],
                'status' => 'completed',
                'days_ago' => 1,
            ],
            [
                'patient' => $createdPatients[1],
                'tests' => [$createdLabTests[2]],
                'status' => 'completed',
                'days_ago' => 2,
            ],
            [
                'patient' => $createdPatients[2],
                'tests' => [$createdLabTests[0], $createdLabTests[3]],
                'status' => 'completed',
                'days_ago' => 3,
            ],
            [
                'patient' => $createdPatients[0],
                'tests' => [$createdLabTests[1], $createdLabTests[2]],
                'status' => 'completed',
                'days_ago' => 5,
            ],
            [
                'patient' => $createdPatients[1],
                'tests' => [$createdLabTests[3]],
                'status' => 'completed',
                'days_ago' => 7,
            ],
        ];

        foreach ($visitData as $data) {
            $visit = Visit::create([
                'patient_id' => $data['patient']->id,
                'visit_number' => Visit::generateVisitNumber(),
                'visit_date' => now()->subDays($data['days_ago'])->toDateString(),
                'visit_time' => now()->subDays($data['days_ago'])->format('H:i:s'),
                'status' => $data['status'],
                'total_amount' => 0,
                'final_amount' => 0,
                'completed_at' => $data['status'] === 'completed' ? now()->subDays($data['days_ago']) : null,
            ]);

            $totalAmount = 0;
            foreach ($data['tests'] as $test) {
                $visitTest = VisitTest::create([
                    'visit_id' => $visit->id,
                    'lab_test_id' => $test->id,
                    'status' => 'completed',
                    'barcode_uid' => 'LAB-' . strtoupper(uniqid()),
                    'price' => $test->price,
                    'result_value' => $this->generateSampleResult($test->name),
                    'result_status' => 'normal',
                    'result_notes' => 'Sample result for demonstration',
                    'performed_by' => $labTech->id,
                    'performed_at' => now()->subDays($data['days_ago']),
                ]);
                $totalAmount += $test->price;
            }

            $visit->update([
                'total_amount' => $totalAmount,
                'final_amount' => $totalAmount,
            ]);
        }

        // Create some pending visits
        $pendingVisit = Visit::create([
            'patient_id' => $createdPatients[0]->id,
            'visit_number' => Visit::generateVisitNumber(),
            'visit_date' => now()->toDateString(),
            'visit_time' => now()->format('H:i:s'),
            'status' => 'registered',
            'total_amount' => 0,
            'final_amount' => 0,
        ]);

        $visitTest = VisitTest::create([
            'visit_id' => $pendingVisit->id,
            'lab_test_id' => $createdLabTests[0]->id,
            'status' => 'pending',
            'barcode_uid' => 'LAB-' . strtoupper(uniqid()),
            'price' => $createdLabTests[0]->price,
        ]);

        $pendingVisit->update([
            'total_amount' => $createdLabTests[0]->price,
            'final_amount' => $createdLabTests[0]->price,
        ]);

        $this->command->info('Sample data created successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . count($createdPatients) . ' patients');
        $this->command->info('- ' . count($createdLabTests) . ' lab tests');
        $this->command->info('- ' . count($visitData) . ' completed visits');
        $this->command->info('- 1 pending visit');
        $this->command->info('');
        $this->command->info('Login credentials:');
        $this->command->info('Admin: admin@lab.com / password');
        $this->command->info('Lab Tech: tech@lab.com / password');
    }

    private function generateSampleResult($testName)
    {
        $results = [
            'Complete Blood Count (CBC)' => 'WBC: 7.2, RBC: 4.5, HGB: 14.2, HCT: 42.1',
            'Blood Glucose' => '95',
            'Cholesterol Panel' => 'Total: 185, LDL: 110, HDL: 55',
            'Thyroid Function Test' => 'TSH: 2.1, T4: 8.5',
        ];

        return $results[$testName] ?? 'Normal';
    }
}

