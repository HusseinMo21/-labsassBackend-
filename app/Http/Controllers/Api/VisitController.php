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
    /**
     * Get receipt details for any visit (works for both check-in and patient registration visits)
     * Updated to match UnpaidInvoicesController format
     */
    public function getReceiptDetails($visitId)
    {
        try {
            // Find the visit with all necessary relationships
            $visit = Visit::with(['patient', 'visitTests.testCategory', 'visitTests.labTest', 'labRequest'])
                ->findOrFail($visitId);
        
            $patient = $visit->patient;
            
            if (!$patient) {
                return response()->json([
                    'message' => 'Patient not found for this visit',
                ], 404);
            }
            
            // Get current payment information from visit metadata first
            $metadata = json_decode($visit->metadata ?? '{}', true);
            $paymentDetails = $metadata['payment_details'] ?? [];
            $patientData = $metadata['patient_data'] ?? [];
            $financialData = $metadata['financial_data'] ?? [];
            
            // Calculate total paid from payment breakdown
            $totalPaid = $financialData['amount_paid'] ?? $paymentDetails['total_paid'] ?? $patientData['amount_paid'] ?? 0;
            
            // If still 0, calculate from payment breakdown
            if ($totalPaid == 0) {
                $cashPaid = $paymentDetails['amount_paid_cash'] ?? $patientData['amount_paid_cash'] ?? 0;
                $cardPaid = $paymentDetails['amount_paid_card'] ?? $patientData['amount_paid_card'] ?? 0;
                $totalPaid = $cashPaid + $cardPaid;
            }
            
            // Final fallback to direct fields
            if ($totalPaid == 0) {
                $totalPaid = $patient->amount_paid ?? $visit->upfront_payment ?? 0;
            }
            
            $totalAmount = $financialData['total_amount'] ?? $visit->final_amount ?? $visit->total_amount ?? 0;
            
            // Build payment breakdown
            $paymentBreakdown = [];
            if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
                $paymentBreakdown['cash'] = $paymentDetails['amount_paid_cash'];
            }
            if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
                $paymentBreakdown['card'] = $paymentDetails['amount_paid_card'];
                $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'card';
            }
            
            // If no breakdown exists but we have a payment method, create a simple breakdown
            if (empty($paymentBreakdown) && $totalPaid > 0) {
                $currentPaymentMethod = $visit->payment_method ?? 'cash';
                if ($currentPaymentMethod === 'cash') {
                    $paymentBreakdown['cash'] = $totalPaid;
                } else {
                    $paymentBreakdown['card'] = $totalPaid;
                    $paymentBreakdown['card_method'] = $currentPaymentMethod;
                }
            }
            
            // Get processed by information
            $processedBy = 'System';
            if ($visit->created_by) {
                $user = \App\Models\User::find($visit->created_by);
                $processedBy = $user ? $user->name : 'System';
            } elseif (auth()->user()) {
                $processedBy = auth()->user()->name;
            }
            
            // Get patient credentials
            $credentials = null;
            if (isset($patientData['username']) && isset($patientData['password'])) {
                $credentials = [
                    'username' => $patientData['username'],
                    'password' => $patientData['password'],
                ];
            }
            
            // Calculate paid before and paid now (for display purposes)
            $paidBefore = 0; // This would be calculated from previous payments
            $paidNow = $totalPaid;
            
            // Debug logging
            \Log::info('VisitController::getReceiptDetails - Updated method called', [
                'visit_id' => $visitId,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'remaining_balance' => $totalAmount - $totalPaid,
                'payment_breakdown' => $paymentBreakdown,
            ]);
            
            return response()->json([
                'receipt_data' => [
                    'receipt_number' => $visit->visit_number, // Use visit number as receipt number
                    'date' => $visit->visit_date ?: now()->format('Y-m-d'),
                    'patient_name' => $patient->name,
                    'patient_age' => $patient->age,
                    'patient_phone' => $patient->phone,
                    'tests' => $this->getTestsForReceipt($visit),
                    'total_amount' => $totalAmount,
                    'discount_amount' => 0, // No discount in current system
                    'final_amount' => $totalAmount,
                    'paid_before' => $paidBefore,
                    'paid_now' => $paidNow,
                    'remaining_balance' => $totalAmount - $totalPaid,
                    'payment_method' => $visit->payment_method ?: 'cash',
                    'expected_delivery_date' => $visit->expected_delivery_date ?: now()->addDays(1)->toDateString(),
                    'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($patient->lab ?: 'N/A'),
                    'processed_by' => $processedBy,
                    'visit_id' => $visit->id,
                    'patient_credentials' => $credentials,
                    'payment_breakdown' => $paymentBreakdown,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to get receipt details', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to get receipt details',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        // Optimize: Use select to only fetch needed columns and eager load relationships
        $query = Visit::select([
            'id', 'visit_number', 'visit_date', 'status', 'created_at', 'updated_at',
            'patient_id', 'lab_request_id', 'total_amount', 'final_amount', 'upfront_payment', 'metadata',
            'checked_by_doctors', 'last_checked_at'
        ])
        ->with([
            'patient:id,name,phone,gender,birth_date,age,amount_paid,lab',
            'visitTests:id,visit_id,lab_test_id,status,result_value,result_status,result_notes,price',
            'visitTests.labTest:id,name,code,reference_range',
            'labRequest:id,lab_no,suffix',
            'labRequest.invoice:id,lab_request_id,amount_paid,balance' // Eager load invoice to avoid N+1
        ]);
        
        // Filter to only include visits with receipts if requested
        // For receipts, we want visits that have visit_number (which serves as receipt_number)
        if ($request->has('include_receipts') && $request->include_receipts === 'true') {
            $query->whereNotNull('visit_number');
        }
        
        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                // Note: receipt_number column doesn't exist in original visits table
                // $q->where('receipt_number', 'like', "%{$searchTerm}%")
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
        // Note: sample_tracking table doesn't exist, so this filter is disabled
        // if ($request->has('sample_completed') && $request->sample_completed === 'true') {
        //     $query->whereHas('visitTests.sampleTracking', function ($q) {
        //         $q->where('status', 'completed');
        //     })->whereDoesntHave('visitTests.sampleTracking', function ($q) {
        //         $q->where('status', '!=', 'completed');
        //     });
        // }
        
        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->whereDate('visit_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->whereDate('visit_date', '<=', $request->end_date);
        }
        
        // Exclude completed visits if requested (for Reports & Analytics)
        if ($request->has('exclude_completed') && $request->exclude_completed === 'true') {
            $query->where('status', '!=', 'completed');
        }
        
        // Pagination
        $perPage = $request->get('per_page', 15);
        $visits = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        // Auto-fix: Link visits to lab requests if missing (for visits created through patient registration)
        foreach ($visits->items() as $visit) {
            if (!$visit->lab_request_id && $visit->patient_id) {
                // Try to find existing lab request for this patient
                $labRequest = \App\Models\LabRequest::where('patient_id', $visit->patient_id)->first();
                if ($labRequest) {
                    $visit->update(['lab_request_id' => $labRequest->id]);
                    \Log::info('Auto-linked visit to lab request', [
                        'visit_id' => $visit->id,
                        'lab_request_id' => $labRequest->id
                    ]);
                }
            }
        }
        
        // Visits query completed successfully
        
        // Optimize: Transform data more efficiently without N+1 queries
        $transformedData = $visits->through(function ($visit) {
            // Try multiple ways to find the invoice (same logic as CheckInController)
            $invoice = null;
            
            if ($visit->labRequest) {
                // First try with lab_no
                $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
                
                // If not found, try with lab_no
                if (!$invoice && $visit->labRequest->lab_no) {
                    $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
                }
                
                // If still not found, try with lab_request_id
                if (!$invoice) {
                    $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
                }
            }
            
            // If still no invoice found, try to find by patient lab number
            if (!$invoice && $visit->patient && $visit->patient->lab) {
                $invoice = \App\Models\Invoice::where('visit_id', $visit->id)->first();
            }
            
            // Get financial data from visit metadata (prioritize financial_data from extra payments)
            $metadata = is_string($visit->metadata) ? json_decode($visit->metadata, true) : ($visit->metadata ?? []);
            $financialData = $metadata['financial_data'] ?? [];
            $paymentDetails = $metadata['payment_details'] ?? [];
            $patientData = $metadata['patient_data'] ?? [];
            
            // Calculate amounts prioritizing financial_data, then fallback to other sources
            $totalAmount = $financialData['total_amount'] ?? $visit->total_amount ?? 0;
            $finalAmount = $financialData['total_amount'] ?? $visit->final_amount ?? 0; // Use total_amount for final_amount too
            $paidAmount = $financialData['amount_paid'] ?? ($invoice ? $invoice->amount_paid : ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0));
            $remainingBalance = $financialData['remaining_balance'] ?? ($invoice ? $invoice->balance : (($visit->final_amount ?? $visit->total_amount ?? 0) - ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0)));
            $billingStatus = $financialData['payment_status'] ?? $this->getBillingStatus($invoice, $visit);
            
            return [
                'id' => $visit->id,
                'visit_number' => $visit->visit_number,
                'visit_date' => $visit->visit_date,
                'status' => $visit->status,
                'test_status' => $this->getTestStatus($visit),
                'created_at' => $visit->created_at,
                'updated_at' => $visit->updated_at,
                'patient' => $visit->patient,
                'visit_tests' => $visit->visitTests,
                'labRequest' => $visit->labRequest,
                'receipt_number' => $visit->visit_number,
                'lab_number' => $visit->labRequest ? ($visit->labRequest->lab_no . ($visit->labRequest->suffix ? '-' . $visit->labRequest->suffix : '')) : ($visit->patient->lab ?: 'N/A'),
                'total_amount' => $totalAmount,
                'final_amount' => $finalAmount,
                'upfront_payment' => $paidAmount,
                'remaining_balance' => $remainingBalance,
                'billing_status' => $billingStatus,
                'checked_by_doctors' => $visit->checked_by_doctors,
                'last_checked_at' => $visit->last_checked_at,
            ];
        });
        
        // Log the query results for debugging
        \Log::info('VisitController::index - Query results', [
            'total_visits' => $visits->total(),
            'current_page' => $visits->currentPage(),
            'per_page' => $visits->perPage(),
            'visit_ids' => $visits->pluck('id')->toArray(),
            'visit_numbers' => $visits->pluck('visit_number')->toArray(),
            'visit_statuses' => $visits->pluck('status')->toArray(),
            'exclude_completed' => $request->get('exclude_completed'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ]);
        
        // If include_receipts is requested, wrap the data in receipt_data structure
        if ($request->has('include_receipts') && $request->include_receipts === 'true') {
            return response()->json([
                'receipt_data' => $transformedData
            ]);
        }
        
        return response()->json($transformedData);
    }

    public function store(Request $request)
    {
        return $this->createVisit($request);
    }

    public function show($id)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest', 'labRequest.reports'])
            ->findOrFail($id);
        
        // Debug: Log what we're returning
        \Log::info('Visit API show method', [
            'visit_id' => $id,
            'has_patient' => $visit->patient ? true : false,
            'has_lab_request' => $visit->labRequest ? true : false,
            'has_reports' => $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->count() : 0,
            'reports_content' => $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->pluck('content') : []
        ]);
        
        // Additional debug: Check if there are any reports at all
        $allReports = \App\Models\Report::where('lab_request_id', $visit->lab_request_id)->get();
        \Log::info('Direct reports query', [
            'lab_request_id' => $visit->lab_request_id,
            'reports_count' => $allReports->count(),
            'reports' => $allReports->map(function($report) {
                return [
                    'id' => $report->id,
                    'content' => $report->content,
                    'parsed' => json_decode($report->content, true)
                ];
            })
        ]);
        
        // Force load the relationships to ensure they're included in JSON
        $visit->load(['patient', 'visitTests.labTest', 'labRequest.reports']);
        
        // Debug: Log the actual JSON structure being sent
        $jsonData = $visit->toArray();
        \Log::info('Visit JSON structure', [
            'has_lab_request_in_json' => isset($jsonData['lab_request']),
            'lab_request_keys' => isset($jsonData['lab_request']) ? array_keys($jsonData['lab_request']) : 'N/A',
            'has_reports_in_json' => isset($jsonData['lab_request']['reports']),
            'reports_count_in_json' => isset($jsonData['lab_request']['reports']) ? count($jsonData['lab_request']['reports']) : 0,
            'reports_data' => isset($jsonData['lab_request']['reports']) ? $jsonData['lab_request']['reports'] : 'N/A'
        ]);
        
        // Ensure reports are properly included in the response
        if ($visit->labRequest && $visit->labRequest->reports) {
            $visit->labRequest->setRelation('reports', $visit->labRequest->reports);
        }
        
        return response()->json($visit);
    }

    public function debugReports($id)
    {
        $visit = Visit::with(['patient', 'labRequest.reports'])->findOrFail($id);
        
        $debugData = [
            'visit_id' => $visit->id,
            'patient_name' => $visit->patient ? $visit->patient->name : 'No patient',
            'lab_request_id' => $visit->labRequest ? $visit->labRequest->id : 'No lab request',
            'reports_count' => $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->count() : 0,
            'reports' => []
        ];
        
        if ($visit->labRequest && $visit->labRequest->reports) {
            foreach ($visit->labRequest->reports as $index => $report) {
                $debugData['reports'][] = [
                    'id' => $report->id,
                    'title' => $report->title,
                    'content' => $report->content,
                    'parsed_content' => json_decode($report->content, true),
                    'status' => $report->status,
                    'created_at' => $report->created_at
                ];
            }
        }
        
        return response()->json($debugData);
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
            ->orWhere('sender', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'phone', 'age', 'gender', 'sender']);

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
            'patient_id' => 'required|exists:patient,id',
            'tests' => 'required|array|min:1',
            'tests.*.lab_test_id' => 'required|exists:lab_tests,id',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Generate a unique visit number
            $nextId = (Visit::max('id') ?? 0) + 1;
            $visitNumber = 'VIS-' . date('Ymd') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            // Get current staff shift
            $currentShift = \App\Models\Shift::where('staff_id', auth()->id())
                ->where('status', 'open')
                ->whereDate('opened_at', today())
                ->first();

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
                'shift_id' => $currentShift?->id, // Link to current shift
                'processed_by_staff' => auth()->id(),
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

            // Create initial report automatically for each visit test
            foreach ($visit->visitTests as $visitTest) {
                try {
                    \App\Models\Report::create([
                        'title' => 'Lab Report - ' . $visitTest->labTest->name ?? 'Test Report',
                        'content' => 'Report generated automatically for visit ' . $visit->visit_number,
                        'status' => 'pending',
                        'generated_by' => auth()->id() ?? 1,
                        'generated_at' => now(),
                        'created_at' => now(),
                    ]);
                    
                    \Log::info('Report created automatically for visit test: ' . $visitTest->id);
                } catch (\Exception $e) {
                    \Log::error('Failed to create report for visit test: ' . $e->getMessage());
                    // Don't fail visit creation if report creation fails
                }
            }

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
        $visits = Visit::with(['patient', 'visitTests.labTest', 'labRequest'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Transform the data to add receipt_number field and financial data for frontend compatibility
        $transformedData = $visits->through(function ($visit) {
            // Try multiple ways to find the invoice (same logic as CheckInController)
            $invoice = null;
            
            if ($visit->labRequest) {
                // First try with lab_no
                $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
                
                // If not found, try with lab_no
                if (!$invoice && $visit->labRequest->lab_no) {
                    $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
                }
                
                // If still not found, try with lab_request_id
                if (!$invoice) {
                    $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
                }
            }
            
            // If still no invoice found, try to find by patient lab number
            if (!$invoice && $visit->patient && $visit->patient->lab) {
                $invoice = \App\Models\Invoice::where('visit_id', $visit->id)->first();
            }
            
            return [
                ...$visit->toArray(),
                'receipt_number' => $visit->visit_number, // Use visit_number as receipt_number
                'lab_number' => $visit->labRequest ? ($visit->labRequest->lab_no . ($visit->labRequest->suffix ? '-' . $visit->labRequest->suffix : '')) : ($visit->patient->lab ?: 'N/A'),
                'upfront_payment' => $invoice ? $invoice->amount_paid : ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0),
                'remaining_balance' => $invoice ? $invoice->balance : (($visit->final_amount ?? $visit->total_amount ?? 0) - ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0)),
                'billing_status' => $this->getBillingStatus($invoice, $visit),
            ];
        });

        return response()->json($transformedData);
    }

    /**
     * Get billing status based on invoice data
     */
    private function getBillingStatus($invoice, $visit)
    {
        if ($invoice) {
            if ($invoice->balance <= 0) {
                return 'paid';
            } elseif ($invoice->amount_paid > 0) {
                return 'partial';
            } else {
                return 'pending';
            }
        }
        
        // If no invoice, use patient payment data
        $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
        $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
        $remainingAmount = $totalAmount - $paidAmount;
        
        if ($remainingAmount <= 0) {
            return 'paid';
        } elseif ($paidAmount > 0) {
            return 'partial';
        } else {
            return 'pending';
        }
    }

    /**
     * Get test status based on visit tests
     */
    private function getTestStatus($visit)
    {
        if (!$visit->visitTests || $visit->visitTests->isEmpty()) {
            return 'pending';
        }

        $statuses = $visit->visitTests->pluck('status')->unique();
        
        if ($statuses->contains('completed')) {
            return 'completed';
        } elseif ($statuses->contains('under_review')) {
            return 'under_review';
        } else {
            return 'pending';
        }
    }

    public function getVisit($id)
    {
        $visit = Visit::with(['patient', 'tests.labTest', 'labRequest'])
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
            'specimen_information' => 'nullable|string',
            'gross_examination' => 'nullable|string',
            'microscopic_description' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'referred_doctor' => 'nullable|string',
            'test_status' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp,webp|max:20480', // 20MB limit
        ]);
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('visit-images', 'public');
            
            $validated['image_path'] = $imagePath;
            $validated['image_filename'] = $image->getClientOriginalName();
            $validated['image_mime_type'] = $image->getMimeType();
            $validated['image_size'] = $image->getSize();
            $validated['image_uploaded_at'] = now();
            $validated['image_uploaded_by'] = auth()->id();
        }
        
        $visit->update($validated);
        
        // If test_status is being set, also update all visit_tests status accordingly
        if (isset($validated['test_status'])) {
            $visit->visitTests()->update(['status' => $validated['test_status']]);
        }
        
        return response()->json([
            'message' => 'Visit updated successfully' . ($request->hasFile('image') ? ' with image' : ''),
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
        // Note: sample_tracking table doesn't exist, so this is disabled
        // if (!$visitTest->sampleTracking) {
        //     \App\Models\SampleTracking::create([
        //         'visit_test_id' => $visitTest->id,
        //         'sample_id' => \App\Models\SampleTracking::generateSampleId(),
        //         'status' => 'collected',
        //         'collected_at' => now(),
        //         'collected_by' => auth()->id(),
        //     ]);
        // }

        // Send result notification if requested
        if ($request->send_notification) {
            \App\Models\Notification::createResultNotification($visitTest, 'patient');
        }

        return response()->json([
            'message' => 'Test result updated successfully',
            'test' => $visitTest->load(['labTest']), // Removed sampleTracking as table doesn't exist
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
        $visit = Visit::with(['labRequest', 'labRequest.patient'])->findOrFail($id);
        
        \Log::info('Completing visit', [
            'visit_id' => $visit->id,
            'visit_number' => $visit->visit_number,
            'has_lab_request' => $visit->labRequest ? 'yes' : 'no',
            'lab_request_id' => $visit->labRequest ? $visit->labRequest->id : 'null',
            'has_patient' => $visit->labRequest && $visit->labRequest->patient ? 'yes' : 'no'
        ]);
        
        $visit->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
        
        // Create a completed report to trigger Enhanced Report creation
        if ($visit->labRequest) {
            try {
                \Log::info('Creating completed report for visit', [
                    'visit_id' => $visit->id,
                    'lab_request_id' => $visit->labRequest->id,
                    'visit_number' => $visit->visit_number
                ]);
                
                $report = \App\Models\Report::create([
                    'lab_request_id' => $visit->labRequest->id,
                    'title' => 'Lab Report - ' . $visit->visit_number,
                    'content' => 'Report completed for visit ' . $visit->visit_number,
                    'status' => 'completed',
                    'generated_by' => auth()->id() ?? 1,
                    'generated_at' => now(),
                ]);
                
                \Log::info('Completed report created successfully', [
                    'visit_id' => $visit->id,
                    'report_id' => $report->id,
                    'lab_request_id' => $visit->labRequest->id,
                    'report_status' => $report->status
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create completed report for visit', [
                    'visit_id' => $visit->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't fail visit completion if report creation fails
            }
        } else {
            \Log::warning('No lab request found for visit', ['visit_id' => $visit->id]);
        }
        
        return response()->json([
            'message' => 'Visit marked as completed successfully',
            'visit' => $visit->fresh()
        ]);
    }

    public function generateReport($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest'])->findOrFail($visitId);
        
        try {
            // Configure MPDF for Arabic support with proper margins for printing
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 8,
                'margin_right' => 8,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_header' => 0,
                'margin_footer' => 0,
                'tempDir' => storage_path('app/temp'),
                'default_font_size' => 12,
                'default_font' => 'dejavusans',
            ]);
            
            // Set font for Arabic support
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            
            // Disable image processing for faster generation
            $mpdf->showImageErrors = false;
            
            $html = view('reports.visit_report_pdf', [
                'visit' => $visit
            ])->render();
            
            $mpdf->WriteHTML($html);
            
            $filename = 'visit_report_' . $visit->visit_number . '.pdf';
            
            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');
            
            // Create response with CORS headers
            $response = response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Expose-Headers' => 'Content-Type, Content-Disposition, Content-Length'
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            \Log::error('Visit Report PDF generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadImage(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        
        // Check if user has permission to edit this visit
        if (auth()->user()->role === 'staff' && $visit->created_by !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,bmp,webp|max:20480', // 20MB limit
        ]);

        try {
            // Remove old image if exists
            if ($visit->image_path && \Storage::disk('public')->exists($visit->image_path)) {
                \Storage::disk('public')->delete($visit->image_path);
            }

            $image = $request->file('image');
            $imagePath = $image->store('visit-images', 'public');

            $visit->update([
                'image_path' => $imagePath,
                'image_filename' => $image->getClientOriginalName(),
                'image_mime_type' => $image->getMimeType(),
                'image_size' => $image->getSize(),
                'image_uploaded_at' => now(),
                'image_uploaded_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_url' => asset('storage/' . $imagePath),
                    'image_filename' => $visit->image_filename,
                    'image_size' => $visit->image_size,
                    'uploaded_at' => $visit->image_uploaded_at,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Image upload error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to upload image: ' . $e->getMessage()], 500);
        }
    }

    public function removeImage($visitId)
    {
        $visit = Visit::findOrFail($visitId);
        
        // Check if user has permission to edit this visit
        if (auth()->user()->role === 'staff' && $visit->created_by !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            // Delete image file from storage
            if ($visit->image_path && \Storage::disk('public')->exists($visit->image_path)) {
                \Storage::disk('public')->delete($visit->image_path);
            }

            // Clear image data from database
            $visit->update([
                'image_path' => null,
                'image_filename' => null,
                'image_mime_type' => null,
                'image_size' => null,
                'image_uploaded_at' => null,
                'image_uploaded_by' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image removed successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Image removal error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to remove image: ' . $e->getMessage()], 500);
        }
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

    public function markAsChecked(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        
        $request->validate([
            'doctor_name' => 'required|string|max:255',
        ]);

        $doctorName = $request->doctor_name;
        $currentDoctors = $visit->checked_by_doctors ?? [];
        
        \Log::info('Marking visit as checked', [
            'visit_id' => $visitId,
            'doctor_name' => $doctorName,
            'current_doctors_before' => $currentDoctors
        ]);
        
        // Add doctor if not already in the list
        if (!in_array($doctorName, $currentDoctors)) {
            $currentDoctors[] = $doctorName;
        }
        
        $updateData = [
            'checked_by_doctors' => $currentDoctors,
            'last_checked_at' => now()
        ];
        
        \Log::info('Updating visit with data:', [
            'visit_id' => $visitId,
            'update_data' => $updateData
        ]);
        
        $visit->update($updateData);

        // Refresh the visit to get updated data
        $visit->refresh();

        \Log::info('Visit marked as checked successfully', [
            'visit_id' => $visitId,
            'updated_doctors' => $visit->checked_by_doctors,
            'last_checked_at' => $visit->last_checked_at,
            'raw_checked_by_doctors' => $visit->getRawOriginal('checked_by_doctors')
        ]);

        return response()->json([
            'message' => 'Report marked as checked successfully',
            'checked_by_doctors' => $visit->checked_by_doctors,
            'last_checked_at' => $visit->last_checked_at
        ]);
    }

    /**
     * Get tests for receipt - handles both visitTests and patient registration sample_type
     */
    private function getTestsForReceipt($visit)
    {
        // Debug logging
        \Log::info('VisitController::getTestsForReceipt - Debug', [
            'visit_id' => $visit->id,
            'visitTests_count' => $visit->visitTests ? $visit->visitTests->count() : 'null',
            'visitTests_loaded' => $visit->relationLoaded('visitTests'),
            'metadata' => $visit->metadata,
        ]);
        
        // First try to get from visitTests (for CheckIn visits)
        if ($visit->visitTests && $visit->visitTests->count() > 0) {
            \Log::info('VisitController::getTestsForReceipt - Using visitTests', [
                'visit_id' => $visit->id,
                'tests' => $visit->visitTests->toArray(),
            ]);
            return $visit->visitTests->map(function ($visitTest) {
                return [
                    'name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                    'category' => $visitTest->testCategory ? $visitTest->testCategory->name : 'Unknown',
                    'price' => $visitTest->final_price ?: $visitTest->price,
                ];
            });
        }
        
        // If no visitTests, try to get from patient registration metadata
        $metadata = json_decode($visit->metadata ?? '{}', true);
        $patientData = $metadata['patient_data'] ?? [];
        
        \Log::info('VisitController::getTestsForReceipt - Checking metadata', [
            'visit_id' => $visit->id,
            'metadata' => $metadata,
            'patientData' => $patientData,
        ]);
        
        // Also check lab request metadata for patient registration data
        $labRequestData = [];
        if ($visit->labRequest && is_object($visit->labRequest) && !is_array($visit->labRequest) && !($visit->labRequest instanceof \Illuminate\Database\Eloquent\Collection) && isset($visit->labRequest->metadata)) {
            // metadata is already cast as array in the model, no need to json_decode
            $labRequestMetadata = is_string($visit->labRequest->metadata) ? json_decode($visit->labRequest->metadata, true) : $visit->labRequest->metadata;
            $labRequestData = $labRequestMetadata['patient_data'] ?? [];
        }
        
        // Check both visit metadata and lab request metadata for sample_type
        $sampleType = $patientData['sample_type'] ?? $labRequestData['sample_type'] ?? $metadata['sample_type'] ?? null;
        
        \Log::info('VisitController::getTestsForReceipt - Sample type found', [
            'visit_id' => $visit->id,
            'sampleType' => $sampleType,
            'labRequestData' => $labRequestData,
        ]);
        
        if (!empty($sampleType)) {
            $totalAmount = $visit->total_amount ?? $visit->final_amount ?? 0;
            
            \Log::info('VisitController::getTestsForReceipt - Creating test from sample type', [
                'visit_id' => $visit->id,
                'sampleType' => $sampleType,
                'totalAmount' => $totalAmount,
            ]);
            
            return collect([
                [
                    'name' => $sampleType,
                    'category' => 'Sample Type',
                    'price' => $totalAmount,
                ]
            ]);
        }
        
        // Fallback - return empty collection
        return collect([]);
    }
} 