<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Visit;

echo "=== Visit Date Range Check ===\n\n";

$min = Visit::min('visit_date');
$max = Visit::max('visit_date');

echo "Min Visit Date: {$min}\n";
echo "Max Visit Date: {$max}\n\n";

echo "Visits in date range 2025-10-31 to 2025-11-29: " . 
    Visit::whereBetween('visit_date', ['2025-10-31', '2025-11-29'])
        ->where('status', '!=', 'completed')
        ->whereHas('labRequest.reports')
        ->count() . "\n\n";

echo "Visits WITHOUT date filter (all non-completed with reports): " . 
    Visit::where('status', '!=', 'completed')
        ->whereHas('labRequest.reports')
        ->count() . "\n\n";

echo "Sample visit dates:\n";
$samples = Visit::select('visit_date')->orderBy('visit_date', 'desc')->limit(10)->get();
foreach ($samples as $sample) {
    echo "  {$sample->visit_date}\n";
}

echo "\nDone!\n";

