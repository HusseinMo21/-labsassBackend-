<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestCategory;
use App\Services\LabCatalogCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TestCategoryController extends Controller
{
    /**
     * Display a listing of test categories (hybrid: global templates + lab-owned; lab sees overrides).
     */
    public function index()
    {
        $labId = $this->currentLabId();
        if ($labId) {
            $list = app(LabCatalogCategoryService::class)->listVisibleForLab($labId);
            $data = $list->map(function (TestCategory $c) {
                return [
                    'id' => $c->id,
                    'name' => $c->getAttribute('display_name') ?? $c->name,
                    'code' => $c->code,
                    'description' => $c->description,
                    'is_active' => $c->is_active,
                    'lab_id' => $c->lab_id,
                    'sort_order' => $c->getAttribute('sort_order'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        $categories = TestCategory::query()->globalTemplates()->active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created test category
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $labId = $request->input('lab_id');
        if ($user && $user->lab_id) {
            $labId = (int) $user->lab_id;
        } else {
            $labId = $labId !== null && $labId !== '' ? (int) $labId : null;
        }

        // Platform admin: only global template categories
        if ($user && $user->lab_id === null) {
            $labId = null;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('test_categories', 'code')->where(fn ($q) => $q->where('lab_id', $labId)),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $payload['lab_id'] = $labId;
        $category = TestCategory::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Test category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified test category
     */
    public function show($id)
    {
        $category = TestCategory::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Update the specified test category
     */
    public function update(Request $request, $id)
    {
        $category = TestCategory::findOrFail($id);
        $user = $request->user();

        if ($user && $user->lab_id === null && $category->lab_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Platform admin can only edit global template categories.',
            ], 403);
        }

        if ($user && $user->lab_id) {
            if ($category->lab_id === null || (int) $category->lab_id !== (int) $user->lab_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit categories owned by your lab.',
                ], 403);
            }
        }

        $labId = $category->lab_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('test_categories', 'code')
                    ->where(fn ($q) => $q->where('lab_id', $labId))
                    ->ignore($id),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Test category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified test category
     */
    public function destroy(Request $request, $id)
    {
        $category = TestCategory::findOrFail($id);
        $user = $request->user();
        if ($user && $user->lab_id) {
            if ($category->lab_id === null || (int) $category->lab_id !== (int) $user->lab_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete categories owned by your lab.',
                ], 403);
            }
        }

        if ($user && $user->lab_id === null && $category->lab_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Only lab admins can delete lab-specific categories.',
            ], 403);
        }

        // Check if category is being used
        if ($category->visitTests()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category. It is being used by existing tests.',
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Test category deleted successfully'
        ]);
    }

    /**
     * Get main categories for check-in form
     */
    public function getMainCategories()
    {
        $labId = $this->currentLabId();
        if ($labId) {
            $list = app(LabCatalogCategoryService::class)->listVisibleForLab($labId);

            return response()->json([
                'success' => true,
                'data' => $list->map(fn (TestCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->getAttribute('display_name') ?? $c->name,
                    'code' => $c->code,
                    'lab_id' => $c->lab_id,
                ]),
            ]);
        }

        $categories = TestCategory::query()->globalTemplates()->active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}


