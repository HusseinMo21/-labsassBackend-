<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $organizations = $query->withCount('patients')
            ->latest()
            ->paginate(15);

        return response()->json($organizations);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organization = Organization::create($validator->validated());

        return response()->json([
            'message' => 'Organization created successfully',
            'organization' => $organization,
        ], 201);
    }

    public function show(Organization $organization)
    {
        $organization->loadCount('patients');
        return response()->json($organization);
    }

    public function update(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organization->update($validator->validated());

        return response()->json([
            'message' => 'Organization updated successfully',
            'organization' => $organization->fresh(),
        ]);
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully',
        ]);
    }

    public function patients(Organization $organization)
    {
        $patients = $organization->patients()
            ->with(['visits' => function ($q) {
                $q->orderBy('id', 'desc')->take(5);
            }])
            ->withCount('visits')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'organization' => $organization,
            'patients' => $patients,
        ]);
    }

    public function search(Request $request)
    {
        $search = $request->get('q', '');
        
        if (empty($search)) {
            return response()->json(['organizations' => []]);
        }

        $organizations = Organization::where('name', 'like', "%{$search}%")
            ->withCount('patients')
            ->limit(10)
            ->get();

        return response()->json(['organizations' => $organizations]);
    }
}
