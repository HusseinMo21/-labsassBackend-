<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // Create a new invoice for a visit
    public function store(Request $request)
    {
        $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $visit = Visit::findOrFail($request->visit_id);
            if ($visit->invoice) {
                return response()->json(['message' => 'Invoice already exists for this visit'], 400);
            }
            $invoice = Invoice::create([
                'visit_id' => $request->visit_id,
                'invoice_number' => 'INV-' . date('Y') . '-' . str_pad(Invoice::count() + 1, 6, 0, STR_PAD_LEFT),
                'invoice_date' => now(),
                'subtotal' => $request->amount,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $request->amount,
                'amount_paid' => 0,
                'balance' => $request->amount,
                'status' => 'unpaid',
                'payment_method' => null,
                'notes' => $request->notes,
                'created_by' => null,
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice' => $invoice->load(['visit.patient', 'payments'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error creating invoice', 'error' => $e->getMessage()], 500);
        }
    }

    // List all invoices (paginated)
    public function index(Request $request)
    {
        $query = Invoice::with(['visit.patient', 'visit.labRequest', 'labRequest', 'payments']);
        
        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', "%{$searchTerm}%")
                  ->orWhereHas('visit', function ($visitQuery) use ($searchTerm) {
                      $visitQuery->where('visit_number', 'like', "%{$searchTerm}%")
                                ->orWhereHas('patient', function ($patientQuery) use ($searchTerm) {
                                    $patientQuery->where('name', 'like', "%{$searchTerm}%")
                                                ->orWhere('phone', 'like', "%{$searchTerm}%");
                                });
                  })
                  ->orWhereHas('labRequest', function ($labQuery) use ($searchTerm) {
                      $labQuery->where('lab_no', 'like', "%{$searchTerm}%");
                  });
            });
        }
        
        $invoices = $query->orderBy('created_at', 'desc')->paginate(15);
        return response()->json($invoices);
    }

    // Get a single invoice
    public function show($id)
    {
        $invoice = Invoice::with(['visit.patient', 'visit.visitTests.labTest', 'visit.labRequest', 'labRequest', 'payments'])
            ->findOrFail($id);
        return response()->json($invoice);
    }

    // Add a payment to an invoice
    public function addPayment(Request $request, $invoiceId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'notes' => 'nullable|string',
        ]);
        DB::beginTransaction();
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $payment = Payment::create([
                'invoice_id' => $invoiceId,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'paid_at' => now(),
                'notes' => $request->notes,
                'created_by' => null,
            ]);
            // Recalculate amount_paid and balance (sum all payments only)
            $amountPaid = $invoice->payments()->sum('amount');
            $total = $invoice->total_amount;
            $balance = max(0, $total - $amountPaid);
            // Determine status
            $status = 'unpaid';
            if ($amountPaid >= $total && $total > 0) {
                $status = 'paid';
            } elseif ($amountPaid > 0 && $amountPaid < $total) {
                $status = 'partial';
            }
            $invoice->update([
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'status' => $status,
            ]);
            
            // Update the visit's billing status and remaining balance
            $visit = $invoice->visit;
            if ($visit) {
                $visit->update([
                    'remaining_balance' => $balance,
                    'billing_status' => $status === 'paid' ? 'paid' : 'partial',
                ]);
            }
            
            DB::commit();
            return response()->json([
                'message' => 'Payment added successfully',
                'payment' => $payment,
                'invoice' => $invoice->fresh(['payments'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error adding payment', 'error' => $e->getMessage()], 500);
        }
    }

    // Preview invoice as HTML with embedded PDF and Download/Print buttons
    public function previewInvoiceHtml($id)
    {
        $pdfUrl = url("/api/invoices/$id/download");
        $downloadUrl = $pdfUrl;
        $printScript = "<script>function printPdf() { var iframe = document.getElementById('pdfFrame'); iframe.contentWindow.focus(); iframe.contentWindow.print(); }</script>";
        return response("<html><head><title>Invoice Preview</title>$printScript<style>body{font-family:sans-serif;background:#f8faff;margin:0;padding:0;} .toolbar{padding:16px;background:#fff;border-bottom:1px solid #eee;display:flex;gap:12px;} iframe{width:100vw;height:90vh;border:none;}</style></head><body><div class='toolbar'><button onclick='window.location.href=\"$downloadUrl\"'>Download PDF</button><button onclick='printPdf()'>Print</button></div><iframe id='pdfFrame' src='$pdfUrl'></iframe></body></html>");
    }

    // Generate a report for an invoice
    public function generateInvoiceReport($id)
    {
        $invoice = Invoice::with(['visit.patient', 'visit.visitTests.labTest', 'payments'])
            ->findOrFail($id);
        $totalPaid = $invoice->payments->sum('amount');
        $remainingAmount = $invoice->total_amount - $totalPaid;
        $report = [
            'invoice' => $invoice,
            'total_paid' => $totalPaid,
            'remaining_amount' => $remainingAmount,
            'payment_history' => $invoice->payments->map(function($payment) {
                return [
                    'date' => $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i') : null,
                    'amount' => $payment->amount,
                    'method' => $payment->payment_method,
                    'notes' => $payment->notes,
                ];
            }),
        ];
        return response()->json($report);
    }

    // Invoice stats
    public function getInvoiceStats()
    {
        $stats = [
            'total_invoices' => Invoice::count(),
            'pending_invoices' => Invoice::where('status', 'pending')->count(),
            'partial_invoices' => Invoice::where('status', 'partial')->count(),
            'paid_invoices' => Invoice::where('status', 'paid')->count(),
            'total_amount' => Invoice::sum('total_amount'),
            'total_collected' => Payment::sum('amount'),
        ];
        return response()->json($stats);
    }

    // Update invoice (status, notes, price)
    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $validated = $request->validate([
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'total_amount' => 'nullable|numeric|min:0',
        ]);
        $invoice->update($validated);
        return response()->json([
            'message' => 'Invoice updated successfully',
            'invoice' => $invoice->fresh(['visit.patient', 'payments'])
        ]);
    }

    // Download invoice as PDF
    public function downloadInvoicePdf($id)
    {
        $invoice = Invoice::with(['visit.patient.credentials', 'payments'])->findOrFail($id);
        
        // Configure MPDF for Arabic support
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9,
            'tempDir' => storage_path('app/temp'),
        ]);
        
        // Set font for Arabic support
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        
        $html = view('invoices.pdf', ['invoice' => $invoice])->render();
        $mpdf->WriteHTML($html);
        
        $filename = 'invoice_' . $invoice->invoice_number . '.pdf';
        
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
    }

    // Handle OPTIONS request for CORS preflight
    public function optionsInvoiceDownload($id)
    {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
    }

    // Delete invoice
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();
        return response()->json([
            'message' => 'Invoice deleted successfully'
        ]);
    }
} 