<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\LabTest;
use App\Models\LabTestOffering;
use App\Services\LabCatalogCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lab-owned reference tests: create under a visible category and attach a catalog offering in one step.
 */
class LabCatalogLabTestController extends Controller
{
    public function store(Request $request, Lab $lab)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if ($user->lab_id !== null && (int) $user->lab_id !== (int) $lab->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $allowedIds = app(LabCatalogCategoryService::class)
            ->listVisibleForLab((int) $lab->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('lab_tests', 'code')->where(fn ($q) => $q->where('lab_id', $lab->id)),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'reference_range' => ['nullable', 'string', 'max:255'],
            'preparation_instructions' => ['nullable', 'string'],
            'turnaround_time_hours' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'report_template' => ['nullable', 'array'],
        ]);

        if (! in_array((int) $data['category_id'], $allowedIds, true)) {
            throw ValidationException::withMessages([
                'category_id' => ['This category is not available for your lab.'],
            ]);
        }

        $sale = isset($data['sale_price']) ? (float) $data['sale_price'] : (float) $data['price'];
        $displayName = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
        $displayName = $displayName === '' ? null : $displayName;

        $payload = [
            'lab_id' => $lab->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'unit' => $data['unit'] ?? null,
            'reference_range' => $data['reference_range'] ?? null,
            'preparation_instructions' => $data['preparation_instructions'] ?? null,
            'turnaround_time_hours' => $data['turnaround_time_hours'] ?? 24,
            'category_id' => $data['category_id'],
            'is_active' => $data['is_active'] ?? true,
            'report_template' => $data['report_template'] ?? null,
        ];

        return DB::transaction(function () use ($lab, $payload, $sale, $displayName) {
            $test = LabTest::create($payload);
            $offering = LabTestOffering::create([
                'lab_id' => $lab->id,
                'lab_test_id' => $test->id,
                'price' => $sale,
                'is_active' => true,
                'display_name' => $displayName,
            ]);

            return response()->json([
                'message' => 'Test added to catalog',
                'data' => $offering->load(['labTest.category']),
            ], 201);
        });
    }
}
