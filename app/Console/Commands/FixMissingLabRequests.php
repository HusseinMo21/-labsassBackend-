<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Visit;
use App\Models\LabRequest;
use App\Services\LabNoGenerator;
use App\Services\BarcodeGenerator;

class FixMissingLabRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lab:fix-missing-requests {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix visits that are missing lab requests by creating them';

    protected $labNoGenerator;
    protected $barcodeGenerator;

    /**
     * Create a new command instance.
     */
    public function __construct(LabNoGenerator $labNoGenerator, BarcodeGenerator $barcodeGenerator)
    {
        parent::__construct();
        $this->labNoGenerator = $labNoGenerator;
        $this->barcodeGenerator = $barcodeGenerator;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Finding visits without lab requests...');
        
        // Find visits that don't have lab requests
        $visitsWithoutLabRequests = Visit::whereNull('lab_request_id')
            ->whereHas('visitTests') // Only visits with tests
            ->with(['patient', 'visitTests.labTest'])
            ->get();
        
        if ($visitsWithoutLabRequests->isEmpty()) {
            $this->info('No visits found without lab requests.');
            return 0;
        }
        
        $this->info("Found {$visitsWithoutLabRequests->count()} visits without lab requests.");
        
        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made.');
            $this->table(
                ['Visit ID', 'Patient Name', 'Visit Date', 'Tests Count'],
                $visitsWithoutLabRequests->map(function ($visit) {
                    return [
                        $visit->id,
                        $visit->patient->name,
                        $visit->visit_date->format('Y-m-d'),
                        $visit->visitTests->count()
                    ];
                })
            );
            return 0;
        }
        
        $this->info('Creating lab requests for missing visits...');
        
        $created = 0;
        $errors = 0;
        
        foreach ($visitsWithoutLabRequests as $visit) {
            try {
                // Check if patient already has a lab request
                $existingLabRequest = LabRequest::where('patient_id', $visit->patient_id)->first();
                
                if ($existingLabRequest) {
                    // Link to existing lab request
                    $visit->update(['lab_request_id' => $existingLabRequest->id]);
                    $this->line("Linked visit {$visit->id} to existing lab request {$existingLabRequest->lab_no}");
                } else {
                    // Create new lab request
                    $labNoData = $this->labNoGenerator->generate();
                    $labNo = $labNoData['base'];
                    
                    $labRequest = LabRequest::create([
                        'patient_id' => $visit->patient_id,
                        'lab_no' => $labNo,
                        'status' => 'pending',
                        'metadata' => [
                            'auto_created' => true,
                            'created_with_fix_command' => true,
                            'visit_id' => $visit->id,
                            'created_at' => now()->toISOString(),
                        ],
                    ]);
                    
                    // Generate barcode and QR code for the new lab request
                    $this->barcodeGenerator->generateForLabRequest($labRequest);
                    
                    // Link the visit to the lab request
                    $visit->update(['lab_request_id' => $labRequest->id]);
                    
                    $this->line("Created lab request {$labNo} for visit {$visit->id}");
                    $created++;
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to create lab request for visit {$visit->id}: " . $e->getMessage());
                $errors++;
            }
        }
        
        $linked = $visitsWithoutLabRequests->count() - $created - $errors;
        $this->info("Completed! Created {$created} new lab requests, linked to {$linked} existing ones, {$errors} errors.");
        
        return 0;
    }
}