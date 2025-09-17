<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteSampleData extends Command
{
    protected $signature = 'legacy:complete-sample-data';
    protected $description = 'Complete the sample data creation (visits, invoices, payments)';

    public function handle()
    {
        $this->info("Completing sample data creation...");
        
        try {
            // Create visits
            $this->createVisits();
            
            // Create invoices
            $this->createInvoices();
            
            // Create payments
            $this->createPayments();
            
            // Create expenses
            $this->createExpenses();
            
            $this->info("✅ Sample data completion successful!");
            $this->info("Your lab system now has realistic sample data to work with.");
            
        } catch (\Exception $e) {
            $this->error("Failed to complete sample data: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
    
    private function createVisits()
    {
        // Get the patient IDs
        $patientIds = DB::table('patients')->pluck('id')->toArray();
        
        if (empty($patientIds)) {
            $this->warn("No patients found. Skipping visits creation.");
            return;
        }
        
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
            try {
                DB::table('visits')->insert($visit);
            } catch (\Exception $e) {
                // Skip if already exists
            }
        }
        
        $this->info("✓ Created visits: " . DB::table('visits')->count() . " records");
    }
    
    private function createInvoices()
    {
        // Get the visit IDs
        $visitIds = DB::table('visits')->pluck('id')->toArray();
        
        if (empty($visitIds)) {
            $this->warn("No visits found. Skipping invoices creation.");
            return;
        }
        
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
            try {
                DB::table('invoices')->insert($invoice);
            } catch (\Exception $e) {
                // Skip if already exists
            }
        }
        
        $this->info("✓ Created invoices: " . DB::table('invoices')->count() . " records");
    }
    
    private function createPayments()
    {
        // Get the invoice IDs
        $invoiceIds = DB::table('invoices')->pluck('id')->toArray();
        
        if (empty($invoiceIds)) {
            $this->warn("No invoices found. Skipping payments creation.");
            return;
        }
        
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
            try {
                DB::table('payments')->insert($payment);
            } catch (\Exception $e) {
                // Skip if already exists
            }
        }
        
        $this->info("✓ Created payments: " . DB::table('payments')->count() . " records");
    }
    
    private function createExpenses()
    {
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
            try {
                DB::table('expenses')->insert($expense);
            } catch (\Exception $e) {
                // Skip if already exists
            }
        }
        
        $this->info("✓ Created expenses: " . DB::table('expenses')->count() . " records");
    }
}
