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
        
        // Get invoices with labRequest relationship (legacy invoices)
        $invoicesWithLabRequest = Invoice::with(['labRequest.patient'])
            ->whereHas('labRequest.patient', function ($q) use ($query) {
                if ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('phone', 'like', "%{$query}%");
                }
            });

        // Get invoices without labRequest (direct invoices from Patient Registration)
        $invoicesWithoutLabRequest = Invoice::whereNull('lab_request_id')
            ->whereNotNull('lab')
            ->where(function ($q) use ($query) {
                if ($query) {
                    // Find patient by lab number
                    $q->where('lab', 'like', "%{$query}%");
                }
            });

        // Apply payment status filters
        if ($status !== 'all') {
            switch ($status) {
                case 'pending':
                    $invoicesWithLabRequest->where('paid', 0);
                    $invoicesWithoutLabRequest->where('paid', 0);
                    break;
                case 'partial':
                    $invoicesWithLabRequest->where('paid', '>', 0)->whereRaw('paid < total');
                    $invoicesWithoutLabRequest->where('paid', '>', 0)->whereRaw('paid < total');
                    break;
                case 'paid':
                    $invoicesWithLabRequest->whereRaw('paid >= total');
                    $invoicesWithoutLabRequest->whereRaw('paid >= total');
                    break;
            }
        }

        // Use a more efficient approach - get recent invoices first
        $perPage = 15;
        $currentPage = $request->get('page', 1);
        
        // Get recent invoices (last 1000) to avoid memory issues
        $recentInvoices = Invoice::orderBy('id', 'desc')
            ->limit(1000)
            ->get();
        
        // Filter by payment status and search
        $filteredInvoices = $recentInvoices->filter(function ($invoice) use ($status, $query) {
            // Apply payment status filter
            if ($status !== 'all') {
                switch ($status) {
                    case 'pending':
                        if ($invoice->paid != 0) return false;
                        break;
                    case 'partial':
                        if ($invoice->paid <= 0 || $invoice->paid >= $invoice->total) return false;
                        break;
                    case 'paid':
                        if ($invoice->paid < $invoice->total) return false;
                        break;
                }
            }
            
            // Apply search filter
            if ($query) {
                // Check if lab number matches
                if (strpos($invoice->lab, $query) !== false) {
                    return true;
                }
                
                // Check if patient name/phone matches (for invoices with labRequest)
                if ($invoice->labRequest && $invoice->labRequest->patient) {
                    $patient = $invoice->labRequest->patient;
                    if (strpos($patient->name, $query) !== false || strpos($patient->phone, $query) !== false) {
                        return true;
                    }
                } else {
                    // For direct invoices, find patient by lab number
                    $patient = \App\Models\Patient::where('lab', $invoice->lab)->first();
                    if ($patient && (strpos($patient->name, $query) !== false || strpos($patient->phone, $query) !== false)) {
                        return true;
                    }
                }
                return false;
            }
            
            return true;
        });
        
        // Sort and paginate
        $filteredInvoices = $filteredInvoices->sortByDesc('id');
        $offset = ($currentPage - 1) * $perPage;
        $paginatedInvoices = $filteredInvoices->slice($offset, $perPage)->values();

        // Transform the data to match frontend expectations
        $transformedData = $paginatedInvoices->map(function ($invoice) {
            $patient = null;
            $visitId = null;
            $visitDate = null;

            if ($invoice->labRequest && $invoice->labRequest->patient) {
                // Legacy invoice with labRequest
                $patient = $invoice->labRequest->patient;
                $visit = \App\Models\Visit::where('patient_id', $patient->id)
                    ->orderBy('id', 'desc')
                    ->first();
                $visitId = $visit ? $visit->id : null;
                $visitDate = $invoice->labRequest->created_at ? $invoice->labRequest->created_at->format('Y-m-d') : null;
            } else {
                // Direct invoice from Patient Registration - find patient by lab number
                $patient = \App\Models\Patient::where('lab', $invoice->lab)->first();
                if ($patient) {
                    $visit = \App\Models\Visit::where('patient_id', $patient->id)
                        ->orderBy('id', 'desc')
                        ->first();
                    $visitId = $visit ? $visit->id : null;
                    $visitDate = $visit ? $visit->visit_date : null;
                }
            }
            
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->lab, // Map lab to invoice_number
                'total_amount' => $invoice->total,
                'amount_paid' => $invoice->paid,
                'remaining_balance' => $invoice->remaining,
                'visit' => $patient ? [
                    'id' => $visitId,
                    'visit_date' => $visitDate,
                    'patient' => [
                        'id' => $patient->id,
                        'name' => $patient->name,
                        'phone' => $patient->phone,
                    ],
                ] : null,
            ];
        });

        // Create pagination response
        $total = $filteredInvoices->count();
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

        $invoice = Invoice::findOrFail($invoiceId);
        
        if ($invoice->isFullyPaid()) {
            return response()->json([
                'message' => 'Invoice is already fully paid',
            ], 422);
        }

        if ($request->amount > $invoice->remaining_balance) {
            return response()->json([
                'message' => 'Payment amount exceeds remaining balance',
                'remaining_balance' => $invoice->remaining_balance,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $invoice->addPayment($request->amount, $request->payment_method, $request->notes);
            
            // Note: Visit relationship is disabled, so we can't update visit billing status
            // $visit = $invoice->visit;
            // if ($visit) {
            //     $visit->update([
            //         'remaining_balance' => $invoice->remaining_balance,
            //         'billing_status' => $invoice->isFullyPaid() ? 'paid' : 'partial',
            //     ]);
            // }
            
            DB::commit();

            return response()->json([
                'message' => 'Payment added successfully',
                'invoice' => $invoice->load(['labRequest.patient', 'payments']),
                'remaining_balance' => $invoice->remaining_balance,
                'is_fully_paid' => $invoice->isFullyPaid(),
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
        $summary = DB::table('invoices')
            ->selectRaw('
                COUNT(*) as total_invoices,
                SUM(total) as total_invoiced,
                SUM(paid) as total_paid,
                SUM(remaining) as total_remaining,
                COUNT(CASE WHEN paid = 0 THEN 1 END) as pending_count,
                COUNT(CASE WHEN paid > 0 AND paid < total THEN 1 END) as partial_count,
                COUNT(CASE WHEN paid >= total THEN 1 END) as paid_count
            ')
            ->first();

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
        
        // Get the visit data to retrieve expected delivery date
        $visit = null;
        if ($invoice->labRequest) {
            $visit = \App\Models\Visit::where('lab_request_id', $invoice->lab_request_id)
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
            'tests' => [], // No visit tests available without visit relationship
            'total_amount' => $invoice->total,
            'discount_amount' => 0, // No discount in original table
            'final_amount' => $invoice->total,
            'paid_before' => $paidBefore,
            'paid_now' => $paidNow,
            'remaining_balance' => $invoice->remaining,
            'payment_method' => 'cash', // Default since payments table doesn't have payment_method field
            'expected_delivery_date' => $visit ? $visit->expected_delivery_date : null,
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