<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\LabRequest;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

echo "=== Fixing Missing Reports ===\n\n";

// Load pathology data using the same method as the seeder
function loadJsonFile($filePath) {
    if (!file_exists($filePath)) {
        throw new \Exception("File not found: {$filePath}");
    }
    
    // Check if it's a simple array format (for test files)
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
                echo "  Processing line " . number_format($lineCount) . "... (loaded " . number_format(count($data)) . " records)\n";
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
                if (substr($line, -1) === ',') {
                    $line = substr($line, 0, -1);
                }
                
                // Try to decode this line as a JSON object
                $decoded = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Filter out metadata entries
                    if (!isset($decoded['type']) || !in_array($decoded['type'], ['header', 'database', 'table'])) {
                        $data[] = $decoded;
                    }
                }
            }
        }
    } finally {
        fclose($handle);
    }
    
    if (empty($data)) {
        throw new \Exception("No data found in file or invalid format");
    }
    
    return $data;
}

// Use full file (skip test files)
$pathologyFile = base_path('../seedes/patholgy.json');
if (!file_exists($pathologyFile)) {
    // Fallback to test file if full file doesn't exist
    $pathologyFile = base_path('../seedes/patholgy_test.json');
    if (file_exists($pathologyFile)) {
        echo "⚠ Using TEST file (patholgy_test.json) - only 10 records will be processed!\n";
    }
}

if (!file_exists($pathologyFile)) {
    echo "ERROR: Pathology file not found. Checked:\n";
    echo "  - " . base_path('../seedes/patholgy_test.json') . "\n";
    echo "  - " . base_path('../seedes/patholgy.json') . "\n";
    exit(1);
}

echo "Loading pathology data from: {$pathologyFile}\n";
$pathologyData = loadJsonFile($pathologyFile);
echo "Loaded " . count($pathologyData) . " pathology records\n\n";

$created = 0;
$skipped = 0;
$errors = 0;
$labRequestMap = [];

// Function to parse lab number
function parseLabNumber($labString) {
    if (empty($labString)) {
        return null;
    }
    
    $labString = trim($labString);
    
    // Handle formats like "2026-1212", "2026M-1212", "2026H-1212"
    if (preg_match('/^(\d{4})([MH]?)-(\d+)$/i', $labString, $matches)) {
        return [
            'base' => $matches[3], // The number part
            'suffix' => !empty($matches[2]) ? strtoupper($matches[2]) : null,
            'original' => $labString,
        ];
    }
    
    // Handle formats like "1212", "1212M", "1212H"
    if (preg_match('/^(\d+)([MH]?)$/i', $labString, $matches)) {
        return [
            'base' => $matches[1],
            'suffix' => !empty($matches[2]) ? strtoupper($matches[2]) : null,
            'original' => $labString,
        ];
    }
    
    return null;
}

echo "Processing reports...\n";
$progress = 0;
foreach ($pathologyData as $index => $pathology) {
    $progress++;
    if ($progress % 1000 === 0) {
        echo "Processed {$progress} records... (Created: {$created}, Skipped: {$skipped}, Errors: {$errors})\n";
    }
    
    try {
        // Skip header/metadata entries
        if (isset($pathology['type']) && in_array($pathology['type'], ['header', 'database', 'table'])) {
            continue;
        }
        
        $labString = $pathology['lab'] ?? null;
        if (empty($labString)) {
            continue;
        }
        
        $parsed = parseLabNumber($labString);
        if (!$parsed) {
            continue;
        }
        
        $base = $parsed['base'];
        $suffix = $parsed['suffix'];
        $key = $base . '|' . ($suffix ?? 'null');
        
        // Find lab request with retry logic
        $labRequest = null;
        if (isset($labRequestMap[$key])) {
            $retries = 0;
            $maxRetries = 3;
            while ($retries < $maxRetries) {
                try {
                    $labRequest = LabRequest::find($labRequestMap[$key]);
                    break;
                } catch (\Exception $e) {
                    $retries++;
                    if ($retries >= $maxRetries) {
                        throw $e;
                    }
                    DB::reconnect();
                    sleep(1);
                }
            }
        } else {
            $retries = 0;
            $maxRetries = 3;
            while ($retries < $maxRetries) {
                try {
                    $query = LabRequest::where('lab_no', $base);
                    if ($suffix) {
                        $query->where('suffix', $suffix);
                    } else {
                        $query->whereNull('suffix');
                    }
                    $labRequest = $query->first();
                    break;
                } catch (\Exception $e) {
                    $retries++;
                    if ($retries >= $maxRetries) {
                        throw $e;
                    }
                    DB::reconnect();
                    sleep(1);
                }
            }
            
            if ($labRequest) {
                $labRequestMap[$key] = $labRequest->id;
            }
        }
        
        if (!$labRequest) {
            $skipped++;
            continue;
        }
        
        // Check if report already exists with retry logic
        $existingReport = null;
        $retries = 0;
        $maxRetries = 3;
        while ($retries < $maxRetries) {
            try {
                $existingReport = Report::where('lab_request_id', $labRequest->id)->first();
                break;
            } catch (\Exception $e) {
                $retries++;
                if ($retries >= $maxRetries) {
                    throw $e;
                }
                DB::reconnect();
                sleep(1);
            }
        }
        
        if ($existingReport) {
            $skipped++;
            continue;
        }
        
        // Build report content
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
        
        // Determine status
        $status = 'pending';
        if (isset($pathology['confirm']) && $pathology['confirm'] == '1') {
            $status = 'completed';
        }
        
        // Parse date
        $generatedAt = null;
        if (!empty($pathology['date'])) {
            try {
                $generatedAt = \Carbon\Carbon::createFromFormat('d/m/Y', $pathology['date']);
            } catch (\Exception $e) {
                try {
                    $generatedAt = \Carbon\Carbon::parse($pathology['date']);
                } catch (\Exception $e2) {
                    // Use null if parsing fails
                }
            }
        }
        
        // Create report (disable model events to prevent EnhancedReport creation) with retry logic
        $retries = 0;
        $maxRetries = 3;
        $reportCreated = false;
        while ($retries < $maxRetries && !$reportCreated) {
            try {
                Report::withoutEvents(function() use ($labRequest, $parsed, $labString, $content, $status, $generatedAt) {
                    Report::create([
                        'lab_request_id' => $labRequest->id,
                        'title' => 'Pathology Report - ' . ($parsed['original'] ?? $labString ?? 'N/A'),
                        'content' => json_encode($content),
                        'status' => $status,
                        'generated_at' => $generatedAt,
                    ]);
                });
                $created++;
                $reportCreated = true;
            } catch (\Exception $e) {
                $retries++;
                if ($retries >= $maxRetries) {
                    throw $e;
                }
                DB::reconnect();
                sleep(1);
            }
        }
        
    } catch (\Exception $e) {
        $errors++;
        if ($errors <= 10) {
            echo "ERROR processing record {$index}: " . $e->getMessage() . "\n";
        }
    }
    
    // Reconnect to database every 1000 records to prevent timeouts (outside try-catch)
    if ($progress % 1000 === 0) {
        try {
            DB::reconnect();
        } catch (\Exception $e) {
            // Ignore reconnect errors, will retry on next query
        }
    }
}

echo "\n=== Summary ===\n";
echo "Reports Created: {$created}\n";
echo "Reports Skipped (already exist): {$skipped}\n";
echo "Errors: {$errors}\n";
echo "\nDone!\n";

