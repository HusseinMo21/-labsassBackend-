<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Services\LabNoGenerator;
use App\Services\BarcodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class LabRequestController extends Controller
{
    protected $labNoGenerator;
    protected $barcodeGenerator;

    public function __construct(LabNoGenerator $labNoGenerator, BarcodeGenerator $barcodeGenerator)
    {
        $this->labNoGenerator = $labNoGenerator;
        $this->barcodeGenerator = $barcodeGenerator;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = LabRequest::with(['patient', 'samples', 'visits']);

            // Search by lab number, full lab number, or patient information
            if ($request->has('search') && !empty($request->search)) {
                $search = trim($request->search);
                if (strlen($search) >= 2) { // Only search if at least 2 characters
                    $query->searchByLabNo($search);
                }
            }

            // Filter by status
            if ($request->has('status') && !empty($request->status)) {
                $query->byStatus($request->status);
            }

            // Filter by date range
            if ($request->has('start_date') && $request->has('end_date') && 
                !empty($request->start_date) && !empty($request->end_date)) {
                $query->byDateRange($request->start_date, $request->end_date);
            }

            // Filter by patient phone
            if ($request->has('patient_phone') && !empty($request->patient_phone)) {
                $query->whereHas('patient', function ($q) use ($request) {
                    $q->where('phone', 'like', '%' . $request->patient_phone . '%');
                });
            }

            // Order by ID descending (most recent first) and paginate
            $perPage = $request->get('per_page', 15);
            $labRequests = $query->orderBy('id', 'desc')->paginate($perPage);

            // Transform the data to include number of samples from patient registration
            $transformedData = $labRequests->through(function ($labRequest) {
                $numberOfSamples = 0;
                
                // Get number of samples from the most recent visit's metadata
                if ($labRequest->visits && $labRequest->visits->count() > 0) {
                    $latestVisit = $labRequest->visits->sortByDesc('created_at')->first();
                    if ($latestVisit && $latestVisit->metadata) {
                        $metadata = is_string($latestVisit->metadata) ? json_decode($latestVisit->metadata, true) : ($latestVisit->metadata ?? []);
                        $patientData = $metadata['patient_data'] ?? [];
                        $numberOfSamples = intval($patientData['number_of_samples'] ?? 0);
                    }
                }
                
                // Add the number of samples to the lab request data
                $labRequest->number_of_samples = $numberOfSamples;
                
                return $labRequest;
            });

            return response()->json($transformedData);
        } catch (\Exception $e) {
            Log::error('Error in LabRequestController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Error fetching lab requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'nullable|exists:patient,id',
            'samples' => 'required|array|min:1',
            'samples.*.sample_type' => 'nullable|string|max:255',
            'samples.*.case_type' => 'nullable|string|max:255',
            'samples.*.sample_size' => 'nullable|string|max:255',
            'samples.*.number_of_samples' => 'nullable|integer|min:1',
            'samples.*.tsample' => 'nullable|string|max:255',
            'samples.*.nsample' => 'nullable|string|max:255',
            'samples.*.isample' => 'nullable|string|max:255',
            'samples.*.notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                // Generate lab number
                $labNoData = $this->labNoGenerator->generate();
                
                // Create lab request
                $labRequest = LabRequest::create([
                    'patient_id' => $request->patient_id,
                    'lab_no' => $labNoData['base'],
                    'status' => 'pending',
                    'metadata' => $request->metadata,
                ]);

                // Create samples
                foreach ($request->samples as $sampleData) {
                    $labRequest->samples()->create($sampleData);
                }

                // Generate barcode and QR code
                $this->barcodeGenerator->generateForLabRequest($labRequest->full_lab_no);

                // Load relationships
                $labRequest->load(['patient', 'samples', 'report', 'invoice']);

                Log::info('Lab request created successfully', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labRequest->lab_no,
                    'full_lab_no' => $labRequest->full_lab_no,
                    'patient_id' => $labRequest->patient_id,
                ]);

                return response()->json([
                    'message' => 'Lab request created successfully',
                    'lab_request' => $labRequest,
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create lab request', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Failed to create lab request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LabRequest $labRequest): JsonResponse
    {
        $labRequest->load(['patient', 'samples', 'report', 'invoice']);
        
        return response()->json([
            'lab_request' => $labRequest,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LabRequest $labRequest): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pending', 'received', 'in_progress', 'under_review', 'completed', 'delivered'])],
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $labRequest->update($request->only(['status', 'metadata']));
            $labRequest->load(['patient', 'samples', 'report', 'invoice']);

            Log::info('Lab request updated successfully', [
                'lab_request_id' => $labRequest->id,
                'lab_no' => $labRequest->lab_no,
                'full_lab_no' => $labRequest->full_lab_no,
            ]);

            return response()->json([
                'message' => 'Lab request updated successfully',
                'lab_request' => $labRequest,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update lab request', [
                'lab_request_id' => $labRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update lab request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LabRequest $labRequest): JsonResponse
    {
        try {
            // Delete barcode and QR code files
            $this->barcodeGenerator->deleteForLabRequest($labRequest->full_lab_no);
            
            $labRequest->delete();

            Log::info('Lab request deleted successfully', [
                'lab_request_id' => $labRequest->id,
                'lab_no' => $labRequest->lab_no,
                'full_lab_no' => $labRequest->full_lab_no,
            ]);

            return response()->json([
                'message' => 'Lab request deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete lab request', [
                'lab_request_id' => $labRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete lab request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the suffix for a lab request (staff only).
     */
    public function updateSuffix(Request $request, LabRequest $labRequest): JsonResponse
    {
        // Check if user has staff or admin role
        if (!auth()->user() || !in_array(auth()->user()->role, ['staff', 'admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only staff and admin can update suffixes.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'suffix' => ['nullable', Rule::in(['m', 'h'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $oldFullLabNo = $labRequest->full_lab_no;
            
            $labRequest->update(['suffix' => $request->suffix]);
            
            $newFullLabNo = $labRequest->full_lab_no;

            // If suffix changed, regenerate barcode and QR code
            if ($oldFullLabNo !== $newFullLabNo) {
                // Delete old files
                $this->barcodeGenerator->deleteForLabRequest($oldFullLabNo);
                
                // Generate new files
                $this->barcodeGenerator->generateForLabRequest($newFullLabNo);
            }

            $labRequest->load(['patient', 'samples', 'report', 'invoice']);

            Log::info('Lab request suffix updated successfully', [
                'lab_request_id' => $labRequest->id,
                'lab_no' => $labRequest->lab_no,
                'old_full_lab_no' => $oldFullLabNo,
                'new_full_lab_no' => $newFullLabNo,
                'suffix' => $request->suffix,
            ]);

            return response()->json([
                'message' => 'Suffix updated successfully',
                'lab_request' => $labRequest,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update lab request suffix', [
                'lab_request_id' => $labRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update suffix',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search lab requests by various criteria.
     */
    public function search(Request $request): JsonResponse
    {
        $query = LabRequest::with(['patient', 'samples']);

        if ($request->has('lab_no')) {
            $query->searchByLabNo($request->lab_no);
        }

        if ($request->has('patient_phone')) {
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('phone', 'like', '%' . $request->patient_phone . '%');
            });
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        $labRequests = $query->latest()->limit(50)->get();

        return response()->json([
            'lab_requests' => $labRequests,
        ]);
    }

    /**
     * Get statistics for lab requests.
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total' => LabRequest::count(),
            'by_status' => LabRequest::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'today' => LabRequest::whereDate('created_at', today())->count(),
            'this_week' => LabRequest::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => LabRequest::whereMonth('created_at', now()->month)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get visit by lab request ID.
     */
    public function getVisitByLabRequest(LabRequest $labRequest): JsonResponse
    {
        try {
            $visit = $labRequest->visit;
            
            if (!$visit) {
                return response()->json([
                    'error' => 'Visit not found for this lab request'
                ], 404);
            }
            
            return response()->json($visit);
        } catch (\Exception $e) {
            Log::error('Error fetching visit by lab request', [
                'lab_request_id' => $labRequest->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch visit',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive patient information by lab number.
     */
    public function getPatientDetailsByLabNo(Request $request): JsonResponse
    {
        try {
            $labNo = $request->query('lab_no');
            
            if (!$labNo) {
                return response()->json(['error' => 'Lab number is required'], 400);
            }

            // Find lab request by lab number
            $labRequest = LabRequest::where('lab_no', $labNo)
                ->with([
                    'patient.doctor',
                    'patient.organization', 
                    'visits', // Get visits to access metadata
                    'samples',
                    'report',
                    'invoice.payments'
                ])
                ->first();

            if (!$labRequest) {
                return response()->json(['error' => 'Lab request not found'], 404);
            }

            $patient = $labRequest->patient;

            // Handle case where patient is null
            if (!$patient) {
                return response()->json([
                    'error' => 'Patient not found for this lab request',
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo
                ], 404);
            }

            // Get patient registration data from the latest visit's metadata
            $patientData = [];
            $numberOfSamples = 0;
            $sampleType = 'Pathology';
            $sampleSize = 'صغيرة جدا';
            $organization = null;
            $totalAmount = 0;
            $paidAmount = 0;
            $remainingAmount = 0;
            $paymentStatus = 'unpaid';
            
            // Try to get from patient record (with error handling)
            try {
                if (isset($patient->number_of_samples) && $patient->number_of_samples) {
                    $numberOfSamples = (int)$patient->number_of_samples;
                }
                if (isset($patient->sample_type) && $patient->sample_type) {
                    $sampleType = $patient->sample_type;
                }
                if (isset($patient->sample_size) && $patient->sample_size) {
                    $sampleSize = $patient->sample_size;
                }
                if (isset($patient->organization) && $patient->organization) {
                    $organization = $patient->organization;
                }
                
                \Log::info('Lab request details - patient record sample data', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'patient_number_of_samples' => $numberOfSamples,
                    'patient_sample_type' => $sampleType,
                    'patient_sample_size' => $sampleSize,
                    'patient_organization' => $organization
                ]);
            } catch (\Exception $e) {
                \Log::warning('Error getting patient record data: ' . $e->getMessage());
            }
            
            if ($labRequest->visits && $labRequest->visits->count() > 0) {
                try {
                    $latestVisit = $labRequest->visits->sortByDesc('created_at')->first();
                    \Log::info('Lab request details - processing visit', [
                        'lab_request_id' => $labRequest->id,
                        'lab_no' => $labNo,
                        'visit_id' => $latestVisit->id,
                        'visit_metadata' => $latestVisit->metadata,
                        'visits_count' => $labRequest->visits->count()
                    ]);
                    
                    if ($latestVisit && $latestVisit->metadata) {
                        try {
                            // Handle both string and array metadata
                            $metadata = is_string($latestVisit->metadata) ? json_decode($latestVisit->metadata, true) : ($latestVisit->metadata ?? []);
                            $patientData = $metadata['patient_data'] ?? [];
                            
                            // Prioritize visit metadata over patient record (visit data is more accurate)
                            $visitNumberOfSamples = intval($patientData['number_of_samples'] ?? 0);
                            $visitSampleType = $patientData['sample_type'] ?? 'Pathology';
                            $visitSampleSize = $patientData['sample_size'] ?? 'صغيرة جدا';
                            $visitOrganization = $patientData['organization'] ?? null;
                            
                            // Use visit data if available, otherwise use patient data
                            if ($visitNumberOfSamples > 0) {
                                $numberOfSamples = $visitNumberOfSamples;
                            }
                            if ($visitSampleType !== 'Pathology') {
                                $sampleType = $visitSampleType;
                            }
                            if ($visitSampleSize !== 'صغيرة جدا') {
                                $sampleSize = $visitSampleSize;
                            }
                            if ($visitOrganization) {
                                $organization = $visitOrganization;
                            }
                            
                            \Log::info('Lab request details - extracted data', [
                                'lab_request_id' => $labRequest->id,
                                'lab_no' => $labNo,
                                'number_of_samples' => $numberOfSamples,
                                'sample_type' => $sampleType,
                                'sample_size' => $sampleSize,
                                'organization' => $organization,
                                'patient_data' => $patientData
                            ]);
                        } catch (\Exception $e) {
                            \Log::warning('Error parsing visit metadata: ' . $e->getMessage(), [
                                'visit_id' => $latestVisit->id,
                                'lab_request_id' => $labRequest->id
                            ]);
                        }
                    } else {
                        \Log::warning('No visit metadata found', [
                            'visit_id' => $latestVisit->id,
                            'lab_request_id' => $labRequest->id,
                            'has_metadata' => $latestVisit ? ($latestVisit->metadata ? 'Yes' : 'No') : 'No visit'
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error processing visit data: ' . $e->getMessage());
                }
            } else {
                \Log::warning('No visits found for lab request', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'visits_count' => $labRequest->visits ? $labRequest->visits->count() : 0
                ]);
                
                // Try to get data from lab request metadata as fallback
                if ($labRequest->metadata) {
                    try {
                        $labRequestMetadata = is_string($labRequest->metadata) ? json_decode($labRequest->metadata, true) : ($labRequest->metadata ?? []);
                        $labRequestPatientData = $labRequestMetadata['patient_data'] ?? [];
                        
                        \Log::info('Lab request details - sample data fallback debug', [
                            'lab_request_id' => $labRequest->id,
                            'lab_no' => $labNo,
                            'raw_metadata' => $labRequest->metadata,
                            'parsed_metadata' => $labRequestMetadata,
                            'patient_data' => $labRequestPatientData,
                            'current_number_of_samples' => $numberOfSamples,
                            'current_organization' => $organization
                        ]);
                        
                        if (empty($numberOfSamples) && isset($labRequestPatientData['number_of_samples'])) {
                            $numberOfSamples = (int)$labRequestPatientData['number_of_samples'];
                        }
                        if (empty($organization) && isset($labRequestPatientData['organization'])) {
                            $organization = $labRequestPatientData['organization'];
                        }
                        // Also try organization_id if organization is not available
                        if (empty($organization) && isset($labRequestPatientData['organization_id'])) {
                            $organization = $labRequestPatientData['organization_id'];
                        }
                        
                        \Log::info('Lab request details - fallback from lab request metadata', [
                            'lab_request_id' => $labRequest->id,
                            'lab_no' => $labNo,
                            'number_of_samples' => $numberOfSamples,
                            'organization' => $organization
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('Error parsing lab request metadata: ' . $e->getMessage());
                    }
                }
            }

            // Final fallback - try to get data from patient record directly
            if ($totalAmount == 0 && isset($patient->total_amount) && $patient->total_amount) {
                $totalAmount = (float)$patient->total_amount;
                \Log::info('Lab request details - final fallback total_amount from patient', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'total_amount' => $totalAmount
                ]);
            }
            
            if ($numberOfSamples == 0 && isset($patient->number_of_samples) && $patient->number_of_samples) {
                $numberOfSamples = (int)$patient->number_of_samples;
                \Log::info('Lab request details - final fallback number_of_samples from patient', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'number_of_samples' => $numberOfSamples
                ]);
            }
            
            // Final fallback - use lab request's number_of_samples field
            if ($numberOfSamples == 0 && isset($labRequest->number_of_samples) && $labRequest->number_of_samples > 0) {
                $numberOfSamples = (int)$labRequest->number_of_samples;
                \Log::info('Lab request details - final fallback number_of_samples from lab request', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'number_of_samples' => $numberOfSamples
                ]);
            }
            
            // Ultimate fallback - use default value of 1 if no samples found
            if ($numberOfSamples == 0) {
                $numberOfSamples = 1; // Default to 1 sample
                \Log::info('Lab request details - ultimate fallback using default number_of_samples', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'default_number_of_samples' => $numberOfSamples
                ]);
            }
            
            if (empty($organization) && isset($patient->organization) && $patient->organization) {
                $organization = $patient->organization;
                \Log::info('Lab request details - final fallback organization from patient', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'organization' => $organization
                ]);
            }
            
            // Final fallback - use patient's organization_id
            if (empty($organization) && isset($patient->organization_id) && $patient->organization_id) {
                $organization = $patient->organization_id;
                \Log::info('Lab request details - final fallback organization_id from patient', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'organization' => $organization
                ]);
            }

            // Final calculation of remaining amount
            $remainingAmount = $totalAmount - $paidAmount;
            $paymentStatus = $remainingAmount > 0 ? 'partial' : 'paid';
            
            \Log::info('Lab request details - final financial calculation', [
                'lab_request_id' => $labRequest->id,
                'lab_no' => $labNo,
                'final_total_amount' => $totalAmount,
                'final_paid_amount' => $paidAmount,
                'final_remaining_amount' => $remainingAmount,
                'final_payment_status' => $paymentStatus,
                'final_number_of_samples' => $numberOfSamples,
                'final_organization' => $organization,
                'patient_total_amount' => $patient->total_amount ?? 'null',
                'patient_amount_paid' => $patient->amount_paid ?? 'null',
                'patient_number_of_samples' => $patient->number_of_samples ?? 'null',
                'patient_organization' => $patient->organization ?? 'null'
            ]);

            // Get all visits for this patient (handle potential null)
            $visits = collect();
            try {
                $visits = $patient->visits()->with([
                    'visitTests.labTest'
                ])->orderBy('visit_date', 'desc')->get();
            } catch (\Exception $e) {
                \Log::warning('Error fetching visits for patient: ' . $e->getMessage(), [
                    'patient_id' => $patient->id,
                    'lab_request_id' => $labRequest->id
                ]);
            }

            // Create samples data from patient registration
            $samples = collect();
            for ($i = 1; $i <= $numberOfSamples; $i++) {
                $samples->push([
                    'id' => $i,
                    'sample_type' => $sampleType,
                    'sample_id' => $labRequest->full_lab_no . '-S' . $i,
                    'sample_size' => $sampleSize,
                ]);
            }

            // Create tests data from patient registration
            $allTests = collect();
            if ($sampleType) {
                $allTests->push([
                    'test_name' => $sampleType,
                ]);
            }

            // Get payment history from visit metadata
            $paymentHistory = [];
            
            // Get financial data from visit metadata (from patient registration)
            // Variables already initialized above
            
            // Try to get financial data from patient record (fallback)
            try {
                if (isset($patient->total_amount) && $patient->total_amount) {
                    $totalAmount = (float)$patient->total_amount;
                }
                if (isset($patient->amount_paid) && $patient->amount_paid) {
                    $paidAmount = (float)$patient->amount_paid;
                }
                
                \Log::info('Lab request details - patient record financial data (initial)', [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo,
                    'patient_total_amount' => $totalAmount,
                    'patient_paid_amount' => $paidAmount
                ]);
            } catch (\Exception $e) {
                \Log::warning('Error getting patient financial data: ' . $e->getMessage());
            }
            
            // Try to get financial data from lab request metadata as fallback
            if ($labRequest->metadata && ($totalAmount == 0 || $paidAmount == 0)) {
                try {
                    $labRequestMetadata = is_string($labRequest->metadata) ? json_decode($labRequest->metadata, true) : $labRequest->metadata;
                    $labRequestPatientData = $labRequestMetadata['patient_data'] ?? [];
                    
                    \Log::info('Lab request details - raw metadata debug', [
                        'lab_request_id' => $labRequest->id,
                        'lab_no' => $labNo,
                        'raw_metadata' => $labRequest->metadata,
                        'parsed_metadata' => $labRequestMetadata,
                        'patient_data' => $labRequestPatientData
                    ]);
                    
                    if ($totalAmount == 0 && isset($labRequestPatientData['total_amount'])) {
                        $totalAmount = (float)$labRequestPatientData['total_amount'];
                    }
                    if ($paidAmount == 0 && isset($labRequestPatientData['amount_paid'])) {
                        $paidAmount = (float)$labRequestPatientData['amount_paid'];
                    }
                    
                    // If we still don't have total_amount, try to calculate it from the lab request
                    if ($totalAmount == 0 && $paidAmount > 0) {
                        // For now, assume total_amount is at least the amount_paid
                        $totalAmount = $paidAmount;
                        \Log::info('Lab request details - estimated total_amount from amount_paid', [
                            'lab_request_id' => $labRequest->id,
                            'lab_no' => $labNo,
                            'estimated_total_amount' => $totalAmount
                        ]);
                    }
                    
                    \Log::info('Lab request details - financial fallback from lab request metadata', [
                        'lab_request_id' => $labRequest->id,
                        'lab_no' => $labNo,
                        'total_amount' => $totalAmount,
                        'paid_amount' => $paidAmount
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Error parsing lab request metadata for financial data: ' . $e->getMessage());
                }
            }
            
            try {
                if ($labRequest->visits && $labRequest->visits->count() > 0) {
                    $latestVisit = $labRequest->visits->sortByDesc('created_at')->first();
                    if ($latestVisit) {
                        // Initialize variables to avoid undefined variable errors
                        $paymentDetails = [];
                        $patientData = [];
                        $financialData = [];
                        $visitMetadata = [];
                        
                        // PRIORITY 1: Get payment data from patient table (source of truth - same as UnpaidInvoicesController)
                        // This ensures consistency across the system
                        $totalAmount = $patient->total_amount ?? 0;
                        $paidAmount = $patient->amount_paid ?? 0;
                        
                        // PRIORITY 2: If patient table doesn't have data, try visit direct fields
                        if ($totalAmount == 0) {
                            $totalAmount = $latestVisit->total_amount ?? $latestVisit->final_amount ?? 0;
                        }
                        if ($paidAmount == 0) {
                            $paidAmount = $latestVisit->upfront_payment ?? 0;
                        }
                        
                        // PRIORITY 3: Fallback to visit metadata if still no data
                        try {
                            $visitMetadata = is_string($latestVisit->metadata) ? json_decode($latestVisit->metadata ?? '{}', true) : ($latestVisit->metadata ?? []);
                            $paymentDetails = $visitMetadata['payment_details'] ?? [];
                            $patientData = $visitMetadata['patient_data'] ?? [];
                            $financialData = $visitMetadata['financial_data'] ?? [];
                            
                            // Only use metadata if patient table doesn't have the data
                            if ($totalAmount == 0) {
                                $totalAmount = $financialData['total_amount'] ?? $patientData['total_amount'] ?? 0;
                            }
                            if ($paidAmount == 0) {
                                $paidAmount = $financialData['amount_paid'] ?? $paymentDetails['total_paid'] ?? $patientData['amount_paid'] ?? 0;
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error parsing visit metadata for payment: ' . $e->getMessage(), [
                                'visit_id' => $latestVisit->id,
                                'lab_request_id' => $labRequest->id
                            ]);
                            // Variables already initialized above, so no need to set them again
                        }
                        
                        $remainingAmount = $totalAmount - $paidAmount;
                        $paymentStatus = $remainingAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');
                        
                        \Log::info('Lab request details - financial calculation (using patient table as source of truth)', [
                            'lab_request_id' => $labRequest->id,
                            'lab_no' => $labNo,
                            'visit_id' => $latestVisit->id,
                            'patient_id' => $patient->id,
                            'patient_total_amount' => $patient->total_amount ?? 0,
                            'patient_amount_paid' => $patient->amount_paid ?? 0,
                            'calculated_total_amount' => $totalAmount,
                            'calculated_paid_amount' => $paidAmount,
                            'remaining_amount' => $remainingAmount,
                            'payment_status' => $paymentStatus,
                            'data_source' => 'patient_table_prioritized'
                        ]);
                    
                    // Create payment history entry
                    $paymentMethod = 'Cash';
                    if (isset($paymentDetails['additional_payment_method'])) {
                        $paymentMethod = $paymentDetails['additional_payment_method'];
                    } elseif (isset($patientData['additional_payment_method'])) {
                        $paymentMethod = $patientData['additional_payment_method'];
                    } elseif ($latestVisit->payment_method) {
                        $paymentMethod = $latestVisit->payment_method;
                    }
                    
                    // Get invoice number - try multiple sources
                    $invoiceNumber = $latestVisit->invoice_number;
                    if (!$invoiceNumber && $labRequest->full_lab_no) {
                        $invoiceNumber = $labRequest->full_lab_no;
                    } elseif (!$invoiceNumber && $labRequest->lab_no) {
                        $invoiceNumber = $labRequest->lab_no;
                    } elseif (!$invoiceNumber && $latestVisit->visit_number) {
                        $invoiceNumber = $latestVisit->visit_number;
                    }
                    
                    // Build payment breakdown - ensure it matches paidAmount (same as CheckInController)
                    $paymentBreakdown = [];
                    $totalPaidFromBreakdown = 0;
                    
                    $cashAmount = floatval($paymentDetails['amount_paid_cash'] ?? $patientData['amount_paid_cash'] ?? 0);
                    $cardAmount = floatval($paymentDetails['amount_paid_card'] ?? $patientData['amount_paid_card'] ?? 0);
                    
                    if ($cashAmount > 0) {
                        $totalPaidFromBreakdown += $cashAmount;
                        $paymentBreakdown['cash'] = $cashAmount;
                    }
                    if ($cardAmount > 0) {
                        $totalPaidFromBreakdown += $cardAmount;
                        $paymentBreakdown['card'] = $cardAmount;
                        $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? $patientData['additional_payment_method'] ?? null;
                    }
                    
                    // If breakdown exists but doesn't match paidAmount, normalize it
                    if (!empty($paymentBreakdown) && $totalPaidFromBreakdown > 0 && abs($totalPaidFromBreakdown - $paidAmount) > 0.01) {
                        // Scale down the breakdown to match paidAmount
                        $scaleFactor = $paidAmount / $totalPaidFromBreakdown;
                        if (isset($paymentBreakdown['cash'])) {
                            $paymentBreakdown['cash'] = round($paymentBreakdown['cash'] * $scaleFactor, 2);
                        }
                        if (isset($paymentBreakdown['card'])) {
                            $paymentBreakdown['card'] = round($paymentBreakdown['card'] * $scaleFactor, 2);
                        }
                    }
                    
                    // If no breakdown exists but we have a payment method, create a simple breakdown
                    if (empty($paymentBreakdown) && $paidAmount > 0) {
                        if (strtolower($paymentMethod) === 'cash' || !$paymentMethod) {
                            $paymentBreakdown['cash'] = $paidAmount;
                        } else {
                            $paymentBreakdown['card'] = $paidAmount;
                            $paymentBreakdown['card_method'] = $paymentMethod;
                        }
                    }
                    
                    $paymentHistory[] = [
                        'visit_id' => $latestVisit->id,
                        'visit_date' => $latestVisit->visit_date,
                        'invoice_number' => $invoiceNumber ?? 'N/A',
                        'total_amount' => $totalAmount,
                        'amount_paid' => $paidAmount,
                        'balance' => $remainingAmount,
                        'status' => $remainingAmount <= 0 ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Unpaid'),
                        'payment_method' => $paymentMethod,
                        'payment_breakdown' => $paymentBreakdown,
                        'created_at' => $latestVisit->created_at ?? now(),
                    ];
                }
            }
            } catch (\Exception $e) {
                \Log::warning('Error processing financial data: ' . $e->getMessage(), [
                    'lab_request_id' => $labRequest->id,
                    'lab_no' => $labNo
                ]);
            }

            // Get reports (handle potential null)
            $reports = collect();
            try {
                $reports = $patient->reports()->orderBy('created_at', 'desc')->get();
            } catch (\Exception $e) {
                \Log::warning('Error fetching reports for patient: ' . $e->getMessage(), [
                    'patient_id' => $patient->id,
                    'lab_request_id' => $labRequest->id
                ]);
            }

            $comprehensiveData = [
                'lab_request' => [
                    'id' => $labRequest->id,
                    'lab_no' => $labRequest->lab_no,
                    'full_lab_no' => $labRequest->full_lab_no,
                    'status' => $labRequest->status,
                    'suffix' => $labRequest->suffix,
                    'created_at' => $labRequest->created_at,
                    'updated_at' => $labRequest->updated_at,
                    'barcode_url' => $labRequest->barcode_url,
                    'qr_code_url' => $labRequest->qr_code_url,
                ],
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'gender' => $patient->gender,
                    'birth_date' => $patient->age ? now()->subYears($patient->age)->format('Y-m-d') : null,
                    'age' => $patient->age,
                    'phone' => $patient->phone,
                    'whatsapp_number' => $patient->whatsapp_number,
                    'address' => $patient->address,
                    'emergency_contact' => $patient->emergency_contact,
                    'emergency_phone' => $patient->emergency_phone,
                    'medical_history' => $patient->medical_history,
                    'allergies' => $patient->allergies,
                    'national_id' => $patient->national_id,
                    'insurance_provider' => $patient->insurance_provider,
                    'insurance_number' => $patient->insurance_number,
                    'has_insurance' => $patient->has_insurance,
                    'insurance_coverage' => $patient->insurance_coverage,
                    'billing_address' => $patient->billing_address,
                    'emergency_relationship' => $patient->emergency_relationship,
                ],
                'doctor' => ($patient->doctor ?? null) ? [
                    'id' => is_object($patient->doctor) ? ($patient->doctor->id ?? null) : null,
                    'name' => is_object($patient->doctor) ? ($patient->doctor->name ?? null) : $patient->doctor,
                ] : null,
                'organization' => $organization ? [
                    'name' => $organization,
                ] : (($patient->organization ?? null) ? [
                    'id' => is_object($patient->organization) ? ($patient->organization->id ?? null) : null,
                    'name' => is_object($patient->organization) ? ($patient->organization->name ?? null) : $patient->organization,
                ] : null),
                'samples' => $samples,
                'all_tests' => $allTests,
                'payment_history' => $paymentHistory,
                'reports' => $reports->map(function($report) {
                    return [
                        'id' => $report->id,
                        'title' => $report->title,
                        'content' => $report->content,
                        'status' => $report->status,
                        'generated_at' => $report->generated_at,
                    ];
                }),
                'visits_summary' => [
                    'total_visits' => $visits->count(),
                    'total_tests' => $allTests->count(),
                    'total_amount' => $totalAmount,
                    'total_paid' => $paidAmount,
                    'total_balance' => $remainingAmount,
                    'last_visit' => $visits->first() ? $visits->first()->visit_date : null,
                ]
            ];

            return response()->json($comprehensiveData);
        } catch (\Exception $e) {
            Log::error('Failed to get patient details by lab number', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'lab_no' => $request->query('lab_no')
            ]);
            return response()->json([
                'error' => 'Failed to get patient details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
