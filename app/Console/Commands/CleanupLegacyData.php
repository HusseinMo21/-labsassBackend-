<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CleanupLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary legacy tables after migration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Cleaning up temporary legacy tables...");
        
        $tables = [
            'legacy_patients',
            'legacy_pathology',
            'legacy_income',
            'legacy_expenses',
            'legacy_login',
            'legacy_invoices',
            'legacy_payments'
        ];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->info("Dropped table: {$table}");
            }
        }
        
        $this->info("Cleanup completed successfully!");
        $this->info("Your legacy data migration is now complete!");
        
        return 0;
    }
}
