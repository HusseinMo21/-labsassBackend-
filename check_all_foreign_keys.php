<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Checking all foreign key constraints...\n\n";
    
    // Check visits table structure
    echo "Visits table columns:\n";
    $visitsColumns = DB::select('DESCRIBE visits');
    foreach($visitsColumns as $col) {
        echo "- " . $col->Field . " (" . $col->Type . ")\n";
    }
    
    echo "\nChecking foreign key constraints:\n";
    
    // Check if visits has lab_request_id column
    $hasLabRequestId = false;
    foreach($visitsColumns as $col) {
        if($col->Field === 'lab_request_id') {
            $hasLabRequestId = true;
            break;
        }
    }
    
    if (!$hasLabRequestId) {
        echo "Adding lab_request_id column to visits table...\n";
        DB::statement('ALTER TABLE visits ADD COLUMN lab_request_id bigint(20) unsigned NULL');
        DB::statement('ALTER TABLE visits ADD KEY visits_lab_request_id_foreign (lab_request_id)');
        DB::statement('ALTER TABLE visits ADD CONSTRAINT visits_lab_request_id_foreign FOREIGN KEY (lab_request_id) REFERENCES lab_requests (id) ON DELETE SET NULL');
        echo "lab_request_id column added to visits table\n";
    } else {
        echo "visits table already has lab_request_id column\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}















