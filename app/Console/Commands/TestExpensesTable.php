<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestExpensesTable extends Command
{
    protected $signature = 'legacy:test-expenses';
    protected $description = 'Test if expenses table exists and works';

    public function handle()
    {
        $this->info("Testing expenses table...");
        
        try {
            $count = DB::table('expenses')->count();
            $this->info("✓ Expenses table exists with {$count} records");
        } catch (\Exception $e) {
            $this->error("✗ Expenses table error: " . $e->getMessage());
            
            // Try to create the table manually
            $this->info("Attempting to create expenses table...");
            try {
                DB::statement("
                    CREATE TABLE expenses (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        description VARCHAR(255) NOT NULL,
                        amount DECIMAL(10,2) NOT NULL,
                        category VARCHAR(100) NOT NULL,
                        expense_date DATE NOT NULL,
                        payment_method VARCHAR(50),
                        reference_number VARCHAR(100),
                        notes TEXT,
                        created_by INTEGER NOT NULL,
                        created_at DATETIME,
                        updated_at DATETIME,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");
                $this->info("✓ Expenses table created successfully");
            } catch (\Exception $e2) {
                $this->error("✗ Failed to create expenses table: " . $e2->getMessage());
            }
        }

        return 0;
    }
}
