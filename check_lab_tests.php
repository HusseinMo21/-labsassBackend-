<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Checking lab_tests table...\n";
    
    $count = DB::table('lab_tests')->count();
    echo "Lab tests count: " . $count . "\n";
    
    if ($count > 0) {
        $first = DB::table('lab_tests')->first();
        echo "First lab test: ID " . $first->id . ", Name: " . $first->name . "\n";
    } else {
        echo "No lab tests found. Creating a default lab test...\n";
        
        // Create a default lab test
        $defaultTestId = DB::table('lab_tests')->insertGetId([
            'name' => 'General Pathology',
            'code' => 'PATH001',
            'description' => 'General pathology examination',
            'price' => 100,
            'category_id' => 1,
            'turnaround_time_hours' => 24,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "Created default lab test with ID: " . $defaultTestId . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

