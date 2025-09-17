<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy-data';
    protected $description = 'Import and fix data from legacy database tables';

    public function handle()
    {
        $this->info('Starting legacy data import...');
        
        // Import patients with proper gender mapping
        $this->importPatients();
        
        // Import pathology reports with proper type mapping
        $this->importPathologyReports();
        
        $this->info('Legacy data import completed!');
    }

    private function importPatients()
    {
        $this->info('Importing patients...');
        
        $legacyPatients = DB::table('patient')->get();
        $imported = 0;
        
        foreach ($legacyPatients as $legacyPatient) {
            // Skip if patient already exists
            if (DB::table('patients')->where('name', $legacyPatient->name)->exists()) {
                continue;
            }
            
            // Map gender from Arabic to English
            $gender = $this->mapGender($legacyPatient->gender);
            
            // Calculate birth date from age (approximate)
            $birthDate = $this->calculateBirthDate($legacyPatient->age);
            
            DB::table('patients')->insert([
                'name' => $legacyPatient->name,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'phone' => $legacyPatient->phone ?: null,
                'address' => $legacyPatient->address ?: null,
                'emergency_contact' => null,
                'emergency_phone' => null,
                'medical_history' => null,
                'allergies' => null,
                'user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $imported++;
        }
        
        $this->info("Imported {$imported} patients");
    }

    private function importPathologyReports()
    {
        $this->info('Importing pathology reports...');
        
        // First, ensure enhanced_reports table exists
        if (!Schema::hasTable('enhanced_reports')) {
            $this->error('Enhanced reports table does not exist. Please run migrations first.');
            return;
        }
        
        $legacyReports = DB::table('patholgy')->get();
        $imported = 0;
        
        foreach ($legacyReports as $legacyReport) {
            // Skip if report already exists
            if (DB::table('enhanced_reports')->where('lab_no', $legacyReport->lab)->exists()) {
                continue;
            }
            
            // Find corresponding patient
            $patient = DB::table('patients')
                ->where('name', 'like', '%' . trim($legacyReport->nos) . '%')
                ->first();
            
            // Map report type
            $reportType = $this->mapReportType($legacyReport->type);
            
            // Map gender
            $gender = $this->mapGender($legacyReport->sex);
            
            DB::table('enhanced_reports')->insert([
                'nos' => $legacyReport->nos,
                'reff' => $legacyReport->reff,
                'clinical' => $legacyReport->clinical,
                'nature' => $legacyReport->nature,
                'report_date' => $legacyReport->date ? Carbon::parse($legacyReport->date) : null,
                'lab_no' => $legacyReport->lab,
                'age' => $legacyReport->age,
                'gross' => $legacyReport->gross,
                'micro' => $legacyReport->micro,
                'conc' => $legacyReport->conc,
                'reco' => $legacyReport->reco,
                'type' => $reportType,
                'sex' => $gender,
                'recieving' => $legacyReport->recieving,
                'discharge' => $legacyReport->discharge,
                'confirm' => (bool) $legacyReport->confirm,
                'print' => (bool) $legacyReport->print,
                'patient_id' => $patient ? $patient->id : null,
                'lab_request_id' => null,
                'created_by' => null,
                'reviewed_by' => null,
                'approved_by' => null,
                'status' => $legacyReport->confirm ? 'approved' : 'draft',
                'priority' => 'normal',
                'examination_details' => null,
                'quality_control' => null,
                'barcode' => null,
                'digital_signature' => null,
                'reviewed_at' => null,
                'approved_at' => $legacyReport->confirm ? now() : null,
                'printed_at' => $legacyReport->print ? now() : null,
                'delivered_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $imported++;
        }
        
        $this->info("Imported {$imported} pathology reports");
    }

    private function mapGender($gender)
    {
        if (empty($gender)) {
            return 'other';
        }
        
        $gender = trim(strtolower($gender));
        
        // Arabic to English mapping
        if (in_array($gender, ['ذكر', 'male', 'm'])) {
            return 'male';
        } elseif (in_array($gender, ['انثي', 'انثى', 'female', 'f'])) {
            return 'female';
        }
        
        return 'other';
    }

    private function mapReportType($type)
    {
        if (empty($type)) {
            return 'N/A';
        }
        
        $type = trim($type);
        
        // Map common pathology types
        $typeMapping = [
            'Pathology' => 'Pathology',
            'FNAC' => 'FNAC',
            'Biopsy' => 'Biopsy',
            'Cytology' => 'Cytology',
            'Histology' => 'Histology',
        ];
        
        return $typeMapping[$type] ?? $type;
    }

    private function calculateBirthDate($age)
    {
        if (empty($age) || $age <= 0) {
            return Carbon::now()->subYears(30)->format('Y-m-d'); // Default to 30 years ago
        }
        
        return Carbon::now()->subYears($age)->format('Y-m-d');
    }
}