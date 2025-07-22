<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CriticalValue;
use App\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CriticalValueController extends Controller
{
    public function index(Request $request)
    {
        $query = CriticalValue::with('labTest');

        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('labTest', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $criticalValues = $query->latest()->paginate(15);
        return response()->json($criticalValues);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lab_test_id' => 'required|exists:lab_tests,id',
            'critical_low' => 'nullable|numeric',
            'critical_high' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'notification_message' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if critical value already exists for this test
        $existing = CriticalValue::where('lab_test_id', $request->lab_test_id)->first();
        if ($existing) {
            return response()->json(['message' => 'Critical value already exists for this test'], 400);
        }

        $criticalValue = CriticalValue::create($validator->validated());
        return response()->json($criticalValue->load('labTest'), 201);
    }

    public function show($id)
    {
        $criticalValue = CriticalValue::with('labTest')->findOrFail($id);
        return response()->json($criticalValue);
    }

    public function update(Request $request, $id)
    {
        $criticalValue = CriticalValue::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'critical_low' => 'nullable|numeric',
            'critical_high' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'notification_message' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $criticalValue->update($validator->validated());
        return response()->json($criticalValue->load('labTest'));
    }

    public function destroy($id)
    {
        $criticalValue = CriticalValue::findOrFail($id);
        $criticalValue->delete();
        return response()->json(['message' => 'Critical value deleted successfully']);
    }

    public function checkCriticalValue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lab_test_id' => 'required|exists:lab_tests,id',
            'value' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $criticalValue = CriticalValue::where('lab_test_id', $request->lab_test_id)
                                    ->where('is_active', true)
                                    ->first();

        if (!$criticalValue) {
            return response()->json(['is_critical' => false]);
        }

        $isCritical = $criticalValue->isCritical($request->value);
        $criticalType = $criticalValue->getCriticalType($request->value);

        return response()->json([
            'is_critical' => $isCritical,
            'critical_type' => $criticalType,
            'critical_value' => $criticalValue,
        ]);
    }

    public function getByTest($testId)
    {
        $criticalValue = CriticalValue::where('lab_test_id', $testId)->first();
        
        if (!$criticalValue) {
            return response()->json(['message' => 'Critical value not found'], 404);
        }

        return response()->json($criticalValue);
    }
} 