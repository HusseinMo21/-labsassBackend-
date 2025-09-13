<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestValidation;
use App\Models\VisitTest;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TestValidationController extends Controller
{
    /**
     * Display a listing of test validation records
     */
    public function index(Request $request)
    {
        $query = TestValidation::with(['visitTest.labTest', 'visitTest.visit.patient', 'validatedBy']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by validation type
        if ($request->has('validation_type')) {
            $query->where('validation_type', $request->validation_type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('validated_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('validated_at', '<=', $request->date_to);
        }

        $validations = $query->orderBy('validated_at', 'desc')->paginate(20);

        return response()->json($validations);
    }

    /**
     * Create initial validation for a visit test
     */
    public function createInitialValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'visit_test_id' => 'required|exists:visit_tests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $visitTest = VisitTest::with('labTest')->findOrFail($request->visit_test_id);

            // Check if initial validation already exists
            $existingValidation = TestValidation::where('visit_test_id', $visitTest->id)
                ->where('validation_type', 'initial')
                ->first();

            if ($existingValidation) {
                return response()->json([
                    'message' => 'Initial validation already exists for this test',
                    'validation' => $existingValidation,
                ], 409);
            }

            // Create initial validation
            $validation = TestValidation::create([
                'visit_test_id' => $visitTest->id,
                'validation_type' => 'initial',
                'status' => 'pending',
            ]);

            // Perform automatic validation checks
            $checks = $validation->performValidationChecks();

            DB::commit();

            Log::info('Initial test validation created', [
                'validation_id' => $validation->id,
                'visit_test_id' => $visitTest->id,
                'test_name' => $visitTest->labTest->name,
            ]);

            return response()->json([
                'message' => 'Initial validation created successfully',
                'validation' => $validation->load(['visitTest.labTest', 'visitTest.visit.patient']),
                'validation_checks' => $checks,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create initial validation', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Failed to create initial validation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Doctor review and validation
     */
    public function doctorReview(Request $request, $id)
    {
        $validation = TestValidation::findOrFail($id);

        // Check if user is a doctor
        if (!auth()->user()->isDoctor()) {
            return response()->json([
                'message' => 'Only doctors can perform test validation',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:validate,reject,require_correction',
            'clinical_correlation' => 'nullable|string',
            'validation_notes' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|string',
            'correction_notes' => 'required_if:action,require_correction|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $action = $request->action;
            $validatedBy = auth()->id();

            // Update validation record
            $validation->update([
                'clinical_correlation' => $request->clinical_correlation,
                'validation_notes' => $request->validation_notes,
            ]);

            switch ($action) {
                case 'validate':
                    $validation->validateTest($validatedBy, $request->validation_notes);
                    break;
                case 'reject':
                    $validation->rejectTest($validatedBy, $request->rejection_reason, $request->correction_notes);
                    break;
                case 'require_correction':
                    $validation->requireCorrection($validatedBy, $request->correction_notes);
                    break;
            }

            DB::commit();

            Log::info('Doctor validation completed', [
                'validation_id' => $validation->id,
                'action' => $action,
                'doctor_id' => $validatedBy,
            ]);

            return response()->json([
                'message' => 'Doctor validation completed successfully',
                'validation' => $validation->load(['visitTest.labTest', 'visitTest.visit.patient', 'validatedBy']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete doctor validation', [
                'error' => $e->getMessage(),
                'validation_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to complete doctor validation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin final approval (Head of Doctors)
     */
    public function adminFinalApproval(Request $request, $id)
    {
        $validation = TestValidation::findOrFail($id);

        // Check if user is admin (Head of Doctors)
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'Only the Head of Doctors (Admin) can provide final approval',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'final_notes' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $action = $request->action;
            $validatedBy = auth()->id();

            if ($action === 'approve') {
                // Final approval - mark test as completed
                $validation->visitTest->update([
                    'status' => 'completed',
                    'performed_at' => now(),
                    'performed_by' => $validatedBy,
                ]);

                // Update validation record
                $validation->update([
                    'status' => 'validated',
                    'validated_by' => $validatedBy,
                    'validated_at' => now(),
                    'validation_notes' => $request->final_notes,
                ]);

                // Update visit status if all tests are completed
                $visit = $validation->visitTest->visit;
                $allTestsCompleted = $visit->visitTests()->where('status', '!=', 'completed')->count() === 0;
                
                if ($allTestsCompleted) {
                    $visit->update(['status' => 'completed']);
                }

            } else {
                // Rejection - send back to doctor for review
                $validation->rejectTest($validatedBy, $request->rejection_reason, $request->final_notes);
            }

            DB::commit();

            Log::info('Admin final approval completed', [
                'validation_id' => $validation->id,
                'action' => $action,
                'admin_id' => $validatedBy,
            ]);

            return response()->json([
                'message' => 'Admin final approval completed successfully',
                'validation' => $validation->load(['visitTest.labTest', 'visitTest.visit.patient', 'validatedBy']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete admin final approval', [
                'error' => $e->getMessage(),
                'validation_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to complete admin final approval',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending validations for doctor review
     */
    public function pendingDoctorReview()
    {
        $pendingValidations = TestValidation::with([
            'visitTest.labTest',
            'visitTest.visit.patient',
            'validatedBy'
        ])
        ->where('status', 'pending')
        ->where('validation_type', 'initial')
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json($pendingValidations);
    }

    /**
     * Get pending validations for admin final approval
     */
    public function pendingAdminApproval()
    {
        $pendingValidations = TestValidation::with([
            'visitTest.labTest',
            'visitTest.visit.patient',
            'validatedBy'
        ])
        ->where('status', 'validated')
        ->where('validation_type', 'initial')
        ->whereHas('visitTest', function($query) {
            $query->where('status', 'validated');
        })
        ->orderBy('validated_at', 'asc')
        ->get();

        return response()->json($pendingValidations);
    }

    /**
     * Get validation history for a visit test
     */
    public function getValidationHistory($visitTestId)
    {
        $validations = TestValidation::with(['validatedBy'])
            ->where('visit_test_id', $visitTestId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($validations);
    }

    /**
     * Get validation statistics
     */
    public function statistics(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $stats = [
            'total_validations' => TestValidation::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'validated_tests' => TestValidation::whereBetween('created_at', [$dateFrom, $dateTo])->where('status', 'validated')->count(),
            'rejected_tests' => TestValidation::whereBetween('created_at', [$dateFrom, $dateTo])->where('status', 'rejected')->count(),
            'pending_review' => TestValidation::whereBetween('created_at', [$dateFrom, $dateTo])->where('status', 'pending')->count(),
            'requires_correction' => TestValidation::whereBetween('created_at', [$dateFrom, $dateTo])->where('status', 'requires_correction')->count(),
        ];

        // Validation by type
        $stats['by_type'] = TestValidation::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('validation_type, COUNT(*) as count, AVG(CASE WHEN status = "validated" THEN 1 ELSE 0 END) * 100 as validation_rate')
            ->groupBy('validation_type')
            ->get();

        return response()->json($stats);
    }

    /**
     * Display the specified validation record
     */
    public function show($id)
    {
        $validation = TestValidation::with([
            'visitTest.labTest',
            'visitTest.visit.patient',
            'validatedBy'
        ])->findOrFail($id);

        return response()->json($validation);
    }
}
