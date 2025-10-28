<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\LabTest;
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
            'payment_method' => 'required|in:cash,card,Fawry,InstaPay,VodafoneCash,Other',
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
            // Get current staff shift
            $currentShift = \App\Models\Shift::where('staff_id', auth()->id())
                ->where('status', 'open')
                ->whereDate('opened_at', today())
                ->first();

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
                'shift_id' => $currentShift?->id,
                'processed_by_staff' => auth()->id(),
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
                    'lab' => $labRequest->full_lab_no,
                    'total' => $finalAmount,
                    'paid' => $request->upfront_payment,
                    'remaining' => $finalAmount - $request->upfront_payment,
                    'lab_request_id' => $labRequest->id,
                    'shift_id' => $currentShift?->id,
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
                
                // Create initial report automatically for each visit test
                foreach ($visit->visitTests as $visitTest) {
                    $existingReport = \App\Models\Report::where('lab_request_id', $labRequest->id)
                        ->where('visit_test_id', $visitTest->id)
                        ->first();
                    
                    if (!$existingReport) {
                        \App\Models\Report::create([
                            'lab_request_id' => $labRequest->id,
                            'title' => 'Lab Report - ' . $visitTest->labTest->name ?? 'Test Report',
                            'content' => 'Report generated automatically for ' . $patient->name,
                            'status' => 'pending',
                            'generated_by' => auth()->id() ?? 1,
                            'generated_at' => now(),
                            'created_at' => now(),
                        ]);
                        
                        \Log::info('Report created automatically for visit test: ' . $visitTest->id);
                    }
                }
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
                    'expected_delivery_date' => $visit->getExpectedDeliveryDate(),
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
        
        // Get the related invoice for financial data (if exists)
        $invoice = null;
        $payments = collect();
        
        // Try to find invoice by lab_request_id or visit_id
        if ($visit->labRequest) {
            $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
        }
        
        if (!$invoice) {
            $invoice = \App\Models\Invoice::where('visit_id', $visit->id)->first();
        }
        
        if ($invoice) {
            $payments = \App\Models\Payment::where('invoice_id', $invoice->id)->get();
        }
        
        // Get current user who is printing the receipt
        $currentUser = auth()->user();
        $printedBy = $currentUser ? $currentUser->name : 'System';
        
        // Generate barcode for the lab number
        $barcodeData = null;
        $barcodeText = null;
        
        // Try different lab number sources for barcode
        if ($visit->labRequest && $visit->labRequest->full_lab_no) {
            $barcodeText = $visit->labRequest->full_lab_no;
        } elseif ($visit->labRequest && $visit->labRequest->lab_no) {
            $barcodeText = $visit->labRequest->lab_no;
        } elseif ($visit->patient && $visit->patient->lab) {
            $barcodeText = $visit->patient->lab;
        }
        
        if ($barcodeText) {
            try {
                // Generate base64 barcode image
                $barcodeData = $this->generateBase64Barcode($barcodeText);
            } catch (\Exception $e) {
                \Log::warning('Failed to generate barcode for receipt', [
                    'barcode_text' => $barcodeText,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Debug logging
        \Log::info('Receipt data debug', [
            'visit_id' => $visit->id,
            'lab_request_id' => $visit->labRequest ? $visit->labRequest->id : null,
            'lab_no' => $visit->labRequest ? $visit->labRequest->lab_no : null,
            'full_lab_no' => $visit->labRequest ? $visit->labRequest->full_lab_no : null,
            'patient_lab' => $visit->patient ? $visit->patient->lab : null,
            'invoice_found' => $invoice ? true : false,
            'invoice_total' => $invoice ? $invoice->total : null,
            'invoice_paid' => $invoice ? $invoice->paid : null,
            'invoice_remaining' => $invoice ? $invoice->remaining : null,
            'barcode_text' => $barcodeText,
            'barcode_generated' => $barcodeData ? true : false,
        ]);
        
        // Get patient age - calculate from birth_date if available, otherwise use age field
        $patientAge = $visit->patient->age;
        if (!$patientAge && $visit->patient->birth_date) {
            $patientAge = $visit->patient->birth_date->age;
        }
        
        // Get payment breakdown from visit metadata
        $metadata = json_decode($visit->metadata ?? '{}', true);
        $financialData = $metadata['financial_data'] ?? []; // Added to read financial_data
        $paymentDetails = $metadata['payment_details'] ?? [];
        
        // Build payment breakdown
        $paymentBreakdown = [];
        if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
            $paymentBreakdown['cash'] = $paymentDetails['amount_paid_cash'];
        }
        if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
            $paymentBreakdown['card'] = $paymentDetails['amount_paid_card'];
            $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
        }
        
        // If no breakdown exists but we have a payment method, create a simple breakdown
        if (empty($paymentBreakdown)) {
            $currentPaymentMethod = $this->getPaymentMethod($visit, $payments);
            $paidAmount = $invoice ? $invoice->amount_paid : ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0);
            
            if ($paidAmount > 0) {
                if ($currentPaymentMethod === 'cash') {
                    $paymentBreakdown['cash'] = $paidAmount;
                } else {
                    $paymentBreakdown['card'] = $paidAmount;
                    $paymentBreakdown['card_method'] = $currentPaymentMethod;
                }
            }
        }
        
        return response()->json([
            'visit' => $visit,
            'receipt_data' => [
                'receipt_number' => $visit->visit_number,
                'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
                'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'),
                'patient_name' => $visit->patient->name ?: 'N/A',
                'patient_age' => $patientAge ?: 'N/A',
                'patient_phone' => $visit->patient->phone ?: 'N/A',
                'tests' => $this->getTestsForReceipt($visit),
                'total_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->total_amount ?: 0)),
                'discount_amount' => $visit->discount_amount ?: 0,
                'final_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->final_amount ?: 0)),
                'upfront_payment' => $financialData['amount_paid'] ?? $this->calculatePaidAmount($visit, $invoice),
                'remaining_balance' => $financialData['remaining_balance'] ?? $this->calculateRemainingBalance($visit, $invoice),
                'payment_method' => $this->getPaymentMethod($visit, $payments),
                'billing_status' => $financialData['payment_status'] ?? $this->getPaymentStatus($invoice, $visit),
                'expected_delivery_date' => $visit->getExpectedDeliveryDate(),
                'barcode' => $barcodeData,
                'barcode_text' => $barcodeText ?: 'N/A',
                'check_in_by' => $visit->check_in_by ?: 'N/A',
                'check_in_at' => $visit->check_in_at ?: 'N/A',
                'payment_breakdown' => $paymentBreakdown,
                'visit_id' => $visit->id,
                'patient_credentials' => $visit->patient->getPortalCredentials(),
                'printed_by' => $printedBy,
                'printed_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Generate base64 barcode image
     */
    private function generateBase64Barcode($text)
    {
        try {
            $generator = new \Milon\Barcode\DNS1D();
            
            // Clean the text for barcode generation - keep hyphens for lab numbers
            $cleanText = str_replace(['_', ' '], '', $text);
            $alphanumericText = preg_replace('/[^A-Za-z0-9]/', '', $text);
            
            // Try different barcode types - prioritize SVG since it's more reliable
            $barcodeTypes = ['C128', 'C39', 'C39+', 'C93'];
            
            // Try SVG first (more reliable than PNG)
            foreach ($barcodeTypes as $type) {
                try {
                    // Try original text first
                    $barcodeSvg = $generator->getBarcodeSVG($text, $type, 2, 50);
                    if ($barcodeSvg && str_contains($barcodeSvg, '<svg')) {
                        \Log::info('Successfully generated SVG barcode with original text', [
                            'text' => $text,
                            'type' => $type
                        ]);
                        return $barcodeSvg; // Return SVG directly, not base64 encoded
                    }
                    
                    // Try with cleaned text
                    $barcodeSvg = $generator->getBarcodeSVG($cleanText, $type, 2, 50);
                    if ($barcodeSvg && str_contains($barcodeSvg, '<svg')) {
                        \Log::info('Successfully generated SVG barcode with cleaned text', [
                            'text' => $text,
                            'clean_text' => $cleanText,
                            'type' => $type
                        ]);
                        return $barcodeSvg; // Return SVG directly, not base64 encoded
                    }
                    
                    // Try with alphanumeric text
                    if (!empty($alphanumericText)) {
                        $barcodeSvg = $generator->getBarcodeSVG($alphanumericText, $type, 2, 50);
                        if ($barcodeSvg && str_contains($barcodeSvg, '<svg')) {
                            \Log::info('Successfully generated SVG barcode with alphanumeric text', [
                                'text' => $text,
                                'alphanumeric_text' => $alphanumericText,
                                'type' => $type
                            ]);
                            return $barcodeSvg; // Return SVG directly, not base64 encoded
                        }
                    }
                } catch (\Exception $e) {
                    \Log::debug('SVG barcode generation failed for type ' . $type, [
                        'text' => $text,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // If SVG fails, try PNG as fallback
            foreach ($barcodeTypes as $type) {
                try {
                    // Try to generate barcode as PNG
                    $barcodePng = $generator->getBarcodePNG($text, $type, 2, 50);
                    
                    if ($barcodePng && strlen($barcodePng) > 100) { // Check if we got actual image data
                        // Validate PNG header
                        $pngHeader = substr($barcodePng, 0, 8);
                        if ($pngHeader === "\x89PNG\r\n\x1a\n") {
                            \Log::info('Successfully generated PNG barcode', [
                                'text' => $text,
                                'type' => $type,
                                'size' => strlen($barcodePng)
                            ]);
                            return base64_encode($barcodePng);
                        } else {
                            \Log::warning('PNG barcode has invalid header', [
                                'text' => $text,
                                'type' => $type,
                                'header' => bin2hex($pngHeader)
                            ]);
                        }
                    }
                    
                    // Try with cleaned text (keeping hyphens)
                    $barcodePng = $generator->getBarcodePNG($cleanText, $type, 2, 50);
                    if ($barcodePng && strlen($barcodePng) > 100) {
                        $pngHeader = substr($barcodePng, 0, 8);
                        if ($pngHeader === "\x89PNG\r\n\x1a\n") {
                            \Log::info('Successfully generated PNG barcode with cleaned text', [
                                'text' => $text,
                                'clean_text' => $cleanText,
                                'type' => $type,
                                'size' => strlen($barcodePng)
                            ]);
                            return base64_encode($barcodePng);
                        }
                    }
                    
                    // Try with alphanumeric only text
                    if (!empty($alphanumericText)) {
                        $barcodePng = $generator->getBarcodePNG($alphanumericText, $type, 2, 50);
                        if ($barcodePng && strlen($barcodePng) > 100) {
                            $pngHeader = substr($barcodePng, 0, 8);
                            if ($pngHeader === "\x89PNG\r\n\x1a\n") {
                                \Log::info('Successfully generated PNG barcode with alphanumeric text', [
                                    'text' => $text,
                                    'alphanumeric_text' => $alphanumericText,
                                    'type' => $type,
                                    'size' => strlen($barcodePng)
                                ]);
                                return base64_encode($barcodePng);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::debug('PNG barcode generation failed for type ' . $type, [
                        'text' => $text,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            \Log::warning('Failed to generate barcode with any method', [
                'text' => $text,
                'clean_text' => $cleanText
            ]);
            
            return null;
        } catch (\Exception $e) {
            \Log::error('Failed to generate base64 barcode', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get tests for receipt - handles both visitTests and patient registration sample_type
     */
    private function getTestsForReceipt($visit)
    {
        // First try to get from visitTests (for CheckIn visits)
        if ($visit->visitTests && $visit->visitTests->count() > 0) {
            return $visit->visitTests->map(function ($visitTest) {
                return [
                    'name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                    'category' => $visitTest->testCategory ? $visitTest->testCategory->name : 'Unknown',
                    'price' => $visitTest->final_price ?: $visitTest->price,
                ];
            });
        }
        
        // If no visitTests, try to get from patient registration metadata
        $metadata = json_decode($visit->metadata ?? '{}', true);
        $patientData = $metadata['patient_data'] ?? [];
        
        // Also check lab request metadata for patient registration data
        $labRequestData = [];
        if ($visit->labRequest && is_object($visit->labRequest) && !is_array($visit->labRequest) && !($visit->labRequest instanceof \Illuminate\Database\Eloquent\Collection) && isset($visit->labRequest->metadata)) {
            $labRequestMetadata = json_decode($visit->labRequest->metadata, true);
            $labRequestData = $labRequestMetadata['patient_data'] ?? [];
        }
        
        // Check both visit metadata and lab request metadata for sample_type
        $sampleType = $patientData['sample_type'] ?? $labRequestData['sample_type'] ?? $metadata['sample_type'] ?? null;
        
        if (!empty($sampleType)) {
            $totalAmount = $visit->total_amount ?? $visit->final_amount ?? 0;
            
            return collect([
                [
                    'name' => $sampleType,
                    'category' => 'Sample Type',
                    'price' => $totalAmount,
                ]
            ]);
        }
        
        // Fallback - return empty collection
        return collect([]);
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
    private function getPaymentStatus($invoice, $visit = null)
    {
        if ($invoice) {
            if ($invoice->balance <= 0) {
                return 'Paid';
            } elseif ($invoice->amount_paid > 0) {
                return 'Partial';
            } else {
                return 'Pending';
            }
        }
        
        // If no invoice, use patient payment data
        if ($visit && $visit->patient) {
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
            $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
            $remainingAmount = $totalAmount - $paidAmount;
            
            if ($remainingAmount <= 0) {
                return 'Paid';
            } elseif ($paidAmount > 0) {
                return 'Partial';
            } else {
                return 'Pending';
            }
        }
        
        return 'Pending';
    }

    public function getSampleLabel($visitId)
    {
        try {
            \Log::info('=== SAMPLE LABEL REQUEST START ===');
            \Log::info('Getting sample label for visit ID: ' . $visitId);
            
            $visit = Visit::with(['patient', 'labRequest'])->findOrFail($visitId);
            
            \Log::info('Visit found: ' . $visit->id . ', Patient: ' . $visit->patient->name);
            \Log::info('Visit metadata: ' . json_encode($visit->metadata));
            \Log::info('Lab request exists: ' . ($visit->labRequest ? 'Yes' : 'No'));
            if ($visit->labRequest) {
                \Log::info('Lab request metadata: ' . json_encode($visit->labRequest->metadata));
            }
            
            // Get patient registration data from metadata
            $metadata = json_decode($visit->metadata ?? '{}', true);
            $patientData = $metadata['patient_data'] ?? [];
            
            // Also check lab request metadata for patient registration data
            $labRequestData = [];
            if ($visit->labRequest && is_object($visit->labRequest) && !is_array($visit->labRequest) && !($visit->labRequest instanceof \Illuminate\Database\Eloquent\Collection) && isset($visit->labRequest->metadata)) {
                $labRequestMetadata = json_decode($visit->labRequest->metadata, true);
                $labRequestData = $labRequestMetadata['patient_data'] ?? [];
            }
            
            // Get sample information from patient registration (check both sources)
            $numberOfSamples = intval($patientData['number_of_samples'] ?? $labRequestData['number_of_samples'] ?? 1);
            $sampleType = $patientData['sample_type'] ?? $labRequestData['sample_type'] ?? 'Pathology';
            $sampleSize = $patientData['sample_size'] ?? $labRequestData['sample_size'] ?? 'صغيرة جدا';
            
            \Log::info('=== SAMPLE LABEL DEBUG ===');
            \Log::info('Visit ID: ' . $visit->id);
            \Log::info('Visit metadata: ' . json_encode($metadata));
            \Log::info('Patient data from visit metadata: ' . json_encode($patientData));
            \Log::info('Patient data from lab request metadata: ' . json_encode($labRequestData));
            \Log::info('Raw number_of_samples from patientData: ' . ($patientData['number_of_samples'] ?? 'NOT_FOUND'));
            \Log::info('Raw number_of_samples from labRequestData: ' . ($labRequestData['number_of_samples'] ?? 'NOT_FOUND'));
            \Log::info('Final numberOfSamples: ' . $numberOfSamples);
            \Log::info('Sample info - Number: ' . $numberOfSamples . ', Type: ' . $sampleType . ', Size: ' . $sampleSize);
            
            // Debug: Check if we have the correct data
            if ($numberOfSamples <= 0) {
                \Log::warning('Number of samples is 0 or negative, using default value of 1');
                $numberOfSamples = 1;
            }
            
            // Generate sample labels based on number of samples
            $sampleLabels = [];
            for ($i = 1; $i <= $numberOfSamples; $i++) {
                // Generate sample ID like "2025-19-S1", "2025-19-S2", etc.
                $sampleId = $visit->labRequest ? $visit->labRequest->full_lab_no . '-S' . $i : 'SAMPLE-' . $i;
                
                // Generate barcode for the sample ID
                $barcodeImage = $this->barcodeService->generateReceiptBarcode($sampleId);
                
                $sampleLabels[] = [
                    'sample_id' => $sampleId,
                    'sample_type' => $sampleType,
                    'sample_size' => $sampleSize,
                    'patient_name' => $visit->patient->name,
                    'patient_id' => $visit->patient->id,
                    'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?? 'N/A'),
                    'sample_date' => $visit->visit_date,
                    'sample_time' => $visit->visit_time ? date('H:i', strtotime($visit->visit_time)) : date('H:i'),
                    'barcode' => $barcodeImage,
                    'barcode_text' => $sampleId,
                ];
            }
            
            \Log::info('Generated ' . count($sampleLabels) . ' sample labels');
            \Log::info('Sample labels data: ' . json_encode($sampleLabels));
            
            $response = [
                'sample_data' => [
                    'patient_name' => $visit->patient->name,
                    'patient_age' => $visit->patient->age,
                    'visit_id' => $visit->id,
                    'visit_date' => $visit->visit_date,
                    'receipt_number' => $visit->visit_number,
                    'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?? 'N/A'),
                    'sample_labels' => $sampleLabels,
                ],
            ];
            
            \Log::info('=== SAMPLE LABEL RESPONSE ===');
            \Log::info('Response: ' . json_encode($response));
            
            return response()->json($response);
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
                'expected_delivery_date' => $visit->getExpectedDeliveryDate(),
                'barcode' => $visit->labRequest ? $this->barcodeService->generateReceiptBarcode($visit->labRequest->full_lab_no) : ($visit->barcode ?: 'N/A'),
                'check_in_by' => auth()->user()->name,
                'check_in_at' => now(),
                'patient_credentials' => $visit->patient->getPortalCredentials(),
                'visit_id' => $visit->id,
                'invoice_id' => $invoice->id,
            ],
        ]);
    }

    public function generateFinalPaymentReceipt($visitId)
    {
        try {
            \Log::info('Starting final payment receipt generation for visit: ' . $visitId);
            
            // Load visit with basic data
            $visit = Visit::findOrFail($visitId);
            \Log::info('Visit found: ' . $visit->id);
            
            // Load patient data safely
            $patientName = 'N/A';
            $patientPhone = 'N/A';
            $patientAge = 'N/A';
            $patientCredentials = null;
            
            try {
                $visit->load('patient');
                if ($visit->patient) {
                    $patientName = $visit->patient->name ?? 'N/A';
                    $patientPhone = $visit->patient->phone ?? 'N/A';
                    $patientAge = $visit->patient->age ?? 'N/A';
                    
                    // Get patient credentials
                    try {
                        if (method_exists($visit->patient, 'getPortalCredentials')) {
                            $patientCredentials = $visit->patient->getPortalCredentials();
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error getting patient credentials: ' . $e->getMessage());
                    }
                }
                \Log::info('Patient data loaded');
            } catch (\Exception $e) {
                \Log::error('Error loading patient: ' . $e->getMessage());
            }
            
            // Load payment data directly from visit model
            $totalPaid = floatval($visit->upfront_payment ?? 0);
            $totalAmount = floatval($visit->total_amount ?? 0);
            $remainingBalance = floatval($visit->remaining_balance ?? 0);
            $discountAmount = floatval($visit->discount_amount ?? 0);
            $finalAmount = floatval($visit->final_amount ?? $totalAmount);
            $paymentMethod = $visit->payment_method ?? 'cash';
            $paymentBreakdown = ['cash' => 0, 'card' => 0];
            
            // Use the same payment breakdown logic as the normal receipt
            try {
                // Get payment breakdown from visit metadata (same as getUnpaidInvoiceReceiptData)
                $metadata = json_decode($visit->metadata ?? '{}', true);
                $financialData = $metadata['financial_data'] ?? [];
                $paymentDetails = $metadata['payment_details'] ?? [];
                
                // Build payment breakdown (same logic as normal receipt)
                if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
                    $paymentBreakdown['cash'] = floatval($paymentDetails['amount_paid_cash']);
                }
                if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
                    $paymentBreakdown['card'] = floatval($paymentDetails['amount_paid_card']);
                    $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
                }
                
                // If no breakdown exists but we have a payment method, create a simple breakdown
                if (empty($paymentBreakdown)) {
                    $currentPaymentMethod = $visit->payment_method ?? 'cash';
                    $paidAmount = $visit->upfront_payment ?? 0;
                    
                    if ($paidAmount > 0) {
                        if ($currentPaymentMethod === 'cash') {
                            $paymentBreakdown['cash'] = $paidAmount;
                        } else {
                            $paymentBreakdown['card'] = $paidAmount;
                            $paymentBreakdown['card_method'] = $currentPaymentMethod;
                        }
                    }
                }
                
                \Log::info('Payment breakdown loaded (same as normal receipt): ' . json_encode($paymentBreakdown));
            } catch (\Exception $e) {
                \Log::error('Error loading payment breakdown: ' . $e->getMessage());
                // Fallback: put all paid amount as cash
                $paymentBreakdown['cash'] = $totalPaid;
            }
            
            \Log::info('Visit payment data loaded - Total: ' . $totalAmount . ', Paid: ' . $totalPaid . ', Remaining: ' . $remainingBalance);
            \Log::info('Visit details: ' . json_encode([
                'visit_id' => $visit->id,
                'total_amount' => $visit->total_amount,
                'upfront_payment' => $visit->upfront_payment,
                'remaining_balance' => $visit->remaining_balance,
                'payment_method' => $visit->payment_method,
                'billing_status' => $visit->billing_status
            ]));
            \Log::info('Payment breakdown for final receipt: ' . json_encode($paymentBreakdown));
            
            // Load tests data
            $tests = [];
            try {
                $visit->load('labRequest.tests');
                if ($visit->labRequest && $visit->labRequest->tests) {
                    foreach ($visit->labRequest->tests as $test) {
                        $tests[] = [
                            'name' => $test->name ?? 'N/A',
                            'category' => $test->category ?? 'N/A',
                            'price' => floatval($test->price ?? 0)
                        ];
                    }
                }
                
                // If no tests found, try alternative approach
                if (empty($tests)) {
                    try {
                        $visit->load('visitTests.labTest');
                        if ($visit->visitTests) {
                            foreach ($visit->visitTests as $visitTest) {
                                if ($visitTest->labTest) {
                                    $tests[] = [
                                        'name' => $visitTest->labTest->name ?? 'N/A',
                                        'category' => $visitTest->labTest->category ?? 'N/A',
                                        'price' => floatval($visitTest->labTest->price ?? 0)
                                    ];
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error loading tests via visitTests: ' . $e->getMessage());
                    }
                }
                
                \Log::info('Tests data loaded: ' . count($tests) . ' tests');
            } catch (\Exception $e) {
                \Log::error('Error loading tests: ' . $e->getMessage());
            }
            
            // Load background image
            $backgroundImage = null;
            try {
                $backgroundPath = public_path('templete/background.jpg');
                if (file_exists($backgroundPath)) {
                    $backgroundImage = base64_encode(file_get_contents($backgroundPath));
                    \Log::info('Background image loaded');
                }
            } catch (\Exception $e) {
                \Log::error('Error loading background image: ' . $e->getMessage());
            }
            
            // Prepare receipt data for the template
            $receiptData = [
                'receipt_number' => $visit->receipt_number ?? 'VIS' . now()->format('Ymd') . str_pad($visitId, 4, '0', STR_PAD_LEFT),
                'patient_name' => $patientName,
                'patient_age' => $patientAge,
                'patient_phone' => $patientPhone,
                'date' => $visit->created_at ? $visit->created_at->format('Y-m-d') : now()->format('Y-m-d'),
                'lab_number' => $visit->id ?? $visitId,
                'doctor_name' => $visit->doctor_name ?? 'N/A',
                'visit_id' => $visitId,
                'tests' => $tests,
                'total_amount' => floatval($totalAmount),
                'discount_amount' => floatval($discountAmount),
                'final_amount' => floatval($finalAmount),
                'upfront_payment' => floatval($totalPaid), // Total paid amount
                'remaining_balance' => floatval($remainingBalance),
                'billing_status' => 'PAYMENT COMPLETED',
                'payment_breakdown' => [
                    'cash' => floatval($paymentBreakdown['cash']),
                    'card' => floatval($paymentBreakdown['card']),
                    'card_method' => $paymentBreakdown['card_method'] ?? 'Card'
                ],
                'payment_method' => $paymentMethod,
                'printed_by' => auth()->user()->name ?? 'System',
                'printed_at' => now()->format('Y-m-d H:i:s'),
                'patient_credentials' => $patientCredentials
            ];
            
            // Create MPDF with same settings as normal receipt
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8', 
                'format' => 'A4', 
                'orientation' => 'P',
                'margin_left' => 0, 
                'margin_right' => 0, 
                'margin_top' => 0, 
                'margin_bottom' => 0,
                'tempDir' => storage_path('app/temp'),
                'default_font_size' => 12, 
                'default_font' => 'dejavusans',
                'autoPageBreak' => false,
                'setAutoTopMargin' => false,
                'setAutoBottomMargin' => false
            ]);
            
            \Log::info('MPDF initialized');
            
            // Use the same template as normal receipt but with "FINAL PAYMENT RECEIPT" title
            $html = view('receipts.unpaid_invoice_receipt', [
                'receiptData' => $receiptData,
                'backgroundImage' => $backgroundImage,
                'isFinalPayment' => true // Flag to change title to "FINAL PAYMENT RECEIPT"
            ])->render();
            
            \Log::info('Receipt data for final payment: ' . json_encode($receiptData));
            \Log::info('HTML prepared, length: ' . strlen($html));
            
            $mpdf->WriteHTML($html);
            $filename = 'final_payment_receipt_' . $visitId . '.pdf';
            $pdfContent = $mpdf->Output('', 'S');
            
            \Log::info("PDF generated successfully. Size: " . strlen($pdfContent) . " bytes");
            
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Final Payment Receipt PDF generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'PDF generation failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function searchPatients(Request $request)
    {
        $query = $request->get('query', '');
        
        if (strlen($query) < 2) {
            return response()->json(['patients' => []]);
        }

        $patients = Patient::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('whatsapp_number', 'like', "%{$query}%")
            ->orWhere('lab', 'like', "%{$query}%")
            ->orWhere('sender', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'phone', 'age', 'gender', 'sender', 'lab']);

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

    private function calculatePaidAmount($visit, $invoice = null)
    {
        if ($invoice) {
            return $invoice->amount_paid;
        }
        
        // Get payment data from visit metadata
        $metadata = json_decode($visit->metadata ?? '{}', true);
        $paymentDetails = $metadata['payment_details'] ?? [];
        $patientData = $metadata['patient_data'] ?? [];
        
        // Get paid amount from metadata first
        $paidAmount = $paymentDetails['total_paid'] ?? $patientData['amount_paid'] ?? 0;
        
        // If still 0, calculate from payment breakdown
        if ($paidAmount == 0) {
            $cashPaid = $paymentDetails['amount_paid_cash'] ?? $patientData['amount_paid_cash'] ?? 0;
            $cardPaid = $paymentDetails['amount_paid_card'] ?? $patientData['amount_paid_card'] ?? 0;
            $paidAmount = $cashPaid + $cardPaid;
        }
        
        // Final fallback to direct fields
        if ($paidAmount == 0) {
            $paidAmount = $visit->patient->amount_paid ?? $visit->upfront_payment ?? 0;
        }
        
        return $paidAmount;
    }

    private function calculateRemainingBalance($visit, $invoice = null)
    {
        if ($invoice) {
            return $invoice->balance;
        }
        
        $totalAmount = $visit->final_amount ?? $visit->total_amount ?? 0;
        $paidAmount = $this->calculatePaidAmount($visit, $invoice);
        
        return $totalAmount - $paidAmount;
    }

    /**
     * Generate A4 receipt PDF
     */
    public function generateUnpaidInvoiceReceipt($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.testCategory', 'labRequest'])->findOrFail($visitId);
        
        // Read background image and convert to base64
        $backgroundImagePath = public_path('templete/background.jpg');
        $backgroundImage = null;
        
        if (file_exists($backgroundImagePath)) {
            $imageData = file_get_contents($backgroundImagePath);
            $backgroundImage = base64_encode($imageData);
        }
        
        // Get the related invoice for financial data (if exists)
        $invoice = null;
        $payments = collect();
        
        // Try to find invoice by lab_request_id or visit_id
        if ($visit->labRequest) {
            $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
        }
        
        if (!$invoice) {
            $invoice = \App\Models\Invoice::where('visit_id', $visit->id)->first();
        }
        
        if ($invoice) {
            $payments = \App\Models\Payment::where('invoice_id', $invoice->id)->get();
        }
        
        // Get current user who is printing the receipt
        $currentUser = auth()->user();
        $printedBy = $currentUser ? $currentUser->name : 'System';
        
        // Generate barcode for the lab number
        $barcodeData = null;
        $barcodeText = null;
        
        // Try different lab number sources for barcode
        if ($visit->labRequest && $visit->labRequest->full_lab_no) {
            $barcodeText = $visit->labRequest->full_lab_no;
        } elseif ($visit->labRequest && $visit->labRequest->lab_no) {
            $barcodeText = $visit->labRequest->lab_no;
        } elseif ($visit->patient && $visit->patient->lab) {
            $barcodeText = $visit->patient->lab;
        }
        
        if ($barcodeText) {
            try {
                // Generate base64 barcode image
                $barcodeData = $this->generateBase64Barcode($barcodeText);
            } catch (\Exception $e) {
                \Log::warning('Failed to generate barcode for receipt', [
                    'barcode_text' => $barcodeText,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Get patient age - calculate from birth_date if available, otherwise use age field
        $patientAge = $visit->patient->age;
        if (!$patientAge && $visit->patient->birth_date) {
            $patientAge = $visit->patient->birth_date->age;
        }
        
        // Get payment breakdown from visit metadata
        $metadata = json_decode($visit->metadata ?? '{}', true);
        $financialData = $metadata['financial_data'] ?? [];
        $paymentDetails = $metadata['payment_details'] ?? [];
        
        // Build payment breakdown
        $paymentBreakdown = [];
        if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
            $paymentBreakdown['cash'] = $paymentDetails['amount_paid_cash'];
        }
        if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
            $paymentBreakdown['card'] = $paymentDetails['amount_paid_card'];
            $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
        }
        
        // If no breakdown exists but we have a payment method, create a simple breakdown
        if (empty($paymentBreakdown)) {
            $currentPaymentMethod = $this->getPaymentMethod($visit, $payments);
            $paidAmount = $invoice ? $invoice->amount_paid : ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0);
            
            if ($paidAmount > 0) {
                if ($currentPaymentMethod === 'cash') {
                    $paymentBreakdown['cash'] = $paidAmount;
                } else {
                    $paymentBreakdown['card'] = $paidAmount;
                    $paymentBreakdown['card_method'] = $currentPaymentMethod;
                }
            }
        }
        
        $receiptData = [
            'receipt_number' => $visit->visit_number,
            'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
            'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'),
            'patient_name' => $visit->patient->name ?: 'N/A',
            'patient_age' => $patientAge ?: 'N/A',
            'patient_phone' => $visit->patient->phone ?: 'N/A',
            'tests' => $this->getTestsForReceipt($visit),
            'total_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->total_amount ?: 0)),
            'discount_amount' => $visit->discount_amount ?: 0,
            'final_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->final_amount ?: 0)),
            'upfront_payment' => $financialData['amount_paid'] ?? $this->calculatePaidAmount($visit, $invoice),
            'remaining_balance' => $financialData['remaining_balance'] ?? $this->calculateRemainingBalance($visit, $invoice),
            'payment_method' => $this->getPaymentMethod($visit, $payments),
            'billing_status' => $financialData['payment_status'] ?? $this->getPaymentStatus($invoice, $visit),
            'expected_delivery_date' => $visit->getExpectedDeliveryDate(),
            'barcode' => $barcodeData,
            'barcode_text' => $barcodeText ?: 'N/A',
            'check_in_by' => $visit->check_in_by ?: 'N/A',
            'check_in_at' => $visit->check_in_at ?: 'N/A',
            'payment_breakdown' => $paymentBreakdown,
            'visit_id' => $visit->id,
            'patient_credentials' => $visit->patient->getPortalCredentials(),
            'printed_by' => $printedBy,
            'printed_at' => now()->format('Y-m-d H:i:s'),
            'doctor' => 'عهدة الأورام',
            'doctor_name' => 'عمرو عبد العزيز عبد السيد',
        ];
        
        // Debug: Log the receipt data
        \Log::info('Unpaid Invoice Receipt Data:', $receiptData);
        
        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8', 
                'format' => 'A4', 
                'orientation' => 'P',
                'margin_left' => 15, 
                'margin_right' => 15, 
                'margin_top' => 15, 
                'margin_bottom' => 15,
                'tempDir' => storage_path('app/temp'),
                'default_font_size' => 11, 
                'default_font' => 'dejavusans',
                'autoPageBreak' => false,
                'setAutoTopMargin' => false,
                'setAutoBottomMargin' => false
            ]);
            
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            $mpdf->showImageErrors = false;
            
            // Render the view
            $html = view('receipts.unpaid_invoice_receipt', [
                'receiptData' => $receiptData,
                'backgroundImage' => $backgroundImage,
            ])->render();
            
            // Log HTML length for debugging
            \Log::info('HTML length: ' . strlen($html) . ' characters');
            
            $mpdf->WriteHTML($html);
            $filename = 'receipt_' . $visit->visit_number . '.pdf';
            $pdfContent = $mpdf->Output('', 'S');
            
            \Log::info("Receipt PDF generated successfully. Size: " . strlen($pdfContent) . " bytes");
        } catch (\Exception $e) {
            \Log::error('Unpaid Invoice Receipt PDF generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'PDF generation failed', 'message' => $e->getMessage()], 500);
        }
        
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

    public function getUnpaidInvoiceReceiptData($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.testCategory', 'labRequest'])->findOrFail($visitId);
        
        // Get the related invoice for financial data (if exists)
        $invoice = null;
        $payments = collect();
        
        // Try to find invoice by lab_request_id or visit_id
        if ($visit->labRequest) {
            $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
        }
        
        if (!$invoice) {
            $invoice = \App\Models\Invoice::where('visit_id', $visit->id)->first();
        }
        
        if ($invoice) {
            $payments = \App\Models\Payment::where('invoice_id', $invoice->id)->get();
        }
        
        // Get current user who is printing the receipt
        $currentUser = auth()->user();
        $printedBy = $currentUser ? $currentUser->name : 'System';
        
        // Get patient age - calculate from birth_date if available, otherwise use age field
        $patientAge = $visit->patient->age;
        if (!$patientAge && $visit->patient->birth_date) {
            $patientAge = $visit->patient->birth_date->age;
        }
        
        // Get payment breakdown from visit metadata
        $metadata = json_decode($visit->metadata ?? '{}', true);
        $financialData = $metadata['financial_data'] ?? [];
        $paymentDetails = $metadata['payment_details'] ?? [];
        
        // Build payment breakdown
        $paymentBreakdown = [];
        if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
            $paymentBreakdown['cash'] = $paymentDetails['amount_paid_cash'];
        }
        if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
            $paymentBreakdown['card'] = $paymentDetails['amount_paid_card'];
            $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
        }
        
        // If no breakdown exists but we have a payment method, create a simple breakdown
        if (empty($paymentBreakdown)) {
            $currentPaymentMethod = $this->getPaymentMethod($visit, $payments);
            $paidAmount = $invoice ? $invoice->amount_paid : ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0);
            
            if ($paidAmount > 0) {
                if ($currentPaymentMethod === 'cash') {
                    $paymentBreakdown['cash'] = $paidAmount;
                } else {
                    $paymentBreakdown['card'] = $paidAmount;
                    $paymentBreakdown['card_method'] = $currentPaymentMethod;
                }
            }
        }
        
        $receiptData = [
            'receipt_number' => $visit->visit_number,
            'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
            'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'),
            'patient_name' => $visit->patient->name ?: 'N/A',
            'patient_age' => $patientAge ?: 'N/A',
            'patient_phone' => $visit->patient->phone ?: 'N/A',
            'tests' => $this->getTestsForReceipt($visit),
            'total_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->total_amount ?: 0)),
            'discount_amount' => $visit->discount_amount ?: 0,
            'final_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->final_amount ?: 0)),
            'upfront_payment' => $financialData['amount_paid'] ?? $this->calculatePaidAmount($visit, $invoice),
            'remaining_balance' => $financialData['remaining_balance'] ?? $this->calculateRemainingBalance($visit, $invoice),
            'payment_method' => $this->getPaymentMethod($visit, $payments),
            'billing_status' => $financialData['payment_status'] ?? $this->getPaymentStatus($invoice, $visit),
            'expected_delivery_date' => $visit->getExpectedDeliveryDate(),
            'barcode_text' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
            'check_in_by' => $visit->check_in_by ?: 'N/A',
            'check_in_at' => $visit->check_in_at ?: 'N/A',
            'payment_breakdown' => $paymentBreakdown,
            'visit_id' => $visit->id,
            'patient_credentials' => $visit->patient->getPortalCredentials(),
            'printed_by' => $printedBy,
            'printed_at' => now()->format('Y-m-d H:i:s'),
        ];
        
        return response()->json([
            'visit' => $visit,
            'receipt_data' => $receiptData,
        ]);
    }

    public function generateA4Receipt($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.testCategory', 'labRequest'])->findOrFail($visitId);
        
        // Get the related invoice for financial data (if exists)
        $invoice = null;
        $payments = collect();
        
        // Try to find invoice by lab_request_id or visit_id
        if ($visit->labRequest) {
            $invoice = \App\Models\Invoice::where('lab_request_id', $visit->labRequest->id)->first();
        }
        
        if (!$invoice) {
            $invoice = \App\Models\Invoice::where('visit_id', $visit->id)->first();
        }
        
        if ($invoice) {
            $payments = \App\Models\Payment::where('invoice_id', $invoice->id)->get();
        }
        
        // Get current user who is printing the receipt
        $currentUser = auth()->user();
        $printedBy = $currentUser ? $currentUser->name : 'System';
        
        // Get patient age - calculate from birth_date if available, otherwise use age field
        $patientAge = $visit->patient->age;
        if (!$patientAge && $visit->patient->birth_date) {
            $patientAge = $visit->patient->birth_date->age;
        }
        
        // Get payment breakdown from visit metadata
        $metadata = json_decode($visit->metadata ?? '{}', true);
        $financialData = $metadata['financial_data'] ?? [];
        $paymentDetails = $metadata['payment_details'] ?? [];
        
        // Build payment breakdown
        $paymentBreakdown = [];
        if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
            $paymentBreakdown['cash'] = $paymentDetails['amount_paid_cash'];
        }
        if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
            $paymentBreakdown['card'] = $paymentDetails['amount_paid_card'];
            $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
        }
        
        // If no breakdown exists but we have a payment method, create a simple breakdown
        if (empty($paymentBreakdown)) {
            $currentPaymentMethod = $this->getPaymentMethod($visit, $payments);
            $paidAmount = $invoice ? $invoice->amount_paid : ($visit->patient->amount_paid ?? $visit->upfront_payment ?? 0);
            
            if ($paidAmount > 0) {
                if ($currentPaymentMethod === 'cash') {
                    $paymentBreakdown['cash'] = $paidAmount;
                } else {
                    $paymentBreakdown['card'] = $paidAmount;
                    $paymentBreakdown['card_method'] = $currentPaymentMethod;
                }
            }
        }
        
        // Prepare receipt data
        $receiptData = [
            'receipt_number' => $visit->visit_number,
            'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
            'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'),
            'patient_name' => $visit->patient->name ?: 'N/A',
            'patient_age' => $patientAge ?: 'N/A',
            'patient_phone' => $visit->patient->phone ?: 'N/A',
            'tests' => $this->getTestsForReceipt($visit),
            'total_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->total_amount ?: 0)),
            'discount_amount' => $visit->discount_amount ?: 0,
            'final_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->final_amount ?: 0)),
            'upfront_payment' => $financialData['amount_paid'] ?? $this->calculatePaidAmount($visit, $invoice),
            'remaining_balance' => $financialData['remaining_balance'] ?? $this->calculateRemainingBalance($visit, $invoice),
            'payment_method' => $this->getPaymentMethod($visit, $payments),
            'billing_status' => $financialData['payment_status'] ?? $this->getPaymentStatus($invoice, $visit),
            'barcode_text' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
            'check_in_by' => $visit->check_in_by ?: 'N/A',
            'check_in_at' => $visit->check_in_at ?: 'N/A',
            'payment_breakdown' => $paymentBreakdown,
            'visit_id' => $visit->id,
            'printed_by' => $printedBy,
            'printed_at' => now()->format('Y-m-d H:i:s'),
        ];
        
        try {
            // Configure MPDF for A4 format
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
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
            
            $html = view('receipts.pathology_lab_receipt_a4', [
                'receiptData' => $receiptData,
            ])->render();
            
            $mpdf->WriteHTML($html);
            
            $filename = 'pathology_receipt_' . ($visit->labRequest->lab_no ?? $visit->visit_number) . '.pdf';
            
            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');
            
            \Log::info("A4 Receipt PDF generated successfully. Size: " . strlen($pdfContent) . " bytes");
            
        } catch (\Exception $e) {
            \Log::error('A4 Receipt PDF generation error: ' . $e->getMessage());
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
} 