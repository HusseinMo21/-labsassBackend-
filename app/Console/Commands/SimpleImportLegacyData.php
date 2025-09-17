<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SimpleImportLegacyData extends Command
{
    protected $signature = 'legacy:simple-import {file}';
    protected $description = 'Simple legacy data import using direct SQL execution';

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting simple legacy data import from: {$filePath}");
        
        try {
            // Read the SQL file
            $sql = File::get($filePath);
            
            // Split into individual statements
            $statements = $this->splitSqlStatements($sql);
            
            $imported = 0;
            $totalStatements = count($statements);
            $this->info("Found {$totalStatements} SQL statements to process");
            
            foreach ($statements as $index => $statement) {
                $statement = trim($statement);
                
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }
                
                // Debug: show first few INSERT statements
                if ($imported < 5 && strpos($statement, 'INSERT INTO') === 0) {
                    $this->info("Sample statement: " . substr($statement, 0, 100) . "...");
                }
                
                // Only process INSERT statements for tables we care about
                if (preg_match('/INSERT INTO `(patient|patholgy|login|expenses|income|invoices|payments)`/', $statement, $matches) ||
                    preg_match('/INSERT INTO `(patient|patholgy|login|expenses|income|invoices|payments)` \(/', $statement, $matches)) {
                    $table = $matches[1];
                    $legacyTable = $this->getLegacyTableName($table);
                    
                    // Replace table name in the statement
                    $statement = str_replace("`{$table}`", "`{$legacyTable}`", $statement);
                    
                    try {
                        DB::statement($statement);
                        $imported++;
                        
                        if ($imported % 100 == 0) {
                            $this->info("Imported {$imported} statements...");
                        }
                    } catch (\Exception $e) {
                        $this->warn("Failed to import statement: " . substr($statement, 0, 100) . "...");
                    }
                }
            }
            
            $this->info("Import completed! Total statements imported: {$imported}");
            
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
    
    private function splitSqlStatements($sql)
    {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon, but be careful with semicolons inside strings
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = null;
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (!$inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar) {
                // Check for escaped quote
                if ($i + 1 < strlen($sql) && $sql[$i + 1] === $stringChar) {
                    $current .= $char;
                    $i++; // Skip next quote
                } else {
                    $inString = false;
                    $stringChar = null;
                }
            } elseif (!$inString && $char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty(trim($current))) {
            $statements[] = $current;
        }
        
        return $statements;
    }
    
    private function getLegacyTableName($table)
    {
        $map = [
            'patient' => 'legacy_patients',
            'patholgy' => 'legacy_pathology',
            'login' => 'legacy_login',
            'expenses' => 'legacy_expenses',
            'income' => 'legacy_income',
            'invoices' => 'legacy_invoices',
            'payments' => 'legacy_payments'
        ];
        
        return $map[$table] ?? 'legacy_' . $table;
    }
}
