<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\LabPackage;
use App\Models\LabTestCategorySetting;
use App\Models\LabTestOffering;
use App\Services\LabCatalogCategoryService;
use Illuminate\Http\Request;

class LabCatalogController extends Controller
{
    public function __construct(
        protected LabCatalogCategoryService $labCatalogCategoryService
    ) {}
    /**
     * Single catalog endpoint: categories, tests (with per-lab price), packages.
     * GET /api/labs/{lab}/catalog
     */
    public function show(Request $request, Lab $lab)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->lab_id !== null && (int) $user->lab_id !== (int) $lab->id) {
            return response()->json(['message' => 'You do not have access to this lab catalog.'], 403);
        }

        $offerings = LabTestOffering::query()
            ->where('lab_id', $lab->id)
            ->where('is_active', true)
            ->with(['labTest' => function ($q) {
                $q->where('is_active', true)->with('category:id,name,code,lab_id');
            }])
            ->get();

        $offerings = $offerings->filter(fn ($o) => $o->labTest !== null);

        // Categories that appear in offerings (have at least one catalog test)
        $categoryIdsFromOfferings = $offerings
            ->pluck('labTest.category_id')
            ->unique()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        // All categories the lab may use (platform visible + lab-owned), so patient registration
        // "one tap" chips match the lab catalog admin — even before tests are added.
        $visibleCategoryIds = $this->labCatalogCategoryService
            ->listVisibleForLab((int) $lab->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $categoryIds = array_values(array_unique(array_merge($visibleCategoryIds, $categoryIdsFromOfferings)));

        $categories = $this->labCatalogCategoryService->resolveForCatalog((int) $lab->id, $categoryIds);

        $hiddenGlobalIds = LabTestCategorySetting::query()
            ->where('lab_id', $lab->id)
            ->where('is_hidden', true)
            ->pluck('test_category_id')
            ->all();

        $catSvc = $this->labCatalogCategoryService;

        $tests = $offerings
            ->filter(function (LabTestOffering $o) use ($lab, $hiddenGlobalIds) {
                $cid = $o->labTest?->category_id;
                if (!$cid) {
                    return true;
                }
                $cat = $o->labTest->category;
                if ($cat && $cat->lab_id !== null && (int) $cat->lab_id !== (int) $lab->id) {
                    return false;
                }
                if ($cat && $cat->lab_id === null && in_array((int) $cid, array_map('intval', $hiddenGlobalIds), true)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->map(function (LabTestOffering $o) use ($lab, $catSvc) {
                $t = $o->labTest;
                $catName = $catSvc->displayNameForCategory(
                    (int) $lab->id,
                    $t->category_id,
                    $t->category?->name
                );

                $catalogName = $o->display_name;
                $catalogName = (is_string($catalogName) && trim($catalogName) !== '') ? trim($catalogName) : $t->name;

                return [
                    'offering_id' => $o->id,
                    'lab_test_id' => $t->id,
                    'name' => $catalogName,
                    'reference_name' => $t->name,
                    'code' => $t->code,
                    'category_id' => $t->category_id,
                    'category_name' => $catName,
                    'price' => (float) $o->price,
                    'unit' => $t->unit,
                    'turnaround_time_hours' => $t->turnaround_time_hours,
                ];
            });

        $packages = LabPackage::query()
            ->activeForLab($lab->id)
            ->with(['items.labTest:id,name,code,category_id'])
            ->orderBy('name')
            ->get()
            ->map(function (LabPackage $p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'description' => $p->description,
                    'package_price' => (float) $p->package_price,
                    'discount_percent' => $p->discount_percent !== null ? (float) $p->discount_percent : null,
                    'valid_from' => $p->valid_from?->format('Y-m-d'),
                    'valid_until' => $p->valid_until?->format('Y-m-d'),
                    'items' => $p->items->map(fn ($item) => [
                        'lab_test_id' => $item->lab_test_id,
                        'quantity' => $item->quantity,
                        'test_name' => $item->labTest?->name,
                        'test_code' => $item->labTest?->code,
                        'name' => $item->labTest?->name,
                        'code' => $item->labTest?->code,
                    ]),
                ];
            });

        return response()->json([
            'lab' => [
                'id' => $lab->id,
                'name' => $lab->name,
                'slug' => $lab->slug,
            ],
            'categories' => $categories,
            'tests' => $tests,
            'packages' => $packages,
        ]);
    }
}
