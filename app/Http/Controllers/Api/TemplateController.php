<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            // If no user is authenticated, return all templates (for testing purposes)
            if (!$user) {
                $templates = Template::with('createdBy')->orderBy('name')->get();
                return response()->json([
                    'success' => true,
                    'data' => $templates
                ]);
            }
            
            // Get templates based on user role
            if ($user->role === 'admin') {
                // Admins can see all templates
                $templates = Template::with('createdBy')->orderBy('name')->get();
            } else {
                // Staff and doctors can see public templates and their own
                $templates = Template::with('createdBy')
                    ->where(function ($query) use ($user) {
                        $query->where('created_by', $user->id)
                              ->orWhereHas('createdBy', function ($q) {
                                  $q->whereIn('role', ['admin', 'doctor']);
                              });
                    })
                    ->orderBy('name')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'clinical_data' => 'nullable|string',
                'specimen_information' => 'nullable|string',
                'gross_examination' => 'nullable|string',
                'microscopic' => 'nullable|string',
                'microscopic_description' => 'nullable|string',
                'diagnosis' => 'nullable|string',
                'recommendations' => 'nullable|string',
                'referred_doctor' => 'nullable|string|max:255',
                'type_of_analysis' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $template = Template::create([
                'name' => $request->name,
                'clinical_data' => $request->clinical_data,
                'specimen_information' => $request->specimen_information,
                'gross_examination' => $request->gross_examination,
                'microscopic' => $request->microscopic ?? $request->microscopic_description, // Support both field names
                'microscopic_description' => $request->microscopic_description ?? $request->microscopic, // Support both field names
                'diagnosis' => $request->diagnosis,
                'recommendations' => $request->recommendations,
                'referred_doctor' => $request->referred_doctor,
                'type_of_analysis' => $request->type_of_analysis,
                'created_by' => Auth::id(),
            ]);

            $template->load('createdBy');

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Template $template)
    {
        try {
            $template->load('createdBy');
            
            return response()->json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Template $template)
    {
        try {
            $user = Auth::user();
            
            // Check if user can edit this template
            if ($user->role !== 'admin' && $template->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own templates'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'clinical_data' => 'nullable|string',
                'specimen_information' => 'nullable|string',
                'gross_examination' => 'nullable|string',
                'microscopic' => 'nullable|string',
                'microscopic_description' => 'nullable|string',
                'diagnosis' => 'nullable|string',
                'recommendations' => 'nullable|string',
                'referred_doctor' => 'nullable|string|max:255',
                'type_of_analysis' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $template->update([
                'name' => $request->name,
                'clinical_data' => $request->clinical_data,
                'specimen_information' => $request->specimen_information,
                'gross_examination' => $request->gross_examination,
                'microscopic' => $request->microscopic ?? $request->microscopic_description,
                'microscopic_description' => $request->microscopic_description ?? $request->microscopic,
                'diagnosis' => $request->diagnosis,
                'recommendations' => $request->recommendations,
                'referred_doctor' => $request->referred_doctor,
                'type_of_analysis' => $request->type_of_analysis,
            ]);

            $template->load('createdBy');

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Template $template)
    {
        try {
            $user = Auth::user();
            
            // Check if user can delete this template
            if ($user->role !== 'admin' && $template->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own templates'
                ], 403);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a template from report data.
     */
    public function createFromReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'clinical_data' => 'nullable|string',
                'specimen_information' => 'nullable|string',
                'gross_examination' => 'nullable|string',
                'microscopic' => 'nullable|string',
                'microscopic_description' => 'nullable|string',
                'diagnosis' => 'nullable|string',
                'recommendations' => 'nullable|string',
                'referred_doctor' => 'nullable|string|max:255',
                'type_of_analysis' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $template = Template::create([
                'name' => $request->name,
                'clinical_data' => $request->clinical_data,
                'specimen_information' => $request->specimen_information,
                'gross_examination' => $request->gross_examination,
                'microscopic' => $request->microscopic ?? $request->microscopic_description,
                'microscopic_description' => $request->microscopic_description ?? $request->microscopic,
                'diagnosis' => $request->diagnosis,
                'recommendations' => $request->recommendations,
                'referred_doctor' => $request->referred_doctor,
                'type_of_analysis' => $request->type_of_analysis,
                'created_by' => Auth::id(),
            ]);

            $template->load('createdBy');

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully from report data',
                'data' => $template
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template from report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
