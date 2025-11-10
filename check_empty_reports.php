<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Report;

echo "=== Checking Empty Reports ===\n\n";

// Count reports with empty/null content
$emptyReports = Report::whereNotNull('lab_request_id')
    ->where(function($q) {
        $q->whereNull('content')
          ->orWhere('content', '')
          ->orWhere('content', 'like', '%"clinical_data":null%')
          ->orWhere('content', 'like', '%"clinical_data":""%')
          ->orWhere('content', 'like', '%"gross_pathology":null%')
          ->orWhere('content', 'like', '%"gross_examination":null%');
    })
    ->count();

echo "Reports with empty/null content: {$emptyReports}\n\n";

// Check reports that have content but all fields are null or empty
$reportsWithNullFields = Report::whereNotNull('lab_request_id')
    ->whereNotNull('content')
    ->where('content', 'like', '{%')
    ->get()
    ->filter(function($report) {
        $parsed = json_decode($report->content, true);
        if (!$parsed) return false;
        
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
        
        return !$hasData;
    });

echo "Reports with content but all fields null/empty: " . $reportsWithNullFields->count() . "\n\n";

$totalEmpty = $emptyReports + $reportsWithNullFields->count();
echo "Total reports needing fix: {$totalEmpty}\n\n";

// Sample a few
echo "Sample empty reports:\n";
$samples = Report::whereNotNull('lab_request_id')
    ->where(function($q) {
        $q->whereNull('content')
          ->orWhere('content', '')
          ->orWhere('content', 'like', '%"clinical_data":null%');
    })
    ->with('labRequest')
    ->limit(5)
    ->get();

foreach ($samples as $report) {
    echo "Report ID: {$report->id}, Lab Request ID: {$report->lab_request_id}";
    if ($report->labRequest) {
        echo ", Lab No: {$report->labRequest->lab_no}";
    }
    echo "\n";
}

echo "\nDone!\n";

