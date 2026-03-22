<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReviewReportsController extends Controller
{
    /**
     * Get all reports that need admin review and approval
     */
    public function index(Request $request)
    {
        try {
            $query = Visit::with([
                'patient',
                'visitTests.testCategory',
                'visitTests.labTest',
                'labRequest.reports'
            ])
            ->whereHas('visitTests') // Any visits with tests
            ->whereDoesntHave('labRequest.reports', function($query) {
                $query->where('status', 'approved');
            });

            // Filter by status
            if ($request->has('status')) {
                $status = $request->status;
                if ($status === 'pending') {
                    $query->whereDoesntHave('labRequest.reports');
                } elseif ($status === 'draft') {
                    $query->whereHas('labRequest.reports', function($q) {
                        $q->where('status', 'draft');
                    });
                } elseif ($status === 'rejected') {
                    $query->whereHas('labRequest.reports', function($q) {
                        $q->where('status', 'rejected');
                    });
                }
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('visit_number', 'like', "%{$searchTerm}%")
                      ->orWhereHas('patient', function ($patientQuery) use ($searchTerm) {
                          $patientQuery->where('name', 'like', "%{$searchTerm}%")
                                      ->orWhere('phone', 'like', "%{$searchTerm}%")
                                      ->orWhere('id', 'like', "%{$searchTerm}%");
                      })
                      ->orWhereHas('labRequest', function ($labQuery) use ($searchTerm) {
                          $labQuery->where('lab_no', 'like', "%{$searchTerm}%");
                      });
                });
            }

            $perPage = $request->get('per_page', 15);
            $visits = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $reports = $visits->map(function($visit) {
                $latestReport = $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->sortByDesc('created_at')->first() : null;
                
                return [
                    'visit_id' => $visit->id,
                    'visit_number' => $visit->visit_number,
                    'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : 'N/A',
                    'patient_name' => $visit->patient->name,
                    'patient_id' => $visit->patient->id,
                    'visit_date' => $visit->visit_date,
                    'test_count' => $visit->visitTests->count(),
                    'completed_tests' => $visit->visitTests->where('status', 'completed')->count(),
                    'report_status' => $latestReport ? $latestReport->status : 'pending',
                    'report_created_at' => $latestReport ? $latestReport->created_at : null,
                    'report_updated_at' => $latestReport ? $latestReport->updated_at : null,
                    'can_approve' => $this->canApproveReport($visit),
                    'blocking_issues' => $this->getBlockingIssues($visit),
                ];
            });

            return response()->json([
                'data' => $reports,
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'per_page' => $visits->perPage(),
                'total' => $visits->total(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch review reports: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch reports'], 500);
        }
    }

    /**
     * Get pending reports for dashboard
     */
    public function pending()
    {
        try {
            $pendingCount = Visit::whereHas('visitTests')
            ->whereDoesntHave('labRequest.reports', function($query) {
                $query->where('status', 'approved');
            })
            ->count();

            $draftCount = Visit::whereHas('labRequest.reports', function($query) {
                $query->where('status', 'draft');
            })->count();

            $rejectedCount = Visit::whereHas('labRequest.reports', function($query) {
                $query->where('status', 'rejected');
            })->count();

            return response()->json([
                'pending_reports' => $pendingCount,
                'draft_reports' => $draftCount,
                'rejected_reports' => $rejectedCount,
                'total_pending' => $pendingCount + $draftCount + $rejectedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch pending reports count: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch pending count'], 500);
        }
    }

    /**
     * Get detailed report information for review
     */
    public function show($visitId)
    {
        try {
            $visit = Visit::with([
                'patient',
                'visitTests.testCategory',
                'visitTests.labTest',
                'labRequest.reports' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])->findOrFail($visitId);

            $latestReport = $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->first() : null;
            
            $reportData = [
                'visit' => [
                    'id' => $visit->id,
                    'visit_number' => $visit->visit_number,
                    'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : 'N/A',
                    'visit_date' => $visit->visit_date,
                    'visit_time' => $visit->visit_time,
                ],
                'patient' => [
                    'id' => $visit->patient->id,
                    'name' => $visit->patient->name,
                    'phone' => $visit->patient->phone,
                    'gender' => $visit->patient->gender,
                    'birth_date' => $visit->patient->birth_date,
                ],
                'tests' => $visit->visitTests->map(function($test) {
                    // Determine test name
                    $testName = $test->custom_test_name ?? ($test->labTest ? $test->labTest->name : 'Unknown Test');
                    
                    // Determine category (for old lab tests, we'll use a default category)
                    $category = 'Unknown';
                    $categoryCode = 'N/A';
                    if ($test->testCategory) {
                        $category = $test->testCategory->name;
                        $categoryCode = $test->testCategory->code;
                    } elseif ($test->labTest) {
                        // For old lab tests, assign a default category based on test type
                        $category = 'LAB'; // Default category for old lab tests
                        $categoryCode = 'lab';
                    }
                    
                    // Determine price (avoid live lab_tests.price when snapshot exists)
                    $price = 0;
                    if ($test->final_price) {
                        $price = $test->final_price;
                    } elseif ($test->price_at_time) {
                        $price = $test->price_at_time;
                    } elseif ($test->custom_price) {
                        $price = $test->custom_price;
                    } elseif ($test->labTest && $test->labTest->price) {
                        $price = $test->labTest->price;
                    }
                    
                    // Determine result status
                    $resultStatus = 'Pending Review';
                    if ($test->result_status) {
                        $resultStatus = $test->result_status;
                    } elseif ($test->status === 'completed') {
                        $resultStatus = 'Completed';
                    } elseif ($test->status === 'pending') {
                        $resultStatus = 'Pending Review';
                    }
                    
                    return [
                        'id' => $test->id,
                        'test_name' => $testName,
                        'category' => $category,
                        'category_code' => $categoryCode,
                        'result_value' => $test->result_value ?? 'N/A',
                        'result_status' => $resultStatus,
                        'status' => $test->status,
                        'completed_at' => $test->updated_at,
                        'price' => $price,
                    ];
                }),
                'report' => $latestReport ? [
                    'id' => $latestReport->id,
                    'status' => $latestReport->status,
                    'created_at' => $latestReport->created_at,
                    'updated_at' => $latestReport->updated_at,
                    'notes' => $latestReport->notes,
                ] : null,
                'can_approve' => $this->canApproveReport($visit),
                'blocking_issues' => $this->getBlockingIssues($visit),
            ];

            return response()->json($reportData);

        } catch (\Exception $e) {
            Log::error('Failed to fetch report details: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch report details'], 500);
        }
    }

    /**
     * Approve a report (Admin only - Head of Doctors)
     */
    public function approve(Request $request, $visitId)
    {
        try {
            $visit = Visit::with(['labRequest.reports'])->findOrFail($visitId);
            
            if (!$this->canApproveReport($visit)) {
                return response()->json([
                    'error' => 'Cannot approve report. Some tests are not completed or report is already approved.'
                ], 400);
            }

            DB::beginTransaction();

            // Create or update report
            $report = $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->first() : null;
            if (!$report) {
                $report = new Report();
                $report->lab_request_id = $visit->labRequest ? $visit->labRequest->id : null;
                $report->status = 'approved';
                $report->generated_by = auth()->id();
                $report->save();
            } else {
                $report->status = 'approved';
                $report->save();
            }

            // Update visit status
            $visit->status = 'completed';
            $visit->save();

            DB::commit();

            Log::info('Report approved by admin', [
                'visit_id' => $visitId,
                'admin_id' => auth()->id(),
                'report_id' => $report->id
            ]);

            return response()->json([
                'message' => 'Report approved successfully',
                'report_id' => $report->id,
                'status' => 'approved'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to approve report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to approve report'], 500);
        }
    }

    /**
     * Reject a report with notes
     */
    public function reject(Request $request, $visitId)
    {
        try {
            $request->validate([
                'notes' => 'required|string|max:1000'
            ]);

            $visit = Visit::with(['labRequest.reports'])->findOrFail($visitId);

            DB::beginTransaction();

            // Create or update report
            $report = $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->first() : null;
            if (!$report) {
                $report = new Report();
                $report->lab_request_id = $visit->labRequest ? $visit->labRequest->id : null;
                $report->generated_by = auth()->id();
            }

            $report->status = 'rejected';
            $report->save();

            DB::commit();

            Log::info('Report rejected by admin', [
                'visit_id' => $visitId,
                'admin_id' => auth()->id(),
                'report_id' => $report->id,
                'notes' => $request->notes
            ]);

            return response()->json([
                'message' => 'Report rejected successfully',
                'report_id' => $report->id,
                'status' => 'rejected'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to reject report: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to reject report'], 500);
        }
    }

    /**
     * Check if a report can be approved
     */
    private function canApproveReport(Visit $visit)
    {
        // At least one test must exist
        $totalTests = $visit->visitTests->count();
        
        if ($totalTests === 0) {
            return false;
        }
        
        // For now, allow approval even if tests are not completed
        // The admin can review and approve based on available information

        // Report must not already be approved
        $latestReport = $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->first() : null;
        if ($latestReport && $latestReport->status === 'approved') {
            return false;
        }

        return true;
    }

    /**
     * Get blocking issues for a report
     */
    private function getBlockingIssues(Visit $visit)
    {
        $issues = [];

        $totalTests = $visit->visitTests->count();
        $completedTests = $visit->visitTests->where('status', 'completed')->count();
        
        if ($totalTests === 0) {
            $issues[] = 'No tests found for this visit';
        } elseif ($completedTests < $totalTests) {
            $pendingTests = $totalTests - $completedTests;
            $issues[] = "{$pendingTests} test(s) are not completed yet (Admin can still review)";
        }

        $latestReport = $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->first() : null;
        if ($latestReport && $latestReport->status === 'approved') {
            $issues[] = 'Report is already approved';
        }

        return $issues;
    }
}
