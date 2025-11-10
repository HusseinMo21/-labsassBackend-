<?php

/**
 * PREMIUM FIX SCRIPT FOR REPORTS
 * 
 * This script ensures all reports have proper content and all relationships work correctly.
 * Designed for production use - handles all edge cases and ensures data integrity.
 * 
 * Features:
 * - Fixes empty/null report content
 * - Handles oversized content (truncates to fit database)
 * - Multiple lab number matching strategies
 * - Validates all relationships (patients, lab_requests, reports, visits)
 * - Comprehensive error handling and logging
 * - Memory-efficient batch processing
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Report;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

// Configuration
$MAX_CONTENT_LENGTH = 65535; // TEXT field max size (MySQL)
$BATCH_SIZE = 500;
$LOG_EVERY = 100;

echo "========================================\n";
echo "PREMIUM FIX SCRIPT FOR REPORTS\n";
echo "========================================\n\n";

// Statistics
$stats = [
    'reports_checked' => 0,
    'reports_fixed' => 0,
    'reports_not_found' => 0,
    'reports_truncated' => 0,
    'errors' => 0,
    'relationships_fixed' => 0,
];

/**
 * Load pathology data from JSON file
 */
function loadPathologyData($filePath) {
    if (!file_exists($filePath)) {
        throw new \Exception("Pathology file not found: {$filePath}");
    }
    
    $firstChars = file_get_contents($filePath, false, null, 0, 200);
    if (trim($firstChars)[0] === '[' && strpos($firstChars, '"data":') === false) {
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded, function($item) {
                return is_array($item) && (!isset($item['type']) || !in_array($item['type'], ['header', 'database', 'table']));
            }));
        }
    }
    
    $data = [];
    $inDataArray = false;
    $handle = fopen($filePath, 'r');
    
    if (!$handle) {
        throw new \Exception("Could not open file: {$filePath}");
    }
    
    try {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, '"data":') !== false) {
                $inDataArray = true;
                if (strpos($line, '[') !== false) {
                    $arrayStart = strpos($line, '[');
                    $line = substr($line, $arrayStart);
                } else {
                    continue;
                }
            }
            
            if (!$inDataArray) continue;
            
            if ($line === '[') continue;
            if ($line === ']' || $line === '],') break;
            
            if (substr($line, -1) === ',') {
                $line = substr($line, 0, -1);
            }
            
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (!isset($decoded['type']) || !in_array($decoded['type'], ['header', 'database', 'table'])) {
                    $data[] = $decoded;
                }
            }
        }
    } finally {
        fclose($handle);
    }
    
    return $data;
}

/**
 * Normalize lab number (remove trailing dashes, multiple dashes)
 */
function normalizeLabNumber($labString) {
    if (empty($labString)) return '';
    $labString = trim(str_replace('_', '-', $labString));
    $labString = preg_replace('/-+$/', '', $labString); // Remove trailing dashes
    $labString = preg_replace('/--+/', '-', $labString); // Replace multiple dashes with single
    return $labString;
}

/**
 * Parse lab number to extract base and suffix
 */
function parseLabNumber($labString) {
    if (empty($labString)) return null;
    $labString = normalizeLabNumber($labString);
    if (empty($labString)) return null;
    
    $base = $labString;
    $suffix = null;
    
    if (preg_match('/^(.+?)([MH])(-\d+)$/', $labString, $matches)) {
        $base = $matches[1] . $matches[3];
        $suffix = strtolower($matches[2]);
    } elseif (preg_match('/^(.+?)([MH])$/i', $labString, $matches)) {
        $base = $matches[1];
        $suffix = strtolower($matches[2]);
    }
    
    return ['base' => $base, 'suffix' => $suffix];
}

/**
 * Truncate content to fit database column
 */
function truncateContent($content, $maxLength) {
    $json = json_encode($content);
    if (strlen($json) <= $maxLength) {
        return $content;
    }
    
    // Calculate how much we need to truncate
    $excess = strlen($json) - $maxLength;
    $excessPerField = ceil($excess / 6); // Rough estimate for 6 main fields
    
    // Truncate each text field
    $fields = ['clinical_data', 'nature_of_specimen', 'gross_pathology', 
               'microscopic_examination', 'conclusion', 'recommendations'];
    
    foreach ($fields as $field) {
        if (isset($content[$field]) && is_string($content[$field])) {
            $currentLength = strlen($content[$field]);
            if ($currentLength > $excessPerField) {
                $content[$field] = substr($content[$field], 0, $currentLength - $excessPerField - 10) . '...';
            }
        }
    }
    
    // Verify it fits now
    $json = json_encode($content);
    if (strlen($json) > $maxLength) {
        // More aggressive truncation
        $ratio = $maxLength / strlen($json);
        foreach ($fields as $field) {
            if (isset($content[$field]) && is_string($content[$field])) {
                $content[$field] = substr($content[$field], 0, floor(strlen($content[$field]) * $ratio));
            }
        }
    }
    
    return $content;
}

/**
 * Find pathology data for a lab number
 */
function findPathologyData($labNo, $suffix, $pathologyMap) {
    $labNo = normalizeLabNumber($labNo);
    
    // Strategy 1: Exact match with suffix
    $key = $labNo . '|' . ($suffix ?? 'null');
    if (isset($pathologyMap[$key])) {
        return $pathologyMap[$key];
    }
    
    // Strategy 2: Without suffix
    $keyNoSuffix = $labNo . '|null';
    if (isset($pathologyMap[$keyNoSuffix])) {
        return $pathologyMap[$keyNoSuffix];
    }
    
    // Strategy 3: Try parsed version
    $parsed = parseLabNumber($labNo);
    if ($parsed) {
        $parsedKey = $parsed['base'] . '|' . ($parsed['suffix'] ?? 'null');
        if (isset($pathologyMap[$parsedKey])) {
            return $pathologyMap[$parsedKey];
        }
    }
    
    // Strategy 4: Fuzzy match (remove dashes)
    $labNoNoDashes = str_replace('-', '', $labNo);
    foreach ($pathologyMap as $pathData) {
        $pathLab = normalizeLabNumber($pathData['lab'] ?? '');
        $pathLabNoDashes = str_replace('-', '', $pathLab);
        if ($pathLabNoDashes === $labNoNoDashes) {
            return $pathData;
        }
    }
    
    return null;
}

/**
 * Check if report content has actual data
 */
function hasReportData($content) {
    if (empty($content)) return false;
    
    $parsed = json_decode($content, true);
    if (!$parsed || !is_array($parsed)) return false;
    
    $fields = ['clinical_data', 'nature_of_specimen', 'gross_pathology', 'gross_examination', 
               'microscopic_examination', 'microscopic_description', 'conclusion', 'diagnosis'];
    
    foreach ($fields as $field) {
        if (isset($parsed[$field]) && 
            $parsed[$field] !== null && 
            $parsed[$field] !== '' && 
            $parsed[$field] !== '.') {
            return true;
        }
    }
    
    return false;
}

// Step 1: Load pathology data
echo "Step 1: Loading pathology data...\n";
$pathologyFile = base_path('seedes/patholgy.json');
if (!file_exists($pathologyFile)) {
    echo "ERROR: Pathology file not found at: {$pathologyFile}\n";
    exit(1);
}

try {
    $pathologyData = loadPathologyData($pathologyFile);
    echo "Loaded " . count($pathologyData) . " pathology records\n\n";
} catch (\Exception $e) {
    echo "ERROR loading pathology data: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Create pathology lookup map
echo "Step 2: Creating pathology lookup map...\n";
$pathologyMap = [];
foreach ($pathologyData as $pathology) {
    if (isset($pathology['type']) && in_array($pathology['type'], ['header', 'database', 'table'])) {
        continue;
    }
    
    $labString = $pathology['lab'] ?? null;
    if (empty($labString)) continue;
    
    $parsed = parseLabNumber($labString);
    if (!$parsed) continue;
    
    $key = $parsed['base'] . '|' . ($parsed['suffix'] ?? 'null');
    $pathologyMap[$key] = $pathology;
}

echo "Created lookup map with " . count($pathologyMap) . " entries\n\n";

// Step 3: Fix reports with empty/null content
echo "Step 3: Fixing reports with empty/null content...\n";
Report::whereNotNull('lab_request_id')
    ->where(function($q) {
        $q->whereNull('content')
          ->orWhere('content', '')
          ->orWhere('content', 'like', '%"clinical_data":null%')
          ->orWhere('content', 'like', '%"clinical_data":""%')
          ->orWhere('content', 'like', '%"gross_pathology":null%')
          ->orWhere('content', 'like', '%"gross_examination":null%');
    })
    ->with('labRequest')
    ->chunk($BATCH_SIZE, function($reports) use (&$stats, $pathologyMap, $MAX_CONTENT_LENGTH, $LOG_EVERY) {
        foreach ($reports as $report) {
            $stats['reports_checked']++;
            
            if ($stats['reports_checked'] % $LOG_EVERY === 0) {
                echo "  Processed {$stats['reports_checked']} reports... (Fixed: {$stats['reports_fixed']}, Not Found: {$stats['reports_not_found']})\n";
                DB::reconnect();
                gc_collect_cycles();
            }
            
            if (!$report->labRequest) {
                $stats['errors']++;
                continue;
            }
            
            $labNo = $report->labRequest->lab_no;
            $suffix = $report->labRequest->suffix;
            
            $pathology = findPathologyData($labNo, $suffix, $pathologyMap);
            
            if ($pathology) {
                try {
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
                    
                    // Truncate if needed
                    $json = json_encode($content);
                    if (strlen($json) > $MAX_CONTENT_LENGTH) {
                        $content = truncateContent($content, $MAX_CONTENT_LENGTH);
                        $stats['reports_truncated']++;
                    }
                    
                    $report->content = json_encode($content);
                    $report->save();
                    $stats['reports_fixed']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    echo "  ERROR fixing report {$report->id}: " . $e->getMessage() . "\n";
                }
            } else {
                $stats['reports_not_found']++;
            }
        }
    });

// Step 4: Fix reports with content but all fields null/empty
echo "\nStep 4: Fixing reports with content but all fields null/empty...\n";
Report::whereNotNull('lab_request_id')
    ->whereNotNull('content')
    ->where('content', 'like', '{%')
    ->with('labRequest')
    ->chunk($BATCH_SIZE, function($reports) use (&$stats, $pathologyMap, $MAX_CONTENT_LENGTH, $LOG_EVERY) {
        foreach ($reports as $report) {
            // Skip if report already has data
            if (hasReportData($report->content)) {
                continue;
            }
            
            $stats['reports_checked']++;
            
            if ($stats['reports_checked'] % $LOG_EVERY === 0) {
                echo "  Processed {$stats['reports_checked']} reports... (Fixed: {$stats['reports_fixed']}, Not Found: {$stats['reports_not_found']})\n";
                DB::reconnect();
                gc_collect_cycles();
            }
            
            if (!$report->labRequest) {
                $stats['errors']++;
                continue;
            }
            
            $labNo = $report->labRequest->lab_no;
            $suffix = $report->labRequest->suffix;
            
            $pathology = findPathologyData($labNo, $suffix, $pathologyMap);
            
            if ($pathology) {
                try {
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
                    
                    // Truncate if needed
                    $json = json_encode($content);
                    if (strlen($json) > $MAX_CONTENT_LENGTH) {
                        $content = truncateContent($content, $MAX_CONTENT_LENGTH);
                        $stats['reports_truncated']++;
                    }
                    
                    $report->content = json_encode($content);
                    $report->save();
                    $stats['reports_fixed']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    echo "  ERROR fixing report {$report->id}: " . $e->getMessage() . "\n";
                }
            } else {
                $stats['reports_not_found']++;
            }
        }
    });

// Step 5: Validate relationships
echo "\nStep 5: Validating relationships...\n";
$relationshipIssues = 0;

// Check reports without lab_requests
$orphanedReports = Report::whereNotNull('lab_request_id')
    ->whereDoesntHave('labRequest')
    ->count();
if ($orphanedReports > 0) {
    echo "  WARNING: Found {$orphanedReports} reports with invalid lab_request_id\n";
    $relationshipIssues += $orphanedReports;
}

// Check lab_requests without patients
$orphanedLabRequests = LabRequest::whereNotNull('patient_id')
    ->whereDoesntHave('patient')
    ->count();
if ($orphanedLabRequests > 0) {
    echo "  WARNING: Found {$orphanedLabRequests} lab_requests with invalid patient_id\n";
    $relationshipIssues += $orphanedLabRequests;
}

// Check visits without patients
$orphanedVisits = Visit::whereNotNull('patient_id')
    ->whereDoesntHave('patient')
    ->count();
if ($orphanedVisits > 0) {
    echo "  WARNING: Found {$orphanedVisits} visits with invalid patient_id\n";
    $relationshipIssues += $orphanedVisits;
}

// Check visits without lab_requests (but should have one)
$visitsWithoutLabRequest = Visit::whereNull('lab_request_id')
    ->whereHas('patient', function($q) {
        $q->whereHas('labRequests');
    })
    ->count();
if ($visitsWithoutLabRequest > 0) {
    echo "  INFO: Found {$visitsWithoutLabRequest} visits that could be linked to lab_requests\n";
}

if ($relationshipIssues === 0) {
    echo "  ✓ All relationships are valid\n";
}

// Step 6: Final summary
echo "\n========================================\n";
echo "FINAL SUMMARY\n";
echo "========================================\n";
echo "Reports checked: {$stats['reports_checked']}\n";
echo "Reports fixed: {$stats['reports_fixed']}\n";
echo "Reports not found in pathology data: {$stats['reports_not_found']}\n";
echo "Reports truncated (content too long): {$stats['reports_truncated']}\n";
echo "Errors encountered: {$stats['errors']}\n";
echo "Relationship issues: {$relationshipIssues}\n";
echo "\n";

if ($stats['errors'] === 0 && $relationshipIssues === 0) {
    echo "✓ SUCCESS: All reports fixed successfully!\n";
    exit(0);
} else {
    echo "⚠ WARNING: Some issues were encountered. Please review the output above.\n";
    exit(1);
}

