<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckLegacyData extends Command
{
    protected $signature = 'legacy:check';
    protected $description = 'Check legacy data import status';

    public function handle()
    {
        $this->info("Checking legacy data...");
        
        $tables = [
            'legacy_patients',
            'legacy_pathology', 
            'legacy_login',
            'legacy_expenses',
            'legacy_income',
            'legacy_invoices',
            'legacy_payments'
        ];
        
        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->info("{$table}: {$count} records");
            } catch (\Exception $e) {
                $this->error("{$table}: Error - " . $e->getMessage());
            }
        }
        
        // Check if any data was imported to the original table names
        $originalTables = ['patient', 'patholgy', 'login', 'expenses', 'income', 'invoices', 'payments'];
        
        $this->info("\nChecking original table names:");
        foreach ($originalTables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->info("{$table}: {$count} records");
            } catch (\Exception $e) {
                $this->error("{$table}: Error - " . $e->getMessage());
            }
        }
        
        return 0;
    }
}
