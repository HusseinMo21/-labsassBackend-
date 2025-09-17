<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DirectImportLegacyData extends Command
{
    protected $signature = 'legacy:direct-import {file}';
    protected $description = 'Direct import of legacy lab data using SQL execution';

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting direct import of legacy data from: {$filePath}");
        
        try {
            // Read the SQL file
            $sql = File::get($filePath);
            
            // Remove problematic statements and comments
            $sql = $this->cleanSql($sql);
            
            // Execute the SQL directly
            $this->info("Executing SQL statements...");
            
            // Split into individual statements and execute them
            $statements = $this->splitStatements($sql);
            
            $executed = 0;
            $skipped = 0;
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                
                if (empty($statement)) {
                    continue;
                }
                
                // Skip certain statements that might cause issues
                if ($this->shouldSkipStatement($statement)) {
                    $skipped++;
                    continue;
                }
                
                try {
                    DB::statement($statement);
                    $executed++;
                    
                    if ($executed % 100 == 0) {
                        $this->info("Executed {$executed} statements...");
                    }
                } catch (\Exception $e) {
                    $this->warn("Failed to execute statement: " . substr($statement, 0, 100) . "...");
                    $this->warn("Error: " . $e->getMessage());
                }
            }
            
            $this->info("Import completed!");
            $this->info("Executed: {$executed} statements");
            $this->info("Skipped: {$skipped} statements");
            
            // Now transform the data to work with the new system
            $this->info("Transforming data to new system structure...");
            $this->transformData();
            
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
    
    private function cleanSql($sql)
    {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Remove problematic statements
        $sql = preg_replace('/SET SQL_MODE.*?;/', '', $sql);
        $sql = preg_replace('/START TRANSACTION.*?;/', '', $sql);
        $sql = preg_replace('/COMMIT.*?;/', '', $sql);
        $sql = preg_replace('/SET time_zone.*?;/', '', $sql);
        $sql = preg_replace('/\/\*!40101.*?\*\//s', '', $sql);
        
        return $sql;
    }
    
    private function splitStatements($sql)
    {
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
    
    private function shouldSkipStatement($statement)
    {
        $skipPatterns = [
            '/CREATE TABLE/',
            '/ALTER TABLE/',
            '/DROP TABLE/',
            '/CREATE INDEX/',
            '/DROP INDEX/',
            '/AUTO_INCREMENT/',
            '/ENGINE=/',
            '/DEFAULT CHARSET=/',
            '/COLLATE=/'
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $statement)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function transformData()
    {
        $this->info("Transforming users...");
        
        // Transform login table to users table
        try {
            DB::statement("
                INSERT INTO users (id, name, email, password, role, created_at, updated_at)
                SELECT 
                    id,
                    COALESCE(name, username, 'User ' || id) as name,
                    COALESCE(username || '@legacy.local', 'user' || id || '@legacy.local') as email,
                    password,
                    CASE 
                        WHEN permssion = '1' THEN 'admin'
                        ELSE 'lab_tech'
                    END as role,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM login
                WHERE username IS NOT NULL
            ");
            $this->info("✓ Users transformed");
        } catch (\Exception $e) {
            $this->warn("Failed to transform users: " . $e->getMessage());
        }
        
        $this->info("Transforming patients...");
        
        // Transform patient table to new patients table
        try {
            DB::statement("
                INSERT INTO patients (id, name, gender, birth_date, phone, address, created_at, updated_at)
                SELECT 
                    id,
                    COALESCE(name, 'Unknown Patient') as name,
                    CASE 
                        WHEN gender = 'ذكر' OR gender = 'male' THEN 'male'
                        WHEN gender = 'انثى' OR gender = 'female' THEN 'female'
                        ELSE 'other'
                    END as gender,
                    CASE 
                        WHEN age IS NOT NULL AND age > 0 THEN date('now', '-' || age || ' years')
                        ELSE '1990-01-01'
                    END as birth_date,
                    COALESCE(phone, '') as phone,
                    COALESCE(address, '') as address,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM patient
                WHERE name IS NOT NULL AND name != ''
            ");
            $this->info("✓ Patients transformed");
        } catch (\Exception $e) {
            $this->warn("Failed to transform patients: " . $e->getMessage());
        }
        
        $this->info("Transforming lab requests...");
        
        // Create lab requests from patient data
        try {
            DB::statement("
                INSERT INTO lab_requests (patient_id, lab_no, status, created_at, updated_at)
                SELECT 
                    p.id as patient_id,
                    pt.lab as lab_no,
                    CASE 
                        WHEN pt.deli IS NOT NULL AND pt.deli != '' THEN 'delivered'
                        WHEN pt.entry IS NOT NULL AND pt.entry != '' THEN 'completed'
                        ELSE 'pending'
                    END as status,
                    CASE 
                        WHEN pt.entry IS NOT NULL AND pt.entry != '' THEN pt.entry
                        ELSE datetime('now')
                    END as created_at,
                    datetime('now') as updated_at
                FROM patient pt
                JOIN patients p ON p.id = pt.id
                WHERE pt.lab IS NOT NULL AND pt.lab != ''
            ");
            $this->info("✓ Lab requests transformed");
        } catch (\Exception $e) {
            $this->warn("Failed to transform lab requests: " . $e->getMessage());
        }
        
        $this->info("Transforming samples...");
        
        // Create samples from patient data
        try {
            DB::statement("
                INSERT INTO samples (lab_request_id, tsample, nsample, isample, created_at, updated_at)
                SELECT 
                    lr.id as lab_request_id,
                    pt.tsample,
                    pt.nsample,
                    pt.isample,
                    lr.created_at,
                    datetime('now') as updated_at
                FROM patient pt
                JOIN lab_requests lr ON lr.lab_no = pt.lab
                WHERE pt.tsample IS NOT NULL OR pt.nsample IS NOT NULL OR pt.isample IS NOT NULL
            ");
            $this->info("✓ Samples transformed");
        } catch (\Exception $e) {
            $this->warn("Failed to transform samples: " . $e->getMessage());
        }
        
        $this->info("Transforming pathology reports...");
        
        // Transform pathology to reports
        try {
            DB::statement("
                INSERT INTO reports (lab_request_id, title, content, status, generated_at, created_at, updated_at)
                SELECT 
                    lr.id as lab_request_id,
                    ('Pathology Report - ' || pt.lab) as title,
                    (COALESCE('Clinical: ' || pt.clinical || char(10), '') ||
                     COALESCE('Nature: ' || pt.nature || char(10), '') ||
                     COALESCE('Gross: ' || pt.gross || char(10), '') ||
                     COALESCE('Micro: ' || pt.micro || char(10), '') ||
                     COALESCE('Conclusion: ' || pt.conc || char(10), '') ||
                     COALESCE('Recommendation: ' || pt.reco || char(10), '')) as content,
                    CASE 
                        WHEN pt.confirm = 1 THEN 'completed'
                        ELSE 'draft'
                    END as status,
                    CASE 
                        WHEN pt.date IS NOT NULL AND pt.date != '' THEN pt.date
                        ELSE datetime('now')
                    END as generated_at,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM patholgy pt
                LEFT JOIN lab_requests lr ON lr.lab_no = pt.lab
                WHERE pt.lab IS NOT NULL AND pt.lab != ''
            ");
            $this->info("✓ Pathology reports transformed");
        } catch (\Exception $e) {
            $this->warn("Failed to transform pathology reports: " . $e->getMessage());
        }
        
        $this->info("Transforming financial data...");
        
        // Transform expenses
        try {
            DB::statement("
                INSERT INTO expenses (description, amount, category, expense_date, created_by, created_at, updated_at)
                SELECT 
                    name as description,
                    amount,
                    'Legacy' as category,
                    date as expense_date,
                    COALESCE(author, 1) as created_by,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM expenses
                WHERE name IS NOT NULL AND name != ''
            ");
            $this->info("✓ Expenses transformed");
        } catch (\Exception $e) {
            $this->warn("Failed to transform expenses: " . $e->getMessage());
        }
        
        // Create visits for patients with financial data
        try {
            DB::statement("
                INSERT INTO visits (patient_id, visit_number, visit_date, total_amount, final_amount, status, created_at, updated_at)
                SELECT DISTINCT
                    p.id as patient_id,
                    ('LEGACY-' || pt.id) as visit_number,
                    CASE 
                        WHEN pt.entry IS NOT NULL AND pt.entry != '' THEN pt.entry
                        ELSE datetime('now')
                    END as visit_date,
                    COALESCE(pt.total, 0) as total_amount,
                    COALESCE(pt.total, 0) as final_amount,
                    CASE 
                        WHEN pt.paid >= pt.total THEN 'completed'
                        ELSE 'pending'
                    END as status,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM patient pt
                JOIN patients p ON p.id = pt.id
                WHERE pt.total IS NOT NULL AND pt.total > 0
            ");
            $this->info("✓ Visits created");
        } catch (\Exception $e) {
            $this->warn("Failed to create visits: " . $e->getMessage());
        }
        
        // Create invoices for visits
        try {
            DB::statement("
                INSERT INTO invoices (visit_id, invoice_number, invoice_date, subtotal, total_amount, amount_paid, balance, status, created_at, updated_at)
                SELECT 
                    v.id as visit_id,
                    ('INV-' || v.visit_number) as invoice_number,
                    v.visit_date as invoice_date,
                    v.total_amount as subtotal,
                    v.total_amount as total_amount,
                    COALESCE(pt.paid, 0) as amount_paid,
                    (v.total_amount - COALESCE(pt.paid, 0)) as balance,
                    CASE 
                        WHEN pt.paid >= pt.total THEN 'paid'
                        WHEN pt.paid > 0 THEN 'partial'
                        ELSE 'unpaid'
                    END as status,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM visits v
                JOIN patient pt ON pt.id = substr(v.visit_number, 8)
                WHERE v.visit_number LIKE 'LEGACY-%'
            ");
            $this->info("✓ Invoices created");
        } catch (\Exception $e) {
            $this->warn("Failed to create invoices: " . $e->getMessage());
        }
        
        // Create payments
        try {
            DB::statement("
                INSERT INTO payments (invoice_id, amount, payment_method, paid_at, created_by, created_at, updated_at)
                SELECT 
                    i.id as invoice_id,
                    pt.paid as amount,
                    'cash' as payment_method,
                    CASE 
                        WHEN pt.entry IS NOT NULL AND pt.entry != '' THEN pt.entry
                        ELSE datetime('now')
                    END as paid_at,
                    COALESCE(pt.sender, 1) as created_by,
                    datetime('now') as created_at,
                    datetime('now') as updated_at
                FROM invoices i
                JOIN visits v ON v.id = i.visit_id
                JOIN patient pt ON pt.id = substr(v.visit_number, 8)
                WHERE v.visit_number LIKE 'LEGACY-%' AND pt.paid > 0
            ");
            $this->info("✓ Payments created");
        } catch (\Exception $e) {
            $this->warn("Failed to create payments: " . $e->getMessage());
        }
        
        $this->info("Data transformation completed!");
    }
}
