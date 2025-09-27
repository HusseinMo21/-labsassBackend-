<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\Doctor;
use App\Models\Organization;
use App\Services\LabNoGenerator;
use App\Services\BarcodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    protected $labNoGenerator;
    protected $barcodeGenerator;

    public function __construct(LabNoGenerator $labNoGenerator, BarcodeGenerator $barcodeGenerator)
    {
        $this->labNoGenerator = $labNoGenerator;
        $this->barcodeGenerator = $barcodeGenerator;
    }

    private function findOrCreateDoctor($doctorName)
    {
        if (empty($doctorName)) {
            return null;
        }
        
        return Doctor::firstOrCreate(
            ['name' => trim($doctorName)],
            ['name' => trim($doctorName)]
        );
    }

    private function findOrCreateOrganization($organizationName)
    {
        if (empty($organizationName)) {
            return null;
        }
        
        return Organization::firstOrCreate(
            ['name' => trim($organizationName)],
            ['name' => trim($organizationName)]
        );
    }


    public function index(Request $request)
    {
        $query = Patient::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('whatsapp_number', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('lab', 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('id', 'desc')->paginate(15);
        
        // Transform the data to ensure proper formatting and avoid N/A values
        $patients->getCollection()->transform(function ($patient) {
            // Calculate birth_date from age if not set
            if ($patient->age && !$patient->birth_date) {
                $patient->birth_date = now()->subYears($patient->age)->format('Y-m-d');
            }
            
            // Handle address - prioritize address_required or address_optional
            if (!$patient->address) {
                if ($patient->address_required) {
                    $patient->address = $patient->address_required;
                } elseif ($patient->address_optional) {
                    $patient->address = $patient->address_optional;
                }
            }
            
            // Handle doctor name - prioritize sender, then doctor_id
            if (!$patient->sender && $patient->doctor_id) {
                $patient->sender = $patient->doctor_id;
            }
            
            // Handle organization - use organization_id if available
            if (!$patient->organization && $patient->organization_id) {
                $patient->organization = $patient->organization_id;
            }
            
            return $patient;
        });
        
        // Convert to array and ensure proper formatting
        $patientsArray = $patients->toArray();
        foreach ($patientsArray['data'] as &$patient) {
            // Calculate birth_date from age if not set
            if ($patient['age'] && !$patient['birth_date']) {
                $patient['birth_date'] = now()->subYears($patient['age'])->format('Y-m-d');
            }
            
            // Handle address - prioritize address_required or address_optional
            if (!$patient['address']) {
                if (isset($patient['address_required']) && $patient['address_required']) {
                    $patient['address'] = $patient['address_required'];
                } elseif (isset($patient['address_optional']) && $patient['address_optional']) {
                    $patient['address'] = $patient['address_optional'];
                }
            }
            
            // Handle doctor name - prioritize sender, then doctor_id
            if (!$patient['sender'] && isset($patient['doctor_id']) && $patient['doctor_id']) {
                $patient['sender'] = $patient['doctor_id'];
            }
            
            // Handle organization - use organization_id if available
            if (!$patient['organization'] && isset($patient['organization_id']) && $patient['organization_id']) {
                $patient['organization'] = $patient['organization_id'];
            }
            
            // Ensure we don't have empty strings showing as N/A
            $patient['address'] = $patient['address'] ?: null;
            $patient['sender'] = $patient['sender'] ?: null;
            $patient['organization'] = $patient['organization'] ?: null;
            $patient['emergency_contact'] = $patient['emergency_contact'] ?: null;
        }

        return response()->json($patientsArray);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'address_required' => 'nullable|string',
            'address_optional' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'doctor' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'sender' => 'nullable|string|max:255', // Doctor name
            'status' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            // New payment fields
            'total_amount' => 'nullable|numeric|min:0',
            'amount_paid_cash' => 'nullable|numeric|min:0',
            'amount_paid_card' => 'nullable|numeric|min:0',
            'additional_payment_method' => 'nullable|string|max:255',
            'amount_paid' => 'nullable|numeric|min:0',
            'lab_number' => 'nullable|string|max:255',
            'sample_type' => 'nullable|string|max:255',
            'sample_size' => 'nullable|string|max:255',
            'number_of_samples' => 'nullable|integer|min:1',
            'day_of_week' => 'nullable|string|max:255',
            'attendance_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'previous_tests' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Auto-create user for patient
        $username = $request->lab_number ?? 'pt-' . strtolower(Str::random(8));
        $password = $request->phone ?? Str::random(10);
        $user = User::create([
            'name' => $username,
            'email' => $username . '@patients.local',
            'role' => 'patient',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $patientData = $validator->validated();
        $patientData['user_id'] = $user->id;
        
        // Handle address - use address_required if available, otherwise use address
        if (isset($patientData['address_required']) && !empty($patientData['address_required'])) {
            $patientData['address'] = $patientData['address_required'];
        } elseif (isset($patientData['address_optional']) && !empty($patientData['address_optional'])) {
            $patientData['address'] = $patientData['address_optional'];
        }
        
        // Handle doctor - use sender if available, otherwise use doctor field
        $doctorName = null;
        if (isset($patientData['sender']) && !empty($patientData['sender'])) {
            $doctorName = $patientData['sender'];
        } elseif (isset($patientData['doctor']) && !empty($patientData['doctor'])) {
            $doctorName = $patientData['doctor'];
        }
        
        if ($doctorName) {
            // Create doctor record and store name as string in doctor_id field
            $doctor = $this->findOrCreateDoctor($doctorName);
            $patientData['doctor_id'] = trim($doctorName); // Store name as string
        }
        
        if (isset($patientData['organization']) && !empty($patientData['organization'])) {
            // Create organization record and store name as string in organization_id field
            $organization = $this->findOrCreateOrganization($patientData['organization']);
            $patientData['organization_id'] = trim($patientData['organization']); // Store name as string
        }
        
        // Clean up fields that don't exist in the patient table
        unset($patientData['doctor']);
        unset($patientData['organization']);
        unset($patientData['sender']);
        unset($patientData['address_required']);
        unset($patientData['address_optional']);
        unset($patientData['status']);
        unset($patientData['age']);
        
        // Handle payment data and preserve lab_number
        $paymentData = [];
        $manualLabNumber = null;
        
        if (isset($patientData['amount_paid'])) {
            $paymentData['amount_paid'] = $patientData['amount_paid'];
        }
        if (isset($patientData['amount_paid_cash'])) {
            $paymentData['amount_paid_cash'] = $patientData['amount_paid_cash'];
        }
        if (isset($patientData['amount_paid_card'])) {
            $paymentData['amount_paid_card'] = $patientData['amount_paid_card'];
        }
        if (isset($patientData['additional_payment_method'])) {
            $paymentData['additional_payment_method'] = $patientData['additional_payment_method'];
        }
        
        // Preserve manually entered lab number
        if (isset($patientData['lab_number']) && !empty($patientData['lab_number'])) {
            $manualLabNumber = $patientData['lab_number'];
        }
        
        // Clean up additional fields that don't exist in the patient table
        unset($patientData['total_amount']);
        unset($patientData['amount_paid_cash']);
        unset($patientData['amount_paid_card']);
        unset($patientData['additional_payment_method']);
        unset($patientData['lab_number']);
        unset($patientData['sample_type']);
        unset($patientData['sample_size']);
        unset($patientData['number_of_samples']);
        unset($patientData['day_of_week']);
        unset($patientData['attendance_date']);
        unset($patientData['delivery_date']);
        unset($patientData['previous_tests']);
        
        $patient = Patient::create($patientData);

        // Create visit record if payment data is provided
        $visit = null;
        if (!empty($paymentData) || isset($request->total_amount)) {
            $visitData = [
                'patient_id' => $patient->id,
                'visit_number' => \App\Models\Visit::generateVisitNumber(),
                'visit_date' => $request->attendance_date ?? now()->toDateString(),
                'visit_time' => now()->toTimeString(),
                'expected_delivery_date' => $request->delivery_date ?? now()->addDays(1)->toDateString(),
                'total_amount' => $request->total_amount ?? 0,
                'final_amount' => $request->total_amount ?? 0,
                'upfront_payment' => $paymentData['amount_paid'] ?? 0,
                'payment_method' => $paymentData['additional_payment_method'] ?? 'cash',
                'billing_status' => ($paymentData['amount_paid'] ?? 0) >= ($request->total_amount ?? 0) ? 'paid' : 'partial',
                'status' => 'pending',
                'created_by' => auth()->id() ?? 1,
                'metadata' => json_encode([
                    'payment_details' => [
                        'amount_paid_cash' => $paymentData['amount_paid_cash'] ?? 0,
                        'amount_paid_card' => $paymentData['amount_paid_card'] ?? 0,
                        'additional_payment_method' => $paymentData['additional_payment_method'] ?? 'cash',
                        'total_paid' => $paymentData['amount_paid'] ?? 0,
                    ],
                    'created_via' => 'patient_registration',
                    'patient_data' => $request->all(),
                ]),
            ];
            
            $visit = \App\Models\Visit::create($visitData);
            
            // Update patient with payment information
            if (isset($paymentData['amount_paid'])) {
                $patient->update(['amount_paid' => $paymentData['amount_paid']]);
            }
        }

        // Create patient credentials record
        $patient->credentials()->create([
            'username' => $username,
            'original_password' => $password,
            'hashed_password' => Hash::make($password),
            'is_active' => true,
        ]);

        // Generate lab number and create lab request
        if ($manualLabNumber) {
            // Use manually entered lab number
            $labNoData = ['full' => $manualLabNumber];
        } else {
            // Generate automatic lab number
            $labNoData = $this->labNoGenerator->generate();
        }
        
        $labRequest = LabRequest::create([
            'patient_id' => $patient->id,
            'lab_no' => $labNoData['full'],
            'suffix' => null,
            'status' => 'pending',
            'metadata' => json_encode([
                'created_via' => 'patient_registration',
                'patient_data' => $patientData,
                'lab_number_source' => $manualLabNumber ? 'manual' : 'auto_generated',
            ]),
        ]);

        // Update patient with lab number
        $patient->update(['lab' => $labNoData['full']]);

        // Create initial report automatically for the patient
        try {
            \App\Models\Report::create([
                'lab_request_id' => $labRequest->id,
                'title' => 'Lab Report - ' . $patient->name,
                'content' => 'Report generated automatically for patient ' . $patient->name,
                'status' => 'pending',
                'generated_by' => auth()->id() ?? 1,
                'generated_at' => now(),
                'created_at' => now(),
            ]);
            
            \Log::info('Report created automatically for patient: ' . $patient->id);
        } catch (\Exception $e) {
            \Log::error('Failed to create report for patient: ' . $e->getMessage());
            // Don't fail patient creation if report creation fails
        }

        try {
            $response = [
                'message' => 'Patient created successfully',
                'patient_id' => $patient->id,
                'lab_number' => $labNoData['full'],
                'user_credentials' => [
                    'username' => $username,
                    'password' => $password,
                ],
            ];
            
            // Include visit information if created
            if ($visit) {
                $response['visit_id'] = $visit->id;
                $response['visit_number'] = $visit->visit_number;
                $response['payment_status'] = $visit->billing_status;
                $response['total_amount'] = $visit->total_amount;
                $response['amount_paid'] = $visit->upfront_payment;
                $response['remaining_balance'] = $visit->total_amount - $visit->upfront_payment;
                $response['lab_number'] = $labNoData['full'];
            }
            
            return response()->json($response, 201);
        } catch (\Exception $e) {
            \Log::error('Error in patient creation response', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Patient created successfully',
                'patient_id' => $patient->id,
                'lab_number' => $labNoData['full'],
            ], 201);
        }
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'visits' => function ($q) {
                $q->with(['visitTests.labTest', 'invoice', 'labRequest'])->latest();
            },
            'visits.visitTests.performedBy',
        ]);

        return response()->json($patient);
    }

    public function update(Request $request, Patient $patient)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'address_required' => 'nullable|string',
            'address_optional' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'doctor' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'sender' => 'nullable|string|max:255', // Doctor name
            'status' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            // New payment fields
            'total_amount' => 'nullable|numeric|min:0',
            'amount_paid_cash' => 'nullable|numeric|min:0',
            'amount_paid_card' => 'nullable|numeric|min:0',
            'additional_payment_method' => 'nullable|string|max:255',
            'amount_paid' => 'nullable|numeric|min:0',
            'lab_number' => 'nullable|string|max:255',
            'sample_type' => 'nullable|string|max:255',
            'sample_size' => 'nullable|string|max:255',
            'number_of_samples' => 'nullable|integer|min:1',
            'day_of_week' => 'nullable|string|max:255',
            'attendance_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'previous_tests' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $patientData = $validator->validated();
        
        // Handle address - use address_required if available, otherwise use address
        if (isset($patientData['address_required']) && !empty($patientData['address_required'])) {
            $patientData['address'] = $patientData['address_required'];
        } elseif (isset($patientData['address_optional']) && !empty($patientData['address_optional'])) {
            $patientData['address'] = $patientData['address_optional'];
        }
        
        // Handle doctor - use sender if available, otherwise use doctor field
        $doctorName = null;
        if (isset($patientData['sender']) && !empty($patientData['sender'])) {
            $doctorName = $patientData['sender'];
        } elseif (isset($patientData['doctor']) && !empty($patientData['doctor'])) {
            $doctorName = $patientData['doctor'];
        }
        
        if ($doctorName) {
            // Create doctor record and store name as string in doctor_id field
            $doctor = $this->findOrCreateDoctor($doctorName);
            $patientData['doctor_id'] = trim($doctorName); // Store name as string
        }
        
        if (isset($patientData['organization']) && !empty($patientData['organization'])) {
            // Create organization record and store name as string in organization_id field
            $organization = $this->findOrCreateOrganization($patientData['organization']);
            $patientData['organization_id'] = trim($patientData['organization']); // Store name as string
        }
        
        // Clean up fields that don't exist in the patient table
        unset($patientData['doctor']);
        unset($patientData['organization']);
        unset($patientData['sender']);
        unset($patientData['address_required']);
        unset($patientData['address_optional']);
        unset($patientData['status']);
        unset($patientData['age']);
        
        $patient->update($patientData);

        return response()->json([
            'message' => 'Patient updated successfully',
            'patient' => $patient->fresh(),
        ]);
    }

    public function destroy(Patient $patient)
    {
        // Check if user is admin
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'Only administrators can delete patients',
            ], 403);
        }

        try {
            \DB::beginTransaction();

            // Get counts for logging
            $visitsCount = $patient->visits()->count();
            $labRequestsCount = $patient->labRequests()->count();
            
            // Get invoices through lab requests
            $labRequestIds = $patient->labRequests()->pluck('id');
            $invoicesCount = \App\Models\Invoice::whereIn('lab_request_id', $labRequestIds)->count();
            
            $paymentsCount = 0;
            $samplesCount = 0;
            $reportsCount = 0;
            $visitTestsCount = 0;

            // Delete all related data in the correct order to avoid foreign key constraints

            // 1. Delete payments (through invoices)
            $invoices = \App\Models\Invoice::whereIn('lab_request_id', $labRequestIds)->get();
            foreach ($invoices as $invoice) {
                $paymentsCount += $invoice->payments()->count();
                $invoice->payments()->delete();
            }

            // 2. Delete invoices
            \App\Models\Invoice::whereIn('lab_request_id', $labRequestIds)->delete();

            // 3. Delete visit tests (through visits)
            foreach ($patient->visits as $visit) {
                $visitTestsCount += $visit->visitTests()->count();
                $visit->visitTests()->delete();
            }

            // 4. Delete visits
            $patient->visits()->delete();

            // 5. Delete samples (through lab requests)
            foreach ($patient->labRequests as $labRequest) {
                $samplesCount += $labRequest->samples()->count();
                $labRequest->samples()->delete();
            }

            // 6. Delete reports (through lab requests)
            foreach ($patient->labRequests as $labRequest) {
                $reportsCount += $labRequest->reports()->count();
                $labRequest->reports()->delete();
            }

            // 7. Delete enhanced reports (through lab requests)
            foreach ($patient->labRequests as $labRequest) {
                \App\Models\EnhancedReport::where('lab_request_id', $labRequest->id)->delete();
            }

            // 8. Delete lab requests
            $patient->labRequests()->delete();

            // 9. Delete patient credentials
            $patient->credentials()->delete();

            // 10. Finally, delete the patient
            $patient->delete();

            \DB::commit();

            // Log the deletion
            \Log::info('Patient deleted by admin', [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'deleted_by' => auth()->user()->id,
                'deleted_data' => [
                    'visits' => $visitsCount,
                    'lab_requests' => $labRequestsCount,
                    'invoices' => $invoicesCount,
                    'payments' => $paymentsCount,
                    'samples' => $samplesCount,
                    'reports' => $reportsCount,
                    'visit_tests' => $visitTestsCount,
                ]
            ]);

            return response()->json([
                'message' => 'Patient and all related data deleted successfully',
                'deleted_data' => [
                    'visits' => $visitsCount,
                    'lab_requests' => $labRequestsCount,
                    'invoices' => $invoicesCount,
                    'payments' => $paymentsCount,
                    'samples' => $samplesCount,
                    'reports' => $reportsCount,
                    'visit_tests' => $visitTestsCount,
                ]
            ]);

        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Error deleting patient', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to delete patient: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $patient = Patient::where('user_id', $user->id)
            ->with(['visits' => function ($q) {
                $q->with(['visitTests.labTest', 'invoice'])->latest();
            }, 'visits.visitTests.performedBy'])
            ->first();
        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }
        return response()->json($patient);
    }

    public function patientsByTest(Request $request)
    {
        $testName = $request->get('test_name');
        if (!$testName) {
            return response()->json(['patients' => []]);
        }
        
        try {
            $patients = \App\Models\Patient::whereHas('visits.visitTests.labTest', function ($q) use ($testName) {
                $q->where('name', 'like', "%$testName%");
            })
            ->select('id', 'name', 'gender', 'age', 'phone')
            ->get();
            
            // Add birth_date attribute calculated from age
            $patients->map(function($p) { 
                $p->birth_date = $p->age ? now()->subYears($p->age)->format('Y-m-d') : null;
                return $p;
            });
            
            return response()->json(['patients' => $patients]);
        } catch (\Exception $e) {
            \Log::error('Error in patientsByTest: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch patients'], 500);
        }
    }

    public function fullHistory($id)
    {
        $patient = Patient::with([
            'visits.visitTests.labTest',
            'visits.invoice.payments',
        ])->findOrFail($id);
        return response()->json(['patient' => $patient]);
    }

    public function reportsList($id)
    {
        $patient = Patient::with(['visits.visitTests.labTest'])->findOrFail($id);
        $reports = [];
        foreach ($patient->visits as $visit) {
            foreach ($visit->visitTests as $test) {
                $reports[] = [
                    'report_id' => $test->id,
                    'test_name' => $test->labTest->name ?? '',
                    'visit_id' => $visit->id,
                    'visit_date' => $visit->visit_date,
                    'result_value' => $test->result_value,
                    'result_status' => $test->result_status,
                    'status' => $test->status,
                ];
            }
        }
        return response()->json(['reports' => $reports]);
    }

    public function paymentsHistory($id)
    {
        $patient = Patient::with(['visits.invoice.payments'])->findOrFail($id);
        $payments = [];
        $total_paid = 0;
        $total_due = 0;
        foreach ($patient->visits as $visit) {
            if ($visit->invoice) {
                foreach ($visit->invoice->payments as $payment) {
                    $payments[] = [
                        'amount' => $payment->amount,
                        'method' => $payment->payment_method,
                        'paid_at' => $payment->paid_at,
                        'notes' => $payment->notes,
                    ];
                    $total_paid += $payment->amount;
                }
                $total_due += max(0, $visit->invoice->total_amount - $visit->invoice->amount_paid);
            }
        }
        return response()->json([
            'payments' => $payments,
            'total_paid' => $total_paid,
            'total_due' => $total_due,
        ]);
    }

    public function printAllReports($id)
    {
        $patient = Patient::with(['visits.visitTests.labTest'])->findOrFail($id);
        $reports = [];
        foreach ($patient->visits as $visit) {
            foreach ($visit->visitTests as $test) {
                $reports[] = [
                    'test_name' => $test->labTest->name ?? '',
                    'visit_date' => $visit->visit_date,
                    'result_value' => $test->result_value,
                    'result_status' => $test->result_status,
                    'status' => $test->status,
                ];
            }
        }
        
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
        
        $html = view('reports.all_reports_pdf', [
            'patient' => $patient,
            'reports' => $reports,
        ])->render();
        $mpdf->WriteHTML($html);
        
        $filename = 'all_reports_' . $patient->id . '.pdf';
        
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

    public function printSingleReport($reportId)
    {
        \Log::info("Starting PDF generation for VisitTest ID: {$reportId}");
        
        // Optimized database query - only get what we need
        $test = \App\Models\VisitTest::with([
            'labTest:id,name',
            'visit:id,patient_id,visit_date,visit_number,clinical_data,microscopic_description,diagnosis,recommendations,referred_doctor,lab_request_id',
            'visit.patient:id,name,birth_date,gender',
            'visit.labRequest:id,lab_no,barcode_url'
        ])->findOrFail($reportId);
        
        $visit = $test->visit;
        \Log::info("Found visit: {$visit->visit_number} for patient: {$visit->patient->name}");
        
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
            
            $html = view('reports.professional_pathology_report', [
                'visit' => $visit,
            ])->render();
            
            $mpdf->WriteHTML($html);
            
            $filename = 'pathology_report_' . ($visit->labRequest->lab_no ?? $visit->visit_number) . '.pdf';
            
            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');
            
            \Log::info("PDF generated successfully. Size: " . strlen($pdfContent) . " bytes");
            
        } catch (\Exception $e) {
            \Log::error('PDF generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
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
    }

    public function myReports(Request $request)
    {
        $user = $request->user();
        $patient = Patient::where('user_id', $user->id)->first();
        
        if (!$patient) {
            \Log::warning('Patient not found for user', ['user_id' => $user->id]);
            return response()->json(['message' => 'Patient not found'], 404);
        }

        // Log patient access for security audit
        \Log::info('Patient accessing their reports', [
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $reports = [];
        
        // ONLY get Enhanced Reports that have been delivered (sent by staff)
        // Double security: ensure patient_id matches the authenticated patient
        $enhancedReports = \App\Models\EnhancedReport::where('patient_id', $patient->id)
            ->where('status', 'delivered')
            ->with(['labRequest.visit'])
            ->get();
        
        foreach ($enhancedReports as $enhancedReport) {
            $reports[] = [
                'id' => $enhancedReport->id,
                'type' => 'enhanced',
                'visit_id' => $enhancedReport->labRequest?->visit?->id,
                'lab_no' => $enhancedReport->lab_no,
                'test_name' => 'Pathology Report',
                'test_category' => $enhancedReport->type ?: 'PATH',
                'result_value' => $enhancedReport->conc,
                'result_status' => $enhancedReport->status,
                'visit_date' => $enhancedReport->labRequest?->visit?->visit_date,
                'report_date' => $enhancedReport->report_date,
                'delivered_at' => $enhancedReport->delivered_at,
                'status' => $enhancedReport->status,
                'patient_name' => $patient->name,
                'clinical_data' => $enhancedReport->clinical,
                'microscopic_description' => $enhancedReport->micro,
                'diagnosis' => $enhancedReport->conc,
                'recommendations' => $enhancedReport->reco,
                'gross_examination' => $enhancedReport->gross,
                'nature_of_specimen' => $enhancedReport->nature,
                'barcode' => $enhancedReport->barcode,
            ];
        }

        // Sort reports by report date (newest first)
        usort($reports, function($a, $b) {
            $dateA = $a['report_date'] ? strtotime($a['report_date']) : 0;
            $dateB = $b['report_date'] ? strtotime($b['report_date']) : 0;
            return $dateB - $dateA;
        });

        return response()->json(['reports' => $reports]);
    }

    public function getMyReport(Request $request, $reportId)
    {
        $user = $request->user();
        $patient = Patient::where('user_id', $user->id)->first();
        
        if (!$patient) {
            \Log::warning('Patient not found for user', ['user_id' => $user->id]);
            return response()->json(['message' => 'Patient not found'], 404);
        }

        // Log specific report access for security audit
        \Log::info('Patient accessing specific report', [
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'user_id' => $user->id,
            'report_id' => $reportId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // ONLY get Enhanced Reports that have been delivered (sent by staff)
        // Triple security: ensure report ID, patient_id, and delivered status
        $enhancedReport = \App\Models\EnhancedReport::where('id', $reportId)
            ->where('patient_id', $patient->id)
            ->where('status', 'delivered')
            ->with(['labRequest.visit'])
            ->first();

        if (!$enhancedReport) {
            \Log::warning('Patient attempted to access unauthorized report', [
                'patient_id' => $patient->id,
                'report_id' => $reportId,
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);
            return response()->json(['message' => 'Report not found or not available yet'], 404);
        }

        return response()->json([
            'report' => [
                'id' => $enhancedReport->id,
                'type' => 'enhanced',
                'visit_id' => $enhancedReport->labRequest?->visit?->id,
                'lab_no' => $enhancedReport->lab_no,
                'test_name' => 'Pathology Report',
                'test_category' => $enhancedReport->type ?: 'PATH',
                'result_value' => $enhancedReport->conc,
                'result_status' => $enhancedReport->status,
                'visit_date' => $enhancedReport->labRequest?->visit?->visit_date,
                'report_date' => $enhancedReport->report_date,
                'delivered_at' => $enhancedReport->delivered_at,
                'status' => $enhancedReport->status,
                'patient_name' => $patient->name,
                'clinical_data' => $enhancedReport->clinical,
                'microscopic_description' => $enhancedReport->micro,
                'diagnosis' => $enhancedReport->conc,
                'recommendations' => $enhancedReport->reco,
                'gross_examination' => $enhancedReport->gross,
                'nature_of_specimen' => $enhancedReport->nature,
                'barcode' => $enhancedReport->barcode,
            ]
        ]);
    }

    public function myVisits(Request $request)
    {
        $user = $request->user();
        $patient = Patient::where('user_id', $user->id)->first();
        
        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $visits = $patient->visits()->with(['visitTests.labTest.category'])->get();
        
        $formattedVisits = $visits->map(function($visit) {
            return [
                'id' => $visit->id,
                'visit_id' => $visit->visit_id,
                'visit_date' => $visit->visit_date,
                'visit_time' => $visit->visit_time,
                'referred_doctor' => $visit->referred_doctor,
                'clinical_data' => $visit->clinical_data,
                'microscopic_description' => $visit->microscopic_description,
                'diagnosis' => $visit->diagnosis,
                'recommendations' => $visit->recommendations,
                'status' => $visit->status,
                'total_amount' => $visit->total_amount,
                'discount_amount' => $visit->discount_amount,
                'final_amount' => $visit->final_amount,
                'upfront_payment' => $visit->upfront_payment,
                'remaining_balance' => $visit->remaining_balance,
                'payment_status' => $visit->payment_status,
                'visit_tests' => $visit->visitTests->map(function($test) {
                    return [
                        'id' => $test->id,
                        'test_name' => $test->labTest->name ?? '',
                        'test_category' => $test->labTest->category->name ?? '',
                        'price' => $test->price,
                        'status' => $test->status,
                        'result_value' => $test->result_value,
                        'result_status' => $test->result_status,
                    ];
                }),
            ];
        });

        return response()->json(['visits' => $formattedVisits]);
    }

    public function myInvoices(Request $request)
    {
        $user = $request->user();
        $patient = Patient::where('user_id', $user->id)->first();
        
        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $invoices = $patient->visits()->with(['invoice.payments', 'labRequest'])->get()
            ->filter(function($visit) {
                return $visit->invoice !== null;
            })
            ->map(function($visit) use ($patient) {
                $invoice = $visit->invoice;
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'visit_id' => $visit->id,
                    'lab_number' => $invoice->lab_number,
                    'patient_name' => $patient->name,
                    'total_amount' => $invoice->total_amount,
                    'discount_amount' => $invoice->discount_amount,
                    'tax_amount' => $invoice->tax_amount,
                    'final_amount' => $invoice->total_amount - $invoice->discount_amount + $invoice->tax_amount,
                    'payment_status' => $invoice->payment_status,
                    'balance_due' => $invoice->balance,
                    'payments' => $invoice->payments->map(function($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => $payment->amount,
                            'payment_method' => $payment->payment_method,
                            'payment_date' => $payment->paid_at,
                            'notes' => $payment->notes,
                        ];
                    }),
                    'visit' => [
                        'visit_tests' => $visit->visitTests->map(function($test) {
                            return [
                                'test_name' => $test->labTest->name ?? '',
                                'price' => $test->price,
                            ];
                        }),
                    ],
                ];
            });

        return response()->json(['invoices' => $invoices]);
    }
} 