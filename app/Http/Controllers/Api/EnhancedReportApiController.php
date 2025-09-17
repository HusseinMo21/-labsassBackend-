<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedReport;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnhancedReportApiController extends Controller
{
    public function index(Request $request)
    {
        $query = EnhancedReport::with(['patient', 'createdBy', 'reviewedBy', 'approvedBy']);

        // Role-based filtering
        $user = Auth::user();
        if ($user->role === 'staff') {
            // Staff can only see approved, printed, and delivered reports
            $query->whereIn('status', ['approved', 'printed', 'delivered']);
        } elseif ($user->role === 'doctor') {
            // Doctors can see under_review and approved reports
            $query->whereIn('status', ['under_review', 'approved', 'printed', 'delivered']);
        }
        // Admins can see all reports

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

        $reports = $query->orderByRaw("CASE 
            WHEN status = 'approved' THEN 1 
            WHEN status = 'printed' THEN 2 
            WHEN status = 'delivered' THEN 3 
            WHEN status = 'under_review' THEN 4 
            WHEN status = 'draft' THEN 5 
            ELSE 6 
        END")
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    public function store(Request $request)
    {
        // Role-based access control
        $user = Auth::user();
        if ($user->role === 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Staff members cannot create reports. Contact an administrator or doctor.'
            ], 403);
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
            'patient_id' => 'nullable|exists:patient,id',
            'lab_request_id' => 'nullable|exists:lab_requests,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'examination_details' => 'nullable|array',
            'quality_control' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp,webp|max:20480', // 20MB limit
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status'] = 'draft';

        // Auto-generate lab number if not provided
        if (empty($validated['lab_no'])) {
            $validated['lab_no'] = 'RPT-' . date('Y') . '-' . str_pad(EnhancedReport::count() + 1, 4, '0', STR_PAD_LEFT);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('report-images', 'public');
            
            $validated['image_path'] = $imagePath;
            $validated['image_filename'] = $image->getClientOriginalName();
            $validated['image_mime_type'] = $image->getMimeType();
            $validated['image_size'] = $image->getSize();
            $validated['image_uploaded_at'] = now();
            $validated['image_uploaded_by'] = Auth::id();
        }

        $report = EnhancedReport::create($validated);

        // Generate barcode
        $report->generateBarcode();

        return response()->json([
            'success' => true,
            'message' => 'Report created successfully',
            'data' => $report->load(['patient', 'labRequest', 'createdBy'])
        ], 201);
    }

    public function show(EnhancedReport $enhancedReport)
    {
        $enhancedReport->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy']);
        
        return response()->json([
            'success' => true,
            'data' => $enhancedReport
        ]);
    }

    public function update(Request $request, EnhancedReport $enhancedReport)
    {
        // Role-based access control
        $user = Auth::user();
        if ($user->role === 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Staff members cannot edit reports. Contact an administrator or doctor.'
            ], 403);
        }

        if (!$enhancedReport->isEditable()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be edited in its current status'
            ], 422);
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
            'patient_id' => 'nullable|exists:patient,id',
            'lab_request_id' => 'nullable|exists:lab_requests,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'examination_details' => 'nullable|array',
            'quality_control' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp,webp|max:20480', // 20MB limit
            'remove_image' => 'nullable|boolean',
        ]);

        // Handle image upload or removal
        if ($request->has('remove_image') && $request->remove_image) {
            // Remove existing image
            $enhancedReport->deleteImage();
        } elseif ($request->hasFile('image')) {
            // Remove old image if exists
            if ($enhancedReport->hasImage()) {
                $enhancedReport->deleteImage();
            }
            
            // Upload new image
            $image = $request->file('image');
            $imagePath = $image->store('report-images', 'public');
            
            $validated['image_path'] = $imagePath;
            $validated['image_filename'] = $image->getClientOriginalName();
            $validated['image_mime_type'] = $image->getMimeType();
            $validated['image_size'] = $image->getSize();
            $validated['image_uploaded_at'] = now();
            $validated['image_uploaded_by'] = Auth::id();
        }

        $enhancedReport->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Report updated successfully',
            'data' => $enhancedReport->load(['patient', 'labRequest', 'createdBy'])
        ]);
    }

    public function destroy(EnhancedReport $enhancedReport)
    {
        if (!$enhancedReport->isEditable()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be deleted in its current status'
            ], 422);
        }

        $enhancedReport->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully'
        ]);
    }

    // Workflow Actions
    public function submitForReview(EnhancedReport $report)
    {
        // Role-based access control
        $user = Auth::user();
        if ($user->role === 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Staff members cannot submit reports for review. Contact an administrator or doctor.'
            ], 403);
        }

        if ($report->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft reports can be submitted for review'
            ], 422);
        }

        $report->markAsUnderReview(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Report submitted for review',
            'data' => $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy'])
        ]);
    }

    public function approve(EnhancedReport $report)
    {
        // Role-based access control - only admins can approve
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can approve reports'
            ], 403);
        }

        if (!$report->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be approved in its current status'
            ], 422);
        }

        $report->markAsApproved(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Report approved successfully',
            'data' => $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy'])
        ]);
    }

    public function print(EnhancedReport $report)
    {
        if (!$report->canBePrinted()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be printed in its current status'
            ], 422);
        }

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
            
            $html = view('reports.enhanced_report_pdf', [
                'report' => $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy', 'imageUploadedBy'])
            ])->render();
            
            $mpdf->WriteHTML($html);
            
            $filename = 'enhanced_report_' . $report->lab_no . '.pdf';
            
            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');
            
            // Mark as printed
            $report->markAsPrinted();
            
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
            \Log::error('Enhanced Report PDF generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deliver(EnhancedReport $report)
    {
        if (!$report->canBeDelivered()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be delivered in its current status'
            ], 422);
        }

        $report->markAsDelivered();

        return response()->json([
            'success' => true,
            'message' => 'Report marked as delivered',
            'data' => $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy'])
        ]);
    }

    public function printView(EnhancedReport $report)
    {
        if (!$report->canBePrinted()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be printed in its current status'
            ], 422);
        }

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
            
            $html = view('reports.enhanced_report_pdf', [
                'report' => $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy', 'imageUploadedBy'])
            ])->render();
            
            $mpdf->WriteHTML($html);
            
            $filename = 'enhanced_report_' . $report->lab_no . '.pdf';
            
            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');
            
            // Mark as printed if not already
            if ($report->status === 'approved') {
                $report->markAsPrinted();
            }
            
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
            \Log::error('Enhanced Report PDF generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

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

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function uploadImage(Request $request, EnhancedReport $report)
    {
        // Role-based access control
        $user = Auth::user();
        if ($user->role === 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Staff members cannot upload images. Contact an administrator or doctor.'
            ], 403);
        }

        if (!$report->isEditable()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be edited in its current status'
            ], 422);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,bmp,webp|max:20480', // 20MB limit
        ]);

        try {
            // Remove old image if exists
            if ($report->hasImage()) {
                $report->deleteImage();
            }
            
            // Upload new image
            $image = $request->file('image');
            $imagePath = $image->store('report-images', 'public');
            
            $report->update([
                'image_path' => $imagePath,
                'image_filename' => $image->getClientOriginalName(),
                'image_mime_type' => $image->getMimeType(),
                'image_size' => $image->getSize(),
                'image_uploaded_at' => now(),
                'image_uploaded_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_url' => $report->getImageUrl(),
                    'image_filename' => $report->image_filename,
                    'image_size' => $report->getImageSizeFormatted(),
                    'uploaded_at' => $report->image_uploaded_at,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Image upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeImage(EnhancedReport $report)
    {
        // Role-based access control
        $user = Auth::user();
        if ($user->role === 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Staff members cannot remove images. Contact an administrator or doctor.'
            ], 403);
        }

        if (!$report->isEditable()) {
            return response()->json([
                'success' => false,
                'message' => 'This report cannot be edited in its current status'
            ], 422);
        }

        if (!$report->hasImage()) {
            return response()->json([
                'success' => false,
                'message' => 'No image to remove'
            ], 404);
        }

        try {
            $report->deleteImage();

            return response()->json([
                'success' => true,
                'message' => 'Image removed successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Image removal error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove image: ' . $e->getMessage()
            ], 500);
        }
    }
}
