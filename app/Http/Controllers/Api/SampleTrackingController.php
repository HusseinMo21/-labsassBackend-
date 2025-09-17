<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Models\VisitTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SampleTrackingController extends Controller
{
    public function index(Request $request)
    {
        $query = Sample::with(['labRequest.patient', 'collectedBy', 'receivedBy', 'processedBy', 'analyzedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sample_id', 'like', "%{$search}%")
                  ->orWhere('sample_type', 'like', "%{$search}%")
                  ->orWhereHas('labRequest.patient', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $samples = $query->latest()->paginate(15);
        return response()->json($samples);
    }

    public function show($id)
    {
        $sample = Sample::with([
            'labRequest.patient', 
            'collectedBy', 
            'receivedBy', 
            'processedBy', 
            'analyzedBy',
            'disposedBy'
        ])->findOrFail($id);

        return response()->json($sample);
    }

    public function createSample($labRequestId)
    {
        $labRequest = \App\Models\LabRequest::findOrFail($labRequestId);
        
        // Check if sample already exists for this lab request
        $existingSample = Sample::where('lab_request_id', $labRequestId)->first();
        if ($existingSample) {
            return response()->json(['message' => 'Sample already exists for this lab request'], 400);
        }

        $sample = Sample::create([
            'lab_request_id' => $labRequestId,
            'sample_id' => Sample::generateSampleId(),
            'sample_type' => 'Pathology', // Default sample type
            'status' => 'collected',
            'collection_date' => now(),
            'collected_by' => auth()->id(),
        ]);

        return response()->json($sample->load(['labRequest.patient', 'collectedBy']));
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:collected,received,processing,analyzing,completed,disposed,lost,rejected',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sample = Sample::findOrFail($id);
        $sample->updateStatus($request->status, auth()->id());
        
        if ($request->has('location')) {
            $sample->location = $request->location;
        }
        
        if ($request->has('notes')) {
            $sample->notes = $request->notes;
        }
        
        $sample->save();

        return response()->json($sample->load(['labRequest.patient', 'collectedBy', 'receivedBy', 'processedBy', 'analyzedBy', 'disposedBy']));
    }

    public function getSampleByLabRequest($labRequestId)
    {
        $sample = Sample::with([
            'labRequest.patient', 
            'collectedBy', 
            'receivedBy', 
            'processedBy', 
            'analyzedBy',
            'disposedBy'
        ])->where('lab_request_id', $labRequestId)->first();

        if (!$sample) {
            return response()->json(['message' => 'Sample not found'], 404);
        }

        return response()->json($sample);
    }

    public function getStats()
    {
        $stats = [
            'total_samples' => Sample::count(),
            'collected' => Sample::where('status', 'collected')->count(),
            'received' => Sample::where('status', 'received')->count(),
            'processing' => Sample::where('status', 'processing')->count(),
            'analyzing' => Sample::where('status', 'analyzing')->count(),
            'completed' => Sample::where('status', 'completed')->count(),
            'disposed' => Sample::where('status', 'disposed')->count(),
            'lost' => Sample::where('status', 'lost')->count(),
            'rejected' => Sample::where('status', 'rejected')->count(),
        ];

        return response()->json($stats);
    }
} 