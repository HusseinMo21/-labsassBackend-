<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Visit;
use App\Models\LabRequest;
use App\Models\Report;

echo "=== Reports Visibility Diagnostic ===\n\n";

echo "Total Reports: " . Report::count() . "\n";
echo "Total Lab Requests: " . LabRequest::count() . "\n";
echo "Total Visits: " . Visit::count() . "\n\n";

echo "Visits with Lab Requests: " . Visit::whereNotNull('lab_request_id')->count() . "\n";
echo "Visits without Lab Requests: " . Visit::whereNull('lab_request_id')->count() . "\n\n";

echo "Lab Requests with Reports: " . LabRequest::has('reports')->count() . "\n";
echo "Lab Requests without Reports: " . LabRequest::doesntHave('reports')->count() . "\n\n";

echo "Visits with Lab Requests that have Reports: " . Visit::whereNotNull('lab_request_id')->whereHas('labRequest.reports')->count() . "\n";
echo "Visits with Lab Requests that DON'T have Reports: " . Visit::whereNotNull('lab_request_id')->whereDoesntHave('labRequest.reports')->count() . "\n\n";

// Check visit statuses
echo "Visit Statuses:\n";
$statuses = Visit::selectRaw('status, COUNT(*) as count')->groupBy('status')->get();
foreach ($statuses as $status) {
    echo "  {$status->status}: {$status->count}\n";
}

echo "\n=== Sample Visits ===\n";
$sampleVisits = Visit::with(['labRequest.reports', 'patient'])->limit(5)->get();
foreach ($sampleVisits as $visit) {
    echo "Visit ID: {$visit->id}, Status: {$visit->status}, Lab Request ID: " . ($visit->lab_request_id ?? 'NULL') . "\n";
    if ($visit->labRequest) {
        echo "  Lab Request: {$visit->labRequest->lab_no}, Reports: " . $visit->labRequest->reports->count() . "\n";
    } else {
        echo "  No Lab Request linked!\n";
    }
}

echo "\n=== Visits that should show on Reports page (non-completed, with lab requests, with reports) ===\n";
$visibleVisits = Visit::where('status', '!=', 'completed')
    ->whereNotNull('lab_request_id')
    ->whereHas('labRequest.reports')
    ->count();
echo "Count: {$visibleVisits}\n";

echo "\n=== Checking if visits need to be linked to lab requests ===\n";
// Find visits that have patients with lab requests but aren't linked
$unlinkedVisits = Visit::whereNull('lab_request_id')
    ->whereHas('patient.labRequests')
    ->count();
echo "Visits that could be linked to lab requests: {$unlinkedVisits}\n";

if ($unlinkedVisits > 0) {
    echo "\n=== Linking visits to lab requests ===\n";
    $visitsToLink = Visit::whereNull('lab_request_id')
        ->whereHas('patient.labRequests')
        ->with('patient.labRequests')
        ->limit(100)
        ->get();
    
    $linked = 0;
    foreach ($visitsToLink as $visit) {
        // Find the most recent lab request for this patient
        $labRequest = $visit->patient->labRequests()->orderBy('created_at', 'desc')->first();
        if ($labRequest) {
            $visit->update(['lab_request_id' => $labRequest->id]);
            $linked++;
        }
    }
    echo "Linked {$linked} visits to lab requests\n";
}

echo "\nDone!\n";

