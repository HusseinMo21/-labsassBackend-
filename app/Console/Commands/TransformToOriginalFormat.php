<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransformToOriginalFormat extends Command
{
    protected $signature = 'transform:original-format';
    protected $description = 'Transform current Laravel data to original database format';

    public function handle()
    {
        $this->info('Starting transformation to original format...');
        
        // Transform patients data
        $this->transformPatients();
        
        // Transform reports data
        $this->transformReports();
        
        $this->info('Transformation completed!');
    }

    private function transformPatients()
    {
        $this->info('Transforming patients data...');
        
        // Get current patients from Laravel table
        $currentPatients = DB::table('patients')->get();
        $transformed = 0;
        
        foreach ($currentPatients as $patient) {
            // Skip if patient already exists in original format
            if (DB::table('patient')->where('name', $patient->name)->exists()) {
                continue;
            }
            
            // Calculate age from birth date
            $age = $patient->birth_date ? Carbon::parse($patient->birth_date)->age : null;
            
            // Transform gender from English to Arabic
            $gender = $this->transformGenderToArabic($patient->gender);
            
            // Generate lab number (you might want to customize this logic)
            $labNumber = $this->generateLabNumber();
            
            DB::table('patient')->insert([
                'name' => $patient->name,
                'address' => $patient->address,
                'entry' => $patient->created_at ? $patient->created_at->format('Y-m-d') : null,
                'deli' => null, // You might want to set this based on your business logic
                'time' => null,
                'age' => $age,
                'phone' => $patient->phone,
                'tsample' => null, // You might want to set this based on your data
                'nsample' => null,
                'isample' => null,
                'paid' => 0, // You might want to set this based on your data
                'had' => 'No', // You might want to set this based on your data
                'sender' => null,
                'pleft' => 0,
                'total' => 0,
                'lab' => $labNumber,
                'entryday' => $patient->created_at ? $this->getArabicDayName($patient->created_at) : null,
                'deliday' => null,
                'gender' => $gender,
                'type' => null, // You might want to set this based on your data
            ]);
            
            $transformed++;
        }
        
        $this->info("Transformed {$transformed} patients");
    }

    private function transformReports()
    {
        $this->info('Transforming reports data...');
        
        // Check if enhanced_reports table exists
        if (!Schema::hasTable('enhanced_reports')) {
            $this->warn('Enhanced reports table does not exist. Skipping reports transformation.');
            return;
        }
        
        $currentReports = DB::table('enhanced_reports')->get();
        $transformed = 0;
        
        foreach ($currentReports as $report) {
            // Skip if report already exists in original format
            if (DB::table('patholgy')->where('lab', $report->lab_no)->exists()) {
                continue;
            }
            
            // Transform gender from English to Arabic
            $sex = $this->transformGenderToArabic($report->sex);
            
            // Transform report type
            $type = $this->transformReportType($report->type);
            
            DB::table('patholgy')->insert([
                'nos' => $report->nos,
                'reff' => $report->reff,
                'clinical' => $report->clinical,
                'nature' => $report->nature,
                'date' => $report->report_date ? $report->report_date->format('Y-m-d') : null,
                'lab' => $report->lab_no,
                'age' => $report->age,
                'gross' => $report->gross,
                'micro' => $report->micro,
                'conc' => $report->conc,
                'reco' => $report->reco,
                'type' => $type,
                'sex' => $sex,
                'recieving' => $report->recieving,
                'discharge' => $report->discharge,
                'confirm' => $report->confirm ? 1 : 0,
                'print' => $report->print ? 1 : 0,
            ]);
            
            $transformed++;
        }
        
        $this->info("Transformed {$transformed} reports");
    }

    private function transformGenderToArabic($gender)
    {
        if (empty($gender)) {
            return null;
        }
        
        $gender = strtolower(trim($gender));
        
        // English to Arabic mapping
        if ($gender === 'male') {
            return 'ذكر';
        } elseif ($gender === 'female') {
            return 'انثي';
        }
        
        return $gender; // Return as is if not recognized
    }

    private function transformReportType($type)
    {
        if (empty($type) || $type === 'N/A') {
            return null;
        }
        
        // You can add more specific mappings here based on your needs
        return $type;
    }

    private function generateLabNumber()
    {
        // Generate a lab number in the format: number-year
        $year = date('Y');
        $lastLab = DB::table('patient')->where('lab', 'like', "%-{$year}")->orderBy('id', 'desc')->first();
        
        if ($lastLab && $lastLab->lab) {
            $parts = explode('-', $lastLab->lab);
            if (count($parts) === 2 && $parts[1] === $year) {
                $nextNumber = intval($parts[0]) + 1;
                return "{$nextNumber}-{$year}";
            }
        }
        
        return "1-{$year}";
    }

    private function getArabicDayName($date)
    {
        $dayNames = [
            'Sunday' => 'الاحد',
            'Monday' => 'الاثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الاربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];
        
        $englishDay = $date->format('l');
        return $dayNames[$englishDay] ?? $englishDay;
    }
}







