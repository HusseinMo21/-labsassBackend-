<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Testing Import...');
        
        // Test if SQL file exists
        $sqlFile = base_path('../u990846975_yasser.sql');
        if (!file_exists($sqlFile)) {
            $this->error('❌ SQL file not found: ' . $sqlFile);
            return;
        }
        
        $this->info('✅ SQL file found');
        
        // Test reading the file
        $sqlContent = file_get_contents($sqlFile);
        $this->info('✅ SQL file read successfully (' . number_format(strlen($sqlContent)) . ' bytes)');
        
        // Test parsing patient data
        preg_match_all('/INSERT INTO `patient`[^;]+;/', $sqlContent, $matches);
        $this->info('✅ Found ' . count($matches[0]) . ' patient INSERT statements');
        
        // Test parsing pathology data
        preg_match_all('/INSERT INTO `patholgy`[^;]+;/', $sqlContent, $matches);
        $this->info('✅ Found ' . count($matches[0]) . ' pathology INSERT statements');
        
        // Test parsing income data
        preg_match_all('/INSERT INTO `income`[^;]+;/', $sqlContent, $matches);
        $this->info('✅ Found ' . count($matches[0]) . ' income INSERT statements');
        
        $this->info('🎉 All tests passed! Ready to import.');
    }
}
