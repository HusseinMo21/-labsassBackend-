<?php

namespace App\Services;

use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\LabTestOffering;
use App\Models\Visit;
use App\Models\VisitTest;
use Illuminate\Support\Str;

/**
 * Single place to create visit_tests from lab catalog (POST /patients + patient-registration).
 * Persists price_at_time so invoices/reports stay correct after catalog price changes.
 */
class CatalogVisitTestWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $catalogTests
     * @param  array<int, array<string, mixed>>  $catalogPackages
     * @return int Number of visit_tests created
     */
    public function write(Visit $visit, int $labId, array $catalogTests, array $catalogPackages): int
    {
        $created = 0;

        foreach ($catalogTests as $row) {
            $labTestId = $row['lab_test_id'] ?? null;
            if (!$labTestId) {
                continue;
            }
            $offeringId = $row['offering_id'] ?? null;
            $price = isset($row['price']) ? (float) $row['price'] : null;

            $offering = null;
            if ($offeringId) {
                $offering = LabTestOffering::where('id', $offeringId)
                    ->where('lab_id', $labId)
                    ->where('lab_test_id', $labTestId)
                    ->where('is_active', true)
                    ->first();
            }
            if (!$offering) {
                $offering = LabTestOffering::where('lab_id', $labId)
                    ->where('lab_test_id', $labTestId)
                    ->where('is_active', true)
                    ->first();
            }
            $labTest = LabTest::find($labTestId);
            if (!$labTest || !$offering) {
                continue;
            }
            $linePrice = $price !== null ? $price : (float) $offering->price;

            $customName = $offering->display_name;
            $customName = (is_string($customName) && trim($customName) !== '') ? trim($customName) : null;

            VisitTest::create([
                'visit_id' => $visit->id,
                'lab_id' => $labId,
                'lab_test_id' => $labTestId,
                'test_category_id' => $labTest->category_id,
                'custom_test_name' => $customName,
                'status' => 'pending',
                'price' => $linePrice,
                'price_at_time' => $linePrice,
                'barcode_uid' => 'LAB-' . strtoupper(Str::random(8)),
            ]);
            $created++;
        }

        foreach ($catalogPackages as $row) {
            $packageId = $row['package_id'] ?? null;
            if (!$packageId) {
                continue;
            }
            $package = LabPackage::with('items')
                ->where('id', $packageId)
                ->where('lab_id', $labId)
                ->where('is_active', true)
                ->first();
            if (!$package || $package->items->isEmpty()) {
                continue;
            }
            $packagePrice = isset($row['price']) ? (float) $row['price'] : (float) $package->package_price;
            $totalUnits = $package->items->sum(fn ($i) => max(1, (int) $i->quantity));
            if ($totalUnits < 1) {
                $totalUnits = 1;
            }

            foreach ($package->items as $item) {
                $labTest = LabTest::find($item->lab_test_id);
                if (!$labTest) {
                    continue;
                }
                $qty = max(1, (int) $item->quantity);
                $linePrice = ($packagePrice * $qty) / $totalUnits;

                VisitTest::create([
                    'visit_id' => $visit->id,
                    'lab_id' => $labId,
                    'lab_test_id' => $item->lab_test_id,
                    'test_category_id' => $labTest->category_id,
                    'status' => 'pending',
                    'price' => $linePrice,
                    'price_at_time' => $linePrice,
                    'barcode_uid' => 'LAB-' . strtoupper(Str::random(8)),
                ]);
                $created++;
            }
        }

        return $created;
    }
}
