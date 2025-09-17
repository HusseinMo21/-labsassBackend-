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
use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
    protected $labNoGenerator;
    protected $barcodeGenerator;
    protected $barcodeService;

    public function __construct(LabNoGenerator $labNoGenerator, BarcodeGenerator $barcodeGenerator, BarcodeService $barcodeService)
    {
        $this->labNoGenerator = $labNoGenerator;
        $this->barcodeGenerator = $barcodeGenerator;
        $this->barcodeService = $barcodeService;
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
            'patient_id' => 'required|exists:patient,id',
            'tests' => 'required|array|min:1',
            'tests.*.test_category_id' => 'required|exists:test_categories,id',
            'tests.*.custom_test_name' => 'required|string|max:255',
            'tests.*.custom_price' => 'required|numeric|min:0',
            'tests.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'upfront_payment' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,insurance,other',
            'notes' => 'nullable|string',
            'expected_delivery_date' => 'nullable|date',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_policy_number' => 'nullable|string|max:255',
            'insurance_claim_number' => 'nullable|string|max:255',
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
            \Log::info('Request data: ' . json_encode($request->all()));
            $patient = Patient::findOrFail($request->patient_id);
            
            // Log existing credentials for debugging
            $existingCredentials = $patient->getPortalCredentials();
            \Log::info('Patient ' . $patient->id . ' existing credentials: ' . json_encode($existingCredentials));
            
            // Calculate total amount
            $totalAmount = 0;
            $selectedTests = [];
            
            foreach ($request->tests as $testData) {
                \Log::info('Processing test data: ' . json_encode($testData));
                $testCategory = \App\Models\TestCategory::find($testData['test_category_id']);
                \Log::info('Found test category: ' . ($testCategory ? json_encode($testCategory->toArray()) : 'null'));
                if (!$testCategory) {
                    return response()->json([
                        'message' => 'Invalid test category ID: ' . $testData['test_category_id'],
                    ], 422);
                }
                
                $customPrice = $testData['custom_price'];
                $discountPercentage = $testData['discount_percentage'] ?? 0;
                
                // Calculate final price after discount
                $finalPrice = $customPrice;
                if ($discountPercentage > 0) {
                    $discountAmount = ($customPrice * $discountPercentage) / 100;
                    $finalPrice = $customPrice - $discountAmount;
                }
                
                $totalAmount += $finalPrice;
                $selectedTests[] = [
                    'category' => $testCategory,
                    'custom_test_name' => $testData['custom_test_name'],
                    'custom_price' => $customPrice,
                    'discount_percentage' => $discountPercentage,
                    'final_price' => $finalPrice
                ];
            }

            // Apply discount (from frontend or insurance)
            $discountAmount = $request->discount_amount ?? 0;
            $insuranceDiscount = $patient->getInsuranceDiscountAmount($totalAmount);
            
            // Use the higher of frontend discount or insurance discount
            $totalDiscount = max($discountAmount, $insuranceDiscount);
            $finalAmount = $totalAmount - $totalDiscount;

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
                'discount_amount' => $totalDiscount,
                'final_amount' => $finalAmount,
                'status' => 'registered',
                'remarks' => $this->buildRemarks($request),
                'expected_delivery_date' => $request->expected_delivery_date,
            ]);
            
            \Log::info('Visit created successfully with ID: ' . $visit->id);

            // Note: Invoice creation will be moved after LabRequest creation

            // Create visit tests and sample tracking
            foreach ($selectedTests as $testData) {
                // Create a dummy lab test for custom tests
                $dummyLabTest = \App\Models\LabTest::firstOrCreate([
                    'name' => $testData['custom_test_name'],
                    'code' => 'CUSTOM_' . now()->format('YmdHis'),
                ], [
                    'category_id' => $testData['category']->id,
                    'price' => $testData['custom_price'],
                    'description' => 'Custom test created during visit',
                    'is_active' => true,
                ]);
                
                $visitTest = $visit->visitTests()->create([
                    'lab_test_id' => $dummyLabTest->id,
                    'price' => $testData['custom_price'],
                    'status' => 'pending',
                    'barcode_uid' => 'VT' . now()->format('YmdHis') . rand(1000, 9999),
                ]);

                // Note: Sample tracking creation disabled - table doesn't exist
                // $visitTest->sampleTracking()->create([...]);
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

                // Link the visit to the lab request
                $visit->update(['lab_request_id' => $labRequest->id]);
                \Log::info('Linked visit ' . $visit->id . ' to lab request ' . $labRequest->id);

                // Create invoice for this visit
                $invoice = Invoice::create([
                    'lab' => $labRequest->lab_no,
                    'total' => $finalAmount,
                    'paid' => $request->upfront_payment,
                    'remaining' => $finalAmount - $request->upfront_payment,
                    'lab_request_id' => $labRequest->id,
                ]);
                
                \Log::info('Invoice created successfully with ID: ' . $invoice->id);

                // Add samples for each selected test to the lab request
                foreach ($selectedTests as $testData) {
                    $testName = $testData['custom_test_name'];
                    $testCode = $testData['category']->code ?? 'unknown';
                    $categoryName = $testData['category']->name ?? 'Unknown';
                    
                    \Log::info('Creating sample for test: ' . $testName . ', category: ' . $categoryName . ', code: ' . $testCode);
                    
                    // Check if this sample already exists for this test
                    $existingSample = $labRequest->samples()
                        ->where('sample_type', $testName)
                        ->where('sample_id', $testCode)
                        ->first();
                    
                    if (!$existingSample) {
                        // Generate sample ID and barcode
                        $sampleId = $this->barcodeService->generateNextSampleId($labRequest->lab_no);
                        $barcode = $this->barcodeService->generateBarcode($labRequest->lab_no, $sampleId);
                        
                        // Create sample with test information and barcode
                        $labRequest->samples()->create([
                            'sample_id' => $sampleId,
                            'sample_type' => $testName, // Sample Type = Custom Test Name
                            'status' => 'collected',
                            'collection_date' => now(),
                            'notes' => "Test: {$testName} ({$categoryName})",
                        ]);
                        
                        \Log::info('Created sample with barcode: ' . $barcode . ' for test: ' . $testName);
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
                'visit' => $visit->load(['patient', 'visitTests.labTest.category']),
                'lab_request' => $labRequest ? $labRequest->load('samples') : null,
                'receipt_data' => [
                    'receipt_number' => $visit->visit_number,
                    'date' => $visit->visit_date,
                    'patient_name' => $visit->patient->name,
                    'patient_age' => $visit->patient->age,
                    'patient_phone' => $visit->patient->phone,
                    'tests' => $visit->visitTests->map(function ($visitTest) {
                        return [
                            'name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                            'category' => $visitTest->testCategory ? $visitTest->testCategory->name : 'Unknown',
                            'price' => $visitTest->final_price ?: $visitTest->price,
                        ];
                    }),
                    'total_amount' => $totalAmount,
                    'discount_amount' => $totalDiscount,
                    'final_amount' => $finalAmount,
                    'upfront_payment' => $request->upfront_payment,
                    'remaining_balance' => $finalAmount - $request->upfront_payment,
                    'payment_method' => $request->payment_method,
                    'expected_delivery_date' => $visit->expected_delivery_date,
                    'barcode' => $visit->barcode,
                    'check_in_by' => auth()->user() ? auth()->user()->name : 'System',
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

    private function buildRemarks($request)
    {
        $remarks = [];
        
        if ($request->notes) {
            $remarks[] = "Notes: " . $request->notes;
        }
        
        if ($request->payment_method === 'insurance') {
            if ($request->insurance_provider) {
                $remarks[] = "Insurance Provider: " . $request->insurance_provider;
            }
            if ($request->insurance_policy_number) {
                $remarks[] = "Policy Number: " . $request->insurance_policy_number;
            }
            if ($request->insurance_claim_number) {
                $remarks[] = "Claim Number: " . $request->insurance_claim_number;
            }
        }
        
        if ($request->discount_amount > 0) {
            $remarks[] = "Discount Applied: EGP " . number_format($request->discount_amount, 2);
            if ($request->discount_percentage > 0) {
                $remarks[] = "Discount Percentage: " . $request->discount_percentage . "%";
            }
        }
        
        return implode("\n", $remarks);
    }

    public function getReceipt($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.testCategory', 'labRequest'])->findOrFail($visitId);
        
        // Get the related invoice for financial data
        $invoice = null;
        $payments = collect();
        if ($visit->labRequest) {
            $invoice = \App\Models\Invoice::where('lab', $visit->labRequest->lab_no)->first();
            if ($invoice) {
                $payments = \App\Models\Payment::where('invoice_id', $invoice->id)->get();
            }
        }
        
        return response()->json([
            'visit' => $visit,
            'receipt_data' => [
                'receipt_number' => $visit->visit_number, // Use visit_number instead of receipt_number
                'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : 'N/A',
                'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : 'N/A',
                'patient_name' => $visit->patient->name,
                'patient_age' => $visit->patient->age,
                'patient_phone' => $visit->patient->phone,
                'tests' => $visit->visitTests->map(function ($visitTest) {
                    return [
                        'name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                        'category' => $visitTest->testCategory ? $visitTest->testCategory->name : 'Unknown',
                        'price' => $visitTest->final_price ?: $visitTest->price,
                    ];
                }),
                'total_amount' => $invoice ? $invoice->total : ($visit->total_amount ?: 0),
                'discount_amount' => $visit->discount_amount ?: 0,
                'final_amount' => $invoice ? $invoice->total : ($visit->final_amount ?: 0),
                'upfront_payment' => $invoice ? $invoice->paid : ($visit->upfront_payment ?: 0),
                'remaining_balance' => $invoice ? $invoice->remaining : ($visit->remaining_balance ?: 0),
                'payment_method' => $this->getPaymentMethod($visit, $payments),
                'billing_status' => $this->getPaymentStatus($invoice),
                'expected_delivery_date' => $visit->expected_delivery_date ?: 'N/A',
                'barcode' => $visit->labRequest ? $this->barcodeService->generateReceiptBarcode($visit->labRequest->full_lab_no) : ($visit->barcode ?: 'N/A'),
                'check_in_by' => $visit->check_in_by ?: 'N/A',
                'check_in_at' => $visit->check_in_at ?: 'N/A',
                'visit_id' => $visit->id,
                'patient_credentials' => $visit->patient->getPortalCredentials(),
            ],
        ]);
    }

    /**
     * Get payment method from visit or payments
     */
    private function getPaymentMethod($visit, $payments)
    {
        // First try to get from visit
        if ($visit->payment_method) {
            return $visit->payment_method;
        }
        
        // Then try to get from the most recent payment
        if ($payments->count() > 0) {
            $latestPayment = $payments->sortByDesc('created_at')->first();
            if ($latestPayment->payment_method) {
                return $latestPayment->payment_method;
            }
        }
        
        return 'Cash'; // Default to Cash
    }

    /**
     * Get payment status based on invoice data
     */
    private function getPaymentStatus($invoice)
    {
        if (!$invoice) {
            return 'Pending';
        }
        
        if ($invoice->remaining <= 0) {
            return 'Paid';
        } elseif ($invoice->paid > 0) {
            return 'Partial';
        } else {
            return 'Pending';
        }
    }

    public function getSampleLabel($visitId)
    {
        try {
            \Log::info('Getting sample label for visit ID: ' . $visitId);
            
            $visit = Visit::with(['patient', 'visitTests.testCategory'])->findOrFail($visitId);
            
            \Log::info('Visit found: ' . $visit->id . ', Patient: ' . $visit->patient->name);
            \Log::info('Visit tests count: ' . $visit->visitTests->count());
            
            // Generate individual labels for each test
            $testLabels = $visit->visitTests->map(function ($visitTest, $index) use ($visit) {
                // Generate sample ID like "2025-19-S1", "2025-19-S2", etc.
                $sampleId = $visit->labRequest ? $visit->labRequest->full_lab_no . '-S' . ($index + 1) : $visitTest->barcode_uid;
                
                // Generate real barcode image for the sample ID
                $barcodeImage = $this->barcodeService->generateReceiptBarcode($sampleId);
                
                return [
                    'test_name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                    'category' => $visitTest->testCategory ? $visitTest->testCategory->name : 'Unknown',
                    'patient_name' => $visit->patient->name,
                    'patient_id' => $visit->patient->id,
                    'sample_id' => $sampleId,
                    'sample_date' => $visit->visit_date,
                    'sample_time' => $visit->visit_time ? date('H:i', strtotime($visit->visit_time)) : date('H:i'),
                    'barcode' => $barcodeImage, // Real scannable barcode image
                    'barcode_text' => $sampleId, // The text that the barcode represents
                ];
            });
            
            \Log::info('Generated ' . $testLabels->count() . ' test labels');
            
            return response()->json([
                'label_data' => [
                    'patient_name' => $visit->patient->name,
                    'patient_age' => $visit->patient->age,
                    'visit_id' => $visit->id,
                    'visit_date' => $visit->visit_date,
                    'receipt_number' => $visit->visit_number,
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
        $visit = Visit::with(['patient', 'visitTests.testCategory', 'invoice', 'labRequest'])->findOrFail($visitId);
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
                'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : 'N/A',
                'date' => now()->format('Y-m-d'),
                'patient_name' => $visit->patient->name,
                'patient_age' => $visit->patient->age,
                'patient_phone' => $visit->patient->phone,
                'tests' => $visit->visitTests->map(function ($visitTest) {
                    return [
                        'name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                        'category' => $visitTest->testCategory ? $visitTest->testCategory->name : 'Unknown',
                        'price' => $visitTest->final_price ?: $visitTest->price,
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
                'barcode' => $visit->labRequest ? $this->barcodeService->generateReceiptBarcode($visit->labRequest->full_lab_no) : ($visit->barcode ?: 'N/A'),
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
            ->orWhere('sender', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'phone', 'age', 'gender', 'sender']);

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
            'patient_id' => 'required|exists:patient,id',
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

    public function getTestCategories()
    {
        $categories = \App\Models\TestCategory::active()->orderBy('name')->get(['id', 'name', 'description']);
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
} 