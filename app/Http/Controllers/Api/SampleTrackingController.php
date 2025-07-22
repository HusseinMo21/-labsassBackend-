<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SampleTracking;
use App\Models\VisitTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SampleTrackingController extends Controller
{
    public function index(Request $request)
    {
        $query = SampleTracking::with(['visitTest.labTest', 'visitTest.visit.patient', 'collectedBy', 'receivedBy', 'processedBy', 'analyzedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sample_id', 'like', "%{$search}%")
                  ->orWhereHas('visitTest.visit.patient', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $samples = $query->latest()->paginate(15);
        return response()->json($samples);
    }

    public function show($id)
    {
        $sample = SampleTracking::with([
            'visitTest.labTest', 
            'visitTest.visit.patient', 
            'collectedBy', 
            'receivedBy', 
            'processedBy', 
            'analyzedBy',
            'disposedBy'
        ])->findOrFail($id);

        return response()->json($sample);
    }

    public function createSample($visitTestId)
    {
        $visitTest = VisitTest::findOrFail($visitTestId);
        
        // Check if sample already exists
        if ($visitTest->sampleTracking) {
            return response()->json(['message' => 'Sample already exists for this test'], 400);
        }

        $sample = SampleTracking::create([
            'visit_test_id' => $visitTestId,
            'sample_id' => SampleTracking::generateSampleId(),
            'status' => 'collected',
            'collected_at' => now(),
            'collected_by' => auth()->id(),
        ]);

        return response()->json($sample->load(['visitTest.labTest', 'visitTest.visit.patient', 'collectedBy']));
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

        $sample = SampleTracking::findOrFail($id);
        $sample->updateStatus($request->status, auth()->id());
        
        if ($request->has('location')) {
            $sample->location = $request->location;
        }
        
        if ($request->has('notes')) {
            $sample->notes = $request->notes;
        }
        
        $sample->save();

        return response()->json($sample->load(['visitTest.labTest', 'visitTest.visit.patient', 'collectedBy', 'receivedBy', 'processedBy', 'analyzedBy', 'disposedBy']));
    }

    public function getSampleByVisitTest($visitTestId)
    {
        $sample = SampleTracking::with([
            'visitTest.labTest', 
            'visitTest.visit.patient', 
            'collectedBy', 
            'receivedBy', 
            'processedBy', 
            'analyzedBy',
            'disposedBy'
        ])->where('visit_test_id', $visitTestId)->first();

        if (!$sample) {
            return response()->json(['message' => 'Sample not found'], 404);
        }

        return response()->json($sample);
    }

    public function getStats()
    {
        $stats = [
            'total_samples' => SampleTracking::count(),
            'collected' => SampleTracking::where('status', 'collected')->count(),
            'received' => SampleTracking::where('status', 'received')->count(),
            'processing' => SampleTracking::where('status', 'processing')->count(),
            'analyzing' => SampleTracking::where('status', 'analyzing')->count(),
            'completed' => SampleTracking::where('status', 'completed')->count(),
            'disposed' => SampleTracking::where('status', 'disposed')->count(),
            'lost' => SampleTracking::where('status', 'lost')->count(),
            'rejected' => SampleTracking::where('status', 'rejected')->count(),
        ];

        return response()->json($stats);
    }
} 