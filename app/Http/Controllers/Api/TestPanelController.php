<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestPanel;
use App\Models\TestPanelItem;
use App\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestPanelController extends Controller
{
    public function index(Request $request)
    {
        $query = TestPanel::with(['labTests.category']);

        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $panels = $query->latest()->paginate(15);
        return response()->json($panels);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:test_panels,code',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'tests' => 'required|array|min:1',
            'tests.*.lab_test_id' => 'required|exists:lab_tests,id',
            'tests.*.is_required' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $panel = TestPanel::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'price' => $request->price,
            'is_active' => $request->is_active ?? true,
        ]);

        // Add tests to panel
        foreach ($request->tests as $index => $test) {
            $panel->addTest($test['lab_test_id'], $index + 1, $test['is_required'] ?? true);
        }

        return response()->json($panel->load(['labTests.category']), 201);
    }

    public function show($id)
    {
        $panel = TestPanel::with(['labTests.category'])->findOrFail($id);
        return response()->json($panel);
    }

    public function update(Request $request, $id)
    {
        $panel = TestPanel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:test_panels,code,' . $id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $panel->update($validator->validated());
        return response()->json($panel->load(['labTests.category']));
    }

    public function destroy($id)
    {
        $panel = TestPanel::findOrFail($id);
        $panel->delete();
        return response()->json(['message' => 'Test panel deleted successfully']);
    }

    public function addTest(Request $request, $id)
    {
        $panel = TestPanel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'lab_test_id' => 'required|exists:lab_tests,id',
            'is_required' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if test already exists in panel
        $existing = $panel->panelItems()->where('lab_test_id', $request->lab_test_id)->first();
        if ($existing) {
            return response()->json(['message' => 'Test already exists in panel'], 400);
        }

        $panel->addTest($request->lab_test_id, null, $request->is_required ?? true);
        
        return response()->json($panel->load(['labTests.category']));
    }

    public function removeTest($id, $testId)
    {
        $panel = TestPanel::findOrFail($id);
        $panel->removeTest($testId);
        
        return response()->json($panel->load(['labTests.category']));
    }

    public function reorderTests(Request $request, $id)
    {
        $panel = TestPanel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'test_ids' => 'required|array|min:1',
            'test_ids.*' => 'exists:lab_tests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $panel->reorderTests($request->test_ids);
        
        return response()->json($panel->load(['labTests.category']));
    }

    public function getAvailableTests()
    {
        $tests = LabTest::with('category')
                       ->where('is_active', true)
                       ->orderBy('name')
                       ->get();

        return response()->json($tests);
    }

    public function getStats()
    {
        $stats = [
            'total_panels' => TestPanel::count(),
            'active_panels' => TestPanel::where('is_active', true)->count(),
            'total_tests_in_panels' => TestPanelItem::count(),
            'average_tests_per_panel' => TestPanel::withCount('labTests')->get()->avg('lab_tests_count'),
        ];

        return response()->json($stats);
    }
} 