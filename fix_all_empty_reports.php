<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Report;
use App\Models\LabRequest;
use Illuminate\Support\Facades\DB;

echo "=== Fixing All Empty Reports ===\n\n";

// Load pathology data
function loadJsonFile($filePath) {
    if (!file_exists($filePath)) {
        throw new \Exception("File not found: {$filePath}");
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

function parseLabNumber($labString) {
    if (empty($labString)) return null;
    $labString = trim(str_replace('_', '-', $labString));
    // Clean up malformed lab numbers (remove trailing dashes, multiple dashes)
    $labString = preg_replace('/-+$/', '', $labString); // Remove trailing dashes
    $labString = preg_replace('/--+/', '-', $labString); // Replace multiple dashes with single
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

function normalizeLabNumber($labString) {
    if (empty($labString)) return '';
    $labString = trim(str_replace('_', '-', $labString));
    $labString = preg_replace('/-+$/', '', $labString); // Remove trailing dashes
    $labString = preg_replace('/--+/', '-', $labString); // Replace multiple dashes with single
    return $labString;
}

$pathologyFile = base_path('../seedes/patholgy.json');
if (!file_exists($pathologyFile)) {
    echo "Pathology file not found!\n";
    exit;
}

echo "Loading pathology data...\n";
$pathologyData = loadJsonFile($pathologyFile);
echo "Loaded " . count($pathologyData) . " records\n\n";

// Create lookup map: lab_no+suffix -> pathology data
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

echo "Created pathology lookup map with " . count($pathologyMap) . " entries\n\n";

// Process reports in batches to avoid memory issues
echo "Processing reports in batches...\n\n";

$fixed = 0;
$notFound = 0;
$progress = 0;
$batchSize = 500;

// Process reports with null/empty content
echo "Processing reports with null/empty content...\n";
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
    ->chunk($batchSize, function($reports) use (&$fixed, &$notFound, &$progress, &$pathologyMap) {
        foreach ($reports as $report) {
            $progress++;
            if ($progress % 100 === 0) {
                echo "Processed {$progress} reports... (Fixed: {$fixed}, Not Found: {$notFound})\n";
                DB::reconnect();
                gc_collect_cycles();
            }
            
            if (!$report->labRequest) {
                continue;
            }
            
            $labNo = normalizeLabNumber($report->labRequest->lab_no);
            $suffix = $report->labRequest->suffix;
            
            // Try multiple matching strategies
            $pathology = null;
            
            // Strategy 1: Exact match with suffix
            $key = $labNo . '|' . ($suffix ?? 'null');
            $pathology = $pathologyMap[$key] ?? null;
            
            // Strategy 2: Without suffix
            if (!$pathology) {
                $keyNoSuffix = $labNo . '|null';
                $pathology = $pathologyMap[$keyNoSuffix] ?? null;
            }
            
            // Strategy 3: Try parsed version
            if (!$pathology) {
                $parsed = parseLabNumber($labNo);
                if ($parsed) {
                    $parsedKey = $parsed['base'] . '|' . ($parsed['suffix'] ?? 'null');
                    $pathology = $pathologyMap[$parsedKey] ?? null;
                }
            }
            
            // Strategy 4: Try direct match in pathology data (fuzzy match)
            if (!$pathology) {
                foreach ($pathologyMap as $pathKey => $pathData) {
                    $pathLab = normalizeLabNumber($pathData['lab'] ?? '');
                    if ($pathLab === $labNo || 
                        str_replace('-', '', $pathLab) === str_replace('-', '', $labNo)) {
                        $pathology = $pathData;
                        break;
                    }
                }
            }
            
            if ($pathology) {
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
                
                $report->content = json_encode($content);
                $report->save();
                $fixed++;
            } else {
                $notFound++;
            }
        }
    });

// Process reports with content but all fields null/empty
echo "\nProcessing reports with content but all fields null/empty...\n";
Report::whereNotNull('lab_request_id')
    ->whereNotNull('content')
    ->where('content', 'like', '{%')
    ->with('labRequest')
    ->chunk($batchSize, function($reports) use (&$fixed, &$notFound, &$progress, &$pathologyMap) {
        foreach ($reports as $report) {
            // Check if report has any data
            $parsed = json_decode($report->content, true);
            if ($parsed) {
                $hasData = false;
                $fields = ['clinical_data', 'nature_of_specimen', 'gross_pathology', 'gross_examination', 
                           'microscopic_examination', 'microscopic_description', 'conclusion', 'diagnosis'];
                
                foreach ($fields as $field) {
                    if (isset($parsed[$field]) && 
                        $parsed[$field] !== null && 
                        $parsed[$field] !== '' && 
                        $parsed[$field] !== '.') {
                        $hasData = true;
                        break;
                    }
                }
                
                if ($hasData) {
                    continue; // Skip reports that already have data
                }
            }
            
            $progress++;
            if ($progress % 100 === 0) {
                echo "Processed {$progress} reports... (Fixed: {$fixed}, Not Found: {$notFound})\n";
                DB::reconnect();
                gc_collect_cycles();
            }
            
            if (!$report->labRequest) {
                continue;
            }
            
            $labNo = normalizeLabNumber($report->labRequest->lab_no);
            $suffix = $report->labRequest->suffix;
            
            // Try multiple matching strategies
            $pathology = null;
            
            // Strategy 1: Exact match with suffix
            $key = $labNo . '|' . ($suffix ?? 'null');
            $pathology = $pathologyMap[$key] ?? null;
            
            // Strategy 2: Without suffix
            if (!$pathology) {
                $keyNoSuffix = $labNo . '|null';
                $pathology = $pathologyMap[$keyNoSuffix] ?? null;
            }
            
            // Strategy 3: Try parsed version
            if (!$pathology) {
                $parsed = parseLabNumber($labNo);
                if ($parsed) {
                    $parsedKey = $parsed['base'] . '|' . ($parsed['suffix'] ?? 'null');
                    $pathology = $pathologyMap[$parsedKey] ?? null;
                }
            }
            
            // Strategy 4: Try direct match in pathology data (fuzzy match)
            if (!$pathology) {
                foreach ($pathologyMap as $pathKey => $pathData) {
                    $pathLab = normalizeLabNumber($pathData['lab'] ?? '');
                    if ($pathLab === $labNo || 
                        str_replace('-', '', $pathLab) === str_replace('-', '', $labNo)) {
                        $pathology = $pathData;
                        break;
                    }
                }
            }
            
            if ($pathology) {
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
                
                $report->content = json_encode($content);
                $report->save();
                $fixed++;
            } else {
                $notFound++;
            }
        }
    });

echo "\n=== Summary ===\n";
echo "Fixed: {$fixed}\n";
echo "Not Found: {$notFound}\n";
echo "\nDone!\n";

