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
            'phone' => 'required|string|max:20',
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
                
                // Convert string values to appropriate types
                if (isset($data['age']) && !empty($data['age'])) {
                    $data['age'] = intval($data['age']);
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
                
                $patient = Patient::create($data);
                
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
                        ],
                    ]);
                    \Log::warning("Created lab request without status", [
                        'lab_request_id' => $labRequest->id
                    ]);
                }
            }
            
            // Create visit if billing information is provided
            if (isset($data['total_amount']) && !empty($data['total_amount']) && floatval($data['total_amount']) > 0) {
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