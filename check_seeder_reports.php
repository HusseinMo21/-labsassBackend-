<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Report;

echo "=== Checking Seeder-Created Reports ===\n\n";

// Get reports created by seeder (should have gross_pathology key, not gross_examination)
$reports = Report::whereNotNull('lab_request_id')
    ->where('content', 'like', '%gross_pathology%')
    ->limit(5)
    ->get();

echo "Found " . $reports->count() . " reports with gross_pathology key\n\n";

foreach ($reports as $report) {
    $parsed = json_decode($report->content, true);
    if ($parsed) {
        echo "Report ID: {$report->id}\n";
        echo "  clinical_data: " . (!empty($parsed['clinical_data']) ? substr($parsed['clinical_data'], 0, 50) : 'EMPTY') . "\n";
        echo "  nature_of_specimen: " . (!empty($parsed['nature_of_specimen']) ? substr($parsed['nature_of_specimen'], 0, 50) : 'EMPTY') . "\n";
        echo "  gross_pathology: " . (!empty($parsed['gross_pathology']) ? substr($parsed['gross_pathology'], 0, 50) : 'EMPTY') . "\n";
        echo "  microscopic_examination: " . (!empty($parsed['microscopic_examination']) ? substr($parsed['microscopic_examination'], 0, 50) : 'EMPTY') . "\n";
        echo "  conclusion: " . (!empty($parsed['conclusion']) ? substr($parsed['conclusion'], 0, 50) : 'EMPTY') . "\n";
        echo "  recommendations: " . (!empty($parsed['recommendations']) ? substr($parsed['recommendations'], 0, 50) : 'EMPTY') . "\n";
        echo "\n";
    }
}

// Check if reports have any data at all
$reportsWithData = Report::whereNotNull('lab_request_id')
    ->where(function($q) {
        $q->where('content', 'like', '%"clinical_data":"%')
          ->orWhere('content', 'like', '%"gross_pathology":"%')
          ->orWhere('content', 'like', '%"microscopic_examination":"%');
    })
    ->whereRaw("JSON_EXTRACT(content, '$.clinical_data') IS NOT NULL")
    ->whereRaw("JSON_EXTRACT(content, '$.clinical_data') != ''")
    ->whereRaw("JSON_EXTRACT(content, '$.clinical_data') != '.'")
    ->count();

echo "Reports with actual data (not empty or '.'): {$reportsWithData}\n";

echo "\nDone!\n";

