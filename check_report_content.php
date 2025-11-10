<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Report;
use App\Models\LabRequest;

echo "=== Checking Report Content ===\n\n";

// Get a sample report created by seeder (has lab_request_id and JSON content)
$report = Report::whereNotNull('lab_request_id')
    ->whereNotNull('content')
    ->where('content', 'like', '{%')
    ->first();

if ($report) {
    echo "Report ID: {$report->id}\n";
    echo "Lab Request ID: {$report->lab_request_id}\n";
    echo "Status: {$report->status}\n";
    echo "Content (raw): " . substr($report->content, 0, 200) . "...\n\n";
    
    $parsed = json_decode($report->content, true);
    if ($parsed) {
        echo "Parsed Content:\n";
        echo "  clinical_data: " . (isset($parsed['clinical_data']) ? substr($parsed['clinical_data'], 0, 50) : 'NULL') . "\n";
        echo "  nature_of_specimen: " . (isset($parsed['nature_of_specimen']) ? substr($parsed['nature_of_specimen'], 0, 50) : 'NULL') . "\n";
        echo "  gross_pathology: " . (isset($parsed['gross_pathology']) ? substr($parsed['gross_pathology'], 0, 50) : 'NULL') . "\n";
        echo "  microscopic_examination: " . (isset($parsed['microscopic_examination']) ? substr($parsed['microscopic_examination'], 0, 50) : 'NULL') . "\n";
        echo "  conclusion: " . (isset($parsed['conclusion']) ? substr($parsed['conclusion'], 0, 50) : 'NULL') . "\n";
        echo "  recommendations: " . (isset($parsed['recommendations']) ? substr($parsed['recommendations'], 0, 50) : 'NULL') . "\n";
    } else {
        echo "Failed to parse JSON: " . json_last_error_msg() . "\n";
    }
} else {
    echo "No reports found with content\n";
}

echo "\nDone!\n";

