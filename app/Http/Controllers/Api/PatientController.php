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

        // Get filter parameters
        $labNoFilter = $request->get('lab_no', '');
        $attendanceDateFilter = $request->get('attendance_date', '');
        $deliveryDateFilter = $request->get('delivery_date', '');
        
        // Get per_page parameter
        $perPage = $request->get('per_page', 15);

        $patients = $query->with([
            'visits' => function($q) {
                $q->latest()->limit(5); // Get the latest 5 visits for financial info
            },
            'labRequests' => function($q) {
                $q->latest()->limit(1); // Get the latest lab request for lab number
            }
        ])->orderBy('id', 'desc')->paginate($perPage);
        
        // Transform the data to ensure proper formatting and avoid N/A values
        $patients->getCollection()->transform(function ($patient) {
            // Calculate birth_date from age if not set
            if ($patient->age && !$patient->birth_date) {
                $patient->birth_date = now()->subYears($patient->age)->format('Y-m-d');
            }
            
            // Calculate age from birth_date if not set
            if (!$patient->age && $patient->birth_date) {
                $patient->age = \Carbon\Carbon::parse($patient->birth_date)->age;
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
            
            // Get lab number from latest lab request, fallback to legacy 'lab' field
            $latestLabRequest = $patient->labRequests->first();
            if ($latestLabRequest && $latestLabRequest->full_lab_no) {
                $patient->lab = $latestLabRequest->full_lab_no;
            }
            
            // Get attendance and delivery dates from visits
            $attendanceDates = [];
            $deliveryDates = [];
            foreach ($patient->visits as $visit) {
                // Get attendance date from visit_date
                if ($visit->visit_date) {
                    $attendanceDates[] = $visit->visit_date;
                }
                
                // Try to get delivery date from metadata or calculate it
                $deliveryDate = null;
                if (isset($visit->metadata) && $visit->metadata) {
                    $metadata = is_string($visit->metadata) ? json_decode($visit->metadata, true) : ($visit->metadata ?? []);
                    if (isset($metadata['delivery_date'])) {
                        $deliveryDate = $metadata['delivery_date'];
                    } elseif (isset($metadata['patient_data']['delivery_date'])) {
                        $deliveryDate = $metadata['patient_data']['delivery_date'];
                    }
                }
                
                // If no delivery date in metadata, try to get from patient record
                if (!$deliveryDate && isset($patient->delivery_date)) {
                    $deliveryDate = $patient->delivery_date;
                }
                
                // If still no delivery date, use attendance date + 1 day as fallback
                if (!$deliveryDate && $visit->visit_date) {
                    $deliveryDate = date('Y-m-d', strtotime($visit->visit_date . ' +1 day'));
                }
                
                if ($deliveryDate) {
                    $deliveryDates[] = $deliveryDate;
                }
            }
            
            $patient->attendance_dates = array_values(array_unique($attendanceDates));
            $patient->delivery_dates = array_values(array_unique($deliveryDates));
            
            // Add financial information from latest visit
            $latestVisit = $patient->visits->first();
            
            // Debug logging for patient dasdasd
            if ($patient->id == 18) {
                \Log::info('Processing patient dasdasd (ID: 18)', [
                    'patient_name' => $patient->name,
                    'visits_count' => $patient->visits->count(),
                    'all_visits' => $patient->visits->map(function($visit) {
                        return [
                            'visit_id' => $visit->id,
                            'total_amount' => $visit->total_amount,
                            'amount_paid' => $visit->amount_paid,
                            'final_amount' => $visit->final_amount,
                            'amount' => $visit->amount,
                            'remaining_amount' => $visit->remaining_amount,
                            'payment_status' => $visit->payment_status,
                            'metadata' => $visit->metadata,
                        ];
                    })
                ]);
            }
            
            if ($latestVisit) {
                // Get financial data from visit metadata if available
                $metadata = $latestVisit->metadata ?? [];
                $patientData = $metadata['patient_data'] ?? [];
                $financialData = $metadata['financial_data'] ?? [];
                
                // Calculate total amount from multiple sources - prioritize database values first
                $totalAmount = $latestVisit->total_amount ?? 
                              $latestVisit->final_amount ?? 
                              $latestVisit->amount ?? 
                              $financialData['total_amount'] ?? 
                              $financialData['final_amount'] ?? 
                              $patientData['total_amount'] ?? 
                              $patient->total_amount ?? 0;
                
                // Calculate amount paid from multiple sources - prioritize database values first
                $amountPaid = $latestVisit->amount_paid ?? 
                             $latestVisit->paid_amount ?? 
                             $financialData['amount_paid'] ?? 
                             $financialData['paid_amount'] ?? 
                             $patientData['amount_paid'] ?? 
                             $patient->amount_paid ?? 0;
                
                // Calculate remaining balance
                $remainingBalance = max(0, $totalAmount - $amountPaid);
                
                // Determine payment status
                $paymentStatus = 'unpaid';
                if ($remainingBalance <= 0 && $totalAmount > 0) {
                    $paymentStatus = 'paid';
                } elseif ($amountPaid > 0) {
                    $paymentStatus = 'partial';
                }
                
                $patient->total_amount = $totalAmount;
                $patient->amount_paid = $amountPaid;
                $patient->remaining_balance = $remainingBalance;
                $patient->payment_status = $paymentStatus;
                
                // Debug logging for patient dasdasd after calculation
                if ($patient->id == 18) {
                    \Log::info('Financial calculation for patient dasdasd (ID: 18)', [
                        'calculated_total_amount' => $totalAmount,
                        'calculated_amount_paid' => $amountPaid,
                        'calculated_remaining_balance' => $remainingBalance,
                        'calculated_payment_status' => $paymentStatus,
                        'metadata' => $metadata,
                        'patientData' => $patientData,
                        'financialData' => $financialData,
                    ]);
                }
            } else {
                // Try to get financial data from patient record itself
                $totalAmount = $patient->total_amount ?? 0;
                $amountPaid = $patient->amount_paid ?? 0;
                $remainingBalance = max(0, $totalAmount - $amountPaid);
                
                $paymentStatus = 'unpaid';
                if ($remainingBalance <= 0 && $totalAmount > 0) {
                    $paymentStatus = 'paid';
                } elseif ($amountPaid > 0) {
                    $paymentStatus = 'partial';
                }
                
                $patient->total_amount = $totalAmount;
                $patient->amount_paid = $amountPaid;
                $patient->remaining_balance = $remainingBalance;
                $patient->payment_status = $paymentStatus;
            }
            
            // Add debug information for the first few patients
            if ($patient->id <= 20) { // Only for first 20 patients to avoid too much data
                $latestVisit = $patient->visits->first();
                $patient->debug_info = [
                    'visits_count' => $patient->visits->count(),
                    'latest_visit' => $latestVisit ? [
                        'id' => $latestVisit->id,
                        'total_amount' => $latestVisit->total_amount,
                        'amount_paid' => $latestVisit->amount_paid,
                        'final_amount' => $latestVisit->final_amount,
                        'amount' => $latestVisit->amount,
                        'remaining_amount' => $latestVisit->remaining_amount,
                        'payment_status' => $latestVisit->payment_status,
                        'metadata' => $latestVisit->metadata,
                        'metadata_patient_data' => $latestVisit->metadata['patient_data'] ?? null,
                        'metadata_financial_data' => $latestVisit->metadata['financial_data'] ?? null,
                    ] : null,
                    'patient_total_amount' => $patient->total_amount,
                    'patient_amount_paid' => $patient->amount_paid,
                    'calculated_total_amount' => $patient->total_amount,
                    'calculated_amount_paid' => $patient->amount_paid,
                    'calculated_remaining_balance' => $patient->remaining_balance,
                    'calculated_payment_status' => $patient->payment_status,
                ];
            }
            
            return $patient;
        });
        
        // Convert to array and ensure proper formatting
        // Apply filters after transformation
        $transformedPatients = $patients->getCollection();
        $hasFilters = !empty($labNoFilter) || !empty($attendanceDateFilter) || !empty($deliveryDateFilter);
        
        if ($hasFilters) {
            $transformedPatients = $transformedPatients->filter(function ($patient) use ($labNoFilter, $attendanceDateFilter, $deliveryDateFilter) {
                // Filter by lab number
                if (!empty($labNoFilter)) {
                    $labMatch = false;
                    if ($patient->lab && stripos($patient->lab, $labNoFilter) !== false) {
                        $labMatch = true;
                    }
                    if (!$labMatch) {
                        return false;
                    }
                }

                // Filter by attendance date
                if (!empty($attendanceDateFilter)) {
                    $attendanceMatch = false;
                    if (!empty($patient->attendance_dates)) {
                        foreach ($patient->attendance_dates as $attendanceDate) {
                            if (date('Y-m-d', strtotime($attendanceDate)) === $attendanceDateFilter) {
                                $attendanceMatch = true;
                                break;
                            }
                        }
                    }
                    if (!$attendanceMatch) {
                        return false;
                    }
                }

                // Filter by delivery date
                if (!empty($deliveryDateFilter)) {
                    $deliveryMatch = false;
                    if (!empty($patient->delivery_dates)) {
                        foreach ($patient->delivery_dates as $deliveryDate) {
                            if (date('Y-m-d', strtotime($deliveryDate)) === $deliveryDateFilter) {
                                $deliveryMatch = true;
                                break;
                            }
                        }
                    }
                    if (!$deliveryMatch) {
                        return false;
                    }
                }

                return true;
            })->values();
            
            // Get total count before pagination (when filters are applied)
            $totalFiltered = $transformedPatients->count();
            
            // Apply pagination to filtered results
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $paginatedPatients = $transformedPatients->slice($offset, $perPage)->values();
            
            // Calculate pagination info
            $lastPage = $totalFiltered > 0 ? ceil($totalFiltered / $perPage) : 1;
            $from = $totalFiltered > 0 ? $offset + 1 : 0;
            $to = min($offset + $perPage, $totalFiltered);
            
            $total = $totalFiltered;
        } else {
            // No filters - use original pagination data
            $paginatedPatients = $transformedPatients;
            $page = $patients->currentPage();
            $lastPage = $patients->lastPage();
            $from = $patients->firstItem();
            $to = $patients->lastItem();
            $total = $patients->total();
        }

        // Convert to array and process
        $patientsArray = [
            'current_page' => (int)$page,
            'data' => $paginatedPatients->map(function ($patient) {
                return $patient->toArray();
            })->toArray(),
            'first_page_url' => $request->url() . '?page=1',
            'from' => $from,
            'last_page' => $lastPage,
            'last_page_url' => $request->url() . '?page=' . $lastPage,
            'next_page_url' => $page < $lastPage ? $request->url() . '?page=' . ($page + 1) : null,
            'path' => $request->url(),
            'per_page' => $perPage,
            'prev_page_url' => $page > 1 ? $request->url() . '?page=' . ($page - 1) : null,
            'to' => $to,
            'total' => $total,
        ];
        
        foreach ($patientsArray['data'] as &$patient) {
            // Calculate birth_date from age if not set
            if (isset($patient['age']) && $patient['age'] && !isset($patient['birth_date'])) {
                $patient['birth_date'] = now()->subYears($patient['age'])->format('Y-m-d');
            }
            
            // Calculate age from birth_date if not set
            if ((!isset($patient['age']) || !$patient['age']) && isset($patient['birth_date']) && $patient['birth_date']) {
                $patient['age'] = \Carbon\Carbon::parse($patient['birth_date'])->age;
            }
            
            // Handle address - prioritize address_required or address_optional
            if (!isset($patient['address']) || !$patient['address']) {
                if (isset($patient['address_required']) && $patient['address_required']) {
                    $patient['address'] = $patient['address_required'];
                } elseif (isset($patient['address_optional']) && $patient['address_optional']) {
                    $patient['address'] = $patient['address_optional'];
                }
            }
            
            // Handle doctor name - prioritize sender, then doctor_id
            if ((!isset($patient['sender']) || !$patient['sender']) && isset($patient['doctor_id']) && $patient['doctor_id']) {
                $patient['sender'] = $patient['doctor_id'];
            }
            
            // Handle organization - use organization_id if available
            if ((!isset($patient['organization']) || !$patient['organization']) && isset($patient['organization_id']) && $patient['organization_id']) {
                $patient['organization'] = $patient['organization_id'];
            }
            
            // Ensure we don't have empty strings showing as N/A
            $patient['address'] = $patient['address'] ?? null;
            $patient['sender'] = $patient['sender'] ?? null;
            $patient['organization'] = $patient['organization'] ?? null;
            $patient['emergency_contact'] = $patient['emergency_contact'] ?? null;
        }

        return response()->json($patientsArray);
    }


    public function store(Request $request)
    {
        // Debug: Log the incoming request data
        \Log::info('Incoming request data:', $request->all());
        
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
            \Log::info('Validation failed in store method:', $validator->errors()->toArray());
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
        
        // Debug: Log the patient data before processing
        \Log::info('Patient data before processing:', $patientData);
        
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
        // Note: Keep age field as it exists in the patient table
        
        // Handle payment data and preserve lab_number
        $paymentData = [];
        $manualLabNumber = null;
        $totalAmount = null;
        
        // Preserve total_amount before unsetting it
        if (isset($patientData['total_amount'])) {
            $totalAmount = floatval($patientData['total_amount']);
        } elseif (isset($request->total_amount)) {
            $totalAmount = floatval($request->total_amount);
        }
        
        if (isset($patientData['amount_paid'])) {
            $paymentData['amount_paid'] = floatval($patientData['amount_paid']);
        }
        if (isset($patientData['amount_paid_cash'])) {
            $paymentData['amount_paid_cash'] = floatval($patientData['amount_paid_cash']);
        }
        if (isset($patientData['amount_paid_card'])) {
            $paymentData['amount_paid_card'] = floatval($patientData['amount_paid_card']);
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
        
        // Debug: Log the final patient data before creation
        \Log::info('Final patient data before creation:', $patientData);
        
        $patient = Patient::create($patientData);
        
        // Debug: Log the patient data after creation to verify age was saved
        \Log::info('Patient created successfully', [
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_age' => $patient->age,
            'patient_age_raw' => $patient->getAttributes()['age'] ?? 'NULL',
        ]);

        // Create visit record if payment data is provided
        $visit = null;
        if (!empty($paymentData) || $totalAmount !== null) {
            // Get current staff shift
            $currentShift = \App\Models\Shift::where('staff_id', auth()->id())
                ->where('status', 'open')
                ->whereDate('opened_at', today())
                ->first();

            // Use preserved total_amount or get from request
            $visitTotalAmount = $totalAmount ?? floatval($request->total_amount ?? 0);
            $finalAmount = $visitTotalAmount;
            $paidAmount = floatval($paymentData['amount_paid'] ?? 0);
            
            \Log::info('Creating visit from PatientController:', [
                'patient_id' => $patient->id,
                'total_amount' => $visitTotalAmount,
                'final_amount' => $finalAmount,
                'upfront_payment' => $paidAmount,
                'payment_method' => $paymentData['additional_payment_method'] ?? 'cash',
                'request_total_amount' => $request->total_amount,
                'preserved_total_amount' => $totalAmount,
                'payment_data' => $paymentData,
            ]);
            
            $visitData = [
                'patient_id' => $patient->id,
                'visit_number' => \App\Models\Visit::generateVisitNumber(),
                'visit_date' => $request->attendance_date ?? now()->toDateString(),
                'visit_time' => now()->toTimeString(),
                'expected_delivery_date' => $request->delivery_date ?? now()->addDays(1)->toDateString(),
                'total_amount' => $visitTotalAmount,
                'final_amount' => $finalAmount,
                'upfront_payment' => $paidAmount,
                'payment_method' => $paymentData['additional_payment_method'] ?? 'cash',
                'billing_status' => $paidAmount >= $visitTotalAmount && $visitTotalAmount > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
                'status' => 'pending',
                'created_by' => auth()->id() ?? 1,
                'shift_id' => $currentShift?->id, // Link to current shift
                'processed_by_staff' => auth()->id(),
                'metadata' => json_encode([
                    'financial_data' => [
                        'total_amount' => $visitTotalAmount,
                        'final_amount' => $finalAmount,
                        'amount_paid' => $paidAmount,
                        'remaining_balance' => max(0, $finalAmount - $paidAmount),
                        'payment_status' => $paidAmount >= $visitTotalAmount && $visitTotalAmount > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
                    ],
                    'payment_details' => [
                        'amount_paid_cash' => $paymentData['amount_paid_cash'] ?? 0,
                        'amount_paid_card' => $paymentData['amount_paid_card'] ?? 0,
                        'additional_payment_method' => $paymentData['additional_payment_method'] ?? 'cash',
                        'total_paid' => $paidAmount,
                    ],
                    'created_via' => 'patient_registration',
                    'patient_data' => $request->all(),
                ]),
            ];
            
            $visit = \App\Models\Visit::create($visitData);
            
            \Log::info('Visit created successfully:', [
                'visit_id' => $visit->id,
                'visit_number' => $visit->visit_number,
                'total_amount' => $visit->total_amount,
                'final_amount' => $visit->final_amount,
                'upfront_payment' => $visit->upfront_payment,
            ]);
            
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

        // Link visit to lab request if visit was created
        if ($visit && $labRequest) {
            $visit->update(['lab_request_id' => $labRequest->id]);
            \Log::info('Linked visit to lab request', [
                'visit_id' => $visit->id,
                'lab_request_id' => $labRequest->id
            ]);
        }

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
        try {
            $patient->load([
                'visits' => function ($q) {
                    $q->with([
                        'visitTests' => function ($q) {
                            $q->with(['labTest', 'performedBy']);
                        },
                        'labRequest.invoice'
                    ])->latest();
                },
            ]);

            // Manually attach invoice to each visit through labRequest
            $patient->visits->each(function ($visit) {
                if ($visit->labRequest && $visit->labRequest->invoice) {
                    $visit->setRelation('invoice', $visit->labRequest->invoice);
                }
            });

            return response()->json($patient);
        } catch (\Exception $e) {
            \Log::error('Error fetching patient details', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Return patient without relationships if loading fails
            return response()->json([
                'id' => $patient->id,
                'name' => $patient->name,
                'phone' => $patient->phone,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'visits' => [],
                'error' => 'Failed to load patient details: ' . $e->getMessage()
            ], 500);
        }
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
            // Delivery tracking fields
            'report_delivered' => 'nullable|boolean',
            'report_delivery_date' => 'nullable|date',
            'report_delivery_notes' => 'nullable|string',
            'report_delivered_by' => 'nullable|string|max:255',
            'wax_blocks_delivered' => 'nullable|boolean',
            'wax_blocks_delivery_date' => 'nullable|date',
            'wax_blocks_delivery_notes' => 'nullable|string',
            'wax_blocks_delivered_by' => 'nullable|string|max:255',
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
        // Note: Keep age field as it exists in the patient table
        
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

    public function debugFinancialData($id)
    {
        try {
            $patient = Patient::with(['visits'])->findOrFail($id);
            
            $debugData = [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_lab' => $patient->lab,
                'patient_total_amount' => $patient->total_amount,
                'patient_amount_paid' => $patient->amount_paid,
                'visits_count' => $patient->visits->count(),
                'visits' => $patient->visits->map(function($visit) {
                    return [
                        'visit_id' => $visit->id,
                        'visit_date' => $visit->visit_date,
                        'total_amount' => $visit->total_amount,
                        'amount_paid' => $visit->amount_paid,
                        'final_amount' => $visit->final_amount,
                        'amount' => $visit->amount,
                        'remaining_amount' => $visit->remaining_amount,
                        'payment_status' => $visit->payment_status,
                        'metadata' => $visit->metadata,
                        'metadata_patient_data' => isset($visit->metadata['patient_data']) ? $visit->metadata['patient_data'] : null,
                    ];
                })
            ];
            
            return response()->json($debugData);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addExtraPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,Fawry,InstaPay,VodafoneCash,Other',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $patient = Patient::with(['visits'])->findOrFail($id);
            
            // Debug: Log patient data
            \Log::info('Patient data for extra payment:', [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_total_amount' => $patient->total_amount,
                'patient_amount_paid' => $patient->amount_paid,
                'visits_count' => $patient->visits->count(),
                'visits_data' => $patient->visits->map(function($visit) {
                    return [
                        'visit_id' => $visit->id,
                        'total_amount' => $visit->total_amount,
                        'amount_paid' => $visit->amount_paid,
                        'final_amount' => $visit->final_amount,
                        'amount' => $visit->amount,
                        'metadata' => $visit->metadata,
                    ];
                })
            ]);
            
            // Get the latest visit for this patient
            $latestVisit = $patient->visits()->latest()->first();
            
            if (!$latestVisit) {
                return response()->json([
                    'message' => 'No visit found for this patient',
                ], 404);
            }

            $amount = $request->amount;
            $paymentMethod = $request->payment_method;
            $notes = $request->notes ?? "Extra payment - {$paymentMethod}";

            // Update visit with additional payment
            $originalPaidAmount = $latestVisit->upfront_payment ?? 0; // Use upfront_payment for original paid amount
            $currentTotalAmount = $latestVisit->total_amount ?? 0;
            
            // Debug: Log current amounts before adding extra payment
            \Log::info('Extra payment calculation for patient', [
                'patient_id' => $patient->id,
                'original_paid_amount' => $originalPaidAmount,
                'current_total_amount' => $currentTotalAmount,
                'extra_payment_amount' => $amount,
            ]);
            
            // For extra payments, we only increase the total amount, keeping the original paid amount the same
            $newTotalAmount = $currentTotalAmount + $amount; // Add extra payment to total amount
            $newPaidAmount = $originalPaidAmount; // Keep original paid amount unchanged
            $remainingBalance = max(0, $newTotalAmount - $newPaidAmount);
            
            // Debug: Log calculated amounts
            \Log::info('Extra payment calculation results', [
                'patient_id' => $patient->id,
                'original_total_amount' => $currentTotalAmount,
                'new_total_amount' => $newTotalAmount,
                'original_paid_amount' => $originalPaidAmount,
                'new_paid_amount' => $newPaidAmount,
                'remaining_balance' => $remainingBalance,
                'note' => 'Only total amount increased by extra payment, original paid amount unchanged',
            ]);

            // Determine new payment status
            $paymentStatus = 'unpaid';
            if ($remainingBalance <= 0) {
                $paymentStatus = 'paid';
            } elseif ($newPaidAmount > 0) {
                $paymentStatus = 'partial';
            }

            // Find the lab request for this patient to link the visit
            $labRequest = LabRequest::where('patient_id', $patient->id)->first();
            
            // Update visit
            $latestVisit->update([
                'total_amount' => $newTotalAmount,
                'upfront_payment' => $newPaidAmount, // Keep original paid amount in upfront_payment
                'remaining_balance' => $remainingBalance,
                'billing_status' => $paymentStatus,
                'lab_request_id' => $labRequest ? $labRequest->id : null,
            ]);

            // Update patient record with new financial data
            $patient->update([
                'total_amount' => $newTotalAmount,
                'amount_paid' => $newPaidAmount,
                'remaining_balance' => $remainingBalance,
                'payment_status' => $paymentStatus,
            ]);
            
            // Debug: Log updated patient data
            \Log::info('Patient record updated with extra payment', [
                'patient_id' => $patient->id,
                'updated_total_amount' => $newTotalAmount,
                'updated_amount_paid' => $newPaidAmount,
                'updated_remaining_balance' => $remainingBalance,
                'updated_payment_status' => $paymentStatus,
            ]);

            // Update visit metadata to reflect new financial data
            $metadata = json_decode($latestVisit->metadata ?? '{}', true);
            $metadata['financial_data'] = [
                'total_amount' => $newTotalAmount,
                'amount_paid' => $newPaidAmount,
                'remaining_balance' => $remainingBalance,
                'payment_status' => $paymentStatus,
                'last_updated' => now()->toISOString(),
                'extra_payment_added' => true,
                'extra_payment_amount' => $amount,
                'extra_payment_method' => $paymentMethod,
                'note' => 'Extra payment added - only total amount increased, paid amount unchanged',
            ];
            
            // Also update patient_data in metadata if it exists
            if (isset($metadata['patient_data'])) {
                $metadata['patient_data']['total_amount'] = $newTotalAmount;
                $metadata['patient_data']['amount_paid'] = $newPaidAmount;
                $metadata['patient_data']['remaining_balance'] = $remainingBalance;
                $metadata['patient_data']['payment_status'] = $paymentStatus;
            }
            
            $latestVisit->update(['metadata' => json_encode($metadata)]);

            // Create payment record if payment model exists
            try {
                // Get current shift for the payment
                $currentShift = \App\Models\Shift::where('staff_id', auth()->id())
                    ->where('status', 'open')
                    ->whereDate('opened_at', today())
                    ->first();

                $paymentData = [
                    'paid' => $amount * 100, // Convert to cents (assuming the field stores in cents)
                    'comment' => $notes,
                    'date' => now()->toDateString(),
                    'author' => auth()->id(),
                    'income' => 1, // Assuming 1 means income
                    'invoice_id' => $labRequest ? $labRequest->id : null, // Use lab request ID as invoice ID
                    'shift_id' => $currentShift ? $currentShift->id : null,
                ];

                // Try to create payment record
                if (class_exists('App\Models\Payment')) {
                    \App\Models\Payment::create($paymentData);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to create payment record', [
                    'error' => $e->getMessage(),
                    'visit_id' => $latestVisit->id
                ]);
            }

            // Prepare receipt data
            $receiptData = [
                'patient_name' => $patient->name,
                'patient_phone' => $patient->phone,
                'lab_number' => $patient->lab,
                'visit_id' => $latestVisit->id,
                'total_amount' => $newTotalAmount,
                'amount_paid_before' => $originalPaidAmount,
                'extra_payment_amount' => $amount,
                'total_paid_now' => $newPaidAmount,
                'remaining_balance' => $remainingBalance,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_date' => now()->format('Y-m-d H:i:s'),
                'processed_by' => auth()->user()->name ?? 'System',
            ];

            return response()->json([
                'message' => 'Extra payment added successfully',
                'receipt_data' => $receiptData,
                'updated_visit' => [
                    'total_amount' => $newTotalAmount,
                    'amount_paid' => $newPaidAmount,
                    'remaining_balance' => $remainingBalance,
                    'payment_status' => $paymentStatus,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to add extra payment', [
                'error' => $e->getMessage(),
                'patient_id' => $id,
                'amount' => $request->amount
            ]);

            return response()->json([
                'message' => 'Failed to add extra payment',
                'error' => $e->getMessage()
            ], 500);
        }
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

    /**
     * Search patients by query (for copy pathology details feature)
     */
    public function search(Request $request)
    {
        $query = $request->get('query');
        
        if (empty($query)) {
            return response()->json(['data' => []]);
        }

        // First, try to find patients by direct fields
        $patients = Patient::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('phone', 'like', "%{$query}%")
              ->orWhere('lab', 'like', "%{$query}%")
              ->orWhere('id', 'like', "%{$query}%");
        })
        ->with(['labRequests' => function($q) {
            $q->latest()->limit(1);
        }])
        ->limit(10)
        ->get();

        // Also search by lab_requests table (lab number might be stored there)
        // Handle different lab number formats: "58984", "58984-2025", "58984-2025m", etc.
        $labRequestPatients = \App\Models\LabRequest::where(function ($q) use ($query) {
            // Search in lab_no field
            $q->where('lab_no', 'like', "%{$query}%")
              // Search in full_lab_no (lab_no + suffix)
              ->orWhereRaw("CONCAT(lab_no, COALESCE(suffix, '')) LIKE ?", ["%{$query}%"])
              // Also try searching without dashes (in case user searches "58984" for "58984-2025")
              ->orWhereRaw("REPLACE(lab_no, '-', '') LIKE ?", ["%" . str_replace('-', '', $query) . "%"])
              ->orWhereRaw("REPLACE(CONCAT(lab_no, COALESCE(suffix, '')), '-', '') LIKE ?", ["%" . str_replace('-', '', $query) . "%"]);
        })
        ->with(['patient.labRequests' => function($q) {
            $q->latest()->limit(1);
        }])
        ->get()
        ->pluck('patient')
        ->filter()
        ->unique('id');

        // Merge both results and remove duplicates
        $allPatients = $patients->merge($labRequestPatients)->unique('id')->take(10);

        // Update lab number from latest lab request for all patients
        $allPatients->transform(function ($patient) {
            if (!$patient) return null;
            
            $latestLabRequest = $patient->labRequests->first();
            if ($latestLabRequest) {
                // Set both lab and labRequest for easier matching in frontend
                $patient->lab = $latestLabRequest->full_lab_no;
                $patient->labRequest = [
                    'lab_no' => $latestLabRequest->lab_no,
                    'full_lab_no' => $latestLabRequest->full_lab_no,
                    'suffix' => $latestLabRequest->suffix,
                ];
            }
            return $patient;
        })->filter();

        return response()->json(['data' => $allPatients->values()]);
    }

    /**
     * Get patient visits (for copy pathology details feature)
     */
    public function visits($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        $visits = $patient->visits()
            ->with(['labRequest.reports'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['visits' => $visits]);
    }
} 