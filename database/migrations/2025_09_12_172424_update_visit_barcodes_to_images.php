<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Visit;
use App\Services\BarcodeGenerator;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing visits that have HTML barcodes to use image barcodes
        $visits = Visit::whereNotNull('barcode')
                      ->where('barcode', 'like', '<div%')
                      ->get();

        $barcodeGenerator = new BarcodeGenerator();

        foreach ($visits as $visit) {
            try {
                // Generate a new image barcode for the visit (lab_id from visit or 1 for legacy)
                $labId = $visit->lab_id ?? 1;
                $barcodePath = $barcodeGenerator->generateBarcode($visit->visit_number, 'CODE128', $labId);
                
                // Update the visit with the new barcode path
                $visit->update(['barcode' => $barcodePath]);
                
                echo "Updated visit {$visit->id} with new barcode: {$barcodePath}\n";
            } catch (\Exception $e) {
                echo "Failed to update visit {$visit->id}: {$e->getMessage()}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we can't restore the original HTML barcodes
        // without knowing what they were
    }
};