<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixOriginalData extends Command
{
    protected $signature = 'fix:original-data';
    protected $description = 'Fix existing data in original tables with proper gender and type mapping';

    public function handle()
    {
        $this->info('Starting to fix original data...');
        
        // Fix patient gender mapping
        $this->fixPatientGender();
        
        // Fix pathology type mapping
        $this->fixPathologyType();
        
        // Fix pathology gender mapping
        $this->fixPathologyGender();
        
        $this->info('Original data fixing completed!');
    }

    private function fixPatientGender()
    {
        $this->info('Fixing patient gender mapping...');
        
        $updated = 0;
        
        // Update patients with proper gender mapping
        $patients = DB::table('patient')->whereNull('gender')->orWhere('gender', '')->get();
        
        foreach ($patients as $patient) {
            // Try to determine gender from name (this is a basic approach)
            $gender = $this->determineGenderFromName($patient->name);
            
            if ($gender) {
                DB::table('patient')
                    ->where('id', $patient->id)
                    ->update(['gender' => $gender]);
                $updated++;
            }
        }
        
        $this->info("Updated {$updated} patient gender records");
    }

    private function fixPathologyType()
    {
        $this->info('Fixing pathology type mapping...');
        
        $updated = 0;
        
        // Update pathology reports with proper type mapping
        $reports = DB::table('patholgy')->whereNull('type')->orWhere('type', '')->get();
        
        foreach ($reports as $report) {
            // Try to determine type from clinical information or other fields
            $type = $this->determineTypeFromContent($report);
            
            if ($type) {
                DB::table('patholgy')
                    ->where('id', $report->id)
                    ->update(['type' => $type]);
                $updated++;
            }
        }
        
        $this->info("Updated {$updated} pathology type records");
    }

    private function fixPathologyGender()
    {
        $this->info('Fixing pathology gender mapping...');
        
        $updated = 0;
        
        // Update pathology reports with proper gender mapping
        $reports = DB::table('patholgy')->whereNull('sex')->orWhere('sex', '')->get();
        
        foreach ($reports as $report) {
            // Try to determine gender from patient name
            $gender = $this->determineGenderFromName($report->nos);
            
            if ($gender) {
                DB::table('patholgy')
                    ->where('id', $report->id)
                    ->update(['sex' => $gender]);
                $updated++;
            }
        }
        
        $this->info("Updated {$updated} pathology gender records");
    }

    private function determineGenderFromName($name)
    {
        if (empty($name)) {
            return null;
        }
        
        // Common Arabic male names
        $maleNames = [
            'محمد', 'أحمد', 'علي', 'حسن', 'حسين', 'عبدالله', 'عبدالرحمن', 'عبدالعزيز',
            'عبدالرحيم', 'عبداللطيف', 'عبدالمجيد', 'عبدالرزاق', 'عبدالسلام', 'عبدالغني',
            'عبدالفتاح', 'عبدالكريم', 'عبداللطيف', 'عبدالملك', 'عبدالمنعم', 'عبدالوهاب',
            'عبدالواحد', 'عبدالواسع', 'عبدالودود', 'عبدالوكيل', 'عبدالولي', 'عبدالوهاب',
            'علاء', 'بدر', 'خالد', 'سعد', 'طارق', 'يوسف', 'إبراهيم', 'إسماعيل',
            'عمر', 'عثمان', 'عبدالله', 'عبدالرحمن', 'عبدالعزيز', 'عبدالرحيم'
        ];
        
        // Common Arabic female names
        $femaleNames = [
            'فاطمة', 'عائشة', 'خديجة', 'مريم', 'زينب', 'رقية', 'أم كلثوم', 'صفية',
            'حفصة', 'جويرية', 'ميمونة', 'سودة', 'أم سلمة', 'أم حبيبة', 'صفية',
            'مريم', 'فاطمة', 'زينب', 'رقية', 'أم كلثوم', 'عائشة', 'خديجة',
            'نور', 'هدى', 'سارة', 'رنا', 'رانيا', 'دينا', 'منى', 'هند',
            'نورا', 'ياسمين', 'فيروز', 'سمر', 'ريم', 'لينا', 'مروة', 'هالة'
        ];
        
        $name = trim($name);
        
        foreach ($maleNames as $maleName) {
            if (strpos($name, $maleName) !== false) {
                return 'ذكر';
            }
        }
        
        foreach ($femaleNames as $femaleName) {
            if (strpos($name, $femaleName) !== false) {
                return 'انثي';
            }
        }
        
        return null;
    }

    private function determineTypeFromContent($report)
    {
        if (empty($report->clinical) && empty($report->nature)) {
            return null;
        }
        
        $content = strtolower($report->clinical . ' ' . $report->nature);
        
        // Determine type based on content
        if (strpos($content, 'fnac') !== false || strpos($content, 'fine needle') !== false) {
            return 'FNAC';
        } elseif (strpos($content, 'biopsy') !== false) {
            return 'Biopsy';
        } elseif (strpos($content, 'cytology') !== false) {
            return 'Cytology';
        } elseif (strpos($content, 'histology') !== false) {
            return 'Histology';
        } elseif (strpos($content, 'pathology') !== false) {
            return 'Pathology';
        }
        
        return 'Pathology'; // Default type
    }
}










