<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Sample;
use App\Models\LegacyData;
use App\Services\LabNoGenerator;
use App\Services\BarcodeGenerator;
use Illuminate\Support\Facades\DB;

class MigrateLegacySamples extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lab:migrate-legacy-samples {--limit=100 : Maximum number of records to migrate} {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy sample data to new lab requests system';

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
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('🚀 Starting legacy samples migration...');

        try {
            // Check if legacy patient table exists
            if (!DB::getSchemaBuilder()->hasTable('patient')) {
                $this->error('❌ Legacy patient table not found. Please import your legacy database first.');
                return 1;
            }

            // Get legacy patients with sample data
            $legacyPatients = DB::table('patient')
                ->whereNotNull('tsample')
                ->orWhereNotNull('nsample')
                ->orWhereNotNull('isample')
                ->limit($limit)
                ->get();

            $this->info("📊 Found {$legacyPatients->count()} legacy patients with sample data to migrate");

            if ($legacyPatients->isEmpty()) {
                $this->warn('⚠️ No legacy patients with sample data found to migrate');
                return 0;
            }

            $migrated = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($legacyPatients as $legacyPatient) {
                try {
                    // Find corresponding patient in new system
                    $patient = Patient::where('name', $legacyPatient->name)
                        ->where('phone', $legacyPatient->phone)
                        ->first();

                    if (!$patient) {
                        $this->warn("⏭️ Patient not found in new system: {$legacyPatient->name}, skipping");
                        $skipped++;
                        continue;
                    }

                    // Check if lab request already exists for this patient
                    $existingLabRequest = LabRequest::where('patient_id', $patient->id)
                        ->where('metadata->legacy_lab', $legacyPatient->lab)
                        ->first();

                    if ($existingLabRequest) {
                        $this->warn("⏭️ Lab request already exists for patient {$patient->name} with lab {$legacyPatient->lab}, skipping");
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        DB::transaction(function () use ($legacyPatient, $patient) {
                            // Generate lab number
                            $labNoData = $this->labNoGenerator->generate();
                            
                            // Create lab request
                            $labRequest = LabRequest::create([
                                'patient_id' => $patient->id,
                                'lab_no' => $labNoData['base'],
                                'status' => 'completed', // Assume legacy data is completed
                                'metadata' => [
                                    'legacy_lab' => $legacyPatient->lab,
                                    'legacy_entry' => $legacyPatient->entry,
                                    'legacy_deli' => $legacyPatient->deli,
                                    'migrated_from' => 'legacy_patient_table',
                                ],
                            ]);

                            // Create sample from legacy data
                            Sample::create([
                                'lab_request_id' => $labRequest->id,
                                'tsample' => $legacyPatient->tsample,
                                'nsample' => $legacyPatient->nsample,
                                'isample' => $legacyPatient->isample,
                                'notes' => 'Migrated from legacy patient data',
                            ]);

                            // Archive unmapped legacy fields
                            $this->archiveLegacyData('patient', $legacyPatient->id, $labRequest->id, $legacyPatient);

                            // Generate barcode and QR code
                            $this->barcodeGenerator->generateForLabRequest($labRequest->full_lab_no);
                        });

                        $this->info("✅ Migrated lab request for patient: {$patient->name} (Lab: {$legacyPatient->lab})");
                    } else {
                        $this->info("🔍 Would migrate lab request for patient: {$patient->name} (Lab: {$legacyPatient->lab})");
                    }

                    $migrated++;

                } catch (\Exception $e) {
                    $this->error("❌ Error migrating patient {$legacyPatient->name}: " . $e->getMessage());
                    $errors++;
                }
            }

            // Summary
            $this->newLine();
            $this->info('📈 Migration Summary:');
            $this->info("✅ Migrated: {$migrated}");
            $this->info("⏭️ Skipped: {$skipped}");
            $this->info("❌ Errors: {$errors}");

            if ($dryRun) {
                $this->warn('🔍 This was a dry run. Run without --dry-run to apply changes.');
            } else {
                $this->info('🎉 Legacy samples migration completed successfully!');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Migration failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Archive legacy data that doesn't have a direct mapping
     */
    private function archiveLegacyData($legacyTable, $legacyId, $newId, $legacyRecord)
    {
        $fieldsToArchive = ['entry', 'deli', 'time', 'age', 'paid', 'had', 'sender', 'pleft', 'total', 'entryday', 'deliday', 'gender', 'type'];
        
        foreach ($fieldsToArchive as $field) {
            if (isset($legacyRecord->$field)) {
                LegacyData::create([
                    'legacy_table' => $legacyTable,
                    'legacy_id' => $legacyId,
                    'legacy_field' => $field,
                    'legacy_value' => $legacyRecord->$field,
                    'new_table' => 'lab_requests',
                    'new_id' => $newId,
                    'migration_notes' => "Legacy field '{$field}' archived during sample migration"
                ]);
            }
        }
    }
}
