<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;

echo "=== Testing API Endpoints ===\n\n";

$controller = new ReportController();

// Test patients endpoint
echo "1. Testing /api/reports/patients\n";
$request = new Request();
$response = $controller->patients($request);
$data = json_decode($response->getContent(), true);
echo "  Summary keys: " . implode(', ', array_keys($data['summary'] ?? [])) . "\n";
echo "  total_patients: " . ($data['summary']['total_patients'] ?? 'NOT FOUND') . "\n\n";

// Test tests endpoint
echo "2. Testing /api/reports/tests\n";
$request = new Request();
$response = $controller->tests($request);
$data = json_decode($response->getContent(), true);
echo "  Summary keys: " . implode(', ', array_keys($data['summary'] ?? [])) . "\n";
echo "  total_tests: " . ($data['summary']['total_tests'] ?? 'NOT FOUND') . "\n";
echo "  completed_tests: " . ($data['summary']['completed_tests'] ?? 'NOT FOUND') . "\n\n";

// Test financial endpoint
echo "3. Testing /api/reports/financial\n";
$request = new Request();
$response = $controller->financial($request);
$data = json_decode($response->getContent(), true);
echo "  Summary keys: " . implode(', ', array_keys($data['summary'] ?? [])) . "\n";
echo "  total_revenue: " . ($data['summary']['total_revenue'] ?? 'NOT FOUND') . "\n\n";

echo "Done!\n";

