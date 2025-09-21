<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\LabRequest;

class ImportPatientsToLabRequests extends Command
{
    protected $signature = 'import:patients-to-lab-requests {--batch-size=1000 : Number of patients to process in each batch}';
    protected $description = 'Import all existing patients from the patient table into lab_requests table';

    public function handle()
    {
        $this->info('Starting import of patients to lab requests...');
        
        $batchSize = $this->option('batch-size');
        $totalPatients = DB::table('patient')->whereNotNull('lab')->count();
        
        $this->info("Total patients to import: {$totalPatients}");
        
        if ($totalPatients === 0) {
            $this->warn('No patients found with lab numbers. Nothing to import.');
            return;
        }
        
        $bar = $this->output->createProgressBar($totalPatients);
        $bar->start();
        
        $imported = 0;
        $skipped = 0;
        
        // Process in batches to avoid memory issues
        DB::table('patient')
            ->whereNotNull('lab')
            ->orderBy('id')
            ->chunk($batchSize, function ($patients) use (&$imported, &$skipped, $bar) {
                foreach ($patients as $patient) {
                    try {
                        // Check if lab request already exists
                        $existingLabRequest = DB::table('lab_requests')
                            ->where('lab_no', $patient->lab)
                            ->first();
                        
                        if ($existingLabRequest) {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }
                        
                        // Create lab request
                        DB::table('lab_requests')->insert([
                            'patient_id' => $patient->id,
                            'lab_no' => $patient->lab,
                            'suffix' => null, // Will be determined based on test type
                            'status' => $this->determineStatus($patient),
                            'metadata' => json_encode([
                                'original_patient_data' => [
                                    'entry' => $patient->entry,
                                    'deli' => $patient->deli,
                                    'time' => $patient->time,
                                    'tsample' => $patient->tsample,
                                    'nsample' => $patient->nsample,
                                    'isample' => $patient->isample,
                                    'paid' => $patient->paid,
                                    'had' => $patient->had,
                                    'sender' => $patient->sender,
                                    'pleft' => $patient->pleft,
                                    'total' => $patient->total,
                                    'entryday' => $patient->entryday,
                                    'deliday' => $patient->deliday,
                                    'type' => $patient->type,
                                    'doctor_id' => $patient->doctor_id,
                                    'organization_id' => $patient->organization_id,
                                ]
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        $imported++;
                    } catch (\Exception $e) {
                        $this->error("Error importing patient ID {$patient->id}: " . $e->getMessage());
                        $skipped++;
                    }
                    
                    $bar->advance();
                }
            });
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Import completed!");
        $this->info("Imported: {$imported} lab requests");
        $this->info("Skipped: {$skipped} lab requests");
        
        // Show the latest lab number for reference
        $latestLabRequest = DB::table('lab_requests')
            ->orderBy('id', 'desc')
            ->first();
        
        if ($latestLabRequest) {
            $this->info("Latest lab request: {$latestLabRequest->lab_no}");
        }
    }
    
    private function determineStatus($patient)
    {
        // Determine status based on patient data
        if ($patient->had === 'Yes') {
            return 'completed';
        } elseif ($patient->paid > 0) {
            return 'in_progress';
        } else {
            return 'pending';
        }
    }
}







