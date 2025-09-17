<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateSampleLabData extends Command
{
    protected $signature = 'legacy:create-sample-data';
    protected $description = 'Create sample lab data that matches the original structure';

    public function handle()
    {
        $this->info("Creating sample lab data...");
        
        try {
            // Create sample users
            $this->createUsers();
            
            // Create sample patients
            $this->createPatients();
            
            // Create sample lab requests
            $this->createLabRequests();
            
            // Create sample samples
            $this->createSamples();
            
            // Create sample reports
            $this->createReports();
            
            // Create sample visits and invoices
            $this->createFinancialData();
            
            $this->info("✅ Sample lab data created successfully!");
            $this->info("You can now test your system with realistic lab data.");
            
        } catch (\Exception $e) {
            $this->error("Failed to create sample data: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
    
    private function createUsers()
    {
        $users = [
            [
                'name' => 'Lab Technician',
                'email' => 'tech@lab.local',
                'password' => bcrypt('password'),
                'role' => 'staff',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Accountant',
                'email' => 'accountant@lab.local',
                'password' => bcrypt('password'),
                'role' => 'staff',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        foreach ($users as $user) {
            DB::table('users')->insert($user);
        }
        
        $this->info("✓ Created " . count($users) . " users");
    }
    
    private function createPatients()
    {
        $patients = [
            [
                'name' => 'محمد أحمد علي',
                'gender' => 'male',
                'birth_date' => '1980-05-15',
                'phone' => '01234567890',
                'address' => 'شارع التحرير، القاهرة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'فاطمة محمود',
                'gender' => 'female',
                'birth_date' => '1975-12-03',
                'phone' => '01123456789',
                'address' => 'شارع النيل، الإسكندرية',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'عبد الرحمن السيد',
                'gender' => 'male',
                'birth_date' => '1990-08-20',
                'phone' => '01012345678',
                'address' => 'شارع الهرم، الجيزة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'نور الدين حسن',
                'gender' => 'male',
                'birth_date' => '1985-03-10',
                'phone' => '01512345678',
                'address' => 'شارع المعز، القاهرة',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'مريم أحمد',
                'gender' => 'female',
                'birth_date' => '1992-11-25',
                'phone' => '01212345678',
                'address' => 'شارع البحر، الإسكندرية',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        foreach ($patients as $patient) {
            DB::table('patients')->insert($patient);
        }
        
        $this->info("✓ Created " . count($patients) . " patients");
    }
    
    private function createLabRequests()
    {
        // Get the patient IDs that were just created
        $patientIds = DB::table('patients')->pluck('id')->toArray();
        
        $labRequests = [
            [
                'patient_id' => $patientIds[0] ?? 1,
                'lab_no' => '001-2025',
                'status' => 'completed',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(2)
            ],
            [
                'patient_id' => $patientIds[1] ?? 2,
                'lab_no' => '002-2025',
                'status' => 'delivered',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(1)
            ],
            [
                'patient_id' => $patientIds[2] ?? 3,
                'lab_no' => '003-2025',
                'status' => 'pending',
                'created_at' => now()->subDays(1),
                'updated_at' => now()
            ],
            [
                'patient_id' => $patientIds[3] ?? 4,
                'lab_no' => '004-2025',
                'status' => 'in_progress',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(2)
            ],
            [
                'patient_id' => $patientIds[4] ?? 5,
                'lab_no' => '005-2025',
                'status' => 'under_review',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(1)
            ]
        ];
        
        foreach ($labRequests as $request) {
            DB::table('lab_requests')->insert($request);
        }
        
        $this->info("✓ Created " . count($labRequests) . " lab requests");
    }
    
    private function createSamples()
    {
        // Get the lab request IDs that were just created
        $labRequestIds = DB::table('lab_requests')->pluck('id')->toArray();
        
        $samples = [
            [
                'lab_request_id' => $labRequestIds[0] ?? 1,
                'tsample' => 'Blood',
                'nsample' => '1',
                'isample' => 'Small',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5)
            ],
            [
                'lab_request_id' => $labRequestIds[1] ?? 2,
                'tsample' => 'Urine',
                'nsample' => '2',
                'isample' => 'Medium',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3)
            ],
            [
                'lab_request_id' => $labRequestIds[2] ?? 3,
                'tsample' => 'Tissue',
                'nsample' => '1',
                'isample' => 'Large',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ],
            [
                'lab_request_id' => $labRequestIds[3] ?? 4,
                'tsample' => 'Blood',
                'nsample' => '3',
                'isample' => 'Small',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6)
            ],
            [
                'lab_request_id' => $labRequestIds[4] ?? 5,
                'tsample' => 'CSF',
                'nsample' => '1',
                'isample' => 'Very Small',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3)
            ]
        ];
        
        foreach ($samples as $sample) {
            DB::table('samples')->insert($sample);
        }
        
        $this->info("✓ Created " . count($samples) . " samples");
    }
    
    private function createReports()
    {
        // Get the lab request IDs that were just created
        $labRequestIds = DB::table('lab_requests')->pluck('id')->toArray();
        
        $reports = [
            [
                'lab_request_id' => $labRequestIds[0] ?? 1,
                'title' => 'Complete Blood Count - 001-2025',
                'content' => "Clinical: Routine checkup\nNature: Blood sample\nGross: Normal appearance\nMicro: All parameters within normal range\nConclusion: Normal CBC results\nRecommendation: Continue regular monitoring",
                'status' => 'completed',
                'generated_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2)
            ],
            [
                'lab_request_id' => $labRequestIds[1] ?? 2,
                'title' => 'Urinalysis - 002-2025',
                'content' => "Clinical: UTI symptoms\nNature: Midstream urine\nGross: Clear, yellow\nMicro: No significant findings\nConclusion: Normal urinalysis\nRecommendation: No further action needed",
                'status' => 'completed',
                'generated_at' => now()->subDays(1),
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ],
            [
                'lab_request_id' => $labRequestIds[2] ?? 3,
                'title' => 'Tissue Biopsy - 003-2025',
                'content' => "Clinical: Suspicious lesion\nNature: Tissue sample\nGross: Irregular mass\nMicro: Under examination\nConclusion: Pending\nRecommendation: Awaiting results",
                'status' => 'draft',
                'generated_at' => null,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ]
        ];
        
        foreach ($reports as $report) {
            DB::table('reports')->insert($report);
        }
        
        $this->info("✓ Created " . count($reports) . " reports");
    }
    
    private function createFinancialData()
    {
        // Get the patient IDs that were just created
        $patientIds = DB::table('patients')->pluck('id')->toArray();
        
        // Create visits
        $visits = [
            [
                'patient_id' => $patientIds[0] ?? 1,
                'visit_number' => 'VIS-001-2025',
                'visit_date' => now()->subDays(5)->toDateString(),
                'visit_time' => now()->subDays(5)->toTimeString(),
                'total_amount' => 150.00,
                'final_amount' => 150.00,
                'status' => 'completed',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5)
            ],
            [
                'patient_id' => $patientIds[1] ?? 2,
                'visit_number' => 'VIS-002-2025',
                'visit_date' => now()->subDays(3)->toDateString(),
                'visit_time' => now()->subDays(3)->toTimeString(),
                'total_amount' => 200.00,
                'final_amount' => 200.00,
                'status' => 'completed',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3)
            ],
            [
                'patient_id' => $patientIds[2] ?? 3,
                'visit_number' => 'VIS-003-2025',
                'visit_date' => now()->subDays(1)->toDateString(),
                'visit_time' => now()->subDays(1)->toTimeString(),
                'total_amount' => 300.00,
                'final_amount' => 300.00,
                'status' => 'pending',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ]
        ];
        
        foreach ($visits as $visit) {
            DB::table('visits')->insert($visit);
        }
        
        // Get the visit IDs that were just created
        $visitIds = DB::table('visits')->pluck('id')->toArray();
        
        // Create invoices
        $invoices = [
            [
                'visit_id' => $visitIds[0] ?? 1,
                'invoice_number' => 'INV-001-2025',
                'invoice_date' => now()->subDays(5)->toDateString(),
                'subtotal' => 150.00,
                'total_amount' => 150.00,
                'amount_paid' => 150.00,
                'balance' => 0.00,
                'status' => 'paid',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5)
            ],
            [
                'visit_id' => $visitIds[1] ?? 2,
                'invoice_number' => 'INV-002-2025',
                'invoice_date' => now()->subDays(3)->toDateString(),
                'subtotal' => 200.00,
                'total_amount' => 200.00,
                'amount_paid' => 100.00,
                'balance' => 100.00,
                'status' => 'partial',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3)
            ],
            [
                'visit_id' => $visitIds[2] ?? 3,
                'invoice_number' => 'INV-003-2025',
                'invoice_date' => now()->subDays(1)->toDateString(),
                'subtotal' => 300.00,
                'total_amount' => 300.00,
                'amount_paid' => 0.00,
                'balance' => 300.00,
                'status' => 'unpaid',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1)
            ]
        ];
        
        foreach ($invoices as $invoice) {
            DB::table('invoices')->insert($invoice);
        }
        
        // Get the invoice IDs that were just created
        $invoiceIds = DB::table('invoices')->pluck('id')->toArray();
        
        // Create payments
        $payments = [
            [
                'invoice_id' => $invoiceIds[0] ?? 1,
                'amount' => 150.00,
                'payment_method' => 'cash',
                'paid_at' => now()->subDays(5),
                'created_by' => 1, // Use the first user ID
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5)
            ],
            [
                'invoice_id' => $invoiceIds[1] ?? 2,
                'amount' => 100.00,
                'payment_method' => 'cash',
                'paid_at' => now()->subDays(3),
                'created_by' => 1, // Use the first user ID
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3)
            ]
        ];
        
        foreach ($payments as $payment) {
            DB::table('payments')->insert($payment);
        }
        
        // Create some expenses
        $expenses = [
            [
                'description' => 'Lab supplies - January',
                'amount' => 500.00,
                'category' => 'Supplies',
                'expense_date' => now()->subDays(10)->toDateString(),
                'created_by' => 1,
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10)
            ],
            [
                'description' => 'Equipment maintenance',
                'amount' => 200.00,
                'category' => 'Maintenance',
                'expense_date' => now()->subDays(5)->toDateString(),
                'created_by' => 1,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5)
            ]
        ];
        
        foreach ($expenses as $expense) {
            DB::table('expenses')->insert($expense);
        }
        
        $this->info("✓ Created financial data:");
        $this->info("  - " . count($visits) . " visits");
        $this->info("  - " . count($invoices) . " invoices");
        $this->info("  - " . count($payments) . " payments");
        $this->info("  - " . count($expenses) . " expenses");
    }
}
