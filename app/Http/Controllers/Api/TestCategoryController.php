<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestCategoryController extends Controller
{
    /**
     * Display a listing of test categories
     */
    public function index()
    {
        $categories = TestCategory::active()->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created test category
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:test_categories,name',
            'code' => 'required|string|max:255|unique:test_categories,code',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = TestCategory::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Test category created successfully',
            'data' => $category
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:test_categories,name,' . $id,
            'code' => 'required|string|max:255|unique:test_categories,code,' . $id,
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
    public function destroy($id)
    {
        $category = TestCategory::findOrFail($id);
        
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
        $categories = TestCategory::active()->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
