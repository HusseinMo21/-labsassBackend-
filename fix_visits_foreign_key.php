<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Checking data types for foreign key fix...\n";
    
    // Check patient table id type
    $patientColumns = DB::select('DESCRIBE patient');
    foreach($patientColumns as $col) {
        if($col->Field === 'id') {
            echo "Patient table id type: " . $col->Type . "\n";
            break;
        }
    }
    
    // Check visits table patient_id type
    $visitsColumns = DB::select('DESCRIBE visits');
    foreach($visitsColumns as $col) {
        if($col->Field === 'patient_id') {
            echo "Visits table patient_id type: " . $col->Type . "\n";
            break;
        }
    }
    
    // Drop existing foreign key if it exists
    echo "Checking for existing foreign key...\n";
    try {
        DB::statement('ALTER TABLE visits DROP FOREIGN KEY visits_patient_id_foreign');
        echo "Dropped existing foreign key\n";
    } catch (Exception $e) {
        echo "No existing foreign key to drop\n";
    }
    
    // Change patient_id to match patient.id type
    echo "Changing patient_id column type...\n";
    DB::statement('ALTER TABLE visits MODIFY COLUMN patient_id int(11) NOT NULL');
    
    // Add new foreign key
    echo "Adding new foreign key...\n";
    DB::statement('ALTER TABLE visits ADD CONSTRAINT visits_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
    
    echo "Foreign key constraint fixed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
