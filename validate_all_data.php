<?php

/**
 * COMPREHENSIVE DATA VALIDATION SCRIPT
 * 
 * Validates all relationships and data integrity after seeding.
 * Ensures everything works correctly before going to production.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Report;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "COMPREHENSIVE DATA VALIDATION\n";
echo "========================================\n\n";

$errors = [];
$warnings = [];
$info = [];

// 1. Validate Patients
echo "1. Validating Patients...\n";
$totalPatients = Patient::count();
$patientsWithLabRequests = Patient::whereHas('labRequests')->count();
$patientsWithVisits = Patient::whereHas('visits')->count();

$info[] = "Total patients: {$totalPatients}";
$info[] = "Patients with lab requests: {$patientsWithLabRequests}";
$info[] = "Patients with visits: {$patientsWithVisits}";

// Check for duplicate patients (same name + phone)
$duplicates = DB::table('patient')
    ->select('name', 'phone', DB::raw('COUNT(*) as count'))
    ->whereNotNull('name')
    ->where('name', '!=', '')
    ->groupBy('name', 'phone')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->count() > 0) {
    $warnings[] = "Found {$duplicates->count()} potential duplicate patients (same name + phone)";
}

// 2. Validate Lab Requests
echo "2. Validating Lab Requests...\n";
$totalLabRequests = LabRequest::count();
$labRequestsWithPatients = LabRequest::whereHas('patient')->count();
$labRequestsWithReports = LabRequest::whereHas('reports')->count();
$labRequestsWithVisits = LabRequest::whereHas('visit')->count();

$info[] = "Total lab requests: {$totalLabRequests}";
$info[] = "Lab requests with patients: {$labRequestsWithPatients}";
$info[] = "Lab requests with reports: {$labRequestsWithReports}";
$info[] = "Lab requests with visits: {$labRequestsWithVisits}";

// Check for orphaned lab requests
$orphanedLabRequests = LabRequest::whereNotNull('patient_id')
    ->whereDoesntHave('patient')
    ->count();
if ($orphanedLabRequests > 0) {
    $errors[] = "Found {$orphanedLabRequests} lab requests with invalid patient_id";
}

// Check for duplicate lab numbers
$duplicateLabNos = DB::table('lab_requests')
    ->select('lab_no', 'suffix', DB::raw('COUNT(*) as count'))
    ->whereNotNull('lab_no')
    ->groupBy('lab_no', 'suffix')
    ->having('count', '>', 1)
    ->get();
if ($duplicateLabNos->count() > 0) {
    $warnings[] = "Found {$duplicateLabNos->count()} duplicate lab number combinations";
}

// 3. Validate Reports
echo "3. Validating Reports...\n";
$totalReports = Report::count();
$reportsWithLabRequests = Report::whereHas('labRequest')->count();
$reportsWithContent = Report::whereNotNull('content')
    ->where('content', '!=', '')
    ->get()
    ->filter(function($report) {
        $parsed = json_decode($report->content, true);
        if (!$parsed) return false;
        
        $fields = ['clinical_data', 'nature_of_specimen', 'gross_pathology', 'gross_examination', 
                   'microscopic_examination', 'microscopic_description', 'conclusion', 'diagnosis'];
        
        foreach ($fields as $field) {
            if (isset($parsed[$field]) && 
                $parsed[$field] !== null && 
                $parsed[$field] !== '' && 
                $parsed[$field] !== '.') {
                return true;
            }
        }
        return false;
    })
    ->count();

$info[] = "Total reports: {$totalReports}";
$info[] = "Reports with lab requests: {$reportsWithLabRequests}";
$info[] = "Reports with actual content: {$reportsWithContent}";

// Check for orphaned reports
$orphanedReports = Report::whereNotNull('lab_request_id')
    ->whereDoesntHave('labRequest')
    ->count();
if ($orphanedReports > 0) {
    $errors[] = "Found {$orphanedReports} reports with invalid lab_request_id";
}

// Check for empty reports
$emptyReports = Report::where(function($q) {
    $q->whereNull('content')
      ->orWhere('content', '')
      ->orWhere('content', 'like', '%"clinical_data":null%');
})->count();
if ($emptyReports > 0) {
    $warnings[] = "Found {$emptyReports} reports with empty/null content";
}

// Check for oversized content
$oversizedReports = Report::whereNotNull('content')
    ->get()
    ->filter(function($report) {
        return strlen($report->content) > 65535;
    })
    ->count();
if ($oversizedReports > 0) {
    $errors[] = "Found {$oversizedReports} reports with content exceeding 65535 bytes";
}

// 4. Validate Visits
echo "4. Validating Visits...\n";
$totalVisits = Visit::count();
$visitsWithPatients = Visit::whereHas('patient')->count();
$visitsWithLabRequests = Visit::whereNotNull('lab_request_id')
    ->whereHas('labRequest')
    ->count();

$info[] = "Total visits: {$totalVisits}";
$info[] = "Visits with patients: {$visitsWithPatients}";
$info[] = "Visits with lab requests: {$visitsWithLabRequests}";

// Check for orphaned visits
$orphanedVisits = Visit::whereNotNull('patient_id')
    ->whereDoesntHave('patient')
    ->count();
if ($orphanedVisits > 0) {
    $errors[] = "Found {$orphanedVisits} visits with invalid patient_id";
}

// Check for visits that should have lab requests
$visitsWithoutLabRequest = Visit::whereNull('lab_request_id')
    ->whereHas('patient', function($q) {
        $q->whereHas('labRequests');
    })
    ->count();
if ($visitsWithoutLabRequest > 0) {
    $warnings[] = "Found {$visitsWithoutLabRequest} visits that could be linked to lab requests";
}

// 5. Validate Relationships
echo "5. Validating Relationships...\n";

// Check: Every lab request should have a patient
$labRequestsWithoutPatients = LabRequest::whereNotNull('patient_id')
    ->whereDoesntHave('patient')
    ->count();
if ($labRequestsWithoutPatients > 0) {
    $errors[] = "Found {$labRequestsWithoutPatients} lab requests without valid patients";
}

// Check: Every report should have a lab request
$reportsWithoutLabRequests = Report::whereNotNull('lab_request_id')
    ->whereDoesntHave('labRequest')
    ->count();
if ($reportsWithoutLabRequests > 0) {
    $errors[] = "Found {$reportsWithoutLabRequests} reports without valid lab requests";
}

// Check: Lab requests should ideally have reports
$labRequestsWithoutReports = LabRequest::whereDoesntHave('reports')->count();
if ($labRequestsWithoutReports > 0) {
    $warnings[] = "Found {$labRequestsWithoutReports} lab requests without reports";
}

// Check: Patients should ideally have visits
$patientsWithoutVisits = Patient::whereDoesntHave('visits')->count();
if ($patientsWithoutVisits > 0) {
    $warnings[] = "Found {$patientsWithoutVisits} patients without visits";
}

// 6. Print Summary
echo "\n========================================\n";
echo "VALIDATION SUMMARY\n";
echo "========================================\n\n";

if (count($info) > 0) {
    echo "INFO:\n";
    foreach ($info as $msg) {
        echo "  ✓ {$msg}\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "WARNINGS:\n";
    foreach ($warnings as $msg) {
        echo "  ⚠ {$msg}\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "ERRORS:\n";
    foreach ($errors as $msg) {
        echo "  ✗ {$msg}\n";
    }
    echo "\n";
}

// Final verdict
if (count($errors) === 0 && count($warnings) === 0) {
    echo "✓ SUCCESS: All data is valid and relationships are correct!\n";
    exit(0);
} elseif (count($errors) === 0) {
    echo "⚠ WARNING: Some warnings were found, but no critical errors.\n";
    echo "  Data is usable but may need attention.\n";
    exit(0);
} else {
    echo "✗ FAILURE: Critical errors found. Please fix these before going to production.\n";
    exit(1);
}

