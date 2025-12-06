<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitTest;
use App\Models\LabTest;
use App\Models\TestCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PatientRegistrationController extends Controller
{
    /**
     * Parse age format and calculate birth_date
     * Supports formats: 
     * - "25" (years)
     * - "25M" (months)
     * - "5D" (days)
     * - "25Y" (years)
     * - Combined: "25M,5D" (25 months and 5 days)
     * - Combined: "2Y,5M,3D" (2 years, 5 months, and 3 days)
     * Also handles formats with spaces: "25 M", "5 D", etc.
     * 
     * @param string $ageInput Age input string (e.g., "25M", "5D", "25", "25M,5D", "2Y,5M,3D")
     * @return array ['age' => int|null, 'birth_date' => string|null]
     */
    private function parseAgeAndCalculateBirthDate($ageInput)
    {
        if (empty($ageInput)) {
            return ['age' => null, 'birth_date' => null];
        }

        // Remove whitespace and convert to uppercase
        $ageInput = preg_replace('/\s+/', '', trim(strtoupper($ageInput)));
        
        $now = now();
        $birthDate = $now->copy();
        $totalDays = 0;
        $totalMonths = 0;
        $totalYears = 0;
        
        // Check if input contains comma (combined format)
        if (strpos($ageInput, ',') !== false) {
            // Split by comma and parse each component
            $components = explode(',', $ageInput);
            
            // Collect all components first
            $years = 0;
            $months = 0;
            $days = 0;
            
            foreach ($components as $component) {
                $component = trim($component);
                
                // Extract number and unit from each component
                if (preg_match('/^(\d+)([MDY]?)$/', $component, $matches)) {
                    $number = (int) $matches[1];
                    $unit = $matches[2] ?? '';
                    
                    if ($unit === 'D') {
                        // Days
                        $days += $number;
                    } elseif ($unit === 'M') {
                        // Months
                        $months += $number;
                    } elseif ($unit === 'Y' || $unit === '') {
                        // Years (default or 'Y')
                        $years += $number;
                    }
                }
            }
            
            // Subtract in order: years, then months, then days (for accuracy)
            if ($years > 0) {
                $birthDate->subYears($years);
            }
            if ($months > 0) {
                $birthDate->subMonths($months);
            }
            if ($days > 0) {
                $birthDate->subDays($days);
            }
            
            // Calculate approximate age in years for display
            $age = $years + ($months / 12) + ($days / 365);
            
            return [
                'age' => round($age, 1),
                'birth_date' => $birthDate->format('Y-m-d')
            ];
        }
        
        // Single component format (no comma)
        // Extract number and unit (handles formats like "25M", "5D", "25", "25Y")
        if (preg_match('/^(\d+)([MDY]?)$/', $ageInput, $matches)) {
            $number = (int) $matches[1];
            $unit = $matches[2] ?? '';
            
            // Calculate birth_date based on unit
            if ($unit === 'D') {
                // Days
                $birthDate = $now->copy()->subDays($number);
                // For display, calculate approximate age in years
                $age = round($number / 365, 1);
            } elseif ($unit === 'M') {
                // Months
                $birthDate = $now->copy()->subMonths($number);
                // For display, calculate approximate age in years
                $age = round($number / 12, 1);
            } else {
                // Years (default or 'Y')
                $birthDate = $now->copy()->subYears($number);
                $age = $number;
            }
            
            return [
                'age' => $age,
                'birth_date' => $birthDate->format('Y-m-d')
            ];
        }
        
        // If format doesn't match, try to parse as plain number (years)
        if (is_numeric($ageInput)) {
            $number = (int) $ageInput;
            $birthDate = now()->copy()->subYears($number);
            
            return [
                'age' => $number,
                'birth_date' => $birthDate->format('Y-m-d')
            ];
        }
        
        // Invalid format - return null
        return ['age' => null, 'birth_date' => null];
    }

    /**
     * Search for existing patients by lab number, mobile, or name
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:lab_number,mobile,name',
            'value' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $request->type;
        $value = $request->value;

        $query = Patient::query();

        switch ($type) {
            case 'lab_number':
                $query->where('lab', $value);
                break;
            case 'mobile':
                $query->where('phone', 'like', "%{$value}%")
                      ->orWhere('whatsapp_number', 'like', "%{$value}%");
                break;
            case 'name':
                $query->where('name', 'like', "%{$value}%");
                break;
        }

        $patients = $query->get();
        
        // Ensure age is returned as string to preserve formats like "25M,5D"
        $patients->transform(function ($patient) {
            if (isset($patient->attributes['age']) && $patient->attributes['age'] !== null) {
                $patient->age = (string) $patient->attributes['age'];
            }
            return $patient;
        });

        return response()->json([
            'success' => true,
            'data' => $patients,
            'count' => $patients->count(),
        ]);
    }

    /**
     * Get the next lab number
     */
    public function getNextLabNumber()
    {
        $lastLabNumber = Patient::whereNotNull('lab')
            ->where('lab', '!=', '')
            ->orderBy('id', 'desc')
            ->value('lab');

        $year = date('Y');
        
        if ($lastLabNumber && preg_match('/^(\d{4})-(\d{4})$/', $lastLabNumber, $matches)) {
            $lastYear = $matches[1];
            $lastNumber = (int) $matches[2];
            
            if ($lastYear == $year) {
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }
        } else {
            $nextNumber = 1;
        }

        $nextLabNumber = $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        // Ensure the lab number is unique
        while (Patient::where('lab', $nextLabNumber)->exists()) {
            $nextNumber++;
            $nextLabNumber = $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return response()->json([
            'success' => true,
            'lab_number' => $nextLabNumber,
        ]);
    }

    /**
     * Submit comprehensive patient registration form
     */
    public function submit(Request $request)
    {
        // Debug logging
        \Log::info('Patient Registration Submit - Request received', [
            'user' => auth()->user() ? auth()->user()->name : 'Not authenticated',
            'user_id' => auth()->id(),
            'request_data' => $request->all(),
        ]);
        
        $validator = Validator::make($request->all(), [
            // Patient basic info
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'age' => 'nullable|string|max:10',
            'gender' => 'nullable|in:male,female,other',
            
            // Organization and doctor
            'organization' => 'nullable|string|max:255',
            'doctor' => 'nullable|string|max:255',
            
            // Dates
            'attendance_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            
            // Sample information
            'sample_type' => 'nullable|string|max:255',
            'case_type' => 'nullable|string|max:255',
            'sample_size' => 'nullable|string|max:255',
            'number_of_samples' => 'nullable|string|max:10',
            'day_of_week' => 'nullable|string|max:255',
            
            // Medical information
            'medical_history' => 'nullable|string',
            'previous_tests' => 'nullable|string',
            
            // Billing
            'total_amount' => 'nullable|string|max:20',
            'amount_paid' => 'nullable|string|max:20',
            
            // Lab number
            'lab_number' => 'nullable|string|max:255',
            
            // Patient ID (for updates)
            'patient_id' => 'nullable|exists:patient,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            
            // Debug: Log the incoming data
            \Log::info('Patient registration data received', [
                'total_amount' => $data['total_amount'] ?? 'not_set',
                'amount_paid' => $data['amount_paid'] ?? 'not_set',
                'number_of_samples' => $data['number_of_samples'] ?? 'not_set',
                'has_billing_info' => isset($data['total_amount']) && !empty($data['total_amount']) && floatval($data['total_amount']) > 0,
                'all_data_keys' => array_keys($data)
            ]);
            
            // Handle lab number
            if (empty($data['lab_number'])) {
                $nextLabResponse = $this->getNextLabNumber();
                $data['lab_number'] = $nextLabResponse->getData()->lab_number;
            }
            
            // Handle organization - create if doesn't exist
            if (isset($data['organization']) && !empty($data['organization'])) {
                $organization = \App\Models\Organization::firstOrCreate(
                    ['name' => $data['organization']],
                    ['name' => $data['organization']]
                );
                $data['organization_id'] = $data['organization']; // Store name as string for compatibility
                unset($data['organization']);
            }
            
            // Handle doctor - create if doesn't exist
            if (isset($data['doctor']) && !empty($data['doctor'])) {
                $doctor = \App\Models\Doctor::firstOrCreate(
                    ['name' => $data['doctor']],
                    ['name' => $data['doctor']]
                );
                $data['doctor_id'] = $data['doctor']; // Store name as string for compatibility
                unset($data['doctor']);
            }
            
            // Check if updating existing patient
            if (isset($data['patient_id'])) {
                // Parse age format and calculate birth_date for updates too
                if (isset($data['age']) && !empty($data['age'])) {
                    $originalAgeFormat = trim($data['age']); // Store original format exactly as entered
                    $ageData = $this->parseAgeAndCalculateBirthDate($data['age']);
                    if ($ageData['birth_date']) {
                        $data['birth_date'] = $ageData['birth_date'];
                    }
                    // Store original age format (e.g., "25M,5D") exactly as entered
                    $data['age'] = $originalAgeFormat;
                }
                
                $patient = Patient::findOrFail($data['patient_id']);
                $patient->update($data);
            } else {
                // Create new user for patient
                $username = 'pt-' . strtolower(Str::random(8));
                $password = Str::random(10);
                $user = User::create([
                    'name' => $username,
                    'email' => $username . '@patients.local',
                    'role' => 'patient',
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]);
                
                $data['user_id'] = $user->id;
                $data['lab'] = $data['lab_number'];
                
                // Parse age format and calculate birth_date
                if (isset($data['age']) && !empty($data['age'])) {
                    $originalAgeFormat = trim($data['age']); // Store original format exactly as entered
                    
                    // Debug logging
                    \Log::info('Age storage - Original format:', [
                        'original' => $originalAgeFormat,
                        'type' => gettype($originalAgeFormat)
                    ]);
                    
                    $ageData = $this->parseAgeAndCalculateBirthDate($data['age']);
                    if ($ageData['birth_date']) {
                        $data['birth_date'] = $ageData['birth_date'];
                    }
                    // Store original age format (e.g., "25M,5D") exactly as entered
                    $data['age'] = $originalAgeFormat;
                    
                    // Debug logging after setting
                    \Log::info('Age storage - After setting:', [
                        'age_value' => $data['age'],
                        'type' => gettype($data['age'])
                    ]);
                }
                if (isset($data['number_of_samples']) && !empty($data['number_of_samples'])) {
                    $data['number_of_samples'] = intval($data['number_of_samples']);
                }
                if (isset($data['total_amount']) && !empty($data['total_amount'])) {
                    $data['total_amount'] = floatval($data['total_amount']);
                }
                if (isset($data['amount_paid']) && !empty($data['amount_paid'])) {
                    $data['amount_paid'] = floatval($data['amount_paid']);
                }
                
                // Remove lab_number from data as it's stored in lab field
                unset($data['lab_number']);
                
                // Ensure organization is properly stored in patient record
                if (isset($data['organization']) && !empty($data['organization'])) {
                    $data['organization'] = $data['organization'];
                }
                
                // Debug logging before create - check age value
                \Log::info('BEFORE Patient::create - Age value:', [
                    'age' => $data['age'] ?? 'NOT_SET',
                    'age_type' => isset($data['age']) ? gettype($data['age']) : 'NOT_SET',
                    'age_value_raw' => isset($data['age']) ? var_export($data['age'], true) : 'NOT_SET',
                    'full_data_age' => $data['age'] ?? 'NOT_SET'
                ]);
                
                // Create patient using mass assignment
                $patient = Patient::create($data);
                
                // Debug logging after create - check what was actually stored
                \Log::info('AFTER Patient::create - Age value from database:', [
                    'patient_id' => $patient->id,
                    'age_from_model' => $patient->age,
                    'age_from_attributes' => $patient->getAttributes()['age'] ?? 'NOT_SET',
                    'age_type_from_attributes' => isset($patient->getAttributes()['age']) ? gettype($patient->getAttributes()['age']) : 'NOT_SET',
                    'raw_query' => \DB::table('patient')->where('id', $patient->id)->value('age')
                ]);
                
                // Refresh patient to ensure we have the latest data from database
                $patient->refresh();
                
                // Create patient credentials
                $patient->credentials()->create([
                    'username' => $username,
                    'original_password' => $password,
                    'hashed_password' => Hash::make($password),
                    'is_active' => true,
                ]);
            }
            
            // Get valid status values for lab request
            $validStatuses = ['pending', 'in_progress', 'processing', 'received', 'completed'];
            $labRequestStatus = 'pending'; // Default status
            
            // Create lab request for all patients so they appear in Lab Requests
            try {
                // Prepare patient data for lab request metadata
                $labRequestPatientData = [
                    'name' => $data['name'] ?? $patient->name,
                    'phone' => $data['phone'] ?? $patient->phone,
                    'age' => $data['age'] ?? $patient->age,
                    'gender' => $data['gender'] ?? $patient->gender,
                    'organization' => $data['organization'] ?? $patient->organization,
                    'doctor' => $data['doctor'] ?? $patient->doctor,
                    'sample_type' => $data['sample_type'] ?? null,
                    'sample_size' => $data['sample_size'] ?? null,
                    'number_of_samples' => $data['number_of_samples'] ?? 1,
                    'case_type' => $data['case_type'] ?? null,
                    'total_amount' => $data['total_amount'] ?? 0,
                    'amount_paid' => $data['amount_paid'] ?? 0,
                    'amount_paid_cash' => $data['amount_paid_cash'] ?? 0,
                    'amount_paid_card' => $data['amount_paid_card'] ?? 0,
                    'additional_payment_method' => $data['additional_payment_method'] ?? null,
                ];
                
                $labRequest = \App\Models\LabRequest::create([
                    'patient_id' => $patient->id,
                    'lab_no' => $patient->lab, // Use the patient's lab number
                    'status' => $labRequestStatus,
                    'metadata' => [
                        'created_via' => 'Patient Registration',
                        'request_date' => $data['attendance_date'] ?? now()->format('Y-m-d'),
                        'notes' => 'Created via Patient Registration',
                        'created_by' => auth()->id() ?? 1,
                        'status' => $labRequestStatus, // Also store in metadata
                        'patient_data' => $labRequestPatientData, // Store patient data in lab request metadata too
                    ],
                ]);
            } catch (\Exception $e) {
                // Try each status until one works
                foreach ($validStatuses as $status) {
                    if ($status === $labRequestStatus) continue; // Skip the one we already tried
                    
                    try {
                        $labRequest = \App\Models\LabRequest::create([
                            'patient_id' => $patient->id,
                            'lab_no' => $patient->lab,
                            'status' => $status,
                            'metadata' => [
                                'created_via' => 'Patient Registration',
                                'request_date' => $data['attendance_date'] ?? now()->format('Y-m-d'),
                                'notes' => 'Created via Patient Registration',
                                'created_by' => auth()->id() ?? 1,
                                'status' => $status,
                                'patient_data' => $labRequestPatientData, // Store patient data in lab request metadata too
                            ],
                        ]);
                        
                        // If we get here, it worked
                        $labRequestStatus = $status;
                        \Log::info("Created lab request with status: $status");
                        break;
                    } catch (\Exception $innerE) {
                        // Continue to next status
                        \Log::warning("Failed to create lab request with status: $status", [
                            'error' => $innerE->getMessage()
                        ]);
                    }
                }
                
                // If we still don't have a lab request, try without status
                if (!isset($labRequest)) {
                    $labRequest = \App\Models\LabRequest::create([
                        'patient_id' => $patient->id,
                        'lab_no' => $patient->lab,
                        'metadata' => [
                            'created_via' => 'Patient Registration',
                            'request_date' => $data['attendance_date'] ?? now()->format('Y-m-d'),
                            'notes' => 'Created via Patient Registration',
                            'created_by' => auth()->id() ?? 1,
                            'patient_data' => $labRequestPatientData, // Store patient data in lab request metadata too
                        ],
                    ]);
                    \Log::warning("Created lab request without status", [
                        'lab_request_id' => $labRequest->id
                    ]);
                }
            }
            
            // Create visit for all patient registrations (with or without billing)
            $visit = null;
            $hasBillingInfo = isset($data['total_amount']) && !empty($data['total_amount']) && floatval($data['total_amount']) > 0;
            
            \Log::info('Visit creation decision', [
                'has_billing_info' => $hasBillingInfo,
                'total_amount' => $data['total_amount'] ?? 'not_set',
                'will_create_visit_with_billing' => $hasBillingInfo,
                'will_create_visit_without_billing' => !$hasBillingInfo
            ]);
            
            if ($hasBillingInfo) {
                $amountPaid = floatval($data['amount_paid'] ?? 0);
                $totalAmount = floatval($data['total_amount']);
                
                // Determine payment status
                $paymentStatus = 'unpaid';
                if ($amountPaid >= $totalAmount) {
                    $paymentStatus = 'paid';
                } elseif ($amountPaid > 0) {
                    $paymentStatus = 'partial';
                }
                
                // Get current staff shift
                $currentShift = \App\Models\Shift::where('staff_id', auth()->id())
                    ->where('status', 'open')
                    ->whereDate('opened_at', today())
                    ->first();

                // Create visit with detailed information
                $visitData = [
                    'patient_id' => $patient->id,
                    'lab_request_id' => $labRequest->id, // Link visit to lab request
                    'visit_number' => 'VIS-' . date('Ymd') . '-' . str_pad($patient->id, 6, '0', STR_PAD_LEFT),
                    'visit_date' => $data['attendance_date'] ?? now(),
                    'visit_time' => now()->format('H:i:s'),
                    'status' => 'registered',
                    'remarks' => 'Created via Patient Registration - Payment Status: ' . $paymentStatus,
                    'shift_id' => $currentShift?->id,
                    'processed_by_staff' => auth()->id(),
                ];
                
                // Add financial fields if they exist in the model
                $visitModel = new Visit();
                $fillable = $visitModel->getFillable();
                
                if (in_array('total_amount', $fillable)) {
                    $visitData['total_amount'] = $totalAmount;
                }
                if (in_array('final_amount', $fillable)) {
                    $visitData['final_amount'] = $totalAmount;
                }
                if (in_array('amount', $fillable)) {
                    $visitData['amount'] = $totalAmount;
                }
                if (in_array('paid_amount', $fillable)) {
                    $visitData['paid_amount'] = $amountPaid;
                }
                if (in_array('remaining_amount', $fillable)) {
                    $visitData['remaining_amount'] = $totalAmount - $amountPaid;
                }
                if (in_array('payment_status', $fillable)) {
                    $visitData['payment_status'] = $paymentStatus;
                }
                
                // Add comprehensive metadata with all patient data
                if (in_array('metadata', $fillable)) {
                    // Prepare payment details for metadata
                    $paymentDetails = [];
                    if (isset($data['amount_paid_cash']) && $data['amount_paid_cash'] > 0) {
                        $paymentDetails['amount_paid_cash'] = floatval($data['amount_paid_cash']);
                    }
                    if (isset($data['amount_paid_card']) && $data['amount_paid_card'] > 0) {
                        $paymentDetails['amount_paid_card'] = floatval($data['amount_paid_card']);
                        $paymentDetails['additional_payment_method'] = $data['additional_payment_method'] ?? 'Card';
                    }
                    $paymentDetails['total_paid'] = $amountPaid;
                    
                    // Prepare comprehensive patient data for metadata
                    $patientData = [
                        'name' => $data['name'] ?? $patient->name,
                        'phone' => $data['phone'] ?? $patient->phone,
                        'age' => $data['age'] ?? $patient->age,
                        'gender' => $data['gender'] ?? $patient->gender,
                        'organization' => $data['organization'] ?? $patient->organization,
                        'doctor' => $data['doctor'] ?? $patient->doctor,
                        'sample_type' => $data['sample_type'] ?? null,
                        'sample_size' => $data['sample_size'] ?? null,
                        'number_of_samples' => $data['number_of_samples'] ?? 1,
                        'case_type' => $data['case_type'] ?? null,
                        'day_of_week' => $data['day_of_week'] ?? null,
                        'medical_history' => $data['medical_history'] ?? null,
                        'previous_tests' => $data['previous_tests'] ?? null,
                        'attendance_date' => $data['attendance_date'] ?? now()->format('Y-m-d'),
                        'delivery_date' => $data['delivery_date'] ?? null,
                        'total_amount' => $totalAmount,
                        'amount_paid' => $amountPaid,
                        'amount_paid_cash' => $data['amount_paid_cash'] ?? 0,
                        'amount_paid_card' => $data['amount_paid_card'] ?? 0,
                        'additional_payment_method' => $data['additional_payment_method'] ?? null,
                        'lab_number' => $patient->lab,
                    ];
                    
                    $visitData['metadata'] = [
                        'created_via' => 'patient_registration',
                        'payment_details' => $paymentDetails,
                        'patient_data' => $patientData,
                        'total_amount' => $totalAmount,
                        'paid_amount' => $amountPaid,
                        'remaining_amount' => $totalAmount - $amountPaid,
                        'payment_status' => $paymentStatus,
                        'lab_request_id' => $labRequest->id,
                    ];
                }
                
                // Create the visit
                $visit = Visit::create($visitData);
                
                // Debug: Log visit creation (with billing)
                \Log::info('Visit created for patient registration (with billing)', [
                    'visit_id' => $visit->id,
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'visit_metadata' => $visit->metadata,
                    'number_of_samples' => $data['number_of_samples'] ?? 'not_set',
                ]);
                
            // Create visit test based on case type
            $visitTest = null;
            if (isset($data['case_type']) && !empty($data['case_type']) && $data['case_type'] !== null) {
                try {
                    $testCategory = TestCategory::where('name', $data['case_type'])->first();
                    
                    if ($testCategory) {
                        $labTest = LabTest::where('category_id', $testCategory->id)->first();
                        
                        if ($labTest) {
                            $visitTest = VisitTest::create([
                                'visit_id' => $visit->id,
                                'lab_test_id' => $labTest->id,
                                'status' => 'pending',
                                'price' => $totalAmount,
                                'barcode_uid' => 'LAB-' . strtoupper(Str::random(8)),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to create visit test', [
                        'error' => $e->getMessage(),
                        'case_type' => $data['case_type'] ?? 'null'
                    ]);
                }
            }
                
                // Update lab request with visit information and financial data
                try {
                    // First try with 'registered' status
                    $labRequest->update([
                        'metadata' => array_merge($labRequest->metadata ?? [], [
                            'visit_id' => $visit->id,
                            'total_amount' => $totalAmount,
                            'paid_amount' => $amountPaid,
                            'remaining_amount' => $totalAmount - $amountPaid,
                            'payment_status' => $paymentStatus,
                            'status' => 'registered', // Add status to metadata as well
                        ]),
                        'status' => 'registered', // Try with 'registered' status
                    ]);
                } catch (\Exception $e) {
                    // If 'registered' fails, try with 'processed' or other common statuses
                    try {
                        $labRequest->update([
                            'metadata' => array_merge($labRequest->metadata ?? [], [
                                'visit_id' => $visit->id,
                                'total_amount' => $totalAmount,
                                'paid_amount' => $amountPaid,
                                'remaining_amount' => $totalAmount - $amountPaid,
                                'payment_status' => $paymentStatus,
                                'status' => 'processed', // Add status to metadata as well
                            ]),
                            'status' => 'processed', // Try with 'processed' status
                        ]);
                    } catch (\Exception $e2) {
                        // If that fails too, try with 'completed' or just update metadata
                        try {
                            $labRequest->update([
                                'metadata' => array_merge($labRequest->metadata ?? [], [
                                    'visit_id' => $visit->id,
                                    'total_amount' => $totalAmount,
                                    'paid_amount' => $amountPaid,
                                    'remaining_amount' => $totalAmount - $amountPaid,
                                    'payment_status' => $paymentStatus,
                                ]),
                                'status' => 'completed', // Try with 'completed' status
                            ]);
                        } catch (\Exception $e3) {
                            // Last resort: just update metadata without changing status
                            $labRequest->update([
                                'metadata' => array_merge($labRequest->metadata ?? [], [
                                    'visit_id' => $visit->id,
                                    'total_amount' => $totalAmount,
                                    'paid_amount' => $amountPaid,
                                    'remaining_amount' => $totalAmount - $amountPaid,
                                    'payment_status' => $paymentStatus,
                                ]),
                            ]);
                            
                            // Log the issue for debugging
                            \Log::warning('Could not update lab request status', [
                                'lab_request_id' => $labRequest->id,
                                'error1' => $e->getMessage(),
                                'error2' => $e2->getMessage(),
                                'error3' => $e3->getMessage(),
                            ]);
                        }
                    }
                }
                
                // Create sample records from patient registration data
                // Get sample data from visit metadata (where it's actually stored)
                $visitMetadata = json_decode($visit->metadata ?? '{}', true);
                $patientData = $visitMetadata['patient_data'] ?? [];
                
                $sampleType = $patientData['sample_type'] ?? $data['sample_type'] ?? null;
                $caseType = $patientData['case_type'] ?? $data['case_type'] ?? null;
                $sampleSize = $patientData['sample_size'] ?? $data['sample_size'] ?? null;
                $numberOfSamples = intval($patientData['number_of_samples'] ?? $data['number_of_samples'] ?? 1);
                
                if ($sampleType || $caseType || $sampleSize || $numberOfSamples > 0) {
                    try {
                        // Create multiple sample records based on number_of_samples
                        for ($i = 1; $i <= $numberOfSamples; $i++) {
                            \App\Models\Sample::create([
                                'lab_request_id' => $labRequest->id,
                                'sample_type' => $sampleType,
                                'case_type' => $caseType,
                                'sample_size' => $sampleSize,
                                'number_of_samples' => 1, // Each individual sample record represents 1 sample
                                'sample_id' => 'SMP-' . str_pad($labRequest->id, 6, '0', STR_PAD_LEFT) . '-' . date('Ymd') . '-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                                'status' => 'collected',
                                'notes' => 'Sample ' . $i . ' of ' . $numberOfSamples . ' - from patient registration',
                                'created_at' => now(),
                            ]);
                        }
                        
                        \Log::info('Sample records created successfully', [
                            'lab_request_id' => $labRequest->id,
                            'number_of_samples' => $numberOfSamples,
                            'sample_type' => $sampleType
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to create sample records', [
                            'error' => $e->getMessage(),
                            'lab_request_id' => $labRequest->id ?? 'null',
                            'sample_type' => $sampleType,
                            'number_of_samples' => $numberOfSamples
                        ]);
                    }
                }
                
                // Create initial report automatically
                if ($visitTest) {
                    \App\Models\Report::create([
                        'lab_request_id' => $labRequest->id,
                        'title' => 'Lab Report - ' . $visitTest->labTest->name ?? 'Test Report',
                        'content' => 'Report generated automatically for patient ' . $patient->name,
                        'status' => 'pending',
                        'generated_by' => auth()->id() ?? 1,
                        'generated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
                
                // Create invoice for billing tracking
                try {
                    // Get the Invoice model and check its fillable properties
                    $invoiceModel = new \App\Models\Invoice();
                    $fillable = $invoiceModel->getFillable();
                    
                    // Create data array with only valid columns
                    $invoiceData = [];
                    
                    // Try to add common invoice fields if they exist in the fillable array
                    if (in_array('amount', $fillable)) {
                        $invoiceData['amount'] = $totalAmount;
                    }
                    if (in_array('total_amount', $fillable)) {
                        $invoiceData['total_amount'] = $totalAmount;
                    }
                    if (in_array('paid_amount', $fillable)) {
                        $invoiceData['paid_amount'] = $amountPaid;
                    }
                    if (in_array('remaining_amount', $fillable)) {
                        $invoiceData['remaining_amount'] = $totalAmount - $amountPaid;
                    }
                    if (in_array('visit_id', $fillable)) {
                        $invoiceData['visit_id'] = $visit->id;
                    }
                    if (in_array('lab_request_id', $fillable)) {
                        $invoiceData['lab_request_id'] = $labRequest->id;
                    }
                    if (in_array('shift_id', $fillable)) {
                        $invoiceData['shift_id'] = $currentShift?->id;
                    }
                    if (in_array('status', $fillable)) {
                        $invoiceData['status'] = $amountPaid >= $totalAmount ? 'paid' : 'unpaid';
                    }
                    
                    // Log the invoice data for debugging
                    \Log::info('Creating invoice with data:', [
                        'invoice_data' => $invoiceData,
                        'fillable_fields' => $fillable
                    ]);
                    
                    // Create the invoice with valid data
                    $invoice = \App\Models\Invoice::create($invoiceData);
                    
                } catch (\Exception $e) {
                    \Log::error('Failed to create invoice', [
                        'error' => $e->getMessage(),
                        'visit_id' => $visit->id ?? 'null',
                        'lab_request_id' => $labRequest->id ?? 'null'
                    ]);
                    
                    // Continue without invoice for now
                    $invoice = null;
                }
                
                // Create payment record if amount was paid during registration and invoice was created
                if ($amountPaid > 0 && $invoice) {
                    try {
                        // Get the Payment model and check its fillable properties
                        $paymentModel = new \App\Models\Payment();
                        $fillable = $paymentModel->getFillable();
                        
                        // Create data array with only valid columns
                        $paymentData = [];
                        
                        // Try to add common payment fields if they exist in the fillable array
                        if (in_array('invoice_id', $fillable)) {
                            $paymentData['invoice_id'] = $invoice->id;
                        }
                        if (in_array('paid', $fillable)) {
                            $paymentData['paid'] = $amountPaid;
                        }
                        if (in_array('amount', $fillable)) {
                            $paymentData['amount'] = $amountPaid;
                        }
                        if (in_array('comment', $fillable)) {
                            $paymentData['comment'] = 'Payment made during patient registration';
                        }
                        if (in_array('date', $fillable)) {
                            $paymentData['date'] = now()->toDateString();
                        }
                        if (in_array('author', $fillable)) {
                            $paymentData['author'] = auth()->id() ?? 1;
                        }
                        if (in_array('income', $fillable)) {
                            $paymentData['income'] = 1;
                        }
                        if (in_array('shift_id', $fillable)) {
                            $paymentData['shift_id'] = $currentShift?->id;
                        }
                        
                        // Log the payment data for debugging
                        \Log::info('Creating payment with data:', [
                            'payment_data' => $paymentData,
                            'fillable_fields' => $fillable
                        ]);
                        
                        // Create the payment with valid data
                        \App\Models\Payment::create($paymentData);
                    } catch (\Exception $e) {
                        \Log::error('Failed to create payment', [
                            'error' => $e->getMessage(),
                            'invoice_id' => $invoice->id ?? 'null'
                        ]);
                        // Continue without payment
                    }
                }
                
                // If partial or no payment, add to unpaid invoices tracking
                if ($paymentStatus !== 'paid') {
                    // Log the unpaid invoice for follow-up
                    \Log::info("Unpaid invoice created for patient {$patient->name} (Lab: {$patient->lab}) - Amount: $" . ($totalAmount - $amountPaid));
                }
                
                // Try to create a receipt record if that model exists
                try {
                    if (class_exists('\\App\\Models\\Receipt')) {
                        $receiptModel = new \App\Models\Receipt();
                        $fillable = $receiptModel->getFillable();
                        
                        $receiptData = [];
                        
                        // Add fields that might exist in the Receipt model
                        if (in_array('visit_id', $fillable)) {
                            $receiptData['visit_id'] = $visit->id;
                        }
                        if (in_array('patient_id', $fillable)) {
                            $receiptData['patient_id'] = $patient->id;
                        }
                        if (in_array('invoice_id', $fillable) && $invoice) {
                            $receiptData['invoice_id'] = $invoice->id;
                        }
                        if (in_array('lab_request_id', $fillable)) {
                            $receiptData['lab_request_id'] = $labRequest->id;
                        }
                        if (in_array('amount', $fillable)) {
                            $receiptData['amount'] = $totalAmount;
                        }
                        if (in_array('total_amount', $fillable)) {
                            $receiptData['total_amount'] = $totalAmount;
                        }
                        if (in_array('paid_amount', $fillable)) {
                            $receiptData['paid_amount'] = $amountPaid;
                        }
                        if (in_array('remaining_amount', $fillable)) {
                            $receiptData['remaining_amount'] = $totalAmount - $amountPaid;
                        }
                        if (in_array('payment_status', $fillable)) {
                            $receiptData['payment_status'] = $paymentStatus;
                        }
                        if (in_array('receipt_number', $fillable)) {
                            $receiptData['receipt_number'] = 'RCP-' . date('Ymd') . '-' . str_pad($visit->id, 6, '0', STR_PAD_LEFT);
                        }
                        if (in_array('receipt_date', $fillable)) {
                            $receiptData['receipt_date'] = now();
                        }
                        if (in_array('status', $fillable)) {
                            $receiptData['status'] = $paymentStatus;
                        }
                        
                        // Create the receipt if we have data
                        if (!empty($receiptData)) {
                            \App\Models\Receipt::create($receiptData);
                            \Log::info('Created receipt record', ['receipt_data' => $receiptData]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to create receipt record', [
                        'error' => $e->getMessage(),
                        'visit_id' => $visit->id ?? 'null'
                    ]);
                    // Continue without receipt
                }
            } else {
                // Create a basic visit even without billing information
                $visitData = [
                    'patient_id' => $patient->id,
                    'lab_request_id' => $labRequest->id ?? null, // Link visit to lab request if available
                    'visit_number' => 'VIS-' . date('Ymd') . '-' . str_pad($patient->id, 6, '0', STR_PAD_LEFT),
                    'visit_date' => $data['attendance_date'] ?? now(),
                    'visit_time' => now()->format('H:i:s'),
                    'status' => 'registered',
                    'remarks' => 'Created via Patient Registration - No billing information',
                    'shift_id' => null,
                    'processed_by_staff' => auth()->id(),
                ];
                
                // Add metadata with patient data
                $visitModel = new Visit();
                $fillable = $visitModel->getFillable();
                
                if (in_array('metadata', $fillable)) {
                    $patientData = [
                        'name' => $data['name'] ?? $patient->name,
                        'phone' => $data['phone'] ?? $patient->phone,
                        'age' => $data['age'] ?? $patient->age,
                        'gender' => $data['gender'] ?? $patient->gender,
                        'organization' => $data['organization'] ?? $patient->organization,
                        'doctor' => $data['doctor'] ?? $patient->doctor,
                        'sample_type' => $data['sample_type'] ?? null,
                        'sample_size' => $data['sample_size'] ?? null,
                        'number_of_samples' => $data['number_of_samples'] ?? 1,
                        'case_type' => $data['case_type'] ?? null,
                        'day_of_week' => $data['day_of_week'] ?? null,
                        'medical_history' => $data['medical_history'] ?? null,
                        'previous_tests' => $data['previous_tests'] ?? null,
                        'attendance_date' => $data['attendance_date'] ?? now()->format('Y-m-d'),
                        'delivery_date' => $data['delivery_date'] ?? null,
                        'lab_number' => $patient->lab,
                    ];
                    
                    $visitData['metadata'] = [
                        'created_via' => 'patient_registration',
                        'patient_data' => $patientData,
                        'lab_request_id' => $labRequest->id,
                    ];
                }
                
                // Create the visit
                $visit = Visit::create($visitData);
                
                // Debug: Log visit creation
                \Log::info('Visit created for patient registration', [
                    'visit_id' => $visit->id,
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'visit_metadata' => $visit->metadata,
                    'number_of_samples' => $data['number_of_samples'] ?? 'not_set',
                ]);
                
                // Update lab request with visit information
                try {
                    $labRequest->update([
                        'metadata' => array_merge($labRequest->metadata ?? [], [
                            'visit_id' => $visit->id,
                            'status' => 'registered',
                        ]),
                        'status' => 'registered',
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to update lab request with visit info', [
                        'error' => $e->getMessage(),
                        'visit_id' => $visit->id ?? 'null'
                    ]);
                }
            }
            
            DB::commit();
            
            // Get patient credentials
            $credentials = $patient->getPortalCredentials();
            
            $responseData = [
                'patient_id' => $patient->id,
                'lab_number' => $patient->lab,
                'visit_id' => $visit->id ?? null,
                'lab_request_id' => $labRequest->id ?? null,
                'invoice_id' => $invoice->id ?? null,
                'user_credentials' => $credentials,
                'financial_data' => [
                    'total_amount' => $totalAmount,
                    'paid_amount' => $amountPaid,
                    'remaining_amount' => $totalAmount - $amountPaid,
                    'payment_status' => $paymentStatus,
                ],
            ];
            
            // Add payment status information
            if (isset($visit)) {
                $responseData['payment_status'] = $paymentStatus;
                $responseData['total_amount'] = $totalAmount;
                $responseData['amount_paid'] = $amountPaid;
                $responseData['balance'] = $totalAmount - $amountPaid;
                
                if ($paymentStatus !== 'paid') {
                    $responseData['unpaid_invoice'] = true;
                    $responseData['message'] = 'Patient registered successfully. Invoice created with unpaid balance - will appear in Unpaid Invoices section.';
                } else {
                    $responseData['unpaid_invoice'] = false;
                    $responseData['message'] = 'Patient registered successfully with full payment.';
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => $responseData['message'] ?? (isset($data['patient_id']) ? 'Patient updated successfully' : 'Patient registered successfully'),
                'lab_number' => $patient->lab, // Add lab number to top level for frontend
                'user_credentials' => $credentials, // Add credentials to top level for frontend
                'data' => $responseData,
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the full error
            \Log::error('Patient Registration Submit - Error occurred', [
                'user' => auth()->user() ? auth()->user()->name : 'Not authenticated',
                'user_id' => auth()->id(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            
            // Handle specific error types
            $statusCode = 500;
            $message = 'Failed to register patient';
            
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'lab') !== false) {
                $statusCode = 409; // Conflict
                $message = 'Lab number already exists. Please use a different lab number.';
            } elseif (strpos($e->getMessage(), 'Integrity constraint violation') !== false) {
                $statusCode = 409; // Conflict
                $message = 'Data conflict. Please check your input and try again.';
            } elseif (strpos($e->getMessage(), 'SQLSTATE') !== false) {
                $statusCode = 500; // Server error
                $message = 'Database error occurred. Please try again.';
            }
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get test categories for dropdowns
     */
    public function getTestCategories()
    {
        $categories = TestCategory::active()->orderBy('name')->get(['id', 'name', 'description']);
        
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}