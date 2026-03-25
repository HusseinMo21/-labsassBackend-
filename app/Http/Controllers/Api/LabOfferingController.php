<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\LabTest;
use App\Models\LabTestOffering;
use Illuminate\Http\Request;

class LabOfferingController extends Controller
{
    private function authorizeLab(Request $request, Lab $lab)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if ($user->lab_id !== null && (int) $user->lab_id !== (int) $lab->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    public function index(Request $request, Lab $lab)
    {
        if ($e = $this->authorizeLab($request, $lab)) {
            return $e;
        }

        $q = LabTestOffering::query()
            ->where('lab_id', $lab->id)
            ->with(['labTest' => fn ($q) => $q->with('category')]);

        if ($request->boolean('active_only')) {
            $q->where('is_active', true);
        }

        if ($request->filled('search')) {
            $s = $request->get('search');
            $q->where(function ($outer) use ($s) {
                $outer->where('display_name', 'like', "%{$s}%")
                    ->orWhereHas('labTest', function ($qq) use ($s) {
                        $qq->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
                    });
            });
        }

        return $q->orderBy('id')->paginate(min((int) $request->get('per_page', 50), 200));
    }

    public function store(Request $request, Lab $lab)
    {
        if ($e = $this->authorizeLab($request, $lab)) {
            return $e;
        }

        $data = $request->validate([
            'lab_test_id' => 'required|integer|exists:lab_tests,id',
            'price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'display_name' => 'nullable|string|max:255',
        ]);

        $test = LabTest::findOrFail($data['lab_test_id']);
        if ($test->lab_id !== null && (int) $test->lab_id !== (int) $lab->id) {
            return response()->json([
                'message' => 'This test belongs to another lab and cannot be linked here.',
            ], 422);
        }
        $price = isset($data['price']) ? (float) $data['price'] : (float) $test->price;
        $displayName = isset($data['display_name']) ? trim((string) $data['display_name']) : null;
        if ($displayName === '') {
            $displayName = null;
        }

        $offering = LabTestOffering::updateOrCreate(
            [
                'lab_id' => $lab->id,
                'lab_test_id' => $test->id,
            ],
            [
                'price' => $price,
                'is_active' => $data['is_active'] ?? true,
                'display_name' => $displayName,
            ]
        );

        return response()->json([
            'message' => 'Offering saved',
            'data' => $offering->load(['labTest.category']),
        ], 201);
    }

    public function update(Request $request, Lab $lab, int $offering)
    {
        if ($e = $this->authorizeLab($request, $lab)) {
            return $e;
        }

        $row = LabTestOffering::where('lab_id', $lab->id)->where('id', $offering)->firstOrFail();

        $data = $request->validate([
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'display_name' => 'nullable|string|max:255',
        ]);

        if (array_key_exists('display_name', $data)) {
            $dn = trim((string) $data['display_name']);
            $data['display_name'] = $dn === '' ? null : $dn;
        }

        $row->update($data);

        return response()->json([
            'message' => 'Offering updated',
            'data' => $row->fresh()->load(['labTest.category']),
        ]);
    }
}
