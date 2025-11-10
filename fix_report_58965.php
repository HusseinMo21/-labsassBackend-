<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Visit;
use App\Models\Report;
use App\Models\LabRequest;

$visitId = 58965;
$visit = Visit::with('labRequest')->find($visitId);

if (!$visit || !$visit->labRequest) {
    echo "Visit or lab request not found!\n";
    exit;
}

$labNo = $visit->labRequest->lab_no;
echo "Looking for pathology data for lab: {$labNo}\n\n";

// Load pathology file
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

$pathologyFile = base_path('seedes/patholgy.json');
if (!file_exists($pathologyFile)) {
    echo "Pathology file not found!\n";
    exit;
}

echo "Loading pathology data...\n";
$pathologyData = loadJsonFile($pathologyFile);
echo "Loaded " . count($pathologyData) . " records\n\n";

// Find pathology record for this lab number
function parseLabNumber($labString) {
    if (empty($labString)) return null;
    $labString = trim($labString);
    if (preg_match('/^(\d{4})([MH]?)-(\d+)$/i', $labString, $matches)) {
        return ['base' => $matches[3], 'suffix' => !empty($matches[2]) ? strtoupper($matches[2]) : null];
    }
    if (preg_match('/^(\d+)([MH]?)$/i', $labString, $matches)) {
        return ['base' => $matches[1], 'suffix' => !empty($matches[2]) ? strtoupper($matches[2]) : null];
    }
    return null;
}

$labParsed = parseLabNumber($labNo);
echo "Parsed lab number - Base: {$labParsed['base']}, Suffix: " . ($labParsed['suffix'] ?? 'NULL') . "\n\n";

$foundPathology = null;
foreach ($pathologyData as $pathology) {
    if (isset($pathology['type']) && in_array($pathology['type'], ['header', 'database', 'table'])) {
        continue;
    }
    
    $pathLab = $pathology['lab'] ?? null;
    if (!$pathLab) continue;
    
    $pathParsed = parseLabNumber($pathLab);
    if ($pathParsed && $pathParsed['base'] === $labParsed['base']) {
        if (($pathParsed['suffix'] ?? null) === ($labParsed['suffix'] ?? null)) {
            $foundPathology = $pathology;
            break;
        }
    }
}

if ($foundPathology) {
    echo "Found pathology data!\n";
    echo "  clinical: " . substr($foundPathology['clinical'] ?? 'NULL', 0, 100) . "\n";
    echo "  nature: " . substr($foundPathology['nature'] ?? 'NULL', 0, 100) . "\n";
    echo "  gross: " . substr($foundPathology['gross'] ?? 'NULL', 0, 100) . "\n";
    echo "  micro: " . substr($foundPathology['micro'] ?? 'NULL', 0, 100) . "\n";
    echo "  conc: " . substr($foundPathology['conc'] ?? 'NULL', 0, 100) . "\n";
    echo "  reco: " . substr($foundPathology['reco'] ?? 'NULL', 0, 100) . "\n\n";
    
    // Update the report
    $report = Report::where('lab_request_id', $visit->labRequest->id)->first();
    if ($report) {
        $content = [
            'clinical_data' => $foundPathology['clinical'] ?? null,
            'nature_of_specimen' => $foundPathology['nature'] ?? null,
            'gross_pathology' => $foundPathology['gross'] ?? null,
            'microscopic_examination' => $foundPathology['micro'] ?? null,
            'conclusion' => $foundPathology['conc'] ?? null,
            'recommendations' => $foundPathology['reco'] ?? null,
            'referred_by' => $foundPathology['reff'] ?? null,
            'type_of_analysis' => $foundPathology['type'] ?? null,
            'report_date' => $foundPathology['date'] ?? null,
            'receiving_date' => $foundPathology['recieving'] ?? null,
            'discharge_date' => $foundPathology['discharge'] ?? null,
        ];
        
        $report->content = json_encode($content);
        $report->save();
        
        echo "Report updated successfully!\n";
    } else {
        echo "Report not found!\n";
    }
} else {
    echo "Pathology data not found for lab {$labNo}\n";
}

echo "\nDone!\n";

