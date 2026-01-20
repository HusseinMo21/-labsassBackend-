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
        $query = EnhancedReport::with(['patient', 'createdBy', 'reviewedBy', 'approvedBy', 'labRequest.visit']);

        // Role-based filtering
        $user = Auth::user();
        \Log::info('Enhanced Reports API called', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'request_params' => $request->all()
        ]);
        
        if ($user->role === 'staff') {
            // Staff can see approved, printed, delivered, and completed reports
            $query->whereIn('status', ['approved', 'printed', 'delivered', 'completed']);
        } elseif ($user->role === 'doctor') {
            // Doctors can see draft, under_review, approved, printed, and delivered reports
            $query->whereIn('status', ['draft', 'under_review', 'approved', 'printed', 'delivered']);
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

        $reports = $query->orderBy('created_at', 'desc')
        ->orderBy('report_date', 'desc')
        ->paginate(20);
        
        \Log::info('Enhanced Reports query results', [
            'total_reports' => $reports->total(),
            'current_page' => $reports->currentPage(),
            'per_page' => $reports->perPage(),
            'user_role' => $user->role,
            'status_filter' => $request->get('status'),
            'reports_count' => $reports->count()
        ]);

        // Transform the data to ensure correct lab number is returned
        $transformedData = $reports->through(function ($report) {
            // Use the lab number from labRequest if available, otherwise use the report's lab_no
            $correctLabNo = $report->labRequest?->full_lab_no ?? $report->lab_no;
            
            return [
                'id' => $report->id,
                'nos' => $report->nos,
                'reff' => $report->reff,
                'clinical' => $report->clinical,
                'nature' => $report->nature,
                'report_date' => $report->report_date,
                'lab_no' => $correctLabNo, // Use the correct lab number
                'age' => $report->age,
                'gross' => $report->gross,
                'micro' => $report->micro,
                'conc' => $report->conc,
                'reco' => $report->reco,
                'type' => $report->type ?: 'PATH',
                'sex' => $report->sex,
                'recieving' => $report->recieving,
                'discharge' => $report->discharge,
                'confirm' => $report->confirm,
                'print' => $report->print,
                'patient_id' => $report->patient_id,
                'lab_request_id' => $report->lab_request_id,
                'created_by' => $report->created_by,
                'reviewed_by' => $report->reviewed_by,
                'approved_by' => $report->approved_by,
                'status' => $report->status,
                'priority' => $report->priority,
                'examination_details' => $report->examination_details,
                'quality_control' => $report->quality_control,
                'barcode' => $report->barcode,
                'digital_signature' => $report->digital_signature,
                'reviewed_at' => $report->reviewed_at,
                'approved_at' => $report->approved_at,
                'printed_at' => $report->printed_at,
                'delivered_at' => $report->delivered_at,
                'image_path' => $report->image_path,
                'image_filename' => $report->image_filename,
                'image_mime_type' => $report->image_mime_type,
                'image_size' => $report->image_size,
                'image_uploaded_at' => $report->image_uploaded_at,
                'image_uploaded_by' => $report->image_uploaded_by,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
                'patient' => $report->patient,
                'labRequest' => $report->labRequest,
                'createdBy' => $report->createdBy,
                'reviewedBy' => $report->reviewedBy,
                'approvedBy' => $report->approvedBy,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData
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

    /**
     * Send report to patient dashboard
     */
    public function sendToPatient(EnhancedReport $report)
    {
        // Role-based access control - only staff can send reports to patients
        $user = Auth::user();
        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Only staff members can send reports to patients'
            ], 403);
        }

        // Check if report is in a state that can be sent to patients
        if (!in_array($report->status, ['completed', 'approved', 'printed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Report must be completed, approved, or printed before sending to patient'
            ], 422);
        }

        // Update the report status to delivered and set delivered_at timestamp
        $report->markAsDelivered();

        // Log the action
        \Log::info('Report sent to patient dashboard', [
            'report_id' => $report->id,
            'lab_no' => $report->lab_no,
            'patient_id' => $report->patient_id,
            'sent_by' => $user->id,
            'sent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report sent to patient dashboard successfully',
            'data' => $report->load(['patient', 'labRequest', 'createdBy', 'reviewedBy', 'approvedBy'])
        ]);
    }

    /**
     * Search reports across multiple fields
     */
    public function search(Request $request)
    {
        $request->validate([
            'search_term' => 'required|string|min:1',
            'fields' => 'required|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'exclude_visit_id' => 'nullable|integer|exists:visits,id',
        ]);

        $searchTerm = $request->search_term;
        $fields = explode(',', $request->fields);
        $perPage = $request->per_page ?? 15;
        $excludeVisitId = $request->exclude_visit_id;

        // Field mapping: frontend field names to database column names
        $fieldMapping = [
            'clinical_data' => 'clinical',
            'nature_of_specimen' => 'nature',
            'gross_pathology' => 'gross',
            'microscopic_examination' => 'micro',
            'conclusion' => 'conc',
            'recommendations' => 'reco',
        ];

        // Build query
        $query = EnhancedReport::with(['patient', 'labRequest.visit']);

        // Exclude current visit if specified
        if ($excludeVisitId) {
            $query->where(function ($q) use ($excludeVisitId) {
                $q->whereDoesntHave('labRequest.visit', function ($subQ) use ($excludeVisitId) {
                    $subQ->where('id', $excludeVisitId);
                })->orWhereNull('lab_request_id'); // Include reports without lab_request_id
            });
        }

        // Apply role-based filtering
        $user = Auth::user();
        if ($user->role === 'staff') {
            $query->whereIn('status', ['approved', 'printed', 'delivered', 'completed']);
        } elseif ($user->role === 'doctor') {
            $query->whereIn('status', ['draft', 'under_review', 'approved', 'printed', 'delivered']);
        }
        // Admins can see all reports

        // Build search conditions for selected fields
        $query->where(function ($q) use ($fields, $fieldMapping, $searchTerm) {
            foreach ($fields as $field) {
                $field = trim($field);
                if (isset($fieldMapping[$field])) {
                    $dbField = $fieldMapping[$field];
                    $q->orWhere($dbField, 'like', '%' . $searchTerm . '%');
                }
            }
        });

        // Execute query with pagination
        $reports = $query->orderBy('report_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform results to include matched fields
        $transformedData = $reports->getCollection()->map(function ($report) use ($fields, $fieldMapping, $searchTerm) {
            $matchedFields = [];
            $result = [
                'id' => $report->id,
                'patient_id' => $report->patient_id,
                'patient_name' => $report->patient?->name ?? $report->nos ?? 'N/A',
                'lab_no' => $report->labRequest?->full_lab_no ?? $report->lab_no ?? 'N/A',
                'report_date' => $report->report_date?->format('Y-m-d') ?? null,
                'visit_id' => $report->labRequest?->visit?->id ?? null,
            ];

            // Check which fields matched
            foreach ($fields as $field) {
                $field = trim($field);
                if (isset($fieldMapping[$field])) {
                    $dbField = $fieldMapping[$field];
                    $fieldValue = $report->$dbField;
                    
                    if ($fieldValue && stripos($fieldValue, $searchTerm) !== false) {
                        $matchedFields[] = $field;
                        // Include the field data in result
                        $result[$field] = $fieldValue;
                    }
                }
            }

            $result['matched_fields'] = $matchedFields;
            return $result;
        });

        return response()->json([
            'data' => $transformedData,
            'current_page' => $reports->currentPage(),
            'last_page' => $reports->lastPage(),
            'per_page' => $reports->perPage(),
            'total' => $reports->total(),
        ]);
    }
}
