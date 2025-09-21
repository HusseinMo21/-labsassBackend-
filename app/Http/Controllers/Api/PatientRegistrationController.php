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
                
                $patient = Patient::create($data);
                
                // Create patient credentials
                $patient->credentials()->create([
                    'username' => $username,
                    'original_password' => $password,
                    'hashed_password' => Hash::make($password),
                    'is_active' => true,
                ]);
            }
            
            // Create lab request for all patients so they appear in Lab Requests
            $labRequest = \App\Models\LabRequest::create([
                'patient_id' => $patient->id,
                'lab_no' => $patient->lab, // Use the patient's lab number
                'status' => 'pending',
                'metadata' => [
                    'created_via' => 'Patient Registration',
                    'request_date' => $data['attendance_date'] ?? now()->format('Y-m-d'),
                    'notes' => 'Created via Patient Registration',
                    'created_by' => auth()->id() ?? 1,
                ],
            ]);
            
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
                
                $visit = Visit::create([
                    'patient_id' => $patient->id,
                    'visit_number' => 'VIS-' . date('Ymd') . '-' . str_pad($patient->id, 6, '0', STR_PAD_LEFT),
                    'visit_date' => $data['attendance_date'] ?? now(),
                    'visit_time' => now()->format('H:i:s'),
                    'total_amount' => $totalAmount,
                    'final_amount' => $totalAmount,
                    'status' => 'registered',
                    'remarks' => 'Created via Patient Registration - Payment Status: ' . $paymentStatus,
                ]);
                
                // Create visit test based on case type
                $visitTest = null;
                if (isset($data['case_type']) && !empty($data['case_type'])) {
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
                }
                
                // Update lab request with visit information
                $labRequest->update([
                    'metadata' => array_merge($labRequest->metadata ?? [], [
                        'visit_id' => $visit->id,
                    ]),
                ]);
                
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
                
                // Create invoice for billing tracking (using legacy structure)
                $invoice = \App\Models\Invoice::create([
                    'lab' => $labRequest->full_lab_no,
                    'total' => $totalAmount,
                    'paid' => $amountPaid,
                    'remaining' => $totalAmount - $amountPaid,
                    'lab_request_id' => $labRequest->id, // Link to lab request
                ]);
                
                // If partial or no payment, add to unpaid invoices tracking
                if ($paymentStatus !== 'paid') {
                    // Log the unpaid invoice for follow-up
                    \Log::info("Unpaid invoice created for patient {$patient->name} (Lab: {$patient->lab}) - Amount: $" . ($totalAmount - $amountPaid));
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
            ];
            
            // Add payment status information
            if (isset($visit) && isset($invoice)) {
                $responseData['payment_status'] = $paymentStatus;
                $responseData['total_amount'] = $invoice->total;
                $responseData['amount_paid'] = $invoice->paid;
                $responseData['balance'] = $invoice->remaining;
                
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