<?php

namespace App\Services;

use App\Models\LabTestCategorySetting;
use App\Models\TestCategory;
use Illuminate\Support\Collection;

/**
 * Hybrid categories: platform templates (lab_id null) + per-lab categories + overrides (hide/rename/sort).
 */
class LabCatalogCategoryService
{
    /**
     * Categories to show in lab catalog filters, for IDs appearing in offerings.
     *
     * @param  array<int>  $categoryIds
     * @return array<int, array{id:int,name:string,code:?string,description:?string,sort_order:?int,is_lab_owned:bool}>
     */
    public function resolveForCatalog(int $labId, array $categoryIds): array
    {
        $ids = array_values(array_unique(array_filter($categoryIds)));
        if ($ids === []) {
            return [];
        }

        $categories = TestCategory::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $settings = LabTestCategorySetting::query()
            ->where('lab_id', $labId)
            ->whereIn('test_category_id', $ids)
            ->get()
            ->keyBy('test_category_id');

        $out = [];
        foreach ($ids as $id) {
            $cat = $categories->get($id);
            if (!$cat) {
                continue;
            }
            // Another lab's private category
            if ($cat->lab_id !== null && (int) $cat->lab_id !== $labId) {
                continue;
            }

            $isLabOwned = $cat->lab_id !== null && (int) $cat->lab_id === $labId;

            if (!$isLabOwned) {
                $st = $settings->get($id);
                if ($st && $st->is_hidden) {
                    continue;
                }
                $name = ($st && $st->display_name) ? $st->display_name : $cat->name;
                $sort = $st?->sort_order ?? $cat->sort_order;
            } else {
                $name = $cat->name;
                $sort = $cat->sort_order;
            }

            $out[] = [
                'id' => (int) $cat->id,
                'name' => $name,
                'code' => $cat->code,
                'description' => $cat->description,
                'sort_order' => $sort,
                'is_lab_owned' => $isLabOwned,
            ];
        }

        usort($out, function ($a, $b) {
            $sa = $a['sort_order'];
            $sb = $b['sort_order'];
            if ($sa !== null || $sb !== null) {
                return ($sa ?? 9999) <=> ($sb ?? 9999);
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $out;
    }

    /**
     * Display name for a category in catalog test rows (after lab overrides).
     */
    public function displayNameForCategory(int $labId, ?int $categoryId, ?string $fallbackName): ?string
    {
        if (!$categoryId) {
            return $fallbackName;
        }
        $cat = TestCategory::query()->where('id', $categoryId)->where('is_active', true)->first();
        if (!$cat) {
            return $fallbackName;
        }
        if ($cat->lab_id !== null && (int) $cat->lab_id !== $labId) {
            return $fallbackName;
        }
        if ($cat->lab_id !== null) {
            return $cat->name;
        }
        $st = LabTestCategorySetting::query()
            ->where('lab_id', $labId)
            ->where('test_category_id', $categoryId)
            ->first();
        if ($st && $st->is_hidden) {
            return $fallbackName;
        }
        if ($st && $st->display_name) {
            return $st->display_name;
        }

        return $cat->name ?? $fallbackName;
    }

    /**
     * For legacy dropdowns: globals visible to lab + lab-owned categories.
     */
    public function listVisibleForLab(int $labId): Collection
    {
        $globals = TestCategory::query()
            ->whereNull('lab_id')
            ->where('is_active', true)
            ->orderByRaw('COALESCE(sort_order, 9999)')
            ->orderBy('name')
            ->get();

        $hiddenIds = LabTestCategorySetting::query()
            ->where('lab_id', $labId)
            ->where('is_hidden', true)
            ->pluck('test_category_id')
            ->all();

        $globals = $globals->reject(fn ($c) => in_array($c->id, $hiddenIds, true));

        $settings = LabTestCategorySetting::query()
            ->where('lab_id', $labId)
            ->whereIn('test_category_id', $globals->pluck('id'))
            ->get()
            ->keyBy('test_category_id');

        $mapped = $globals->map(function (TestCategory $c) use ($settings) {
            $st = $settings->get($c->id);
            $c->setAttribute('display_name', ($st && $st->display_name) ? $st->display_name : $c->name);
            $c->setAttribute('sort_order', $st?->sort_order ?? $c->sort_order);

            return $c;
        });

        $owned = TestCategory::query()
            ->where('lab_id', $labId)
            ->where('is_active', true)
            ->orderByRaw('COALESCE(sort_order, 9999)')
            ->orderBy('name')
            ->get()
            ->map(function (TestCategory $c) {
                $c->setAttribute('display_name', $c->name);
                $c->setAttribute('sort_order', null);

                return $c;
            });

        return $mapped->concat($owned)->sort(function ($a, $b) {
            $oa = $a->getAttribute('sort_order');
            $ob = $b->getAttribute('sort_order');
            if ($oa !== null || $ob !== null) {
                return ($oa ?? 9999) <=> ($ob ?? 9999);
            }

            return strcmp((string) $a->getAttribute('display_name'), (string) $b->getAttribute('display_name'));
        })->values();
    }
}
