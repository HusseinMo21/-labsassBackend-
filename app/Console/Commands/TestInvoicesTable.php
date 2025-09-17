<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestInvoicesTable extends Command
{
    protected $signature = 'legacy:test-invoices';
    protected $description = 'Test if invoices table exists and works';

    public function handle()
    {
        $this->info("Testing invoices table...");
        
        try {
            $count = DB::table('invoices')->count();
            $this->info("✓ Invoices table exists with {$count} records");
        } catch (\Exception $e) {
            $this->error("✗ Invoices table error: " . $e->getMessage());
            
            // Try to create the table manually
            $this->info("Attempting to create invoices table...");
            try {
                DB::statement("
                    CREATE TABLE invoices (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        visit_id INTEGER NOT NULL,
                        invoice_number VARCHAR(255) NOT NULL UNIQUE,
                        invoice_date DATE NOT NULL,
                        subtotal DECIMAL(10,2) NOT NULL,
                        discount_amount DECIMAL(10,2) DEFAULT 0,
                        tax_amount DECIMAL(10,2) DEFAULT 0,
                        total_amount DECIMAL(10,2) NOT NULL,
                        amount_paid DECIMAL(10,2) DEFAULT 0,
                        balance DECIMAL(10,2) DEFAULT 0,
                        status VARCHAR(50) DEFAULT 'unpaid',
                        payment_method VARCHAR(50),
                        notes TEXT,
                        created_by INTEGER,
                        created_at DATETIME,
                        updated_at DATETIME,
                        lab_request_id INTEGER,
                        FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )
                ");
                $this->info("✓ Invoices table created successfully");
            } catch (\Exception $e2) {
                $this->error("✗ Failed to create invoices table: " . $e2->getMessage());
            }
        }

        return 0;
    }
}
