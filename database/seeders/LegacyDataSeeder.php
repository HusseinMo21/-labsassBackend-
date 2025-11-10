<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\Report;
use App\Models\Visit;

class LegacyDataSeeder extends Seeder
{
    /**
     * Lookup maps for efficient processing
     */
    private $patientMap = []; // name+phone -> patient_id
    private $labRequestMap = []; // base+suffix -> lab_request_id
    private $skippedRecords = [];

    /**
     * Statistics
     */
    private $stats = [
        'patients_created' => 0,
        'patients_updated' => 0,
        'lab_requests_created' => 0,
        'lab_requests_found' => 0,
        'reports_created' => 0,
        'reports_skipped' => 0,
        'visits_created' => 0,
        'errors' => 0,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Legacy Data Migration...');
        $this->command->info('========================================');
        $this->command->info('Note: For large files, this may take several minutes and use significant memory.');
        $this->command->info('If you encounter memory errors, increase PHP memory_limit to 1024M or higher.');
        $this->command->info('');

        try {
            // Remove unique constraint on patient_id if it exists (patients can have multiple lab requests)
            $this->removePatientIdUniqueConstraint();
            // Step 1: Load JSON files (using streaming to reduce memory)
            $this->command->info('Step 1: Loading JSON files (this may take a while for large files)...');
            
            // Check if test files exist, use them if available
            $patientFile = base_path('../seedes/patient_test.json');
            $pathologyFile = base_path('../seedes/patholgy_test.json');
            
            if (!file_exists($patientFile)) {
                $patientFile = base_path('../seedes/patient.json');
            } else {
                $this->command->info('⚠ Using TEST files (patient_test.json) - only 10 records will be processed!');
            }
            
            if (!file_exists($pathologyFile)) {
                $pathologyFile = base_path('../seedes/patholgy.json');
            } else {
                $this->command->info('⚠ Using TEST files (patholgy_test.json) - only 10 records will be processed!');
            }
            
            $this->command->info('Loading patient file...');
            $patientData = $this->loadJsonFile($patientFile);
            $this->command->info("✓ Loaded " . number_format(count($patientData)) . " patient records");
            
            $this->command->info('Loading pathology file...');
            $pathologyData = $this->loadJsonFile($pathologyFile);
            $this->command->info("✓ Loaded " . number_format(count($pathologyData)) . " pathology records");

            // Step 2: Process Patients
            $this->command->info('');
            $this->command->info('Step 2: Processing Patients...');
            $this->processPatients($patientData);

            // Step 3: Create Lab Requests
            $this->command->info('');
            $this->command->info('Step 3: Creating Lab Requests...');
            $this->createLabRequests($patientData);

            // Step 4: Process Reports
            $this->command->info('');
            $this->command->info('Step 4: Processing Reports...');
            $this->processReports($pathologyData);
            
            // Free pathology data memory (no longer needed)
            unset($pathologyData);
            gc_collect_cycles();

            // Step 5: Create Visits
            $this->command->info('');
            $this->command->info('Step 5: Creating Visits...');
            $this->createVisits($patientData);
            
            // Free memory
            unset($patientData);
            gc_collect_cycles();

            // Step 6: Generate Summary
            $this->command->info('');
            $this->command->info('========================================');
            $this->command->info('Migration Summary:');
            $this->command->info("Patients Created: {$this->stats['patients_created']}");
            $this->command->info("Patients Updated: {$this->stats['patients_updated']}");
            $this->command->info("Lab Requests Created: {$this->stats['lab_requests_created']}");
            $this->command->info("Lab Requests Found (existing): {$this->stats['lab_requests_found']}");
            $this->command->info("Reports Created: {$this->stats['reports_created']}");
            $this->command->info("Reports Skipped (already exist): {$this->stats['reports_skipped']}");
            $this->command->info("Visits Created: {$this->stats['visits_created']}");
            $this->command->info("Errors: {$this->stats['errors']}");
            $this->command->info("Skipped Records: " . count($this->skippedRecords));
            
            if (!empty($this->skippedRecords)) {
                $this->command->warn('Skipped Records:');
                foreach (array_slice($this->skippedRecords, 0, 10) as $record) {
                    $this->command->warn("  - {$record}");
                }
                if (count($this->skippedRecords) > 10) {
                    $this->command->warn("  ... and " . (count($this->skippedRecords) - 10) . " more");
                }
            }

        } catch (\Exception $e) {
            $this->command->error('Migration failed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            Log::error('Legacy Data Migration Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Load JSON file and extract data array
     * Handles both PHPMyAdmin JSON export format and simple array format (for test files)
     * Uses streaming to avoid memory exhaustion for large files
     */
    private function loadJsonFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("JSON file not found: {$filePath}");
        }

        // Check if it's a simple array format (for test files like patient_test.json)
        $firstChars = file_get_contents($filePath, false, null, 0, 200);
        if (trim($firstChars)[0] === '[' && strpos($firstChars, '"data":') === false) {
            // Simple array format - decode directly (safe for small test files)
            $content = file_get_contents($filePath);
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Filter out metadata entries
                return array_values(array_filter($decoded, function($item) {
                    return is_array($item) && (!isset($item['type']) || !in_array($item['type'], ['header', 'database', 'table']));
                }));
            }
        }

        // PHPMyAdmin format - use streaming
        $data = [];
        $inDataArray = false;
        $lineCount = 0;
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $lineCount++;
                $line = trim($line);
                
                // Show progress every 10000 lines
                if ($lineCount % 10000 === 0) {
                    $this->command->getOutput()->write("\r  Processing line " . number_format($lineCount) . "... (loaded " . number_format(count($data)) . " records)");
                }
                
                // Skip empty lines
                if (empty($line)) {
                    continue;
                }
                
                // Check if we're entering the data array
                if (strpos($line, '"data":') !== false) {
                    $inDataArray = true;
                    // Check if array starts on same line
                    if (strpos($line, '[') !== false) {
                        // Extract the array part
                        $arrayStart = strpos($line, '[');
                        $line = substr($line, $arrayStart);
                    } else {
                        continue;
                    }
                }
                
                // Skip metadata lines
                if (!$inDataArray && (
                    strpos($line, '"type":"header"') !== false || 
                    strpos($line, '"type":"database"') !== false || 
                    strpos($line, '"type":"table"') !== false
                )) {
                    continue;
                }
                
                // If we're in the data array, process the line
                if ($inDataArray) {
                    // Skip opening bracket
                    if ($line === '[') {
                        continue;
                    }
                    
                    // Skip closing bracket
                    if ($line === ']' || $line === '],') {
                        break;
                    }
                    
                    // Remove trailing comma if present
                    $line = rtrim($line, ',');
                    
                    // Decode the JSON object
                    $decoded = json_decode($line, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // Skip metadata entries
                        if (!isset($decoded['type']) || !in_array($decoded['type'], ['header', 'database', 'table'])) {
                            $data[] = $decoded;
                        }
                    }
                }
            }
            
            // Clear progress line
            $this->command->getOutput()->write("\r" . str_repeat(' ', 80) . "\r");
        } finally {
            fclose($handle);
        }

        return $data;
    }

    /**
     * Parse lab number to extract base and suffix
     */
    private function parseLabNumber(?string $labString): ?array
    {
        if (empty($labString)) {
            return null;
        }

        // Normalize: replace underscores with dashes, trim
        $labString = trim(str_replace('_', '-', $labString));
        
        if (empty($labString)) {
            return null;
        }

        $base = $labString;
        $suffix = null;

        // Check for suffix M or H at various positions
        // Pattern 1: "151M-2019" -> base: "151-2019", suffix: "m"
        if (preg_match('/^(.+?)([MH])(-\d+)$/', $labString, $matches)) {
            $base = $matches[1] . $matches[3];
            $suffix = strtolower($matches[2]);
        }
        // Pattern 2: "2026M-1212" -> base: "2026-1212", suffix: "m"
        elseif (preg_match('/^(.+?)([MH])(-\d+)$/', $labString, $matches)) {
            $base = $matches[1] . $matches[3];
            $suffix = strtolower($matches[2]);
        }
        // Pattern 3: "labM" or "labH" at end
        elseif (preg_match('/^(.+?)([MH])$/i', $labString, $matches)) {
            $base = $matches[1];
            $suffix = strtolower($matches[2]);
        }

        return [
            'base' => $base,
            'suffix' => $suffix,
            'original' => $labString,
        ];
    }

    /**
     * Get patient lookup key
     */
    private function getPatientKey(?string $name, ?string $phone): string
    {
        $name = trim($name ?? '');
        $phone = trim($phone ?? '');
        return strtolower($name . '|' . $phone);
    }

    /**
     * Find or create patient
     */
    private function findOrCreatePatient(array $patientData): ?Patient
    {
        $name = trim($patientData['name'] ?? '');
        $phone = trim($patientData['phone'] ?? '');
        
        if (empty($name)) {
            return null;
        }

        $key = $this->getPatientKey($name, $phone);

        // Check if patient already exists in map
        if (isset($this->patientMap[$key])) {
            $patient = Patient::find($this->patientMap[$key]);
            if ($patient) {
                $this->stats['patients_updated']++;
                return $patient;
            }
        }

        // Check database for existing patient
        $query = Patient::where('name', $name);
        if (!empty($phone)) {
            $query->where('phone', $phone);
        }
        $patient = $query->first();

        if ($patient) {
            $this->patientMap[$key] = $patient->id;
            $this->stats['patients_updated']++;
            return $patient;
        }

        // Create new patient
        try {
            $patient = Patient::create([
                'name' => $name,
                'phone' => $phone ?: null,
                'address' => $patientData['address'] ?? null,
                'age' => $this->parseInteger($patientData['age'] ?? null),
                'gender' => $this->parseGender($patientData['gender'] ?? null),
                // Legacy fields
                'entry' => $patientData['entry'] ?? null,
                'deli' => $patientData['deli'] ?? null,
                'time' => $patientData['time'] ?? null,
                'tsample' => $patientData['tsample'] ?? null,
                'nsample' => $patientData['nsample'] ?? null,
                'isample' => $patientData['isample'] ?? null,
                'paid' => $this->parseInteger($patientData['paid'] ?? null),
                'pleft' => $this->parseInteger($patientData['pleft'] ?? null),
                'total' => $this->parseInteger($patientData['total'] ?? null),
                'had' => $patientData['had'] ?? null,
                'sender' => $patientData['sender'] ?? null,
                // NOTE: 'lab' field is NOT set here to avoid unique constraint violation
                // Lab numbers are stored in lab_requests table instead
                'entryday' => $patientData['entryday'] ?? null,
                'deliday' => $patientData['deliday'] ?? null,
                'type' => $patientData['type'] ?? null,
                // Modern fields
                'doctor_id' => $patientData['sender'] ?? null,
                'sample_type' => $patientData['tsample'] ?? null,
                'sample_size' => $patientData['isample'] ?? null,
                'number_of_samples' => $this->parseInteger($patientData['nsample'] ?? null),
                'total_amount' => $this->parseDecimal($patientData['total'] ?? null),
                'amount_paid' => $this->parseDecimal($patientData['paid'] ?? null),
                'attendance_date' => $this->parseDate($patientData['entry'] ?? null),
                'delivery_date' => $this->parseDate($patientData['deli'] ?? null),
            ]);

            $this->patientMap[$key] = $patient->id;
            $this->stats['patients_created']++;
            
            return $patient;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Failed to create patient', [
                'data' => $patientData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Process patients from patient.json
     */
    private function processPatients(array $patientData): void
    {
        $progressBar = $this->command->getOutput()->createProgressBar(count($patientData));
        $progressBar->start();

        foreach ($patientData as $index => $patient) {
            try {
                // Skip header/metadata entries
                if (isset($patient['type']) && in_array($patient['type'], ['header', 'database', 'table'])) {
                    continue;
                }

                $this->findOrCreatePatient($patient);
                
                if (($index + 1) % 100 === 0) {
                    $progressBar->advance(100);
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->skippedRecords[] = "Patient record: " . ($patient['name'] ?? 'Unknown') . " - " . $e->getMessage();
            }
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    /**
     * Create lab requests from patient.json
     */
    private function createLabRequests(array $patientData): void
    {
        $progressBar = $this->command->getOutput()->createProgressBar(count($patientData));
        $progressBar->start();

        foreach ($patientData as $index => $patient) {
            try {
                // Skip header/metadata entries
                if (isset($patient['type']) && in_array($patient['type'], ['header', 'database', 'table'])) {
                    continue;
                }

                $labString = $patient['lab'] ?? null;
                if (empty($labString)) {
                    continue;
                }

                $parsed = $this->parseLabNumber($labString);
                if (!$parsed) {
                    continue;
                }

                $base = $parsed['base'];
                $suffix = $parsed['suffix'];
                $key = $base . '|' . ($suffix ?? 'null');

                // Check if lab request already exists
                if (isset($this->labRequestMap[$key])) {
                    if (($index + 1) % 100 === 0) {
                        $progressBar->advance(100);
                    }
                    continue;
                }

                // Find patient
                $patientModel = $this->findOrCreatePatient($patient);
                if (!$patientModel) {
                    continue;
                }

                // Check database for existing lab request
                $query = LabRequest::where('lab_no', $base);
                if ($suffix) {
                    $query->where('suffix', $suffix);
                } else {
                    $query->whereNull('suffix');
                }
                $labRequest = $query->first();

                if (!$labRequest) {
                    try {
                        // Determine status
                        $status = 'pending';
                        if (!empty($patient['deli'])) {
                            $status = 'completed';
                        } elseif (!empty($patient['entry'])) {
                            $status = 'received';
                        }

                        // Create lab request (use firstOrCreate to handle race conditions)
                        $labRequest = LabRequest::firstOrCreate(
                            [
                                'lab_no' => $base,
                                'suffix' => $suffix,
                            ],
                            [
                                'patient_id' => $patientModel->id,
                                'status' => $status,
                                'metadata' => [
                                    'legacy_lab' => $parsed['original'],
                                    'entry' => $patient['entry'] ?? null,
                                    'deli' => $patient['deli'] ?? null,
                                    'tsample' => $patient['tsample'] ?? null,
                                    'nsample' => $patient['nsample'] ?? null,
                                    'isample' => $patient['isample'] ?? null,
                                    'paid' => $patient['paid'] ?? null,
                                    'total' => $patient['total'] ?? null,
                                    'had' => $patient['had'] ?? null,
                                ],
                            ]
                        );

                        // Update patient_id if it was null
                        if (!$labRequest->patient_id && $patientModel->id) {
                            $labRequest->update(['patient_id' => $patientModel->id]);
                        }

                        if ($labRequest->wasRecentlyCreated) {
                            $this->stats['lab_requests_created']++;
                        }
                    } catch (\Exception $e) {
                        $this->stats['errors']++;
                        $this->skippedRecords[] = "Lab request creation failed: " . ($patient['lab'] ?? 'Unknown') . " - " . $e->getMessage();
                        continue;
                    }
                }

                $this->labRequestMap[$key] = $labRequest->id;

                if (($index + 1) % 100 === 0) {
                    $progressBar->advance(100);
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->skippedRecords[] = "Lab request: " . ($patient['lab'] ?? 'Unknown') . " - " . $e->getMessage();
            }
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    /**
     * Process reports from patholgy.json
     */
    private function processReports(array $pathologyData): void
    {
        $progressBar = $this->command->getOutput()->createProgressBar(count($pathologyData));
        $progressBar->start();

        foreach ($pathologyData as $index => $pathology) {
            try {
                // Skip header/metadata entries
                if (isset($pathology['type']) && in_array($pathology['type'], ['header', 'database', 'table'])) {
                    continue;
                }

                $labString = $pathology['lab'] ?? null;
                $parsed = $labString ? $this->parseLabNumber($labString) : null;

                // Find or create lab request
                $labRequest = null;
                if ($parsed) {
                    $base = $parsed['base'];
                    $suffix = $parsed['suffix'];
                    $key = $base . '|' . ($suffix ?? 'null');

                    if (isset($this->labRequestMap[$key])) {
                        $labRequest = LabRequest::find($this->labRequestMap[$key]);
                    } else {
                        // Check database
                        $query = LabRequest::where('lab_no', $base);
                        if ($suffix) {
                            $query->where('suffix', $suffix);
                        } else {
                            $query->whereNull('suffix');
                        }
                        $labRequest = $query->first();
                        if ($labRequest) {
                            // Add to map for future lookups
                            $this->labRequestMap[$key] = $labRequest->id;
                            $this->stats['lab_requests_found']++;
                        }
                    }
                }

                // If lab request not found, try to create from pathology data
                if (!$labRequest) {
                    $patientName = trim($pathology['nos'] ?? '');
                    if (!empty($patientName) && $parsed) {
                        // Find or create patient
                        $patient = Patient::where('name', $patientName)->first();
                        if (!$patient) {
                            $patient = Patient::create([
                                'name' => $patientName,
                                'gender' => $this->parseGender($pathology['sex'] ?? null),
                                'age' => $this->parseAgeFromString($pathology['age'] ?? null),
                            ]);
                            $this->stats['patients_created']++;
                        }

                        // Create lab request (use firstOrCreate to handle duplicates)
                        $labRequest = LabRequest::firstOrCreate(
                            [
                                'lab_no' => $parsed['base'],
                                'suffix' => $parsed['suffix'],
                            ],
                            [
                                'patient_id' => $patient->id,
                                'status' => 'completed',
                                'metadata' => [
                                    'legacy_lab' => $parsed['original'],
                                    'created_from_pathology' => true,
                                ],
                            ]
                        );

                        // Update patient_id if it was null
                        if (!$labRequest->patient_id && $patient->id) {
                            $labRequest->update(['patient_id' => $patient->id]);
                        }

                        $key = $parsed['base'] . '|' . ($parsed['suffix'] ?? 'null');
                        $this->labRequestMap[$key] = $labRequest->id;
                        
                        if ($labRequest->wasRecentlyCreated) {
                            $this->stats['lab_requests_created']++;
                        }
                    }
                }

                if (!$labRequest) {
                    $this->skippedRecords[] = "Report (lab: {$labString}) - No lab request found";
                    continue;
                }

                // Build report content - ensure we have at least some data
                $hasData = false;
                $content = [
                    'clinical_data' => $pathology['clinical'] ?? null,
                    'nature_of_specimen' => $pathology['nature'] ?? null,
                    'gross_pathology' => $pathology['gross'] ?? null,
                    'microscopic_examination' => $pathology['micro'] ?? null,
                    'conclusion' => $pathology['conc'] ?? null,
                    'recommendations' => $pathology['reco'] ?? null,
                    'referred_by' => $pathology['reff'] ?? null,
                    'type_of_analysis' => $pathology['type'] ?? null,
                    'report_date' => $pathology['date'] ?? null,
                    'receiving_date' => $pathology['recieving'] ?? null,
                    'discharge_date' => $pathology['discharge'] ?? null,
                ];
                
                // Check if we have any actual data (not just nulls/empty strings)
                foreach (['clinical_data', 'nature_of_specimen', 'gross_pathology', 'microscopic_examination', 'conclusion'] as $field) {
                    if (!empty($content[$field]) && $content[$field] !== '.' && trim($content[$field]) !== '') {
                        $hasData = true;
                        break;
                    }
                }
                
                // Skip if no data (will be fixed by premium_fix_reports.php later)
                if (!$hasData) {
                    $this->stats['reports_skipped']++;
                    if (($index + 1) % 100 === 0) {
                        $progressBar->advance(100);
                    }
                    continue;
                }
                
                // Truncate content if too long (TEXT field max 65535 bytes)
                $jsonContent = json_encode($content);
                if (strlen($jsonContent) > 65535) {
                    // Truncate each field proportionally
                    $excess = strlen($jsonContent) - 65535;
                    $fieldsToTruncate = ['clinical_data', 'nature_of_specimen', 'gross_pathology', 
                                        'microscopic_examination', 'conclusion', 'recommendations'];
                    $excessPerField = ceil($excess / count($fieldsToTruncate));
                    
                    foreach ($fieldsToTruncate as $field) {
                        if (isset($content[$field]) && is_string($content[$field]) && strlen($content[$field]) > $excessPerField) {
                            $content[$field] = substr($content[$field], 0, strlen($content[$field]) - $excessPerField - 10) . '...';
                        }
                    }
                }

                // Determine status
                $status = 'pending';
                if (isset($pathology['confirm']) && $pathology['confirm'] == '1') {
                    $status = 'completed';
                }

                // Check if report already exists
                $existingReport = Report::where('lab_request_id', $labRequest->id)->first();
                if ($existingReport) {
                    $this->stats['reports_skipped']++;
                    if (($index + 1) % 100 === 0) {
                        $progressBar->advance(100);
                    }
                    continue;
                }

                // Create report (disable model events to prevent EnhancedReport creation during seeding)
                try {
                    Report::withoutEvents(function() use ($labRequest, $parsed, $labString, $content, $status, $pathology) {
                        Report::create([
                            'lab_request_id' => $labRequest->id,
                            'title' => 'Pathology Report - ' . ($parsed['original'] ?? $labString ?? 'N/A'),
                            'content' => json_encode($content),
                            'status' => $status,
                            'generated_at' => $this->parseDate($pathology['date'] ?? $pathology['recieving'] ?? null),
                        ]);
                    });
                    $this->stats['reports_created']++;
                } catch (\Exception $reportError) {
                    $this->stats['errors']++;
                    $this->skippedRecords[] = "Report creation failed (lab: {$labString}, lab_request_id: {$labRequest->id}) - " . $reportError->getMessage();
                    continue;
                }

                if (($index + 1) % 100 === 0) {
                    $progressBar->advance(100);
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->skippedRecords[] = "Report (lab: " . ($pathology['lab'] ?? 'Unknown') . ") - " . $e->getMessage();
            }
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    /**
     * Create visits from patient.json
     */
    private function createVisits(array $patientData): void
    {
        $progressBar = $this->command->getOutput()->createProgressBar(count($patientData));
        $progressBar->start();

        $skippedNoEntryDate = 0;
        $skippedNoPatient = 0;
        $skippedExists = 0;
        $created = 0;
        $errors = 0;

        foreach ($patientData as $index => $patient) {
            try {
                // Skip header/metadata entries
                if (isset($patient['type']) && in_array($patient['type'], ['header', 'database', 'table'])) {
                    if (($index + 1) % 100 === 0) {
                        $progressBar->advance(100);
                    }
                    continue;
                }

                // Skip if no entry date
                $entryDate = $this->parseDate($patient['entry'] ?? null);
                if (!$entryDate) {
                    $skippedNoEntryDate++;
                    if (($index + 1) % 100 === 0) {
                        $progressBar->advance(100);
                    }
                    continue;
                }

                // Find patient
                $patientModel = $this->findOrCreatePatient($patient);
                if (!$patientModel) {
                    $skippedNoPatient++;
                    if (($index + 1) % 100 === 0) {
                        $progressBar->advance(100);
                    }
                    continue;
                }

                // Find lab request
                $labString = $patient['lab'] ?? null;
                $labRequest = null;
                
                if ($labString) {
                    $parsed = $this->parseLabNumber($labString);
                    if ($parsed) {
                        $base = $parsed['base'];
                        $suffix = $parsed['suffix'];
                        $key = $base . '|' . ($suffix ?? 'null');

                        if (isset($this->labRequestMap[$key])) {
                            $labRequest = LabRequest::find($this->labRequestMap[$key]);
                        } else {
                            $query = LabRequest::where('lab_no', $base);
                            if ($suffix) {
                                $query->where('suffix', $suffix);
                            } else {
                                $query->whereNull('suffix');
                            }
                            $labRequest = $query->first();
                        }
                    }
                }

                // Check if visit already exists (more lenient check - just patient + date)
                $existingVisit = Visit::where('patient_id', $patientModel->id)
                    ->where('visit_date', $entryDate->format('Y-m-d'))
                    ->first();

                if ($existingVisit) {
                    // Update lab_request_id if it's missing and we have one
                    if (!$existingVisit->lab_request_id && $labRequest) {
                        $existingVisit->lab_request_id = $labRequest->id;
                        $existingVisit->save();
                    }
                    $skippedExists++;
                    if (($index + 1) % 100 === 0) {
                        $progressBar->advance(100);
                    }
                    continue;
                }

                // Determine status
                $totalAmount = $this->parseDecimal($patient['total'] ?? null) ?? 0;
                $paidAmount = $this->parseDecimal($patient['paid'] ?? null) ?? 0;
                
                $status = 'pending';
                if ($paidAmount >= $totalAmount && $totalAmount > 0) {
                    $status = 'paid';
                } elseif ($paidAmount > 0) {
                    $status = 'partial';
                }

                // Generate unique visit number based on visit_date (not current date)
                $visitNumber = $this->generateVisitNumberForDate($entryDate);

                // Check if visit number already exists (handle duplicates)
                $existingVisitNumber = Visit::where('visit_number', $visitNumber)->first();
                if ($existingVisitNumber) {
                    // Append a suffix to make it unique
                    $counter = 1;
                    do {
                        $visitNumber = $this->generateVisitNumberForDate($entryDate) . '-' . $counter;
                        $counter++;
                    } while (Visit::where('visit_number', $visitNumber)->exists() && $counter < 1000);
                }

                // Parse visit time from patient data, default to 09:00:00 if not available
                $visitTime = '09:00:00';
                if (!empty($patient['time'])) {
                    $timeStr = trim($patient['time']);
                    // Try to parse time in various formats
                    if (preg_match('/(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
                        $visitTime = sprintf('%02d:%02d:00', (int)$matches[1], (int)$matches[2]);
                    }
                }

                // Create visit with retry logic for connection errors
                $maxRetries = 3;
                $retryCount = 0;
                $visitCreated = false;
                
                while ($retryCount < $maxRetries && !$visitCreated) {
                    try {
                        // Reconnect to database if connection was lost
                        if ($retryCount > 0) {
                            \DB::reconnect();
                            sleep(1); // Wait 1 second before retry
                        }
                        
                        $visit = Visit::create([
                            'patient_id' => $patientModel->id,
                            'lab_request_id' => $labRequest?->id,
                            'visit_number' => $visitNumber,
                            'visit_date' => $entryDate->format('Y-m-d'),
                            'visit_time' => $visitTime,
                            'total_amount' => $totalAmount,
                            'final_amount' => $totalAmount,
                            'discount_amount' => 0,
                            'status' => $status,
                            'expected_delivery_date' => $this->parseDate($patient['deli'] ?? null)?->format('Y-m-d'),
                            'referred_doctor' => $patient['sender'] ?? null,
                        ]);

                        $this->stats['visits_created']++;
                        $created++;
                        $visitCreated = true;
                    } catch (\Exception $e) {
                        $retryCount++;
                        $errorMessage = $e->getMessage();
                        
                        // Check if it's a connection error
                        $isConnectionError = strpos($errorMessage, 'connection') !== false || 
                                           strpos($errorMessage, 'refused') !== false ||
                                           strpos($errorMessage, 'timeout') !== false ||
                                           strpos($errorMessage, '2002') !== false ||
                                           strpos($errorMessage, '2006') !== false;
                        
                        if ($isConnectionError && $retryCount < $maxRetries) {
                            // Will retry
                            continue;
                        } else {
                            // Final failure or non-connection error
                            $this->stats['errors']++;
                            $errors++;
                            if (count($this->skippedRecords) < 100) { // Limit error messages
                                $this->skippedRecords[] = "Visit creation failed: " . ($patient['name'] ?? 'Unknown') . " (Lab: " . ($labString ?? 'N/A') . ") - " . $errorMessage;
                            }
                            break;
                        }
                    }
                }

                // Periodic database reconnection to prevent timeout (every 1000 records)
                if (($index + 1) % 1000 === 0) {
                    try {
                        \DB::reconnect();
                    } catch (\Exception $e) {
                        // Ignore reconnection errors, will retry on next operation
                    }
                }

                if (($index + 1) % 100 === 0) {
                    $progressBar->advance(100);
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $errors++;
                if (count($this->skippedRecords) < 100) {
                    $this->skippedRecords[] = "Visit: " . ($patient['name'] ?? 'Unknown') . " - " . $e->getMessage();
                }
            }
        }

        $progressBar->finish();
        $this->command->newLine();
        
        // Print visit creation summary
        $this->command->info("Visit Creation Summary:");
        $this->command->info("  - Created: {$created}");
        $this->command->info("  - Skipped (no entry date): {$skippedNoEntryDate}");
        $this->command->info("  - Skipped (no patient): {$skippedNoPatient}");
        $this->command->info("  - Skipped (already exists): {$skippedExists}");
        $this->command->info("  - Errors: {$errors}");
    }

    /**
     * Helper: Parse integer from string
     */
    private function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    /**
     * Helper: Parse decimal from string
     */
    private function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $float = (float) $value;
        return $float >= 0 ? $float : null;
    }

    /**
     * Helper: Parse date from string
     */
    private function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper: Parse gender
     */
    private function parseGender($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = strtolower(trim($value));
        if (in_array($value, ['male', 'female', 'other'])) {
            return $value;
        }

        return null;
    }

    /**
     * Helper: Parse age from string (handles "23y", "23 y", "23")
     */
    private function parseAgeFromString($value): ?int
    {
        if (empty($value)) {
            return null;
        }

        // Remove "y" suffix and spaces
        $value = trim(str_replace(['y', 'Y'], '', $value));
        return $this->parseInteger($value);
    }

    /**
     * Generate unique visit number based on visit date
     * Format: VIS{YYYYMMDD}{NNNN}
     */
    private function generateVisitNumberForDate(Carbon $visitDate): string
    {
        $prefix = 'VIS';
        $datePart = $visitDate->format('Ymd');
        
        // Find the last visit number for this date
        $lastVisit = Visit::where('visit_number', 'like', $prefix . $datePart . '%')
                         ->orderBy('visit_number', 'desc')
                         ->first();
        
        if ($lastVisit) {
            // Extract the sequence number (last 4 digits)
            $lastNumber = intval(substr($lastVisit->visit_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $datePart . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Remove unique constraint on lab_requests.patient_id
     * This allows patients to have multiple lab requests (base and suffix variations)
     * 
     * Note: The unique index is used by a foreign key, so we need to:
     * 1. Drop the foreign key constraint
     * 2. Drop the unique index
     * 3. Re-add the foreign key constraint (without unique requirement)
     */
    private function removePatientIdUniqueConstraint(): void
    {
        try {
            // Check if unique constraint exists
            $constraintExists = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = 'lab_requests'
                AND CONSTRAINT_NAME = 'lab_requests_patient_id_unique'
            ");

            if (isset($constraintExists[0]) && $constraintExists[0]->count > 0) {
                $this->command->info('Removing unique constraint on lab_requests.patient_id...');
                
                // Find the foreign key constraint name
                $fkInfo = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'lab_requests'
                    AND COLUMN_NAME = 'patient_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                $fkName = null;
                if (!empty($fkInfo) && isset($fkInfo[0]->CONSTRAINT_NAME)) {
                    $fkName = $fkInfo[0]->CONSTRAINT_NAME;
                } else {
                    // Try Laravel's default naming convention
                    $fkName = 'lab_requests_patient_id_foreign';
                }

                // Step 1: Drop foreign key if it exists
                if ($fkName) {
                    try {
                        DB::statement("ALTER TABLE lab_requests DROP FOREIGN KEY `{$fkName}`");
                        $this->command->info("  ✓ Dropped foreign key: {$fkName}");
                    } catch (\Exception $e) {
                        // Foreign key might not exist or have different name, continue
                        $this->command->warn("  Note: Could not drop foreign key {$fkName}: " . $e->getMessage());
                    }
                }

                // Step 2: Drop unique index
                DB::statement('ALTER TABLE lab_requests DROP INDEX lab_requests_patient_id_unique');
                $this->command->info('  ✓ Dropped unique index: lab_requests_patient_id_unique');

                // Step 3: Re-add foreign key constraint (without unique requirement)
                if ($fkName) {
                    try {
                        DB::statement("
                            ALTER TABLE lab_requests 
                            ADD CONSTRAINT `{$fkName}` 
                            FOREIGN KEY (`patient_id`) 
                            REFERENCES `patient` (`id`) 
                            ON DELETE SET NULL
                        ");
                        $this->command->info("  ✓ Re-added foreign key: {$fkName}");
                    } catch (\Exception $e) {
                        $this->command->warn("  Note: Could not re-add foreign key: " . $e->getMessage());
                    }
                }

                // Step 4: Add regular (non-unique) index for performance
                try {
                    DB::statement('ALTER TABLE lab_requests ADD INDEX lab_requests_patient_id_index (patient_id)');
                    $this->command->info('  ✓ Added non-unique index on patient_id for performance');
                } catch (\Exception $e) {
                    // Index might already exist, that's okay
                }

                $this->command->info('✓ Unique constraint removed (patients can now have multiple lab requests)');
            } else {
                $this->command->info('✓ Unique constraint does not exist (already removed or never existed)');
            }
        } catch (\Exception $e) {
            // Constraint might not exist or already removed, continue
            $this->command->warn('Note: Could not remove patient_id unique constraint: ' . $e->getMessage());
            $this->command->warn('If you encounter duplicate entry errors, manually run:');
            $this->command->warn('  ALTER TABLE lab_requests DROP FOREIGN KEY lab_requests_patient_id_foreign;');
            $this->command->warn('  ALTER TABLE lab_requests DROP INDEX lab_requests_patient_id_unique;');
            $this->command->warn('  ALTER TABLE lab_requests ADD CONSTRAINT lab_requests_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES patient(id) ON DELETE SET NULL;');
        }
    }
}

