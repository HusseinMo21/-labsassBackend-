<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTables extends Command
{
    protected $signature = 'legacy:check-tables';
    protected $description = 'Check what tables exist in the database';

    public function handle()
    {
        $this->info("Checking database tables...");
        
        try {
            $tables = DB::select('SELECT name FROM sqlite_master WHERE type="table"');
            
            $this->info("Available tables:");
            foreach ($tables as $table) {
                $count = DB::table($table->name)->count();
                $this->info("  - {$table->name}: {$count} records");
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to check tables: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
