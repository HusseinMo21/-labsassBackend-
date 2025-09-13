<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\VisitTest;
use App\Models\Patient;
use App\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        $query = Visit::with(['patient', 'visitTests.labTest', 'invoice', 'labRequest']);
        
        // Filter to only include visits with receipts if requested
        if ($request->has('include_receipts') && $request->include_receipts === 'true') {
            $query->whereNotNull('receipt_number');
        }
        
        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('receipt_number', 'like', "%{$searchTerm}%")
                  ->orWhere('visit_number', 'like', "%{$searchTerm}%")
                  ->orWhereHas('patient', function ($patientQuery) use ($searchTerm) {
                      $patientQuery->where('name', 'like', "%{$searchTerm}%")
                                  ->orWhere('phone', 'like', "%{$searchTerm}%");
                  })
                  ->orWhereHas('labRequest', function ($labQuery) use ($searchTerm) {
                      $labQuery->where('lab_no', 'like', "%{$searchTerm}%");
                  });
            });
        }
        
        // Filter by visit status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by test status if provided (for role-based access)
        if ($request->has('test_status')) {
            $testStatuses = explode(',', $request->test_status);
            $query->whereHas('visitTests', function ($q) use ($testStatuses) {
                $q->whereIn('status', $testStatuses);
            });
        }
        
        // Filter by sample completion status if provided
        if ($request->has('sample_completed') && $request->sample_completed === 'true') {
            $query->whereHas('visitTests.sampleTracking', function ($q) {
                $q->where('status', 'completed');
            })->whereDoesntHave('visitTests.sampleTracking', function ($q) {
                $q->where('status', '!=', 'completed');
            });
        }
        
        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->whereDate('visit_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->whereDate('visit_date', '<=', $request->end_date);
        }
        
        // Pagination
        $perPage = $request->get('per_page', 15);
        $visits = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        return response()->json($visits);
    }

    public function store(Request $request)
    {
        return $this->createVisit($request);
    }

    public function show($id)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest', 'invoice.payments', 'labRequest'])
            ->findOrFail($id);
        
        return response()->json($visit);
    }

    public function destroy($id)
    {
        $visit = Visit::findOrFail($id);
        
        // Check if visit can be deleted (e.g., not completed)
        if ($visit->status === 'completed') {
            return response()->json(['message' => 'Cannot delete completed visits'], 422);
        }
        
        $visit->delete();
        
        return response()->json(['message' => 'Visit deleted successfully']);
    }

    public function getDashboardStats()
    {
        $totalPatients = \App\Models\Patient::count();
        $totalVisits = Visit::count();
        $pendingTests = VisitTest::where('status', 'pending')->count();
        $underReviewTests = VisitTest::where('status', 'under_review')->count();
        $completedTests = VisitTest::where('status', 'completed')->count();
        $totalTests = VisitTest::count();
        $totalRevenue = Visit::sum('total_amount');
        // Add more stats as needed

        $recentVisits = Visit::with(['patient', 'visitTests.labTest', 'labRequest'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'totalPatients' => $totalPatients,
            'totalVisits' => $totalVisits,
            'totalRevenue' => $totalRevenue,
            'pendingTests' => $pendingTests,
            'underReviewTests' => $underReviewTests,
            'completedTests' => $completedTests,
            'totalTests' => $totalTests,
            // Add more as needed
            'recentVisits' => $recentVisits
        ]);
    }

    public function searchPatients(Request $request)
    {
        $query = $request->get('query', '');
        
        if (strlen($query) < 2) {
            return response()->json(['patients' => []]);
        }

        $patients = Patient::where('name', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->orWhere('national_id', 'LIKE', "%{$query}%")
            ->orWhere('username', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'phone', 'email', 'birth_date']);

        return response()->json(['patients' => $patients]);
    }

    public function getLabTests()
    {
        $tests = LabTest::all(['id', 'name', 'price']);
        return response()->json(['tests' => $tests]);
    }

    public function createVisit(Request $request)
    {
        \Log::info('Create Visit Request', $request->all());
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'tests' => 'required|array|min:1',
            'tests.*.lab_test_id' => 'required|exists:lab_tests,id',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Generate a unique visit number
            $nextId = (Visit::max('id') ?? 0) + 1;
            $visitNumber = 'VIS-' . date('Ymd') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            $visit = Visit::create([
                // Required from frontend:
                'patient_id' => $request->patient_id,
                // Generated automatically:
                'visit_number' => $visitNumber,
                'visit_date' => now(),
                'visit_time' => now()->format('H:i:s'),
                'status' => 'pending',
                'notes' => $request->notes,
                'total_amount' => 0,
                'final_amount' => 0,
            ]);

            $totalAmount = 0;
            foreach ($request->tests as $testData) {
                $labTest = LabTest::find($testData['lab_test_id']);
                $visitTest = VisitTest::create([
                    'visit_id' => $visit->id,
                    'lab_test_id' => $testData['lab_test_id'],
                    'status' => 'pending',
                    'barcode_uid' => 'LAB-' . strtoupper(Str::random(8)),
                    'price' => $labTest->price,
                ]);
                $totalAmount += $labTest->price;
            }

            // Update amounts
            $visit->update([
                'total_amount' => $totalAmount,
                'final_amount' => $totalAmount, // If you have discounts, update this accordingly
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Visit created successfully',
                'visit' => $visit->load(['patient', 'visitTests.labTest'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error creating visit', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public function getVisits()
    {
        $visits = Visit::with(['patient', 'visitTests.labTest', 'invoice', 'labRequest'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($visits);
    }

    public function getVisit($id)
    {
        $visit = Visit::with(['patient', 'tests.labTest', 'invoice.payments', 'labRequest'])
            ->findOrFail($id);

        return response()->json($visit);
    }

    public function update(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
            'clinical_data' => 'nullable|string',
            'microscopic_description' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'referred_doctor' => 'nullable|string',
            'test_status' => 'nullable|string',
        ]);
        $visit->update($validated);
        
        // If test_status is being set, also update all visit_tests status accordingly
        if (isset($validated['test_status'])) {
            $visit->visitTests()->update(['status' => $validated['test_status']]);
        }
        
        return response()->json([
            'message' => 'Visit updated successfully',
            'visit' => $visit->fresh()
        ]);
    }

    public function updateTestResult(Request $request, $visitId, $testId)
    {
        $request->validate([
            'result_value' => 'nullable|string',
            'result_status' => 'nullable|string',
            'result_notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,under_review,completed',
        ]);

        $visitTest = VisitTest::where('visit_id', $visitId)
            ->where('id', $testId)
            ->firstOrFail();

        $oldValues = $visitTest->getAttributes();
        $user = auth()->user();
        
        // Role-based status validation
        $newStatus = $request->status ?? $visitTest->status;
        
        if ($user->role === 'doctor') {
            // Doctors can only set status to pending or under_review
            if (!in_array($newStatus, ['pending', 'under_review'])) {
                return response()->json([
                    'message' => 'Doctors can only update status to pending or under_review'
                ], 403);
            }
        } elseif ($user->role === 'admin') {
            // Admins can set any status including completed
            // No restrictions
        } else {
            // Other roles cannot update status
            return response()->json([
                'message' => 'You do not have permission to update test status'
            ], 403);
        }
        
        $updateData = [
            'result_value' => $request->result_value,
            'result_status' => $request->result_status,
            'result_notes' => $request->result_notes,
            'performed_by' => auth()->id(),
            'performed_at' => now(),
        ];
        
        // Only update status if provided and user has permission
        if ($request->has('status')) {
            $updateData['status'] = $newStatus;
        }
        
        $visitTest->update($updateData);

        // Check if all tests in this visit are completed
        $this->checkAndCompleteVisit($visitId);

        // Check for critical values
        $criticalType = null;
        if ($request->result_value && is_numeric($request->result_value)) {
            $criticalType = $visitTest->checkCriticalValue($request->result_value);
        }

        // Create sample tracking if not exists
        if (!$visitTest->sampleTracking) {
            \App\Models\SampleTracking::create([
                'visit_test_id' => $visitTest->id,
                'sample_id' => \App\Models\SampleTracking::generateSampleId(),
                'status' => 'collected',
                'collected_at' => now(),
                'collected_by' => auth()->id(),
            ]);
        }

        // Send result notification if requested
        if ($request->send_notification) {
            \App\Models\Notification::createResultNotification($visitTest, 'patient');
        }

        return response()->json([
            'message' => 'Test result updated successfully',
            'test' => $visitTest->load(['labTest', 'sampleTracking']),
            'critical_alert' => $criticalType ? "Critical {$criticalType} value detected" : null,
        ]);
    }

    public function updateVisitResults(Request $request, $visitId)
    {
        $request->validate([
            'visit_tests' => 'required|array',
            'visit_tests.*.id' => 'required|exists:visit_tests,id',
            'visit_tests.*.result_value' => 'nullable|string',
            'visit_tests.*.result_status' => 'nullable|string',
            'visit_tests.*.result_notes' => 'nullable|string',
            'visit_tests.*.status' => 'nullable|string|in:pending,under_review,completed',
        ]);

        $visit = Visit::findOrFail($visitId);
        $user = auth()->user();
        
        foreach ($request->visit_tests as $testData) {
            $visitTest = VisitTest::where('visit_id', $visitId)
                ->where('id', $testData['id'])
                ->firstOrFail();

            // Role-based status validation
            $newStatus = $testData['status'] ?? $visitTest->status;
            
            if ($user->role === 'doctor') {
                // Doctors can only set status to pending or under_review
                if (!in_array($newStatus, ['pending', 'under_review'])) {
                    return response()->json([
                        'message' => 'Doctors can only update status to pending or under_review'
                    ], 403);
                }
            } elseif ($user->role === 'admin') {
                // Admins can set any status including completed
                // No restrictions
            } else {
                // Other roles cannot update status
                return response()->json([
                    'message' => 'You do not have permission to update test status'
                ], 403);
            }

            $updateData = [
                'result_value' => $testData['result_value'] ?? null,
                'result_status' => $testData['result_status'] ?? null,
                'result_notes' => $testData['result_notes'] ?? null,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ];
            
            // Only update status if provided and user has permission
            if (isset($testData['status'])) {
                $updateData['status'] = $newStatus;
            }

            $visitTest->update($updateData);
        }

        // Check if all tests in this visit are completed
        $this->checkAndCompleteVisit($visitId);

        return response()->json([
            'message' => 'Test results updated successfully',
            'visit' => $visit->fresh(['visitTests.labTest'])
        ]);
    }

    public function updateTestStatus(Request $request, $visitId, $testId)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled'
        ]);

        $visitTest = VisitTest::where('visit_id', $visitId)
            ->where('id', $testId)
            ->firstOrFail();

        $visitTest->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Test status updated',
            'test' => $visitTest->load('labTest')
        ]);
    }

    public function printLabel($visitId, $testId)
    {
        $visitTest = VisitTest::with(['visit.patient', 'labTest'])
            ->where('visit_id', $visitId)
            ->where('id', $testId)
            ->firstOrFail();

        return response()->json([
            'label_data' => [
                'barcode' => $visitTest->barcode_uid,
                'patient_name' => $visitTest->visit->patient->name,
                'test_name' => $visitTest->labTest->name,
                'visit_date' => $visitTest->visit->visit_date->format('Y-m-d'),
                'test_id' => $visitTest->id,
            ]
        ]);
    }

    /**
     * Manually mark a visit as completed
     */
    public function completeVisit($id)
    {
        $visit = Visit::findOrFail($id);
        
        $visit->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
        
        return response()->json([
            'message' => 'Visit marked as completed successfully',
            'visit' => $visit->fresh()
        ]);
    }

    /**
     * Check if all tests in a visit are completed and mark visit as completed if so
     */
    private function checkAndCompleteVisit($visitId)
    {
        $visit = Visit::find($visitId);
        if (!$visit) return;

        // Check if all tests are completed
        $totalTests = $visit->visitTests()->count();
        $completedTests = $visit->visitTests()->where('status', 'completed')->count();

        if ($totalTests > 0 && $totalTests === $completedTests) {
            $visit->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
        }
    }
} 