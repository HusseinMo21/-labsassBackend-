<?php

namespace App\Http\Controllers;

use App\Models\EnhancedReport;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnhancedReportController extends Controller
{
    public function index(Request $request)
    {
        $query = EnhancedReport::with(['patient', 'createdBy', 'reviewedBy', 'approvedBy']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('lab_no')) {
            $query->where('lab_no', 'like', '%' . $request->lab_no . '%');
        }

        if ($request->filled('patient_name')) {
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->patient_name . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('reports.index', compact('reports'));
    }

    public function create(Request $request)
    {
        $patients = Patient::orderBy('name')->get();
        $labRequests = LabRequest::with('patient')->orderBy('created_at', 'desc')->get();
        $users = User::where('role', '!=', 'patient')->get();

        // Pre-select lab request if provided
        $selectedLabRequest = null;
        if ($request->filled('lab_request_id')) {
            $selectedLabRequest = LabRequest::with('patient')->find($request->lab_request_id);
        }

        return view('reports.create', compact('patients', 'labRequests', 'users', 'selectedLabRequest'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nos' => 'nullable|string|max:255',
            'reff' => 'nullable|string|max:255',
            'clinical' => 'nullable|string',
            'nature' => 'nullable|string',
            'report_date' => 'nullable|date',
            'lab_no' => 'nullable|string|max:255',
            'age' => 'nullable|string|max:30',
            'gross' => 'nullable|string',
            'micro' => 'nullable|string',
            'conc' => 'nullable|string',
            'reco' => 'nullable|string',
            'type' => 'nullable|string|max:25',
            'sex' => 'nullable|string|max:10',
            'recieving' => 'nullable|string|max:100',
            'discharge' => 'nullable|string|max:100',
            'patient_id' => 'nullable|exists:patients,id',
            'lab_request_id' => 'nullable|exists:lab_requests,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'examination_details' => 'nullable|array',
            'quality_control' => 'nullable|array',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status'] = 'draft';

        // Auto-generate lab number if not provided
        if (empty($validated['lab_no'])) {
            $validated['lab_no'] = 'RPT-' . date('Y') . '-' . str_pad(EnhancedReport::count() + 1, 4, '0', STR_PAD_LEFT);
        }

        $report = EnhancedReport::create($validated);

        // Generate barcode
        $report->generateBarcode();

        return redirect()->route('reports.show', $report)
            ->with('success', 'Report created successfully.');
    }

    public function show(EnhancedReport $report)
    {
        $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy']);
        return view('reports.show', compact('report'));
    }

    public function edit(EnhancedReport $report)
    {
        if (!$report->isEditable()) {
            return redirect()->route('reports.show', $report)
                ->with('error', 'This report cannot be edited in its current status.');
        }

        $patients = Patient::orderBy('name')->get();
        $labRequests = LabRequest::with('patient')->orderBy('created_at', 'desc')->get();
        $users = User::where('role', '!=', 'patient')->get();

        return view('reports.edit', compact('report', 'patients', 'labRequests', 'users'));
    }

    public function update(Request $request, EnhancedReport $report)
    {
        if (!$report->isEditable()) {
            return redirect()->route('reports.show', $report)
                ->with('error', 'This report cannot be edited in its current status.');
        }

        $validated = $request->validate([
            'nos' => 'nullable|string|max:255',
            'reff' => 'nullable|string|max:255',
            'clinical' => 'nullable|string',
            'nature' => 'nullable|string',
            'report_date' => 'nullable|date',
            'lab_no' => 'nullable|string|max:255',
            'age' => 'nullable|string|max:30',
            'gross' => 'nullable|string',
            'micro' => 'nullable|string',
            'conc' => 'nullable|string',
            'reco' => 'nullable|string',
            'type' => 'nullable|string|max:25',
            'sex' => 'nullable|string|max:10',
            'recieving' => 'nullable|string|max:100',
            'discharge' => 'nullable|string|max:100',
            'patient_id' => 'nullable|exists:patients,id',
            'lab_request_id' => 'nullable|exists:lab_requests,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'examination_details' => 'nullable|array',
            'quality_control' => 'nullable|array',
        ]);

        $report->update($validated);

        return redirect()->route('reports.show', $report)
            ->with('success', 'Report updated successfully.');
    }

    public function destroy(EnhancedReport $report)
    {
        if (!$report->isEditable()) {
            return redirect()->route('reports.index')
                ->with('error', 'This report cannot be deleted in its current status.');
        }

        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report deleted successfully.');
    }

    // Workflow Actions
    public function submitForReview(EnhancedReport $report)
    {
        if ($report->status !== 'draft') {
            return redirect()->back()->with('error', 'Only draft reports can be submitted for review.');
        }

        $report->markAsUnderReview(Auth::id());

        return redirect()->back()->with('success', 'Report submitted for review.');
    }

    public function approve(EnhancedReport $report)
    {
        if (!$report->canBeApproved()) {
            return redirect()->back()->with('error', 'This report cannot be approved in its current status.');
        }

        $report->markAsApproved(Auth::id());

        return redirect()->back()->with('success', 'Report approved successfully.');
    }

    public function print(EnhancedReport $report)
    {
        if (!$report->canBePrinted()) {
            return redirect()->back()->with('error', 'This report cannot be printed in its current status.');
        }

        $report->markAsPrinted();

        return redirect()->back()->with('success', 'Report marked as printed.');
    }

    public function deliver(EnhancedReport $report)
    {
        if (!$report->canBeDelivered()) {
            return redirect()->back()->with('error', 'This report cannot be delivered in its current status.');
        }

        $report->markAsDelivered();

        return redirect()->back()->with('success', 'Report marked as delivered.');
    }

    // Print/Export Actions
    public function printReport(EnhancedReport $report)
    {
        $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy']);
        
        // Mark as printed if not already
        if ($report->status === 'approved') {
            $report->markAsPrinted();
        }

        return view('reports.print', compact('report'));
    }

    public function exportPdf(EnhancedReport $report)
    {
        $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy']);
        
        // You can use a PDF library like DomPDF or TCPDF here
        // For now, we'll return a view that can be printed as PDF
        return view('reports.pdf', compact('report'));
    }

    // Statistics
    public function statistics()
    {
        $stats = [
            'total_reports' => EnhancedReport::count(),
            'draft_reports' => EnhancedReport::draft()->count(),
            'under_review' => EnhancedReport::underReview()->count(),
            'approved_reports' => EnhancedReport::approved()->count(),
            'printed_reports' => EnhancedReport::printed()->count(),
            'delivered_reports' => EnhancedReport::delivered()->count(),
            'urgent_reports' => EnhancedReport::byPriority('urgent')->count(),
            'reports_today' => EnhancedReport::whereDate('created_at', today())->count(),
        ];

        return view('reports.statistics', compact('stats'));
    }
}
