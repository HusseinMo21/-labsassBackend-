<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\Report;
use App\Models\Visit;

echo "=== Database Record Counts ===\n";
echo "Patients: " . Patient::count() . "\n";
echo "Lab Requests: " . LabRequest::count() . "\n";
echo "Reports: " . Report::count() . "\n";
echo "Visits: " . Visit::count() . "\n\n";

echo "=== Sample Lab Requests (first 5) ===\n";
$labRequests = LabRequest::with('reports')->limit(5)->get();
foreach ($labRequests as $lr) {
    echo "Lab Request ID: {$lr->id}, Lab No: {$lr->lab_no}, Suffix: " . ($lr->suffix ?? 'null') . ", Reports: " . $lr->reports->count() . "\n";
}

echo "\n=== Sample Reports (first 5) ===\n";
$reports = Report::limit(5)->get();
foreach ($reports as $r) {
    echo "Report ID: {$r->id}, Lab Request ID: {$r->lab_request_id}, Status: {$r->status}\n";
}

echo "\n=== Visits with Lab Requests (first 5) ===\n";
$visits = Visit::with('labRequest.reports')->limit(5)->get();
foreach ($visits as $v) {
    $hasLabRequest = $v->labRequest ? 'Yes' : 'No';
    $reportCount = $v->labRequest && $v->labRequest->reports ? $v->labRequest->reports->count() : 0;
    echo "Visit ID: {$v->id}, Visit Number: {$v->visit_number}, Has Lab Request: {$hasLabRequest}, Reports: {$reportCount}\n";
}

echo "\n=== Lab Requests without Reports ===\n";
$labRequestsWithoutReports = LabRequest::doesntHave('reports')->count();
echo "Lab Requests without Reports: {$labRequestsWithoutReports}\n";

echo "\n=== Lab Requests with Reports ===\n";
$labRequestsWithReports = LabRequest::has('reports')->count();
echo "Lab Requests with Reports: {$labRequestsWithReports}\n";

