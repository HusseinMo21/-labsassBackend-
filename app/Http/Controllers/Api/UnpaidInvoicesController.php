<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnpaidInvoicesController extends Controller
{
    public function searchUnpaidInvoices(Request $request)
    {
        try {
            $query = $request->get('query', '');
            $status = $request->get('status', 'all'); // all, pending, partial, paid
            $perPage = 15;
            $currentPage = $request->get('page', 1);
            
            \Log::info('UnpaidInvoicesController::searchUnpaidInvoices called', [
                'query' => $query,
                'status' => $status,
                'page' => $currentPage,
            ]);
            
            // Get ALL visits - remove billing restrictions for search
            $visitsQuery = Visit::with(['patient', 'labRequest']);
        
        // Apply search filter
                if ($query) {
                    $visitsQuery->where(function ($q) use ($query) {
                        $q->where('visit_number', 'like', "%{$query}%")
                          ->orWhereHas('patient', function ($patientQuery) use ($query) {
                              $patientQuery->where('name', 'like', "%{$query}%")
                                          ->orWhere('phone', 'like', "%{$query}%")
                                          ->orWhere('id', 'like', "%{$query}%")
                                          ->orWhere('lab', 'like', "%{$query}%"); // Search in patient.lab field
                          })
                          ->orWhereHas('labRequest', function ($labQuery) use ($query) {
                              $labQuery->where('lab_no', 'like', "%{$query}%")
                                      ->orWhereRaw("CONCAT(lab_no, COALESCE(suffix, '')) LIKE ?", ["%{$query}%"]);
                          });
                    });
                }
        
        // Get all visits and filter by payment status
        $visits = $visitsQuery->orderBy('id', 'desc')->get();
        
        \Log::info('UnpaidInvoicesController::searchUnpaidInvoices visits found', [
            'total_visits' => $visits->count(),
            'search_query' => $query,
            'all_visits_with_lab_11111' => $visits->filter(function($visit) {
                return $visit->labRequest && 
                       ($visit->labRequest->lab_no === '11111' || 
                        $visit->labRequest->full_lab_no === '11111');
            })->map(function($visit) {
                return [
                    'id' => $visit->id,
                    'visit_number' => $visit->visit_number,
                    'lab_no' => $visit->labRequest->lab_no,
                    'full_lab_no' => $visit->labRequest->full_lab_no,
                    'patient_name' => $visit->patient->name ?? 'no patient',
                ];
            })->toArray(),
            'sample_visit' => $visits->first() ? [
                'id' => $visits->first()->id,
                'visit_number' => $visits->first()->visit_number,
                'total_amount' => $visits->first()->total_amount,
                'final_amount' => $visits->first()->final_amount,
                'patient_total_amount' => $visits->first()->patient->total_amount ?? 'null',
                'patient_amount_paid' => $visits->first()->patient->amount_paid ?? 'null',
                'lab_request' => $visits->first()->labRequest ? [
                    'id' => $visits->first()->labRequest->id,
                    'lab_no' => $visits->first()->labRequest->lab_no,
                    'suffix' => $visits->first()->labRequest->suffix,
                    'full_lab_no' => $visits->first()->labRequest->full_lab_no,
                ] : 'no lab request',
            ] : 'no visits found',
        ]);
        
        // Filter by payment status
        $filteredVisits = $visits->filter(function ($visit) use ($status) {
            // Get paid amount from patient table (where the actual payment data is stored)
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            // Use patient total_amount if available, otherwise fallback to visit amounts
            $totalAmount = $visit->patient->total_amount ?? $visit->final_amount ?? $visit->total_amount ?? 0;
            $remainingAmount = $totalAmount - $paidAmount;
            
            switch ($status) {
                case 'pending':
                    return $paidAmount == 0;
                case 'partial':
                    return $paidAmount > 0 && $remainingAmount > 0;
                case 'paid':
                    return $remainingAmount <= 0;
                default:
                    return true;
            }
        });
        
        // Sort and paginate
        $filteredVisits = $filteredVisits->sortByDesc('id');
        $offset = ($currentPage - 1) * $perPage;
        $paginatedVisits = $filteredVisits->slice($offset, $perPage)->values();

        // Transform the data to match frontend expectations
        $transformedData = $paginatedVisits->map(function ($visit) {
            // Get paid amount from patient table (where the actual payment data is stored)
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            // Use patient total_amount if available, otherwise fallback to visit amounts
            $totalAmount = $visit->patient->total_amount ?? $visit->final_amount ?? $visit->total_amount ?? 0;
            $remainingAmount = $totalAmount - $paidAmount;
            
            return [
                'id' => $visit->id,
                'invoice_number' => $visit->visit_number, // Use visit number as invoice number
                'total_amount' => $totalAmount,
                'amount_paid' => $paidAmount,
                'remaining_balance' => $remainingAmount,
                'status' => $remainingAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending'),
                'visit' => [
                    'id' => $visit->id,
                    'visit_date' => $visit->visit_date,
                    'patient' => [
                        'id' => $visit->patient->id,
                        'name' => $visit->patient->name,
                        'phone' => $visit->patient->phone,
                        'lab' => $visit->patient->lab,
                    ],
                ],
            ];
        });

        // Create pagination response
        $total = $filteredVisits->count();
        $lastPage = ceil($total / $perPage);

        \Log::info('UnpaidInvoicesController::searchUnpaidInvoices response', [
            'total_visits' => $visits->count(),
            'filtered_visits' => $filteredVisits->count(),
            'transformed_data_count' => $transformedData->count(),
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'search_query' => $query,
            'status_filter' => $status,
            'sample_transformed_data' => $transformedData->first() ? [
                'id' => $transformedData->first()['id'],
                'invoice_number' => $transformedData->first()['invoice_number'],
                'total_amount' => $transformedData->first()['total_amount'],
                'amount_paid' => $transformedData->first()['amount_paid'],
                'remaining_balance' => $transformedData->first()['remaining_balance'],
                'status' => $transformedData->first()['status'],
            ] : 'no transformed data',
        ]);

            return response()->json([
                'data' => $transformedData,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ]);
        } catch (\Exception $e) {
            \Log::error('UnpaidInvoicesController::searchUnpaidInvoices error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'from' => 0,
                'to' => 0,
                'error' => 'An error occurred while fetching invoices',
            ], 500);
        }
    }

    public function testEndpoint()
    {
        try {
            $visits = Visit::with(['patient', 'labRequest'])->limit(10)->get();
            $labRequests = \App\Models\LabRequest::limit(10)->get();
            
            // Check specifically for lab number 11111
            $lab11111 = \App\Models\LabRequest::where('lab_no', '11111')->first();
            $lab11111Full = \App\Models\LabRequest::whereRaw("CONCAT(lab_no, COALESCE(suffix, '')) = ?", ['11111'])->first();
            
            return response()->json([
                'message' => 'Test endpoint working',
                'visits_count' => $visits->count(),
                'lab_requests_count' => $labRequests->count(),
                'searching_for_lab_11111' => [
                    'lab_no_11111_exists' => $lab11111 ? true : false,
                    'lab_11111_data' => $lab11111 ? [
                        'id' => $lab11111->id,
                        'lab_no' => $lab11111->lab_no,
                        'suffix' => $lab11111->suffix,
                        'full_lab_no' => $lab11111->full_lab_no,
                        'patient_id' => $lab11111->patient_id,
                    ] : null,
                    'full_lab_11111_exists' => $lab11111Full ? true : false,
                    'full_lab_11111_data' => $lab11111Full ? [
                        'id' => $lab11111Full->id,
                        'lab_no' => $lab11111Full->lab_no,
                        'suffix' => $lab11111Full->suffix,
                        'full_lab_no' => $lab11111Full->full_lab_no,
                        'patient_id' => $lab11111Full->patient_id,
                    ] : null,
                ],
                'all_lab_numbers' => $labRequests->map(function ($lr) {
                    return [
                        'id' => $lr->id,
                        'lab_no' => $lr->lab_no,
                        'suffix' => $lr->suffix,
                        'full_lab_no' => $lr->full_lab_no,
                    ];
                }),
                'sample_visits' => $visits->map(function ($visit) {
                    return [
                        'id' => $visit->id,
                        'visit_number' => $visit->visit_number,
                        'total_amount' => $visit->total_amount,
                        'patient_name' => $visit->patient->name ?? 'No patient',
                        'lab_request' => $visit->labRequest ? [
                            'id' => $visit->labRequest->id,
                            'lab_no' => $visit->labRequest->lab_no,
                            'suffix' => $visit->labRequest->suffix,
                            'full_lab_no' => $visit->labRequest->full_lab_no,
                        ] : 'No lab request',
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function getPatientBalance($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $invoices = Invoice::with(['visit'])
            ->whereHas('visit', function ($q) use ($patientId) {
                $q->where('patient_id', $patientId);
            })
            ->get();

        $totalInvoiced = $invoices->sum('total_amount');
        $totalPaid = $invoices->sum('amount_paid');
        $totalRemaining = $totalInvoiced - $totalPaid;

        $unpaidInvoices = $invoices->filter(function ($invoice) {
            return $invoice->remaining_balance > 0;
        });

        return response()->json([
            'patient' => $patient,
            'summary' => [
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'total_remaining' => $totalRemaining,
                'unpaid_invoices_count' => $unpaidInvoices->count(),
            ],
            'unpaid_invoices' => $unpaidInvoices->values(),
        ]);
    }

    public function addPayment(Request $request, $invoiceId)
    {
        // Log the request data for debugging
        Log::info('Add payment request', [
            'invoice_id' => $invoiceId,
            'request_data' => $request->all(),
        ]);

        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:cash,card,Fawry,InstaPay,VodafoneCash,Other',
                'notes' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for add payment', [
                'invoice_id' => $invoiceId,
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        // Find the visit instead of invoice
        $visit = Visit::with('patient')->findOrFail($invoiceId);
        
        // Get current paid amount from patient table (where the actual payment data is stored)
        $currentPaidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
        $totalAmount = $visit->patient->total_amount ?? $visit->final_amount ?? $visit->total_amount ?? 0;
        $remainingBalance = $totalAmount - $currentPaidAmount;
        
        if ($remainingBalance <= 0) {
            return response()->json([
                'message' => 'Visit is already fully paid',
            ], 422);
        }

        if ($request->amount > $remainingBalance) {
            return response()->json([
                'message' => 'Payment amount exceeds remaining balance',
                'remaining_balance' => $remainingBalance,
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update the patient with the new payment (where the actual payment data is stored)
            $newPaidAmount = $currentPaidAmount + $request->amount;
            $newRemainingBalance = $totalAmount - $newPaidAmount;
            
            // Get existing payment details from visit metadata
            $metadata = json_decode($visit->metadata ?? '{}', true);
            $paymentDetails = $metadata['payment_details'] ?? [];
            
            // Update payment breakdown based on payment method
            $paymentMethod = $request->payment_method;
            $paymentAmount = $request->amount;
            
            if ($paymentMethod === 'cash') {
                $paymentDetails['amount_paid_cash'] = ($paymentDetails['amount_paid_cash'] ?? 0) + $paymentAmount;
            } else {
                // For card, Fawry, InstaPay, VodafoneCash, Other
                $paymentDetails['amount_paid_card'] = ($paymentDetails['amount_paid_card'] ?? 0) + $paymentAmount;
                $paymentDetails['additional_payment_method'] = $paymentMethod;
            }
            
            // Update total paid amount
            $paymentDetails['total_paid'] = $newPaidAmount;
            
            // Update metadata with new payment details
            $metadata['payment_details'] = $paymentDetails;
            
            // Update patient's amount_paid field
            $visit->patient->update([
                'amount_paid' => $newPaidAmount,
            ]);
            
            // Also update visit for consistency
            $visit->update([
                'upfront_payment' => $newPaidAmount,
                'payment_status' => $newRemainingBalance <= 0 ? 'paid' : 'partial',
                'payment_method' => $request->payment_method,
                'payment_notes' => $request->notes,
                'metadata' => json_encode($metadata),
            ]);
            
            DB::commit();

            return response()->json([
                'message' => 'Payment added successfully',
                'visit' => $visit->fresh(['patient']),
                'remaining_balance' => $newRemainingBalance,
                'is_fully_paid' => $newRemainingBalance <= 0,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add payment', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to add payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUnpaidInvoicesSummary()
    {
        // Get visits with billing information instead of separate invoices
        $visits = Visit::where('total_amount', '>', 0)->get();
        
        $totalInvoices = $visits->count();
        $totalInvoiced = $visits->sum(function ($visit) {
            return $visit->patient->total_amount ?? $visit->final_amount ?? $visit->total_amount ?? 0;
        });
        $totalPaid = $visits->sum(function ($visit) {
            return $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
        });
        $totalRemaining = $totalInvoiced - $totalPaid;
        
        $pendingCount = $visits->filter(function ($visit) {
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            return $paidAmount == 0;
        })->count();
        
        $partialCount = $visits->filter(function ($visit) {
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            $totalAmount = $visit->patient->total_amount ?? $visit->final_amount ?? $visit->total_amount ?? 0;
            return $paidAmount > 0 && $paidAmount < $totalAmount;
        })->count();
        
        $paidCount = $visits->filter(function ($visit) {
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            $totalAmount = $visit->patient->total_amount ?? $visit->final_amount ?? $visit->total_amount ?? 0;
            return $paidAmount >= $totalAmount;
        })->count();

        $summary = (object) [
            'total_invoices' => $totalInvoices,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'pending_count' => $pendingCount,
            'partial_count' => $partialCount,
            'paid_count' => $paidCount,
        ];

        return response()->json($summary);
    }

    public function checkPatientPortalAccess($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $totalRemaining = Invoice::with(['labRequest.patient'])
            ->whereHas('labRequest.patient', function ($q) use ($patientId) {
                $q->where('id', $patientId);
            })
            ->get()
            ->sum(function ($invoice) {
                return $invoice->remaining;
            });

        $hasUnpaidBalance = $totalRemaining > 0;

        return response()->json([
            'patient' => $patient,
            'portal_access' => [
                'has_unpaid_balance' => $hasUnpaidBalance,
                'total_remaining' => $totalRemaining,
                'can_access_portal' => !$hasUnpaidBalance,
                'message' => $hasUnpaidBalance 
                    ? 'You need to complete your payment to access your results.' 
                    : 'You can access your test results.',
            ],
        ]);
    }

    public function getFinalPaymentReceiptData(Request $request, $visitId)
    {
        try {
            // Find the visit instead of invoice
            $visit = \App\Models\Visit::with(['patient', 'visitTests.testCategory', 'visitTests.labTest', 'labRequest'])
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
        
        // Calculate total paid from payment breakdown
        $totalPaid = $paymentDetails['total_paid'] ?? $patientData['amount_paid'] ?? 0;
        
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
        
        $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
        
        // Build payment breakdown
        $paymentBreakdown = [];
        if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
            $paymentBreakdown['cash'] = $paymentDetails['amount_paid_cash'];
        }
        if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
            $paymentBreakdown['card'] = $paymentDetails['amount_paid_card'];
            $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
        }
        
        // For final payment receipt, we assume the last payment was the final one
        $paidNow = $totalPaid; // All payments are considered as the final payment
        $paidBefore = 0; // No previous payments for simplicity
        
        // Get patient credentials
        $credentials = $patient->getPortalCredentials() ?? [
            'username' => 'N/A',
            'password' => 'N/A'
        ];
        
        // Get the user who processed the payment (current user)
        $processedBy = auth()->user() ? auth()->user()->name : 'Unknown';
        
        return response()->json([
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
        ]);
        } catch (\Exception $e) {
            Log::error('Failed to get final payment receipt data', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to get final payment receipt data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tests for receipt - handles both visitTests and patient registration sample_type
     */
    private function getTestsForReceipt($visit)
    {
        // First try to get from visitTests (for CheckIn visits)
        if ($visit->visitTests && $visit->visitTests->count() > 0) {
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
        
        // Also check lab request metadata for patient registration data
        $labRequestData = [];
        if ($visit->labRequest && is_object($visit->labRequest) && !is_array($visit->labRequest) && !($visit->labRequest instanceof \Illuminate\Database\Eloquent\Collection) && isset($visit->labRequest->metadata)) {
            $labRequestMetadata = json_decode($visit->labRequest->metadata, true);
            $labRequestData = $labRequestMetadata['patient_data'] ?? [];
        }
        
        // Check both visit metadata and lab request metadata for sample_type
        $sampleType = $patientData['sample_type'] ?? $labRequestData['sample_type'] ?? $metadata['sample_type'] ?? null;
        
        if (!empty($sampleType)) {
            $totalAmount = $visit->total_amount ?? $visit->final_amount ?? 0;
            
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