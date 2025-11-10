<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing file paths:\n\n";

$patientFile = base_path('seedes/patient.json');
$pathologyFile = base_path('seedes/patholgy.json');

echo "Patient file: {$patientFile}\n";
echo "  Exists: " . (file_exists($patientFile) ? 'YES ✓' : 'NO ✗') . "\n";
if (file_exists($patientFile)) {
    echo "  Size: " . number_format(filesize($patientFile) / 1024 / 1024, 2) . " MB\n";
}

echo "\nPathology file: {$pathologyFile}\n";
echo "  Exists: " . (file_exists($pathologyFile) ? 'YES ✓' : 'NO ✗') . "\n";
if (file_exists($pathologyFile)) {
    echo "  Size: " . number_format(filesize($pathologyFile) / 1024 / 1024, 2) . " MB\n";
}

echo "\n";

if (file_exists($patientFile) && file_exists($pathologyFile)) {
    echo "✓ All files found! Ready for seeding.\n";
} else {
    echo "✗ Some files are missing. Please check the paths.\n";
}

