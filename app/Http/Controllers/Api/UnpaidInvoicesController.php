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
        $query = $request->get('query', '');
        $status = $request->get('status', 'all'); // all, pending, partial, paid
        $perPage = 15;
        $currentPage = $request->get('page', 1);
        
        // Get visits with billing information instead of separate invoices
        $visitsQuery = Visit::with(['patient', 'labRequest'])
            ->where('total_amount', '>', 0); // Only visits with billing
        
        // Apply search filter
        if ($query) {
            $visitsQuery->where(function ($q) use ($query) {
                $q->where('visit_number', 'like', "%{$query}%")
                  ->orWhereHas('patient', function ($patientQuery) use ($query) {
                      $patientQuery->where('name', 'like', "%{$query}%")
                                  ->orWhere('phone', 'like', "%{$query}%")
                                  ->orWhere('id', 'like', "%{$query}%");
                  })
                  ->orWhereHas('labRequest', function ($labQuery) use ($query) {
                      $labQuery->where('lab_no', 'like', "%{$query}%")
                              ->orWhere('full_lab_no', 'like', "%{$query}%");
                  });
            });
        }
        
        // Get all visits and filter by payment status
        $visits = $visitsQuery->orderBy('id', 'desc')->get();
        
        // Filter by payment status
        $filteredVisits = $visits->filter(function ($visit) use ($status) {
            // Get paid amount from patient table (where the actual payment data is stored)
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
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
            $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
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
                    ],
                ],
            ];
        });

        // Create pagination response
        $total = $filteredVisits->count();
        $lastPage = ceil($total / $perPage);

        return response()->json([
            'data' => $transformedData,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ]);
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
                'payment_method' => 'required|in:cash,card,insurance,other',
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
        $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
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
            return $visit->final_amount ?? $visit->total_amount ?? 0;
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
            $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
            return $paidAmount > 0 && $paidAmount < $totalAmount;
        })->count();
        
        $paidCount = $visits->filter(function ($visit) {
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
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

    public function getFinalPaymentReceiptData(Request $request, $invoiceId)
    {
        try {
            $invoice = Invoice::with(['labRequest.patient', 'payments'])
                ->findOrFail($invoiceId);
        
        $patient = $invoice->labRequest ? $invoice->labRequest->patient : null;
        
        // Get the visit data to retrieve expected delivery date and tests
        $visit = null;
        if ($invoice->labRequest) {
            $visit = \App\Models\Visit::with(['visitTests.testCategory', 'visitTests.labTest'])
                ->where('lab_request_id', $invoice->lab_request_id)
                ->orderBy('id', 'desc')
                ->first();
        }
        
        if (!$patient) {
            return response()->json([
                'message' => 'Patient not found for this invoice',
            ], 404);
        }
        
        // Get the last payment (the final payment)
        $lastPayment = $invoice->payments()->orderBy('id', 'desc')->first();
        
        // Calculate payment breakdown
        $totalPaid = $invoice->paid;
        $paidBefore = $totalPaid - ($lastPayment ? $lastPayment->paid : 0);
        $paidNow = $lastPayment ? $lastPayment->paid : 0;
        
        // Get patient credentials
        $credentials = $patient->getPortalCredentials() ?? [
            'username' => 'N/A',
            'password' => 'N/A'
        ];
        
        // Get the user who processed the payment (author of the last payment)
        $processedBy = null;
        if ($lastPayment && $lastPayment->author) {
            $user = \App\Models\User::find($lastPayment->author);
            $processedBy = $user ? $user->name : 'Unknown';
        }
        
        return response()->json([
            'receipt_number' => $invoice->lab, // Use lab number as receipt number
            'date' => $invoice->labRequest ? $invoice->labRequest->created_at->format('Y-m-d') : now()->format('Y-m-d'),
            'patient_name' => $patient->name,
            'patient_age' => $patient->age,
            'patient_phone' => $patient->phone,
            'tests' => $visit ? $visit->visitTests->map(function ($visitTest) {
                return [
                    'name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                    'category' => $visitTest->testCategory ? $visitTest->testCategory->name : 'Unknown',
                    'price' => $visitTest->final_price ?: $visitTest->price,
                ];
            }) : [],
            'total_amount' => $invoice->total,
            'discount_amount' => 0, // No discount in original table
            'final_amount' => $invoice->total,
            'paid_before' => $paidBefore,
            'paid_now' => $paidNow,
            'remaining_balance' => $invoice->remaining,
            'payment_method' => 'cash', // Default since payments table doesn't have payment_method field
            'expected_delivery_date' => $visit ? $visit->getExpectedDeliveryDate() : now()->addDays(1)->toDateString(),
            'lab_number' => $invoice->labRequest ? $invoice->labRequest->full_lab_no : 'N/A',
            'processed_by' => $processedBy,
            'visit_id' => $invoice->labRequest ? $invoice->labRequest->id : null,
            'patient_credentials' => $credentials,
        ]);
        } catch (\Exception $e) {
            Log::error('Failed to get final payment receipt data', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to get final payment receipt data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
} 