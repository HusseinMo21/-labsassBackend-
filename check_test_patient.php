<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Test Patient Details (V20250920125739):\n";
echo str_repeat("-", 50) . "\n";

$visit = \App\Models\Visit::where('visit_number', 'V20250920125739')->with(['patient', 'labRequest'])->first();

if ($visit) {
    echo "Visit ID: " . $visit->id . "\n";
    echo "Visit Number: " . $visit->visit_number . "\n";
    echo "Patient Name: " . $visit->patient->name . "\n";
    echo "Status: " . $visit->status . "\n";
    echo "Created At: " . $visit->created_at . "\n";
    echo "Visit Date: " . $visit->visit_date . "\n";
    echo "Lab Number: " . ($visit->labRequest->full_lab_no ?? 'N/A') . "\n";
    
    // Check if this visit has reports
    $reports = \App\Models\Report::where('lab_request_id', $visit->lab_request_id)->get();
    echo "Reports Count: " . $reports->count() . "\n";
    
    if ($reports->count() > 0) {
        foreach ($reports as $report) {
            echo "Report ID: " . $report->id . ", Status: " . $report->status . "\n";
        }
    }
} else {
    echo "Visit not found\n";
}

echo "\nAll visits with 'V20250920125739' pattern:\n";
echo str_repeat("-", 50) . "\n";

$visits = \App\Models\Visit::where('visit_number', 'like', '%20250920125739%')
    ->with(['patient', 'labRequest'])
    ->get(['id', 'visit_number', 'status', 'created_at', 'visit_date']);

foreach ($visits as $v) {
    echo $v->id . " | " . $v->visit_number . " | " . $v->status . " | " . $v->created_at . "\n";
}