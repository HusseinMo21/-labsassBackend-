<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Creating default test data...\n";
    
    // Check if test_categories exists
    $categoryCount = DB::table('test_categories')->count();
    echo "Test categories count: " . $categoryCount . "\n";
    
    if ($categoryCount == 0) {
        echo "Creating default test category...\n";
        $categoryId = DB::table('test_categories')->insertGetId([
            'name' => 'Pathology',
            'description' => 'Pathology tests',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Created test category with ID: " . $categoryId . "\n";
    } else {
        $firstCategory = DB::table('test_categories')->first();
        $categoryId = $firstCategory->id;
        echo "Using existing test category ID: " . $categoryId . "\n";
    }
    
    // Check if lab_tests exists
    $testCount = DB::table('lab_tests')->count();
    echo "Lab tests count: " . $testCount . "\n";
    
    if ($testCount == 0) {
        echo "Creating default lab test...\n";
        $testId = DB::table('lab_tests')->insertGetId([
            'name' => 'General Pathology',
            'code' => 'PATH001',
            'description' => 'General pathology examination',
            'price' => 100,
            'category_id' => $categoryId,
            'turnaround_time_hours' => 24,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Created lab test with ID: " . $testId . "\n";
    } else {
        $firstTest = DB::table('lab_tests')->first();
        echo "Using existing lab test ID: " . $firstTest->id . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}







