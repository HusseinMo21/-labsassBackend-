<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Visit;
use App\Models\Report;

$visitId = 58965;

echo "=== Debugging Visit {$visitId} ===\n\n";

$visit = Visit::with(['patient', 'labRequest.reports'])->find($visitId);

if (!$visit) {
    echo "Visit not found!\n";
    exit;
}

echo "Visit ID: {$visit->id}\n";
echo "Visit Number: {$visit->visit_number}\n";
echo "Patient ID: {$visit->patient_id}\n";
echo "Lab Request ID: " . ($visit->lab_request_id ?? 'NULL') . "\n\n";

if ($visit->labRequest) {
    echo "Lab Request Found:\n";
    echo "  Lab No: {$visit->labRequest->lab_no}\n";
    echo "  Suffix: " . ($visit->labRequest->suffix ?? 'NULL') . "\n";
    echo "  Reports Count: " . $visit->labRequest->reports->count() . "\n\n";
    
    if ($visit->labRequest->reports->count() > 0) {
        foreach ($visit->labRequest->reports as $index => $report) {
            echo "Report #{$index}:\n";
            echo "  ID: {$report->id}\n";
            echo "  Status: {$report->status}\n";
            echo "  Content Length: " . strlen($report->content) . "\n";
            echo "  Content Preview: " . substr($report->content, 0, 200) . "...\n\n";
            
            $parsed = json_decode($report->content, true);
            if ($parsed) {
                echo "  Parsed Content:\n";
                echo "    clinical_data: " . (isset($parsed['clinical_data']) && !empty($parsed['clinical_data']) && $parsed['clinical_data'] !== '.' ? substr($parsed['clinical_data'], 0, 100) : 'EMPTY/NULL') . "\n";
                echo "    nature_of_specimen: " . (isset($parsed['nature_of_specimen']) && !empty($parsed['nature_of_specimen']) ? substr($parsed['nature_of_specimen'], 0, 100) : 'EMPTY/NULL') . "\n";
                echo "    gross_pathology: " . (isset($parsed['gross_pathology']) && !empty($parsed['gross_pathology']) ? substr($parsed['gross_pathology'], 0, 100) : 'EMPTY/NULL') . "\n";
                echo "    microscopic_examination: " . (isset($parsed['microscopic_examination']) && !empty($parsed['microscopic_examination']) ? substr($parsed['microscopic_examination'], 0, 100) : 'EMPTY/NULL') . "\n";
                echo "    conclusion: " . (isset($parsed['conclusion']) && !empty($parsed['conclusion']) ? substr($parsed['conclusion'], 0, 100) : 'EMPTY/NULL') . "\n";
                echo "    recommendations: " . (isset($parsed['recommendations']) && !empty($parsed['recommendations']) ? substr($parsed['recommendations'], 0, 100) : 'EMPTY/NULL') . "\n";
            } else {
                echo "  Failed to parse JSON: " . json_last_error_msg() . "\n";
            }
            echo "\n";
        }
    } else {
        echo "No reports found for this lab request!\n";
        // Check if there are any reports for this lab request
        $allReports = Report::where('lab_request_id', $visit->labRequest->id)->get();
        echo "Direct query found " . $allReports->count() . " reports\n";
    }
} else {
    echo "No lab request linked to this visit!\n";
}

echo "\nDone!\n";

