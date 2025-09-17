<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Fixing enhanced_reports foreign key constraint...\n";
    
    // Check data types
    $patientColumns = DB::select('DESCRIBE patient');
    foreach($patientColumns as $col) {
        if($col->Field === 'id') {
            echo "Patient table id type: " . $col->Type . "\n";
            break;
        }
    }
    
    $enhancedReportsColumns = DB::select('DESCRIBE enhanced_reports');
    foreach($enhancedReportsColumns as $col) {
        if($col->Field === 'patient_id') {
            echo "Enhanced_reports table patient_id type: " . $col->Type . "\n";
            break;
        }
    }
    
    // Drop existing foreign key if it exists
    try {
        DB::statement('ALTER TABLE enhanced_reports DROP FOREIGN KEY enhanced_reports_patient_id_foreign');
        echo "Dropped existing foreign key\n";
    } catch (Exception $e) {
        echo "No existing foreign key to drop\n";
    }
    
    // Change patient_id to match patient.id type
    echo "Changing patient_id column type...\n";
    DB::statement('ALTER TABLE enhanced_reports MODIFY COLUMN patient_id int(11) NULL');
    
    // Add new foreign key
    echo "Adding new foreign key...\n";
    DB::statement('ALTER TABLE enhanced_reports ADD CONSTRAINT enhanced_reports_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE SET NULL');
    
    echo "Enhanced_reports foreign key constraint fixed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

