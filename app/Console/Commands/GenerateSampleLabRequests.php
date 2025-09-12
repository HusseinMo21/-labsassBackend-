<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Sample;
use App\Services\LabNoGenerator;
use App\Services\BarcodeGenerator;
use Illuminate\Support\Facades\DB;

class GenerateSampleLabRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lab:generate-sample {count=10 : Number of lab requests to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sample lab requests for testing';

    protected $labNoGenerator;
    protected $barcodeGenerator;

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
        $count = (int) $this->argument('count');
        
        if ($count <= 0) {
            $this->error('Count must be a positive number');
            return 1;
        }

        $this->info("🚀 Generating {$count} sample lab requests...");

        try {
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            for ($i = 0; $i < $count; $i++) {
                DB::transaction(function () {
                    // Get a random patient or create a sample one
                    $patient = Patient::inRandomOrder()->first();
                    
                    if (!$patient) {
                        // Create a sample patient if none exist
                        $patient = Patient::create([
                            'name' => 'Sample Patient ' . rand(1, 1000),
                            'gender' => ['male', 'female'][rand(0, 1)],
                            'birth_date' => now()->subYears(rand(18, 80)),
                            'phone' => '01' . rand(10000000, 99999999),
                            'address' => 'Sample Address',
                            'user_id' => null,
                        ]);
                    }

                    // Generate lab number
                    $labNoData = $this->labNoGenerator->generate();
                    
                    // Create lab request
                    $labRequest = LabRequest::create([
                        'patient_id' => $patient->id,
                        'lab_no' => $labNoData['base'],
                        'status' => ['pending', 'received', 'in_progress', 'completed'][rand(0, 3)],
                        'metadata' => [
                            'generated_by' => 'artisan_command',
                            'sample_data' => true,
                        ],
                    ]);

                    // Create 1-3 samples per lab request
                    $sampleCount = rand(1, 3);
                    for ($j = 0; $j < $sampleCount; $j++) {
                        Sample::create([
                            'lab_request_id' => $labRequest->id,
                            'tsample' => 'Sample Type ' . ($j + 1),
                            'nsample' => 'Sample Name ' . ($j + 1),
                            'isample' => 'Sample ID ' . ($j + 1),
                            'notes' => 'Sample notes for test ' . ($j + 1),
                        ]);
                    }

                    // Generate barcode and QR code
                    $this->barcodeGenerator->generateForLabRequest($labRequest->full_lab_no);
                });

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $this->info('🎉 Sample lab requests generated successfully!');
            $this->newLine();
            $this->info('📊 Summary:');
            $this->info("- Generated: {$count} lab requests");
            $this->info('- Total lab requests: ' . LabRequest::count());
            $this->info('- Total samples: ' . Sample::count());

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to generate sample lab requests: " . $e->getMessage());
            return 1;
        }
    }
}
