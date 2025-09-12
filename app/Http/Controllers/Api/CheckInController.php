<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\LabTest;
use App\Models\SampleTracking;
use App\Models\VisitTest;
use App\Models\PatientCredential;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Services\LabNoGenerator;
use App\Services\BarcodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
    protected $labNoGenerator;
    protected $barcodeGenerator;

    public function __construct(LabNoGenerator $labNoGenerator, BarcodeGenerator $barcodeGenerator)
    {
        $this->labNoGenerator = $labNoGenerator;
        $this->barcodeGenerator = $barcodeGenerator;
    }

    public function registerPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'birth_date' => 'required|date',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string',
            'national_id' => 'nullable|string|max:50',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_number' => 'nullable|string|max:255',
            'has_insurance' => 'boolean',
            'insurance_coverage' => 'nullable|numeric|min:0|max:100',
            'billing_address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'emergency_relationship' => 'nullable|string|max:100',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate credentials
            $username = Patient::generateUsername($request->name);
            $password = Patient::generatePassword();

            // Create patient
            $patient = Patient::create([
                ...$validator->validated(),
                'username' => $username,
                'password' => Hash::make($password),
            ]);

            // Also create credentials in patient_credentials table for consistency
            PatientCredential::create([
                'patient_id' => $patient->id,
                'username' => $username,
                'original_password' => $password,
                'hashed_password' => Hash::make($password),
                'is_active' => true,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Patient registered successfully',
                'patient' => $patient->load('visits'),
                'user_credentials' => [
                    'username' => $username,
                    'password' => $password,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to register patient',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createVisitWithBilling(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'tests' => 'required|array|min:1',
            'tests.*.lab_test_id' => 'required|exists:lab_tests,id',
            'upfront_payment' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,insurance,other',
            'notes' => 'nullable|string',
            'expected_delivery_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            \Log::info('Starting visit creation for patient: ' . $request->patient_id);
            $patient = Patient::findOrFail($request->patient_id);
            
            // Log existing credentials for debugging
            $existingCredentials = $patient->getPortalCredentials();
            \Log::info('Patient ' . $patient->id . ' existing credentials: ' . json_encode($existingCredentials));
            
            // Calculate total amount
            $totalAmount = 0;
            $selectedTests = [];
            
            foreach ($request->tests as $testData) {
                $test = LabTest::findOrFail($testData['lab_test_id']);
                $totalAmount += $test->price;
                $selectedTests[] = $test;
            }

            // Apply insurance discount if applicable
            $insuranceDiscount = $patient->getInsuranceDiscountAmount($totalAmount);
            $finalAmount = $totalAmount - $insuranceDiscount;

            // Validate upfront payment
            $minimumUpfront = ($finalAmount * 50) / 100; // 50% minimum
            if ($request->upfront_payment < $minimumUpfront) {
                return response()->json([
                    'message' => 'Upfront payment must be at least 50% of total amount',
                    'minimum_required' => $minimumUpfront,
                    'total_amount' => $finalAmount,
                ], 422);
            }

            \Log::info('Calculated amounts - Total: ' . $totalAmount . ', Final: ' . $finalAmount);
            
            // Generate visit number
            $visitNumber = Visit::generateVisitNumber();
            \Log::info('Generated visit number: ' . $visitNumber);
            
            // Create visit
            $visit = Visit::create([
                'patient_id' => $patient->id,
                'visit_number' => $visitNumber,
                'visit_date' => now()->toDateString(),
                'visit_time' => now(),
                'total_amount' => $totalAmount,
                'discount_amount' => $insuranceDiscount,
                'final_amount' => $finalAmount,
                'upfront_payment' => $request->upfront_payment,
                'remaining_balance' => $finalAmount - $request->upfront_payment,
                'minimum_upfront_percentage' => 50,
                'payment_method' => $request->payment_method,
                'receipt_number' => Visit::generateReceiptNumber(),
                'expected_delivery_date' => $request->expected_delivery_date ?? now()->addDays(1)->toDateString(),
                'barcode' => Visit::generateBarcode(),
                'check_in_by' => auth()->user()->name,
                'check_in_at' => now(),
                'billing_status' => $request->upfront_payment >= $finalAmount ? 'paid' : 'partial',
                'status' => 'registered',
                'remarks' => $request->notes,
            ]);
            
            \Log::info('Visit created successfully with ID: ' . $visit->id);

            // Create invoice for this visit
            $invoice = Invoice::create([
                'visit_id' => $visit->id,
                'invoice_number' => 'INV' . now()->format('Ymd') . str_pad($visit->id, 4, '0', STR_PAD_LEFT),
                'invoice_date' => now()->toDateString(),
                'subtotal' => $totalAmount,
                'discount_amount' => $insuranceDiscount,
                'tax_amount' => 0, // No tax for now
                'total_amount' => $finalAmount,
                'amount_paid' => $request->upfront_payment,
                'balance' => $finalAmount - $request->upfront_payment,
                'status' => $request->upfront_payment >= $finalAmount ? 'paid' : 'partial',
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);
            
            \Log::info('Invoice created successfully with ID: ' . $invoice->id);

            // Create visit tests and sample tracking
            foreach ($request->tests as $testData) {
                $labTest = LabTest::find($testData['lab_test_id']);
                $visitTest = $visit->visitTests()->create([
                    'lab_test_id' => $testData['lab_test_id'],
                    'price' => $labTest->price,
                    'status' => 'pending',
                    'barcode_uid' => VisitTest::generateBarcodeUid(),
                ]);

                // Create sample tracking for this test
                $visitTest->sampleTracking()->create([
                    'sample_id' => SampleTracking::generateSampleId(),
                    'status' => 'collected',
                    'collected_at' => now(),
                    'collected_by' => auth()->id(),
                ]);
            }

            // Create or update lab request with samples automatically filled from selected tests
            $labRequest = null;
            try {
                // Check if patient already has a lab request
                $existingLabRequest = LabRequest::where('patient_id', $patient->id)->first();
                
                if ($existingLabRequest) {
                    // Patient already has a lab number - add samples to existing lab request
                    $labRequest = $existingLabRequest;
                    \Log::info('Using existing lab request for patient: ' . $patient->id . ', Lab No: ' . $labRequest->lab_no);
                } else {
                    // Patient doesn't have a lab number - create new lab request
                    $labNoData = $this->labNoGenerator->generate();
                    $labNo = $labNoData['base'];
                    
                    $labRequest = LabRequest::create([
                        'patient_id' => $patient->id,
                        'lab_no' => $labNo,
                        'status' => 'pending',
                        'metadata' => [
                            'auto_created' => true,
                            'created_with_visit' => true,
                            'visit_id' => $visit->id,
                            'created_at' => now()->toISOString(),
                        ],
                    ]);
                    
                    // Generate barcode and QR code for the new lab request
                    $this->barcodeGenerator->generateForLabRequest($labRequest);
                    
                    \Log::info('New lab request created for patient: ' . $patient->id . ', Lab No: ' . $labNo);
                }

                // Add samples for each selected test to the lab request
                foreach ($request->tests as $testData) {
                    $labTest = LabTest::find($testData['lab_test_id']);
                    
                    // Check if this sample already exists for this test
                    $existingSample = $labRequest->samples()
                        ->where('tsample', $labTest->name)
                        ->where('nsample', $labTest->code)
                        ->first();
                    
                    if (!$existingSample) {
                        // Create sample with test information
                        $labRequest->samples()->create([
                            'tsample' => $labTest->name, // Sample Type = Test Name
                            'nsample' => $labTest->code, // Sample Name = Test Code
                            'isample' => $labTest->id,   // Sample ID = Test ID
                            'notes' => $labTest->description ?: "Test: {$labTest->name}",
                        ]);
                    }
                }
                
                \Log::info('Samples added to lab request: ' . $labRequest->id);
            } catch (\Exception $e) {
                \Log::error('Failed to create/update lab request for visit: ' . $e->getMessage());
                // Don't fail the visit creation if lab request creation fails
            }

            DB::commit();

            return response()->json([
                'message' => 'Visit created successfully',
                'visit' => $visit->load(['patient', 'visitTests.labTest']),
                'lab_request' => $labRequest ? $labRequest->load('samples') : null,
                'receipt_data' => [
                    'receipt_number' => $visit->receipt_number,
                    'date' => $visit->visit_date,
                    'patient_name' => $visit->patient->name,
                    'patient_age' => $visit->patient->age,
                    'patient_phone' => $visit->patient->phone,
                    'tests' => $visit->visitTests->map(function ($visitTest) {
                        return [
                            'name' => $visitTest->labTest->name,
                            'price' => $visitTest->price,
                        ];
                    }),
                    'total_amount' => $totalAmount,
                    'discount_amount' => $insuranceDiscount,
                    'final_amount' => $finalAmount,
                    'upfront_payment' => $request->upfront_payment,
                    'remaining_balance' => $finalAmount - $request->upfront_payment,
                    'payment_method' => $request->payment_method,
                    'expected_delivery_date' => $visit->expected_delivery_date,
                    'barcode' => $visit->barcode,
                    'check_in_by' => auth()->user()->name,
                    'check_in_at' => now(),
                    'patient_credentials' => $visit->patient->getPortalCredentials(),
                    'visit_id' => $visit->id,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating visit: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to create visit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getReceipt($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest'])->findOrFail($visitId);
        
        return response()->json([
            'visit' => $visit,
            'receipt_data' => [
                'receipt_number' => $visit->receipt_number,
                'date' => $visit->visit_date,
                'patient_name' => $visit->patient->name,
                'patient_age' => $visit->patient->age,
                'patient_phone' => $visit->patient->phone,
                'tests' => $visit->visitTests->map(function ($visitTest) {
                    return [
                        'name' => $visitTest->labTest->name,
                        'price' => $visitTest->price,
                    ];
                }),
                'total_amount' => $visit->total_amount,
                'discount_amount' => $visit->discount_amount,
                'final_amount' => $visit->final_amount,
                'upfront_payment' => $visit->upfront_payment,
                'remaining_balance' => $visit->remaining_balance,
                'payment_method' => $visit->payment_method,
                'expected_delivery_date' => $visit->expected_delivery_date,
                'barcode' => $visit->barcode,
                'check_in_by' => $visit->check_in_by,
                'check_in_at' => $visit->check_in_at,
                'patient_credentials' => $visit->patient->getPortalCredentials(),
            ],
        ]);
    }

    public function getSampleLabel($visitId)
    {
        try {
            \Log::info('Getting sample label for visit ID: ' . $visitId);
            
            $visit = Visit::with(['patient', 'visitTests.labTest'])->findOrFail($visitId);
            
            \Log::info('Visit found: ' . $visit->id . ', Patient: ' . $visit->patient->name);
            \Log::info('Visit tests count: ' . $visit->visitTests->count());
            
            // Generate individual labels for each test
            $testLabels = $visit->visitTests->map(function ($visitTest) use ($visit) {
                return [
                    'test_name' => $visitTest->labTest->name,
                    'patient_name' => $visit->patient->name,
                    'patient_id' => $visit->patient->id,
                    'sample_date' => $visit->visit_date,
                    'sample_time' => $visit->visit_time ? date('H:i', strtotime($visit->visit_time)) : date('H:i'),
                    'barcode' => $visitTest->barcode_uid,
                ];
            });
            
            \Log::info('Generated ' . $testLabels->count() . ' test labels');
            
            return response()->json([
                'label_data' => [
                    'patient_name' => $visit->patient->name,
                    'patient_age' => $visit->patient->age,
                    'visit_id' => $visit->id,
                    'visit_date' => $visit->visit_date,
                    'receipt_number' => $visit->receipt_number,
                    'test_labels' => $testLabels,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error generating sample label: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to generate sample label',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getFinalPaymentReceipt(Request $request, $visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest', 'invoice'])->findOrFail($visitId);
        $paymentAmount = $request->get('payment_amount', 0);
        $paymentMethod = $request->get('payment_method', 'cash');
        
        // Get the invoice for this visit
        $invoice = $visit->invoice;
        if (!$invoice) {
            return response()->json([
                'message' => 'No invoice found for this visit',
            ], 404);
        }
        
        // Calculate payment breakdown
        $paidBefore = $invoice->amount_paid - $paymentAmount;
        $paidNow = $paymentAmount;
        $remainingBalance = $invoice->remaining_balance;
        
        return response()->json([
            'receipt_data' => [
                'receipt_number' => $visit->receipt_number,
                'date' => now()->format('Y-m-d'),
                'patient_name' => $visit->patient->name,
                'patient_age' => $visit->patient->age,
                'patient_phone' => $visit->patient->phone,
                'tests' => $visit->visitTests->map(function ($visitTest) {
                    return [
                        'name' => $visitTest->labTest->name,
                        'price' => $visitTest->price,
                    ];
                }),
                'total_amount' => $visit->total_amount,
                'discount_amount' => $visit->discount_amount,
                'final_amount' => $visit->final_amount,
                'paid_before' => $paidBefore,
                'paid_now' => $paidNow,
                'remaining_balance' => $remainingBalance,
                'payment_method' => $paymentMethod,
                'expected_delivery_date' => $visit->expected_delivery_date,
                'barcode' => $visit->barcode,
                'check_in_by' => auth()->user()->name,
                'check_in_at' => now(),
                'patient_credentials' => $visit->patient->getPortalCredentials(),
                'visit_id' => $visit->id,
                'invoice_id' => $invoice->id,
            ],
        ]);
    }

    public function searchPatients(Request $request)
    {
        $query = $request->get('query', '');
        
        if (strlen($query) < 2) {
            return response()->json(['patients' => []]);
        }

        $patients = Patient::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('national_id', 'like', "%{$query}%")
            ->orWhere('username', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'phone', 'email', 'birth_date']);

        return response()->json(['patients' => $patients]);
    }

    public function getAvailableTests()
    {
        $tests = LabTest::with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($tests);
    }

    public function calculateBilling(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'tests' => 'required|array|min:1',
            'tests.*.lab_test_id' => 'required|exists:lab_tests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $patient = Patient::findOrFail($request->patient_id);
        
        // Calculate total amount
        $totalAmount = 0;
        $selectedTests = [];
        
        foreach ($request->tests as $testData) {
            $test = LabTest::findOrFail($testData['lab_test_id']);
            $totalAmount += $test->price;
            $selectedTests[] = $test;
        }

        // Apply insurance discount if applicable
        $insuranceDiscount = $patient->getInsuranceDiscountAmount($totalAmount);
        $finalAmount = $totalAmount - $insuranceDiscount;
        $minimumUpfront = ($finalAmount * 50) / 100;

        return response()->json([
            'billing_summary' => [
                'total_amount' => $totalAmount,
                'insurance_discount' => $insuranceDiscount,
                'final_amount' => $finalAmount,
                'minimum_upfront' => $minimumUpfront,
                'patient_has_insurance' => $patient->has_insurance,
                'insurance_coverage' => $patient->insurance_coverage,
            ],
            'selected_tests' => $selectedTests,
        ]);
    }
} 