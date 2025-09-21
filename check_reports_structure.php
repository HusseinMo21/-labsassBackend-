<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Checking reports table structure...\n";
    
    $columns = DB::select('DESCRIBE reports');
    echo "Reports table columns:\n";
    foreach ($columns as $col) {
        echo "- " . $col->Field . " (" . $col->Type . ") " . ($col->Null === 'NO' ? 'NOT NULL' : 'NULL') . " " . ($col->Default ? "DEFAULT " . $col->Default : '') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}







