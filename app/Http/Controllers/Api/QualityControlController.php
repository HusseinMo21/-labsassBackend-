<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QualityControl;
use App\Models\VisitTest;
use App\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class QualityControlController extends Controller
{
    /**
     * Display a listing of quality control records
     */
    public function index(Request $request)
    {
        $query = QualityControl::with(['visitTest.labTest', 'visitTest.visit.patient', 'performedBy', 'reviewedBy']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by QC type
        if ($request->has('qc_type')) {
            $query->where('qc_type', $request->qc_type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('performed_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('performed_at', '<=', $request->date_to);
        }

        $qualityControls = $query->orderBy('performed_at', 'desc')->paginate(20);

        return response()->json($qualityControls);
    }

    /**
     * Store a newly created quality control record
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'visit_test_id' => 'required|exists:visit_tests,id',
            'qc_type' => 'required|in:pre_test,post_test,batch_control',
            'control_sample_id' => 'nullable|string|max:255',
            'expected_value' => 'nullable|numeric',
            'actual_value' => 'nullable|numeric',
            'tolerance_range' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'equipment_used' => 'nullable|string|max:255',
            'reagent_lot_number' => 'nullable|string|max:255',
            'reagent_expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $qualityControl = QualityControl::create([
                ...$validator->validated(),
                'performed_by' => auth()->id(),
                'performed_at' => now(),
                'status' => 'pending',
            ]);

            // Auto-evaluate QC result if both expected and actual values are provided
            if ($qualityControl->expected_value && $qualityControl->actual_value) {
                $this->evaluateQCResult($qualityControl);
            }

            DB::commit();

            Log::info('Quality control record created', [
                'qc_id' => $qualityControl->id,
                'visit_test_id' => $qualityControl->visit_test_id,
                'qc_type' => $qualityControl->qc_type,
                'performed_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Quality control record created successfully',
                'quality_control' => $qualityControl->load(['visitTest.labTest', 'visitTest.visit.patient', 'performedBy']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create quality control record', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Failed to create quality control record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified quality control record
     */
    public function show($id)
    {
        $qualityControl = QualityControl::with([
            'visitTest.labTest',
            'visitTest.visit.patient',
            'performedBy',
            'reviewedBy'
        ])->findOrFail($id);

        return response()->json($qualityControl);
    }

    /**
     * Update the specified quality control record
     */
    public function update(Request $request, $id)
    {
        $qualityControl = QualityControl::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'actual_value' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'equipment_used' => 'nullable|string|max:255',
            'reagent_lot_number' => 'nullable|string|max:255',
            'reagent_expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $qualityControl->update($validator->validated());

            // Re-evaluate QC result if actual value was updated
            if ($request->has('actual_value') && $qualityControl->expected_value) {
                $this->evaluateQCResult($qualityControl);
            }

            Log::info('Quality control record updated', [
                'qc_id' => $qualityControl->id,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Quality control record updated successfully',
                'quality_control' => $qualityControl->load(['visitTest.labTest', 'visitTest.visit.patient', 'performedBy', 'reviewedBy']),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update quality control record', [
                'error' => $e->getMessage(),
                'qc_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to update quality control record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Review and approve/reject quality control result
     */
    public function review(Request $request, $id)
    {
        $qualityControl = QualityControl::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,requires_review',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $action = $request->action;
            $notes = $request->notes;

            switch ($action) {
                case 'approve':
                    $qualityControl->markAsPassed(auth()->id());
                    break;
                case 'reject':
                    $qualityControl->markAsFailed(auth()->id(), $notes);
                    break;
                case 'requires_review':
                    $qualityControl->markAsRequiresReview(auth()->id(), $notes);
                    break;
            }

            Log::info('Quality control reviewed', [
                'qc_id' => $qualityControl->id,
                'action' => $action,
                'reviewed_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Quality control review completed successfully',
                'quality_control' => $qualityControl->load(['visitTest.labTest', 'visitTest.visit.patient', 'performedBy', 'reviewedBy']),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to review quality control', [
                'error' => $e->getMessage(),
                'qc_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to review quality control',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get quality control statistics
     */
    public function statistics(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $stats = [
            'total_qc_records' => QualityControl::whereBetween('performed_at', [$dateFrom, $dateTo])->count(),
            'passed_qc' => QualityControl::whereBetween('performed_at', [$dateFrom, $dateTo])->where('status', 'passed')->count(),
            'failed_qc' => QualityControl::whereBetween('performed_at', [$dateFrom, $dateTo])->where('status', 'failed')->count(),
            'pending_review' => QualityControl::whereBetween('performed_at', [$dateFrom, $dateTo])->where('status', 'requires_review')->count(),
            'pass_rate' => 0,
        ];

        if ($stats['total_qc_records'] > 0) {
            $stats['pass_rate'] = round(($stats['passed_qc'] / $stats['total_qc_records']) * 100, 2);
        }

        // QC by type
        $stats['by_type'] = QualityControl::whereBetween('performed_at', [$dateFrom, $dateTo])
            ->selectRaw('qc_type, COUNT(*) as count, AVG(CASE WHEN status = "passed" THEN 1 ELSE 0 END) * 100 as pass_rate')
            ->groupBy('qc_type')
            ->get();

        return response()->json($stats);
    }

    /**
     * Auto-evaluate QC result based on expected vs actual values
     */
    private function evaluateQCResult(QualityControl $qualityControl)
    {
        if (!$qualityControl->expected_value || !$qualityControl->actual_value) {
            return;
        }

        $deviation = $qualityControl->calculateDeviation();
        $percentageDeviation = $qualityControl->calculatePercentageDeviation();

        // Auto-evaluate based on tolerance range
        if ($qualityControl->tolerance_range) {
            if ($qualityControl->isWithinTolerance()) {
                $qualityControl->markAsPassed();
            } else {
                $qualityControl->markAsFailed(null, "Deviation exceeds tolerance range. Expected: {$qualityControl->expected_value}, Actual: {$qualityControl->actual_value}, Deviation: {$deviation}");
            }
        } else {
            // Default tolerance: 10% deviation
            if ($percentageDeviation <= 10) {
                $qualityControl->markAsPassed();
            } else {
                $qualityControl->markAsRequiresReview(null, "High deviation detected: {$percentageDeviation}%. Expected: {$qualityControl->expected_value}, Actual: {$qualityControl->actual_value}");
            }
        }
    }

    /**
     * Get pending QC records for review
     */
    public function pendingReview()
    {
        $pendingQC = QualityControl::with([
            'visitTest.labTest',
            'visitTest.visit.patient',
            'performedBy'
        ])
        ->where('status', 'requires_review')
        ->orderBy('performed_at', 'asc')
        ->get();

        return response()->json($pendingQC);
    }

    /**
     * Get QC records for a specific visit test
     */
    public function getByVisitTest($visitTestId)
    {
        $qualityControls = QualityControl::with(['performedBy', 'reviewedBy'])
            ->where('visit_test_id', $visitTestId)
            ->orderBy('performed_at', 'desc')
            ->get();

        return response()->json($qualityControls);
    }
}
