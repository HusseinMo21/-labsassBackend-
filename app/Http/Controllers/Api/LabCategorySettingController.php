<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\LabTestCategorySetting;
use App\Models\TestCategory;
use Illuminate\Http\Request;

/**
 * Per-lab overrides on platform category templates: hide, rename (display), sort.
 */
class LabCategorySettingController extends Controller
{
    public function upsert(Request $request, Lab $lab, TestCategory $testCategory)
    {
        $user = $request->user();
        if ($user->lab_id !== null && (int) $user->lab_id !== (int) $lab->id) {
            return response()->json(['message' => 'You do not have access to this lab.'], 403);
        }

        if ($testCategory->lab_id !== null) {
            return response()->json([
                'message' => 'Lab-owned categories are edited via test-categories API, not overrides.',
            ], 422);
        }

        $data = $request->validate([
            'is_hidden' => 'sometimes|boolean',
            'display_name' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $row = LabTestCategorySetting::updateOrCreate(
            [
                'lab_id' => $lab->id,
                'test_category_id' => $testCategory->id,
            ],
            $data
        );

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }
}
