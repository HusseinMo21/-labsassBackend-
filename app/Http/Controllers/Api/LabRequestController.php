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
            $query = LabRequest::with(['patient', 'samples', 'report', 'invoice']);

            // Search by lab number or full lab number
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->searchByLabNo($search);
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

            $labRequests = $query->latest()->paginate(15);

            return response()->json($labRequests);
        } catch (\Exception $e) {
            Log::error('Error in LabRequestController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            'patient_id' => 'nullable|exists:patients,id',
            'samples' => 'required|array|min:1',
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
                    'patient.visits.visitTests.labTest',
                    'patient.visits.invoice.payments',
                    'samples',
                    'report',
                    'invoice'
                ])
                ->first();

            if (!$labRequest) {
                return response()->json(['error' => 'Lab request not found'], 404);
            }

            $patient = $labRequest->patient;

            // Get all visits for this patient
            $visits = $patient->visits()->with([
                'visitTests.labTest',
                'invoice.payments'
            ])->orderBy('visit_date', 'desc')->get();

            // Get all lab tests from all visits
            $allTests = collect();
            foreach ($visits as $visit) {
                foreach ($visit->visitTests as $visitTest) {
                    $allTests->push([
                        'visit_id' => $visit->id,
                        'visit_date' => $visit->visit_date,
                        'visit_number' => $visit->visit_number,
                        'test_id' => $visitTest->lab_test_id,
                        'test_name' => $visitTest->labTest->name,
                        'test_code' => $visitTest->labTest->code,
                        'test_price' => $visitTest->price,
                        'status' => $visitTest->status,
                        'barcode_uid' => $visitTest->barcode_uid,
                    ]);
                }
            }

            // Get payment history
            $paymentHistory = collect();
            foreach ($visits as $visit) {
                if ($visit->invoice) {
                    $paymentHistory->push([
                        'visit_id' => $visit->id,
                        'visit_date' => $visit->visit_date,
                        'invoice_number' => $visit->invoice->invoice_number,
                        'total_amount' => $visit->invoice->total_amount,
                        'amount_paid' => $visit->invoice->amount_paid,
                        'balance' => $visit->invoice->balance,
                        'status' => $visit->invoice->status,
                        'payment_method' => $visit->invoice->payment_method,
                        'created_at' => $visit->invoice->created_at,
                    ]);
                }
            }

            // Get reports
            $reports = $patient->reports()->with('labTest')->orderBy('created_at', 'desc')->get();

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
                    'birth_date' => $patient->birth_date,
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
                'doctor' => $patient->doctor ? [
                    'id' => $patient->doctor->id,
                    'name' => $patient->doctor->name,
                ] : null,
                'organization' => $patient->organization ? [
                    'id' => $patient->organization->id,
                    'name' => $patient->organization->name,
                ] : null,
                'samples' => $labRequest->samples->map(function($sample) {
                    return [
                        'id' => $sample->id,
                        'tsample' => $sample->tsample,
                        'nsample' => $sample->nsample,
                        'isample' => $sample->isample,
                        'notes' => $sample->notes,
                        'created_at' => $sample->created_at,
                    ];
                }),
                'all_tests' => $allTests,
                'payment_history' => $paymentHistory,
                'reports' => $reports->map(function($report) {
                    return [
                        'id' => $report->id,
                        'title' => $report->title,
                        'content' => $report->content,
                        'status' => $report->status,
                        'generated_at' => $report->generated_at,
                        'lab_test' => $report->labTest ? [
                            'name' => $report->labTest->name,
                            'code' => $report->labTest->code,
                        ] : null,
                    ];
                }),
                'visits_summary' => [
                    'total_visits' => $visits->count(),
                    'total_tests' => $allTests->count(),
                    'total_amount' => $paymentHistory->sum('total_amount'),
                    'total_paid' => $paymentHistory->sum('amount_paid'),
                    'total_balance' => $paymentHistory->sum('balance'),
                    'last_visit' => $visits->first() ? $visits->first()->visit_date : null,
                ]
            ];

            return response()->json($comprehensiveData);
        } catch (\Exception $e) {
            Log::error('Failed to get patient details by lab number: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get patient details'], 500);
        }
    }
}
