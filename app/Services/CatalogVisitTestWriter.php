<?php

namespace App\Services;

use App\Models\LabPackage;
use App\Models\LabTestOffering;
use App\Models\Visit;
use App\Models\VisitTest;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates visit_tests from lab catalog selections (offerings + fixed packages).
 * Server prices: original_price = list/catalog line total; price_at_time = final billed line total;
 * discount_amount = max(0, original_price - price_at_time) per line (e.g. package bundle savings).
 */
class CatalogVisitTestWriter
{
    /**
     * Sum of final line totals (price_at_time) for the given selections — no DB writes.
     */
    public function previewSubtotal(int $labId, array $catalogTests, array $catalogPackages): float
    {
        $rows = $this->expandSelectionsToRows($labId, $catalogTests, $catalogPackages);

        return round(array_sum(array_map(fn ($r) => (float) $r['price_at_time'], $rows)), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalogTests
     * @param  array<int, array<string, mixed>>  $catalogPackages
     * @return int Number of visit_tests created
     */
    public function write(Visit $visit, int $labId, array $catalogTests, array $catalogPackages): int
    {
        $rows = $this->expandSelectionsToRows($labId, $catalogTests, $catalogPackages);
        $created = 0;
        foreach ($rows as $row) {
            VisitTest::create(array_merge($row, [
                'visit_id' => $visit->id,
                'barcode_uid' => 'LAB-' . strtoupper(Str::random(8)),
            ]));
            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalogTests
     * @param  array<int, array<string, mixed>>  $catalogPackages
     * @return list<array<string, mixed>>  Attributes for VisitTest::create (no visit_id / barcode_uid)
     */
    public function expandSelectionsToRows(int $labId, array $catalogTests, array $catalogPackages): array
    {
        $out = [];

        foreach ($catalogTests as $row) {
            $offeringId = isset($row['offering_id']) ? (int) $row['offering_id'] : 0;
            if ($offeringId < 1) {
                continue;
            }

            $offering = LabTestOffering::query()
                ->where('id', $offeringId)
                ->where('lab_id', $labId)
                ->where('is_active', true)
                ->with(['labTest' => fn ($q) => $q->where('is_active', true)])
                ->first();

            if (!$offering || !$offering->labTest) {
                continue;
            }

            $labTest = $offering->labTest;
            $lineList = round((float) $offering->price, 2);
            $snapshotName = $this->snapshotNameFromOffering($offering, $labTest);

            $out[] = [
                'lab_id' => $labId,
                'lab_test_id' => $labTest->id,
                'lab_test_offering_id' => $offering->id,
                'lab_package_id' => null,
                'test_category_id' => $labTest->category_id,
                'custom_test_name' => $snapshotName,
                'test_name_snapshot' => $snapshotName,
                'original_price' => $lineList,
                'discount_amount' => 0.0,
                'price' => $lineList,
                'price_at_time' => $lineList,
                'status' => 'pending',
            ];
        }

        foreach ($catalogPackages as $row) {
            $packageId = isset($row['package_id']) ? (int) $row['package_id'] : 0;
            if ($packageId < 1) {
                continue;
            }

            $package = LabPackage::with(['items'])
                ->activeForLab($labId)
                ->where('id', $packageId)
                ->first();

            if (!$package || $package->items->isEmpty()) {
                continue;
            }

            $packagePrice = isset($row['price']) ? (float) $row['price'] : (float) $package->package_price;
            $packagePrice = round($packagePrice, 2);

            $resolved = [];
            foreach ($package->items as $item) {
                $offering = LabTestOffering::query()
                    ->where('lab_id', $labId)
                    ->where('lab_test_id', $item->lab_test_id)
                    ->where('is_active', true)
                    ->with(['labTest' => fn ($q) => $q->where('is_active', true)])
                    ->first();

                if (!$offering || !$offering->labTest) {
                    throw new InvalidArgumentException(
                        "Package \"{$package->name}\" includes a test that is not in this lab's active catalog (lab_test_id {$item->lab_test_id})."
                    );
                }

                $qty = max(1, (int) $item->quantity);
                $unitList = round((float) $offering->price, 2);
                $lineList = round($unitList * $qty, 2);
                $snapshotName = $this->snapshotNameFromOffering($offering, $offering->labTest);

                $resolved[] = [
                    'offering' => $offering,
                    'labTest' => $offering->labTest,
                    'qty' => $qty,
                    'line_list' => $lineList,
                    'snapshot_name' => $snapshotName,
                ];
            }

            if ($resolved === []) {
                continue;
            }

            $totalWeight = 0.0;
            foreach ($resolved as $r) {
                $totalWeight += $r['line_list'] > 0 ? $r['line_list'] : (float) $r['qty'];
            }
            if ($totalWeight <= 0) {
                $totalWeight = (float) count($resolved);
            }

            $rawAlloc = [];
            foreach ($resolved as $i => $r) {
                $w = $r['line_list'] > 0 ? $r['line_list'] : (float) $r['qty'];
                if ($totalWeight <= 0) {
                    $w = 1.0;
                }
                $rawAlloc[$i] = $packagePrice * ($w / $totalWeight);
            }

            $rounded = [];
            foreach ($rawAlloc as $i => $raw) {
                $rounded[$i] = round($raw, 2);
            }
            $sumRounded = round(array_sum($rounded), 2);
            $drift = round($packagePrice - $sumRounded, 2);
            if (abs($drift) >= 0.01 && $rounded !== []) {
                $lastKey = array_key_last($rounded);
                $rounded[$lastKey] = round($rounded[$lastKey] + $drift, 2);
            }

            foreach ($resolved as $i => $r) {
                $allocated = $rounded[$i] ?? 0.0;
                $original = $r['line_list'];
                $lineDiscount = max(0, round($original - $allocated, 2));

                $out[] = [
                    'lab_id' => $labId,
                    'lab_test_id' => $r['labTest']->id,
                    'lab_test_offering_id' => $r['offering']->id,
                    'lab_package_id' => $package->id,
                    'test_category_id' => $r['labTest']->category_id,
                    'custom_test_name' => $r['snapshot_name'],
                    'test_name_snapshot' => $r['snapshot_name'],
                    'original_price' => $original,
                    'discount_amount' => $lineDiscount,
                    'price' => $allocated,
                    'price_at_time' => $allocated,
                    'status' => 'pending',
                ];
            }
        }

        return $out;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertPackageResolvableForLab(int $labId, int $packageId): void
    {
        $package = LabPackage::with('items')->activeForLab($labId)->where('id', $packageId)->first();
        if (!$package) {
            throw new InvalidArgumentException('Package not found, inactive, or not valid for this lab/date.');
        }
        foreach ($package->items as $item) {
            $exists = LabTestOffering::query()
                ->where('lab_id', $labId)
                ->where('lab_test_id', $item->lab_test_id)
                ->where('is_active', true)
                ->exists();
            if (!$exists) {
                throw new InvalidArgumentException(
                    "Package \"{$package->name}\" requires catalog offering for lab_test_id {$item->lab_test_id}."
                );
            }
        }
    }

    private function snapshotNameFromOffering(LabTestOffering $offering, $labTest): string
    {
        $displayFromOffering = $offering->display_name;
        if (is_string($displayFromOffering) && trim($displayFromOffering) !== '') {
            return trim($displayFromOffering);
        }

        return (string) $labTest->name;
    }

    /**
     * After visit_tests are written from the catalog, align visit totals with summed price_at_time.
     */
    public function syncVisitTotalsFromVisitTests(Visit $visit): void
    {
        $visit->load('visitTests');
        if ($visit->visitTests->isEmpty()) {
            return;
        }

        $sum = round((float) $visit->visitTests->sum(fn ($vt) => (float) $vt->price_at_time), 2);
        $paid = (float) ($visit->upfront_payment ?? 0);
        $billing = $paid >= $sum && $sum > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $md = $visit->metadata;
        if (!is_array($md)) {
            $md = [];
        }
        $md['financial_data'] = array_merge($md['financial_data'] ?? [], [
            'total_amount' => $sum,
            'final_amount' => $sum,
            'amount_paid' => $paid,
            'remaining_balance' => max(0, round($sum - $paid, 2)),
            'payment_status' => $billing,
        ]);

        $visit->update([
            'total_amount' => $sum,
            'final_amount' => $sum,
            'billing_status' => $billing,
            'metadata' => $md,
        ]);
    }
}
