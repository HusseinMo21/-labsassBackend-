<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestPaymentsTable extends Command
{
    protected $signature = 'legacy:test-payments';
    protected $description = 'Test if payments table exists and works';

    public function handle()
    {
        $this->info("Testing payments table...");
        
        try {
            $count = DB::table('payments')->count();
            $this->info("✓ Payments table exists with {$count} records");
        } catch (\Exception $e) {
            $this->error("✗ Payments table error: " . $e->getMessage());
            
            // Try to create the table manually
            $this->info("Attempting to create payments table...");
            try {
                DB::statement("
                    CREATE TABLE payments (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        invoice_id INTEGER NOT NULL,
                        amount DECIMAL(10,2) NOT NULL,
                        payment_method VARCHAR(50),
                        paid_at DATETIME,
                        created_by INTEGER,
                        notes TEXT,
                        created_at DATETIME,
                        updated_at DATETIME,
                        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    )
                ");
                $this->info("✓ Payments table created successfully");
            } catch (\Exception $e2) {
                $this->error("✗ Failed to create payments table: " . $e2->getMessage());
            }
        }

        return 0;
    }
}
