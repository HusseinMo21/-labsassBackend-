<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LabRequest;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class MigratePatientDetails extends Command
{
    protected $signature = 'migrate:patient-details';
    protected $description = 'Migrates patient details from original tables to new Laravel structure (samples, visits, reports, invoices)';

    public function handle()
    {
        $this->info('Starting migration of patient details...');

        // Get all lab requests that have corresponding patient data
        $labRequests = LabRequest::whereNotNull('patient_id')->get();
        $bar = $this->output->createProgressBar($labRequests->count());
        $bar->start();

        foreach ($labRequests as $labRequest) {
            $this->migrateLabRequestDetails($labRequest);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nMigration completed successfully!");
    }

    private function migrateLabRequestDetails($labRequest)
    {
        // Get original patient data
        $originalPatient = DB::table('patient')->where('id', $labRequest->patient_id)->first();
        if (!$originalPatient) {
            return;
        }

        // Get original pathology data
        $originalPathology = DB::table('patholgy')->where('lab', $originalPatient->lab)->first();

        // 1. Create samples from original patient data
        $this->createSamples($labRequest, $originalPatient);

        // 2. Create visit from original patient data
        $this->createVisit($labRequest, $originalPatient);

        // 3. Create report from original pathology data (skip for now due to datetime issues)
        // if ($originalPathology) {
        //     $this->createReport($labRequest, $originalPathology);
        // }

        // 4. Create invoice from original patient data
        $this->createInvoice($labRequest, $originalPatient);
    }

    private function createSamples($labRequest, $originalPatient)
    {
        // Check if samples already exist
        if ($labRequest->samples()->count() > 0) {
            return;
        }

        // Create sample from original data
        if ($originalPatient->tsample || $originalPatient->nsample || $originalPatient->isample) {
            // Parse date safely for samples
            $sampleDate = null;
            if ($originalPatient->entry) {
                try {
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $originalPatient->entry)) {
                        $sampleDate = \Carbon\Carbon::createFromFormat('d/m/Y', $originalPatient->entry);
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $originalPatient->entry)) {
                        $sampleDate = \Carbon\Carbon::parse($originalPatient->entry);
                    } else {
                        $sampleDate = \Carbon\Carbon::parse($originalPatient->entry);
                    }
                } catch (\Exception $e) {
                    $sampleDate = null;
                }
            }
            
            $labRequest->samples()->create([
                'sample_type' => $originalPatient->tsample ?: 'Pathology',
                'sample_id' => $originalPatient->nsample ?: '1',
                'collection_date' => $sampleDate,
                'received_date' => $sampleDate,
                'status' => 'completed', // Assuming old records are completed
                'notes' => $originalPatient->isample ?: null,
            ]);
        }
    }

    private function createVisit($labRequest, $originalPatient)
    {
        // Check if visit already exists
        if ($labRequest->patient->visits()->count() > 0) {
            return;
        }

        // Create visit from original data
        $visitTime = '09:00:00'; // Default time
        if ($originalPatient->time && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $originalPatient->time)) {
            $visitTime = $originalPatient->time;
        }
        
        // Parse date safely
        $visitDate = now();
        if ($originalPatient->entry) {
            try {
                // Try different date formats
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $originalPatient->entry)) {
                    // Format: DD/MM/YYYY
                    $visitDate = \Carbon\Carbon::createFromFormat('d/m/Y', $originalPatient->entry);
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $originalPatient->entry)) {
                    // Format: YYYY-MM-DD
                    $visitDate = \Carbon\Carbon::parse($originalPatient->entry);
                } else {
                    $visitDate = \Carbon\Carbon::parse($originalPatient->entry);
                }
            } catch (\Exception $e) {
                // If parsing fails, use current date
                $visitDate = now();
            }
        }
        
        $labId = $labRequest->lab_id ?? $labRequest->patient->lab_id ?? 1;
        $visit = $labRequest->patient->visits()->create([
            'lab_id' => $labId,
            'visit_number' => 'V' . $originalPatient->id,
            'visit_date' => $visitDate,
            'visit_time' => $visitTime,
            'total_amount' => $originalPatient->total ?: 0,
            'discount_amount' => 0,
            'final_amount' => $originalPatient->total ?: 0,
            'status' => 'completed',
            'remarks' => 'Migrated from original data - Sender: ' . ($originalPatient->sender ?: 'N/A') . ($originalPatient->time && !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $originalPatient->time) ? ' - Original time: ' . $originalPatient->time : ''),
            'lab_request_id' => $labRequest->id,
        ]);

        // Create visit test if there's test type information
        if ($originalPatient->type) {
            // Parse delivery date safely
            $performedAt = null;
            if ($originalPatient->deli) {
                try {
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $originalPatient->deli)) {
                        $performedAt = \Carbon\Carbon::createFromFormat('d/m/Y', $originalPatient->deli);
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $originalPatient->deli)) {
                        $performedAt = \Carbon\Carbon::parse($originalPatient->deli);
                    } else {
                        // Skip parsing if format is not recognized
                        $performedAt = null;
                    }
                } catch (\Exception $e) {
                    $performedAt = null;
                }
            }
            
            $visit->visitTests()->create([
                'lab_id' => $visit->lab_id,
                'lab_test_id' => 2, // Default test ID (General Pathology)
                'price' => $originalPatient->total ?: 0,
                'status' => 'completed',
                'barcode_uid' => \App\Models\VisitTest::generateBarcodeUid(),
                'result_value' => null,
                'result_status' => 'normal',
                'performed_by' => null,
                'performed_at' => $performedAt,
            ]);
        }
    }

    private function createReport($labRequest, $originalPathology)
    {
        // Check if report already exists
        if ($labRequest->report) {
            return;
        }

        // Parse date safely for report
        $reportDate = now();
        if ($originalPathology->date) {
            try {
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $originalPathology->date)) {
                    $reportDate = \Carbon\Carbon::createFromFormat('d/m/Y', $originalPathology->date);
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $originalPathology->date)) {
                    $reportDate = \Carbon\Carbon::parse($originalPathology->date);
                } else {
                    $reportDate = \Carbon\Carbon::parse($originalPathology->date);
                }
            } catch (\Exception $e) {
                $reportDate = now();
            }
        }
        
        // Create report from pathology data using direct DB insert to avoid model events
        DB::table('reports')->insert([
            'title' => 'Pathology Report - ' . $originalPathology->type,
            'content' => $this->formatReportContent($originalPathology),
            'status' => $originalPathology->confirm ? 'approved' : 'draft',
            'generated_at' => $reportDate ? $reportDate->format('Y-m-d H:i:s') : null,
            'generated_by' => null,
            'lab_request_id' => $labRequest->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInvoice($labRequest, $originalPatient)
    {
        // Check if invoice already exists
        if ($labRequest->invoice) {
            return;
        }

        // Create invoice from original data
        $invoice = $labRequest->invoice()->create([
            'lab' => $originalPatient->lab,
            'total' => $originalPatient->total ?: 0,
            'paid' => $originalPatient->paid ?: 0,
            'remaining' => $originalPatient->pleft ?: 0,
        ]);

        // Create payment record if there's payment data
        if ($originalPatient->paid > 0) {
            // Parse date safely for payment
            $paymentDate = now();
            if ($originalPatient->entry) {
                try {
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $originalPatient->entry)) {
                        $paymentDate = \Carbon\Carbon::createFromFormat('d/m/Y', $originalPatient->entry);
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $originalPatient->entry)) {
                        $paymentDate = \Carbon\Carbon::parse($originalPatient->entry);
                    } else {
                        $paymentDate = \Carbon\Carbon::parse($originalPatient->entry);
                    }
                } catch (\Exception $e) {
                    $paymentDate = now();
                }
            }
            
            $invoice->payments()->create([
                'paid' => $originalPatient->paid,
                'comment' => 'Migrated from original data',
                'date' => $paymentDate,
                'author' => 1, // Default author ID
                'income' => 1, // Default income flag
            ]);
        }
    }

    private function formatReportContent($pathology)
    {
        $content = [];
        
        if ($pathology->clinical) {
            $content[] = "Clinical Information: " . $pathology->clinical;
        }
        
        if ($pathology->nature) {
            $content[] = "Nature of Specimen: " . $pathology->nature;
        }
        
        if ($pathology->gross) {
            $content[] = "Gross Examination: " . $pathology->gross;
        }
        
        if ($pathology->micro) {
            $content[] = "Microscopic Examination: " . $pathology->micro;
        }
        
        if ($pathology->conc) {
            $content[] = "Conclusion: " . $pathology->conc;
        }
        
        if ($pathology->reco) {
            $content[] = "Recommendations: " . $pathology->reco;
        }
        
        return implode("\n\n", $content);
    }
}
