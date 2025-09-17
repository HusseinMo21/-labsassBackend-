<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:migrate-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy data from temporary tables to new structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting legacy data migration...");
        
        // Check if legacy tables exist
        if (!Schema::hasTable('legacy_patients')) {
            $this->error("Legacy tables not found. Please run the migration first.");
            return 1;
        }

        try {
            // Migrate users first
            $this->migrateUsers();
            
            // Migrate patients
            $this->migratePatients();
            
            // Migrate lab requests
            $this->migrateLabRequests();
            
            // Migrate samples
            $this->migrateSamples();
            
            // Migrate pathology reports
            $this->migratePathologyReports();
            
            // Migrate financial data
            $this->migrateFinancialData();
            
            $this->info("Legacy data migration completed successfully!");
            $this->info("You can now run 'php artisan legacy:cleanup' to remove temporary tables.");
            
        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function migrateUsers(): void
    {
        $this->info("Migrating users...");
        
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
            FROM legacy_login
            WHERE username IS NOT NULL
        ");
        
        $count = DB::table('legacy_login')->count();
        $this->info("Migrated {$count} users");
    }

    private function migratePatients(): void
    {
        $this->info("Migrating patients...");
        
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
            FROM legacy_patients
            WHERE name IS NOT NULL AND name != ''
        ");
        
        $count = DB::table('legacy_patients')->whereNotNull('name')->where('name', '!=', '')->count();
        $this->info("Migrated {$count} patients");
    }

    private function migrateLabRequests(): void
    {
        $this->info("Migrating lab requests...");
        
        DB::statement("
            INSERT INTO lab_requests (patient_id, lab_no, status, created_at, updated_at)
            SELECT 
                p.id as patient_id,
                lp.lab as lab_no,
                CASE 
                    WHEN lp.deli IS NOT NULL AND lp.deli != '' THEN 'delivered'
                    WHEN lp.entry IS NOT NULL AND lp.entry != '' THEN 'completed'
                    ELSE 'pending'
                END as status,
                CASE 
                    WHEN lp.entry IS NOT NULL AND lp.entry != '' THEN lp.entry
                    ELSE datetime('now')
                END as created_at,
                datetime('now') as updated_at
            FROM legacy_patients lp
            JOIN patients p ON p.id = lp.id
            WHERE lp.lab IS NOT NULL AND lp.lab != ''
        ");
        
        $count = DB::table('legacy_patients')->whereNotNull('lab')->where('lab', '!=', '')->count();
        $this->info("Migrated {$count} lab requests");
    }

    private function migrateSamples(): void
    {
        $this->info("Migrating samples...");
        
        DB::statement("
            INSERT INTO samples (lab_request_id, tsample, nsample, isample, created_at, updated_at)
            SELECT 
                lr.id as lab_request_id,
                lp.tsample,
                lp.nsample,
                lp.isample,
                lr.created_at,
                datetime('now') as updated_at
            FROM legacy_patients lp
            JOIN lab_requests lr ON lr.lab_no = lp.lab
            WHERE lp.tsample IS NOT NULL OR lp.nsample IS NOT NULL OR lp.isample IS NOT NULL
        ");
        
        $count = DB::table('legacy_patients')
            ->where(function($query) {
                $query->whereNotNull('tsample')
                      ->orWhereNotNull('nsample')
                      ->orWhereNotNull('isample');
            })
            ->count();
        $this->info("Migrated {$count} samples");
    }

    private function migratePathologyReports(): void
    {
        $this->info("Migrating pathology reports...");
        
        DB::statement("
            INSERT INTO reports (lab_request_id, title, content, status, generated_at, created_at, updated_at)
            SELECT 
                lr.id as lab_request_id,
                ('Pathology Report - ' || lp.lab) as title,
                (COALESCE('Clinical: ' || lp.clinical || char(10), '') ||
                 COALESCE('Nature: ' || lp.nature || char(10), '') ||
                 COALESCE('Gross: ' || lp.gross || char(10), '') ||
                 COALESCE('Micro: ' || lp.micro || char(10), '') ||
                 COALESCE('Conclusion: ' || lp.conc || char(10), '') ||
                 COALESCE('Recommendation: ' || lp.reco || char(10), '')) as content,
                CASE 
                    WHEN lp.confirm = 1 THEN 'completed'
                    ELSE 'draft'
                END as status,
                CASE 
                    WHEN lp.date IS NOT NULL AND lp.date != '' THEN lp.date
                    ELSE datetime('now')
                END as generated_at,
                datetime('now') as created_at,
                datetime('now') as updated_at
            FROM legacy_pathology lp
            LEFT JOIN lab_requests lr ON lr.lab_no = lp.lab
            WHERE lp.lab IS NOT NULL AND lp.lab != ''
        ");
        
        $count = DB::table('legacy_pathology')->whereNotNull('lab')->where('lab', '!=', '')->count();
        $this->info("Migrated {$count} pathology reports");
    }

    private function migrateFinancialData(): void
    {
        $this->info("Migrating financial data...");
        
        // Migrate expenses
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
            FROM legacy_expenses
            WHERE name IS NOT NULL AND name != ''
        ");

        // Create visits for patients with financial data
        DB::statement("
            INSERT INTO visits (patient_id, visit_number, visit_date, total_amount, final_amount, status, created_at, updated_at)
            SELECT DISTINCT
                p.id as patient_id,
                ('LEGACY-' || lp.id) as visit_number,
                CASE 
                    WHEN lp.entry IS NOT NULL AND lp.entry != '' THEN lp.entry
                    ELSE datetime('now')
                END as visit_date,
                COALESCE(lp.total, 0) as total_amount,
                COALESCE(lp.total, 0) as final_amount,
                CASE 
                    WHEN lp.paid >= lp.total THEN 'completed'
                    ELSE 'pending'
                END as status,
                datetime('now') as created_at,
                datetime('now') as updated_at
            FROM legacy_patients lp
            JOIN patients p ON p.id = lp.id
            WHERE lp.total IS NOT NULL AND lp.total > 0
        ");

        // Create invoices for visits
        DB::statement("
            INSERT INTO invoices (visit_id, invoice_number, invoice_date, subtotal, total_amount, amount_paid, balance, status, created_at, updated_at)
            SELECT 
                v.id as visit_id,
                ('INV-' || v.visit_number) as invoice_number,
                v.visit_date as invoice_date,
                v.total_amount as subtotal,
                v.total_amount as total_amount,
                COALESCE(lp.paid, 0) as amount_paid,
                (v.total_amount - COALESCE(lp.paid, 0)) as balance,
                CASE 
                    WHEN lp.paid >= lp.total THEN 'paid'
                    WHEN lp.paid > 0 THEN 'partial'
                    ELSE 'unpaid'
                END as status,
                datetime('now') as created_at,
                datetime('now') as updated_at
            FROM visits v
            JOIN legacy_patients lp ON lp.id = substr(v.visit_number, 8)
            WHERE v.visit_number LIKE 'LEGACY-%'
        ");

        // Create payments
        DB::statement("
            INSERT INTO payments (invoice_id, amount, payment_method, paid_at, created_by, created_at, updated_at)
            SELECT 
                i.id as invoice_id,
                lp.paid as amount,
                'cash' as payment_method,
                CASE 
                    WHEN lp.entry IS NOT NULL AND lp.entry != '' THEN lp.entry
                    ELSE datetime('now')
                END as paid_at,
                COALESCE(lp.sender, 1) as created_by,
                datetime('now') as created_at,
                datetime('now') as updated_at
            FROM invoices i
            JOIN visits v ON v.id = i.visit_id
            JOIN legacy_patients lp ON lp.id = substr(v.visit_number, 8)
            WHERE v.visit_number LIKE 'LEGACY-%' AND lp.paid > 0
        ");
        
        $this->info("Migrated financial data (expenses, visits, invoices, payments)");
    }
}