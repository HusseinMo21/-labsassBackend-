<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Report;

echo "=== Testing Report Content ===\n\n";

// Get a report created by seeder
$report = Report::whereNotNull('lab_request_id')
    ->where('content', 'like', '{%')
    ->where('status', 'completed')
    ->first();

if ($report) {
    echo "Report ID: {$report->id}\n";
    echo "Lab Request ID: {$report->lab_request_id}\n";
    echo "Status: {$report->status}\n\n";
    
    $parsed = json_decode($report->content, true);
    if ($parsed) {
        echo "Content Keys: " . implode(', ', array_keys($parsed)) . "\n\n";
        echo "Values:\n";
        echo "  clinical_data: " . (isset($parsed['clinical_data']) && !empty($parsed['clinical_data']) ? substr($parsed['clinical_data'], 0, 100) : 'EMPTY') . "\n";
        echo "  nature_of_specimen: " . (isset($parsed['nature_of_specimen']) && !empty($parsed['nature_of_specimen']) ? substr($parsed['nature_of_specimen'], 0, 100) : 'EMPTY') . "\n";
        echo "  gross_pathology: " . (isset($parsed['gross_pathology']) && !empty($parsed['gross_pathology']) ? substr($parsed['gross_pathology'], 0, 100) : 'EMPTY') . "\n";
        echo "  gross_examination: " . (isset($parsed['gross_examination']) && !empty($parsed['gross_examination']) ? substr($parsed['gross_examination'], 0, 100) : 'EMPTY') . "\n";
        echo "  microscopic_examination: " . (isset($parsed['microscopic_examination']) && !empty($parsed['microscopic_examination']) ? substr($parsed['microscopic_examination'], 0, 100) : 'EMPTY') . "\n";
        echo "  conclusion: " . (isset($parsed['conclusion']) && !empty($parsed['conclusion']) ? substr($parsed['conclusion'], 0, 100) : 'EMPTY') . "\n";
        echo "  recommendations: " . (isset($parsed['recommendations']) && !empty($parsed['recommendations']) ? substr($parsed['recommendations'], 0, 100) : 'EMPTY') . "\n";
    } else {
        echo "Failed to parse JSON\n";
    }
} else {
    echo "No completed report found\n";
}

echo "\nDone!\n";

