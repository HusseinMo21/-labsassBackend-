<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $query = Plan::query();

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $plans = $query->orderBy('price')->get();

        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:plans,slug',
            'price' => 'required|numeric|min:0',
            'price_period' => 'required|in:monthly,yearly',
            'max_users' => 'nullable|integer|min:0',
            'max_tests_per_month' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $plan = Plan::create($validated);

        return response()->json($plan, 201);
    }

    public function show(Plan $plan)
    {
        return response()->json($plan);
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:plans,slug,' . $plan->id,
            'price' => 'sometimes|numeric|min:0',
            'price_period' => 'sometimes|in:monthly,yearly',
            'max_users' => 'nullable|integer|min:0',
            'max_tests_per_month' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $plan->update($validated);

        return response()->json($plan);
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete plan with existing subscriptions.',
            ], 422);
        }

        $plan->delete();

        return response()->json(null, 204);
    }
}
