<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Fixing invalid dates in payments table...\n";
    
    // Fix invalid dates
    DB::statement("UPDATE payments SET date = NULL WHERE date = '0000-00-00'");
    echo "Fixed invalid dates\n";
    
    // Now add the invoice_id column
    echo "Adding invoice_id column to payments table...\n";
    DB::statement('ALTER TABLE payments ADD COLUMN invoice_id int(11) NULL');
    DB::statement('ALTER TABLE payments ADD KEY payments_invoice_id_foreign (invoice_id)');
    DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL');
    echo "invoice_id column added to payments table\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
