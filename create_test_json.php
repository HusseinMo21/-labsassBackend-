<?php

/**
 * Quick script to create test JSON files with 10 records
 * Uses the LegacyDataSeeder's loadJsonFile method
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create a temporary seeder instance to use its loadJsonFile method
$seeder = new \Database\Seeders\LegacyDataSeeder(new class {
    public function info($message) { echo $message . "\n"; }
    public function warn($message) { echo $message . "\n"; }
    public function getOutput() {
        return new class {
            public function write($message) { echo $message; }
        };
    }
});

// Use reflection to access the private loadJsonFile method
$reflection = new ReflectionClass($seeder);
$method = $reflection->getMethod('loadJsonFile');
$method->setAccessible(true);

echo "Loading patient.json...\n";
$patientData = $method->invoke($seeder, base_path('seedes/patient.json'));
echo "Found " . count($patientData) . " patient records\n";

echo "Loading patholgy.json...\n";
$pathologyData = $method->invoke($seeder, base_path('seedes/patholgy.json'));
echo "Found " . count($pathologyData) . " pathology records\n";

// Take first 10 records
$testPatientData = array_slice($patientData, 0, 10);
$testPathologyData = array_slice($pathologyData, 0, 10);

// Save to test files
$patientTestFile = base_path('seedes/patient_test.json');
$pathologyTestFile = base_path('seedes/patholgy_test.json');

file_put_contents($patientTestFile, json_encode($testPatientData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($pathologyTestFile, json_encode($testPathologyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n✓ Created {$patientTestFile} with " . count($testPatientData) . " records\n";
echo "✓ Created {$pathologyTestFile} with " . count($testPathologyData) . " records\n";
echo "\nYou can now run: php artisan db:seed --class=LegacyDataSeeder\n";


