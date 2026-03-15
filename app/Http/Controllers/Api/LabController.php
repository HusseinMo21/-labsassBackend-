<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LabController extends Controller
{
    /**
     * List all labs (platform admin only).
     */
    public function index(Request $request)
    {
        $query = Lab::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->has('per_page') ? (int) $request->per_page : 15;
        $labs = $query->latest()->paginate($perPage);

        return response()->json($labs);
    }

    /**
     * Store a new lab (platform admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:labs,slug',
            'subdomain' => 'nullable|string|max:100|unique:labs,subdomain',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $validated['subdomain'] = $validated['subdomain'] ?? Str::slug($validated['slug']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $lab = Lab::create($validated);

        return response()->json($lab, 201);
    }

    /**
     * Show a single lab (platform admin only).
     */
    public function show(Lab $lab)
    {
        return response()->json($lab);
    }

    /**
     * Update a lab (platform admin only).
     */
    public function update(Request $request, Lab $lab)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('labs')->ignore($lab->id)],
            'subdomain' => ['nullable', 'string', 'max:100', Rule::unique('labs')->ignore($lab->id)],
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Invalidate cache before update (ResolveLab caches by subdomain/slug)
        if (isset($validated['subdomain']) || isset($validated['slug'])) {
            $oldSubdomain = $lab->subdomain;
            $oldSlug = $lab->slug;
            if ($oldSubdomain) {
                Cache::forget("lab:{$oldSubdomain}");
            }
            if ($oldSlug && $oldSlug !== $oldSubdomain) {
                Cache::forget("lab:{$oldSlug}");
            }
        }

        $lab->update($validated);

        return response()->json($lab);
    }

    /**
     * Delete a lab (platform admin only).
     * Soft delete or prevent if lab has data - for now we allow delete.
     */
    public function destroy(Lab $lab)
    {
        $lab->delete();

        // Invalidate cache
        if ($lab->subdomain) {
            Cache::forget("lab:{$lab->subdomain}");
        }
        if ($lab->slug) {
            Cache::forget("lab:{$lab->slug}");
        }

        return response()->json(null, 204);
    }
}
