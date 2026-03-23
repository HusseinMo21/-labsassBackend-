<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabTest;
use App\Models\TestCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LabTestController extends Controller
{
    /**
     * Master catalog (lab_tests) is maintained by platform admins only.
     */
    protected function ensurePlatformMayEditMasterCatalog(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if ($user && $user->lab_id !== null) {
            return response()->json([
                'message' => 'Only platform administrators can create, update, or delete master catalog tests.',
            ], 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        $query = LabTest::with('category');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        $tests = $query->latest()->paginate(15);

        return response()->json($tests);
    }

    public function store(Request $request)
    {
        if ($r = $this->ensurePlatformMayEditMasterCatalog($request)) {
            return $r;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:lab_tests,code',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'reference_range' => 'nullable|string|max:255',
            'preparation_instructions' => 'nullable|string',
            'turnaround_time_hours' => 'required|integer|min:1',
            'category_id' => 'required|exists:test_categories,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $test = LabTest::create($validator->validated());

        return response()->json([
            'message' => 'Lab test created successfully',
            'test' => $test->load('category'),
        ], 201);
    }

    /**
     * Route: api/tests/{test} — parameter name must be $test for implicit binding.
     */
    public function show(LabTest $test)
    {
        $test->load('category');
        return response()->json($test);
    }

    public function update(Request $request, LabTest $test)
    {
        if ($r = $this->ensurePlatformMayEditMasterCatalog($request)) {
            return $r;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:lab_tests,code,' . $test->id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'reference_range' => 'nullable|string|max:255',
            'preparation_instructions' => 'nullable|string',
            'turnaround_time_hours' => 'required|integer|min:1',
            'category_id' => 'required|exists:test_categories,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $test->update($validator->validated());

        return response()->json([
            'message' => 'Lab test updated successfully',
            'test' => $test->fresh()->load('category'),
        ]);
    }

    public function destroy(Request $request, LabTest $test)
    {
        if ($r = $this->ensurePlatformMayEditMasterCatalog($request)) {
            return $r;
        }

        if ($test->visitTests()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete test with existing orders',
            ], 422);
        }

        $test->delete();

        return response()->json([
            'message' => 'Lab test deleted successfully',
        ]);
    }

    public function categories()
    {
        $categories = TestCategory::withCount('labTests')
            ->where('is_active', true)
            ->get();

        return response()->json($categories);
    }
} 