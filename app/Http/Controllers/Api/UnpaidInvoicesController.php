<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnpaidInvoicesController extends Controller
{
    public function searchUnpaidInvoices(Request $request)
    {
        $query = $request->get('query', '');
        $status = $request->get('status', 'all'); // all, pending, partial, paid
        
        $invoices = Invoice::with(['visit.patient'])
            ->whereHas('visit.patient', function ($q) use ($query) {
                if ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('phone', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                }
            });

        // Filter by payment status
        if ($status !== 'all') {
            switch ($status) {
                case 'pending':
                    $invoices->where('amount_paid', 0);
                    break;
                case 'partial':
                    $invoices->where('amount_paid', '>', 0)
                             ->whereRaw('amount_paid < total_amount');
                    break;
                case 'paid':
                    $invoices->whereRaw('amount_paid >= total_amount');
                    break;
            }
        }

        $invoices = $invoices->orderBy('created_at', 'desc')
                            ->paginate(15);

        return response()->json($invoices);
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
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,insurance,other',
            'notes' => 'nullable|string',
        ]);

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
            
            DB::commit();

            return response()->json([
                'message' => 'Payment added successfully',
                'invoice' => $invoice->load(['visit.patient', 'payments']),
                'remaining_balance' => $invoice->remaining_balance,
                'is_fully_paid' => $invoice->isFullyPaid(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
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
                SUM(total_amount) as total_invoiced,
                SUM(amount_paid) as total_paid,
                SUM(total_amount - amount_paid) as total_remaining,
                COUNT(CASE WHEN amount_paid = 0 THEN 1 END) as pending_count,
                COUNT(CASE WHEN amount_paid > 0 AND amount_paid < total_amount THEN 1 END) as partial_count,
                COUNT(CASE WHEN amount_paid >= total_amount THEN 1 END) as paid_count
            ')
            ->first();

        return response()->json($summary);
    }

    public function checkPatientPortalAccess($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $totalRemaining = Invoice::with(['visit'])
            ->whereHas('visit', function ($q) use ($patientId) {
                $q->where('patient_id', $patientId);
            })
            ->get()
            ->sum(function ($invoice) {
                return $invoice->remaining_balance;
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
} 