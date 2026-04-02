<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lab;
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
use App\Services\CatalogVisitTestWriter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
    protected $labNoGenerator;
    protected $barcodeGenerator;
    protected $barcodeService;

    public function __construct(
        LabNoGenerator $labNoGenerator,
        BarcodeGenerator $barcodeGenerator,
        BarcodeService $barcodeService,
        protected CatalogVisitTestWriter $catalogVisitTestWriter
    ) {
        $this->labNoGenerator = $labNoGenerator;
        $this->barcodeGenerator = $barcodeGenerator;
        $this->barcodeService = $barcodeService;
    }

    /**
     * Convert date to Arabic day name
     * 
     * @param string $date Date string (Y-m-d format)
     * @return string Arabic day name
     */
    private function getArabicDayName($date)
    {
        if (!$date) {
            return 'السبت';
        }
        
        try {
            $carbon = \Carbon\Carbon::parse($date);
            $dayOfWeek = $carbon->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
            
            $arabicDays = [
                0 => 'الأحد',    // Sunday
                1 => 'الاثنين',  // Monday
                2 => 'الثلاثاء',  // Tuesday
                3 => 'الأربعاء',  // Wednesday
                4 => 'الخميس',    // Thursday
                5 => 'الجمعة',    // Friday
                6 => 'السبت',     // Saturday
            ];
            
            return $arabicDays[$dayOfWeek] ?? 'السبت';
        } catch (\Exception $e) {
            \Log::warning('Failed to parse date for day name: ' . $e->getMessage(), ['date' => $date]);
            return 'السبت';
        }
    }

    /**
     * Helper method to safely parse metadata from visit
     */
    private function parseMetadata($visit)
    {
        if (!$visit->metadata) {
            return [];
        }
        
        if (is_array($visit->metadata)) {
            return $visit->metadata;
        }
        
        if (is_string($visit->metadata)) {
            try {
                return json_decode($visit->metadata, true) ?? [];
            } catch (\Exception $e) {
                \Log::error('Failed to decode metadata: ' . $e->getMessage());
                return [];
            }
        }
        
        return [];
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
            $labId = $this->currentLabId() ?? 1;
            $username = Patient::generateUsername($request->name, $labId);
            $password = Patient::generatePassword();

            // Create patient
            $patient = Patient::create([
                ...$validator->validated(),
                'lab_id' => $labId,
                'username' => $username,
                'password' => Hash::make($password),
            ]);

            // Also create credentials in patient_credentials table for consistency
            PatientCredential::create([
                'patient_id' => $patient->id,
                'lab_id' => $labId,
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
        $labId = $this->currentLabId() ?? 1;

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patient,id',
            'catalog_tests' => 'nullable|array',
            'catalog_tests.*.offering_id' => [
                'required',
                'integer',
                Rule::exists('lab_test_offerings', 'id')->where(fn ($q) => $q->where('lab_id', $labId)->where('is_active', true)),
            ],
            'catalog_tests.*.test_name' => 'nullable|string|max:255',
            'catalog_packages' => 'nullable|array',
            'catalog_packages.*.package_id' => [
                'required',
                'integer',
                Rule::exists('lab_packages', 'id')->where(fn ($q) => $q->where('lab_id', $labId)->where('is_active', true)),
            ],
            'catalog_packages.*.price' => 'nullable|numeric|min:0',
            'upfront_payment' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,Fawry,InstaPay,VodafoneCash,Other',
            'notes' => 'nullable|string',
            'expected_delivery_date' => 'nullable|date',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_policy_number' => 'nullable|string|max:255',
            'insurance_claim_number' => 'nullable|string|max:255',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $catalogTests = $request->input('catalog_tests', []);
        $catalogPackages = $request->input('catalog_packages', []);
        if (count($catalogTests) + count($catalogPackages) < 1) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['catalog_tests' => ['Provide at least one catalog test (offering) or package.']],
            ], 422);
        }

        try {
            foreach ($catalogPackages as $pkgRow) {
                $pid = (int) ($pkgRow['package_id'] ?? 0);
                if ($pid > 0) {
                    $this->catalogVisitTestWriter->assertPackageResolvableForLab($labId, $pid);
                }
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $patient = Patient::findOrFail($request->patient_id);
        $catalogSubtotalPreview = $this->catalogVisitTestWriter->previewSubtotal($labId, $catalogTests, $catalogPackages);
        if ($catalogSubtotalPreview <= 0) {
            return response()->json([
                'message' => 'No billable catalog lines could be resolved for this lab.',
            ], 422);
        }

        $requestDiscount = (float) ($request->discount_amount ?? 0);
        $insuranceDiscount = $patient->getInsuranceDiscountAmount($catalogSubtotalPreview);
        $totalDiscount = max($requestDiscount, $insuranceDiscount);
        $finalAmountPreview = max(0, round($catalogSubtotalPreview - $totalDiscount, 2));

        $minimumUpfront = ($finalAmountPreview * 50) / 100;
        if ((float) $request->upfront_payment < $minimumUpfront) {
            return response()->json([
                'message' => 'Upfront payment must be at least 50% of total amount',
                'minimum_required' => $minimumUpfront,
                'total_amount' => $finalAmountPreview,
            ], 422);
        }

        DB::beginTransaction();
        try {
            \Log::info('Starting visit creation for patient: ' . $request->patient_id);
            \Log::info('Request data: ' . json_encode($request->all()));
            $existingCredentials = $patient->getPortalCredentials();
            \Log::info('Patient ' . $patient->id . ' existing credentials: ' . json_encode($existingCredentials));

            $visitNumber = Visit::generateVisitNumber();
            \Log::info('Generated visit number: ' . $visitNumber);

            $currentShift = \App\Models\Shift::where('staff_id', auth()->id())
                ->where('status', 'open')
                ->whereDate('opened_at', today())
                ->first();

            $upfront = (float) $request->upfront_payment;

            $visit = Visit::create([
                'lab_id' => $labId,
                'patient_id' => $patient->id,
                'visit_number' => $visitNumber,
                'visit_date' => now()->toDateString(),
                'visit_time' => now(),
                'total_amount' => 0,
                'discount_amount' => 0,
                'final_amount' => 0,
                'upfront_payment' => $upfront,
                'payment_method' => $request->payment_method,
                'status' => 'registered',
                'remarks' => $this->buildRemarks($request),
                'expected_delivery_date' => $request->expected_delivery_date,
                'shift_id' => $currentShift?->id,
                'processed_by_staff' => auth()->id(),
            ]);

            \Log::info('Visit created successfully with ID: ' . $visit->id);

            try {
                $created = $this->catalogVisitTestWriter->write($visit, $labId, $catalogTests, $catalogPackages);
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                return response()->json(['message' => $e->getMessage()], 422);
            }

            if ($created < 1) {
                DB::rollBack();

                return response()->json([
                    'message' => 'No visit test lines were created from the catalog selection.',
                ], 422);
            }

            $this->catalogVisitTestWriter->syncVisitTotalsFromVisitTests($visit->fresh());

            $visit->refresh();
            $catalogSubtotal = (float) $visit->total_amount;

            $insuranceDiscount = $patient->getInsuranceDiscountAmount($catalogSubtotal);
            $totalDiscount = max($requestDiscount, $insuranceDiscount);
            $finalAmount = max(0, round($catalogSubtotal - $totalDiscount, 2));

            $minimumUpfront = ($finalAmount * 50) / 100;
            if ($upfront < $minimumUpfront) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Upfront payment must be at least 50% of total amount',
                    'minimum_required' => $minimumUpfront,
                    'total_amount' => $finalAmount,
                ], 422);
            }

            $billing = $upfront >= $finalAmount && $finalAmount > 0 ? 'paid' : ($upfront > 0 ? 'partial' : 'unpaid');
            $remainingBalance = max(0, round($finalAmount - $upfront, 2));

            $md = $visit->metadata;
            if (!is_array($md)) {
                $md = [];
            }
            $fd = array_merge($md['financial_data'] ?? [], [
                'catalog_subtotal' => $catalogSubtotal,
                'visit_discount_amount' => $totalDiscount,
                'total_amount' => $catalogSubtotal,
                'final_amount' => $finalAmount,
                'amount_paid' => $upfront,
                'remaining_balance' => $remainingBalance,
                'payment_status' => $billing,
            ]);

            $visit->update([
                'total_amount' => $catalogSubtotal,
                'discount_amount' => $totalDiscount,
                'final_amount' => $finalAmount,
                'remaining_balance' => $remainingBalance,
                'billing_status' => $billing,
                'metadata' => array_merge($md, ['financial_data' => $fd]),
            ]);

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
                        'lab_id' => $labId,
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
                    $this->barcodeGenerator->generateForLabRequest($labRequest->full_lab_no, $labRequest->lab_id);
                    
                    \Log::info('New lab request created for patient: ' . $patient->id . ', Lab No: ' . $labNo);
                }

                // Link the visit to the lab request
                $visit->update(['lab_request_id' => $labRequest->id]);
                \Log::info('Linked visit ' . $visit->id . ' to lab request ' . $labRequest->id);

                // Create invoice for this visit
                $invoice = Invoice::create([
                    'lab_id' => $labId,
                    'lab' => $labRequest->full_lab_no,
                    'total' => $finalAmount,
                    'paid' => $request->upfront_payment,
                    'remaining' => $remainingBalance,
                    'lab_request_id' => $labRequest->id,
                    'shift_id' => $currentShift?->id,
                ]);
                
                \Log::info('Invoice created successfully with ID: ' . $invoice->id);

                $visit->load(['visitTests.labTest.category']);

                // Add samples for each catalog line on the visit
                foreach ($visit->visitTests as $visitTestRow) {
                    $testName = $visitTestRow->test_name;
                    $testCode = $visitTestRow->labTest?->category?->code ?? 'unknown';
                    $categoryName = $visitTestRow->labTest?->category?->name ?? 'Unknown';

                    \Log::info('Creating sample for test: ' . $testName . ', category: ' . $categoryName . ', code: ' . $testCode);

                    $existingSample = $labRequest->samples()
                        ->where('sample_type', $testName)
                        ->where('sample_id', $testCode)
                        ->first();

                    if (!$existingSample) {
                        $sampleId = $this->barcodeService->generateNextSampleId($labRequest->lab_no);
                        $barcode = $this->barcodeService->generateBarcode($labRequest->lab_no, $sampleId);

                        $labRequest->samples()->create([
                            'sample_id' => $sampleId,
                            'sample_type' => $testName,
                            'status' => 'collected',
                            'collection_date' => now(),
                            'notes' => "Test: {$testName} ({$categoryName})",
                        ]);

                        \Log::info('Created sample with barcode: ' . $barcode . ' for test: ' . $testName);
                    }
                }

                \Log::info('Samples added to lab request: ' . $labRequest->id);

                // Create initial report automatically for the lab request (one report for all tests)
                $existingReport = \App\Models\Report::where('lab_request_id', $labRequest->id)->first();

                if (!$existingReport) {
                    $testNames = $visit->visitTests->map(function ($visitTest) {
                        return $visitTest->test_name;
                    })->unique()->implode(', ');
                    
                    $reportTitle = $testNames ? 'Lab Report - ' . $testNames : 'Lab Report - ' . $visit->visit_number;
                    
                    \App\Models\Report::create([
                        'lab_request_id' => $labRequest->id,
                        'title' => $reportTitle,
                        'content' => 'Report generated automatically for ' . $patient->name,
                        'status' => 'pending',
                        'generated_by' => auth()->id() ?? 1,
                        'generated_at' => now(),
                        'created_at' => now(),
                    ]);
                    
                    \Log::info('Report created automatically for lab request: ' . $labRequest->id . ' with ' . $visit->visitTests->count() . ' tests');
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
                            'name' => $visitTest->test_name,
                            'category' => $visitTest->testCategory ? $visitTest->testCategory->name : ($visitTest->labTest?->category?->name ?? 'Unknown'),
                            'price' => $visitTest->unitPriceForBilling(),
                        ];
                    }),
                    'total_amount' => $catalogSubtotal,
                    'discount_amount' => $totalDiscount,
                    'final_amount' => $finalAmount,
                    'upfront_payment' => $request->upfront_payment,
                    'remaining_balance' => $remainingBalance,
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
                $barcodeData = $this->normalizeReceiptBarcodeForHtml(
                    $this->generateBase64Barcode($barcodeText)
                );
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
        $metadata = $this->parseMetadata($visit);
        $financialData = $metadata['financial_data'] ?? []; // Added to read financial_data
        $paymentDetails = $metadata['payment_details'] ?? [];
        
        // Build payment breakdown - ensure it matches total_paid
        $paymentBreakdown = [];
        $totalPaidFromBreakdown = 0;
        
        // Get the actual total paid amount
        $paidAmount = $financialData['amount_paid'] ?? $this->calculatePaidAmount($visit, $invoice);
        
        if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
            $cashAmount = floatval($paymentDetails['amount_paid_cash']);
            $totalPaidFromBreakdown += $cashAmount;
            $paymentBreakdown['cash'] = $cashAmount;
        }
        if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
            $cardAmount = floatval($paymentDetails['amount_paid_card']);
            $totalPaidFromBreakdown += $cardAmount;
            $paymentBreakdown['card'] = $cardAmount;
            $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
        }
        
        // If breakdown exists but doesn't match total_paid, normalize it
        if (!empty($paymentBreakdown) && $totalPaidFromBreakdown > 0 && abs($totalPaidFromBreakdown - $paidAmount) > 0.01) {
            // Scale down the breakdown to match total_paid
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
            $currentPaymentMethod = $this->getPaymentMethod($visit, $payments);
            
            if ($currentPaymentMethod === 'cash' || !$currentPaymentMethod) {
                $paymentBreakdown['cash'] = $paidAmount;
            } else {
                $paymentBreakdown['card'] = $paidAmount;
                $paymentBreakdown['card_method'] = $currentPaymentMethod;
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
     * Remove XML/DOCTYPE from SVG barcodes so Arabic RTL layout does not corrupt markup in mPDF.
     */
    private function normalizeReceiptBarcodeForHtml(?string $barcode): ?string
    {
        if ($barcode === null || $barcode === '' || ! str_contains($barcode, '<svg')) {
            return $barcode;
        }
        $out = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $barcode);
        $out = preg_replace('/<!DOCTYPE[^>]*>\s*/i', '', $out ?? '');

        return $out !== null ? trim($out) : $barcode;
    }

    /**
     * Normalize metadata / relations for receipt Blade (escaping expects strings).
     */
    private function scalarForReceiptView($value, string $default = ''): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            if (isset($value['name']) && (is_string($value['name']) || is_numeric($value['name']))) {
                return (string) $value['name'];
            }

            return $default;
        }
        if (is_object($value)) {
            if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                $n = $value->getAttribute('name');
                if ($n !== null && $n !== '') {
                    return (string) $n;
                }
            }
            if (isset($value->name)) {
                return (string) $value->name;
            }
        }

        return $default;
    }

    /**
     * Lab-specific receipt header/footer text for mPDF / Blade.
     */
    private function buildLabReceiptPresentation(Visit $visit): array
    {
        $visit->loadMissing('lab');
        $lab = $visit->lab;
        if (!$lab && $visit->lab_id) {
            $lab = Lab::find($visit->lab_id);
        }
        $raw = $lab ? $lab->receiptBranding() : Lab::fallbackReceiptBranding();

        return [
            'labBranding' => [
                'display_name' => $raw['display_name'],
                'tagline' => $raw['tagline'],
                'address' => $raw['address'],
                'phone' => $raw['phone'],
                'email' => $raw['email'],
                'vat' => $raw['vat'],
                'website' => $raw['website'],
                'doc_label' => $raw['doc_label'],
                'currency_label' => $raw['currency_label'],
            ],
        ];
    }

    /**
     * Get tests for receipt - handles both visitTests and patient registration sample_type
     */
    private function getTestsForReceipt($visit)
    {
        // First try to get from visitTests (for CheckIn visits)
        if ($visit->visitTests && $visit->visitTests->count() > 0) {
            return $visit->visitTests->map(function ($visitTest) {
                $categoryName = optional($visitTest->labTest?->category)->name;

                return [
                    'name' => $visitTest->custom_test_name ?: ($visitTest->labTest ? $visitTest->labTest->name : 'Unknown Test'),
                    'category' => $categoryName ?: 'Unknown',
                    'price' => $visitTest->unitPriceForBilling(),
                ];
            });
        }
        
        // If no visitTests, try to get from patient registration metadata
        $metadata = $this->parseMetadata($visit);
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
            $metadata = $this->parseMetadata($visit);
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
            // Get attendance date and delivery date from patient or visit metadata
            $attendanceDate = null;
            $deliveryDate = null;
            
            // Try to get attendance date from multiple sources
            if (!empty($patientData['attendance_date'])) {
                try {
                    $attendanceDate = \Carbon\Carbon::parse($patientData['attendance_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse attendance_date from patient_data: ' . $e->getMessage());
                }
            }
            if (!$attendanceDate && $visit->patient->attendance_date) {
                try {
                    $attendanceDate = \Carbon\Carbon::parse($visit->patient->attendance_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse attendance_date from patient: ' . $e->getMessage());
                }
            }
            if (!$attendanceDate && $visit->visit_date) {
                try {
                    $attendanceDate = \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse visit_date: ' . $e->getMessage());
                }
            }
            if (!$attendanceDate) {
                $attendanceDate = now()->format('Y-m-d');
            }
            
            // Try to get delivery date from multiple sources
            if (!empty($patientData['delivery_date'])) {
                try {
                    $deliveryDate = \Carbon\Carbon::parse($patientData['delivery_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse delivery_date from patient_data: ' . $e->getMessage());
                }
            }
            if (!$deliveryDate && $visit->patient->delivery_date) {
                try {
                    $deliveryDate = \Carbon\Carbon::parse($visit->patient->delivery_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse delivery_date from patient: ' . $e->getMessage());
                }
            }
            if (!$deliveryDate && $visit->expected_delivery_date) {
                try {
                    $deliveryDate = \Carbon\Carbon::parse($visit->expected_delivery_date)->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse expected_delivery_date: ' . $e->getMessage());
                }
            }
            if (!$deliveryDate) {
                $deliveryDate = now()->addDays(1)->format('Y-m-d');
            }
            
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
                    'attendance_date' => $attendanceDate,
                    'delivery_date' => $deliveryDate,
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
                        'price' => $visitTest->unitPriceForBilling(),
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
        \Log::info('generateFinalPaymentReceipt called for visit ID: ' . $visitId);
        
        // Check authentication - if not authenticated, try token from request
        $request = request();
        if (!auth()->user() && $request->has('token')) {
            try {
                $token = $request->get('token');
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($personalAccessToken && $personalAccessToken->tokenable) {
                    auth()->setUser($personalAccessToken->tokenable);
                    \Log::info('Authenticated user via token parameter: ' . $personalAccessToken->tokenable->id);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to authenticate via token parameter: ' . $e->getMessage());
            }
        }
        
        // Log current authentication status
        $currentUser = auth()->user();
        \Log::info('Current authenticated user: ' . ($currentUser ? $currentUser->id . ' (' . $currentUser->email . ')' : 'None'));
        
        // If still no authentication, try to continue without auth for public receipts
        if (!$currentUser) {
            \Log::warning('No authenticated user found for PDF generation, proceeding without authentication for visit: ' . $visitId);
        }
        
        try {
            $visit = Visit::with(['patient', 'visitTests.labTest.category', 'labRequest', 'lab'])->findOrFail($visitId);
            \Log::info('Visit found: ' . $visit->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Visit not found in generateFinalPaymentReceipt: ' . $e->getMessage());
            return response()->json(['error' => 'Visit not found', 'message' => $e->getMessage()], 404, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading visit in generateFinalPaymentReceipt: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading visit', 'message' => $e->getMessage()], 500, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
        
        try {
            // Read background image and convert to base64
            $backgroundImagePath = public_path('templete/b2.jpg');
            $backgroundImage = null;
            
            if (file_exists($backgroundImagePath)) {
                $imageData = file_get_contents($backgroundImagePath);
                $backgroundImage = base64_encode($imageData);
            }

            $receiptPresentation = $this->buildLabReceiptPresentation($visit);
            $labBranding = $receiptPresentation['labBranding'];
            
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
                    $barcodeData = $this->normalizeReceiptBarcodeForHtml(
                        $this->generateBase64Barcode($barcodeText)
                    );
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
            // Handle metadata - it might be an array (from cast) or a JSON string
            $metadata = [];
            if ($visit->metadata) {
                if (is_array($visit->metadata)) {
                    $metadata = $visit->metadata;
                } elseif (is_string($visit->metadata)) {
                    try {
                        $metadata = json_decode($visit->metadata, true) ?? [];
                    } catch (\Exception $e) {
                        \Log::error('Failed to decode metadata in generateFinalPaymentReceipt: ' . $e->getMessage());
                        $metadata = [];
                    }
                }
            }
            $financialData = $metadata['financial_data'] ?? [];
            $paymentDetails = $metadata['payment_details'] ?? [];
            
            // Build payment breakdown - ensure it matches total_paid
            $paymentBreakdown = [];
            $totalPaidFromBreakdown = 0;
            
            // Get the actual total paid amount (will be calculated later, but we need it here)
            $paidAmount = $this->calculatePaidAmount($visit, $invoice);
            
            if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
                $cashAmount = floatval($paymentDetails['amount_paid_cash']);
                $totalPaidFromBreakdown += $cashAmount;
                $paymentBreakdown['cash'] = $cashAmount;
            }
            if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
                $cardAmount = floatval($paymentDetails['amount_paid_card']);
                $totalPaidFromBreakdown += $cardAmount;
                $paymentBreakdown['card'] = $cardAmount;
                $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
            }
            
            // If breakdown exists but doesn't match total_paid, normalize it
            if (!empty($paymentBreakdown) && $totalPaidFromBreakdown > 0 && abs($totalPaidFromBreakdown - $paidAmount) > 0.01) {
                // Scale down the breakdown to match total_paid
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
                $currentPaymentMethod = $this->getPaymentMethod($visit, $payments);
                
                if ($currentPaymentMethod === 'cash' || !$currentPaymentMethod) {
                    $paymentBreakdown['cash'] = $paidAmount;
                } else {
                    $paymentBreakdown['card'] = $paidAmount;
                    $paymentBreakdown['card_method'] = $currentPaymentMethod;
                }
            }
            
            // Get attendance date and delivery date from patient or visit metadata
            $patientData = $metadata['patient_data'] ?? [];
            $attendanceDate = $patientData['attendance_date'] ?? ($visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'));
            $deliveryDate = $patientData['delivery_date'] ?? ($visit->expected_delivery_date ? \Carbon\Carbon::parse($visit->expected_delivery_date)->format('Y-m-d') : now()->addDays(1)->format('Y-m-d'));
            
            // Also check patient record directly
            if (!$attendanceDate && $visit->patient->attendance_date) {
                $attendanceDate = \Carbon\Carbon::parse($visit->patient->attendance_date)->format('Y-m-d');
            }
            if (!$deliveryDate && $visit->patient->delivery_date) {
                $deliveryDate = \Carbon\Carbon::parse($visit->patient->delivery_date)->format('Y-m-d');
            }
            
            // Calculate financial values with proper fallbacks
            // Priority: patient.total_amount (same as UnpaidInvoicesController) > visit fields > invoice fields > metadata
            $totalAmount = floatval($visit->patient->total_amount ?? 0);
            if ($totalAmount == 0) {
                $totalAmount = floatval($visit->final_amount ?? $visit->total_amount ?? 0);
            }
            if ($totalAmount == 0 && $invoice) {
                $totalAmount = floatval($invoice->total_amount ?? $invoice->total ?? 0);
            }
            if ($totalAmount == 0 && isset($financialData['total_amount'])) {
                $totalAmount = floatval($financialData['total_amount']);
            }
            if ($totalAmount == 0 && isset($patientData['total_amount'])) {
                $totalAmount = floatval($patientData['total_amount']);
            }
            
            $finalAmount = floatval($visit->patient->total_amount ?? 0);
            if ($finalAmount == 0) {
                $finalAmount = floatval($visit->final_amount ?? $visit->total_amount ?? 0);
            }
            if ($finalAmount == 0) {
                $finalAmount = $totalAmount; // Use total_amount if final_amount is not set
            }
            if ($finalAmount == 0 && $invoice) {
                $finalAmount = floatval($invoice->total_amount ?? $invoice->total ?? 0);
            }
            
            $discountAmount = floatval($visit->discount_amount ?? 0);
            
            $paidAmount = $this->calculatePaidAmount($visit, $invoice);
            $remainingBalance = max(0, $finalAmount - $paidAmount);
            
            \Log::info('Financial calculations for final payment receipt visit ' . $visit->id . ':', [
                'visit_total_amount' => $visit->total_amount,
                'visit_final_amount' => $visit->final_amount,
                'visit_upfront_payment' => $visit->upfront_payment,
                'calculated_total_amount' => $totalAmount,
                'calculated_final_amount' => $finalAmount,
                'calculated_paid_amount' => $paidAmount,
                'calculated_remaining_balance' => $remainingBalance,
                'invoice_total' => $invoice ? ($invoice->total_amount ?? $invoice->total) : null,
            ]);
            
            // Get additional patient data from metadata
            $organization = $this->scalarForReceiptView($patientData['organization'] ?? null);
            if ($organization === '' && $visit->patient) {
                $organization = $this->scalarForReceiptView($visit->patient->organization);
            }
            if ($organization === '' && $visit->patient) {
                $organization = $this->scalarForReceiptView($visit->patient->getAttributes()['organization_id'] ?? null);
            }
            $sampleType = $this->scalarForReceiptView($patientData['sample_type'] ?? null, '');
            if ($sampleType === '' && $visit->patient && $visit->patient->sample_type) {
                $sampleType = $this->scalarForReceiptView($visit->patient->sample_type, '');
            }
            $sampleSize = $this->scalarForReceiptView($patientData['sample_size'] ?? null, '');
            if ($sampleSize === '' && $visit->patient && $visit->patient->sample_size) {
                $sampleSize = $this->scalarForReceiptView($visit->patient->sample_size, '');
            }
            $numberOfSamples = $this->scalarForReceiptView($patientData['number_of_samples'] ?? null, '');
            if ($numberOfSamples === '' && $visit->patient && $visit->patient->number_of_samples !== null && $visit->patient->number_of_samples !== '') {
                $numberOfSamples = $this->scalarForReceiptView($visit->patient->number_of_samples, '');
            }
            $medicalHistory = $this->scalarForReceiptView($patientData['medical_history'] ?? $visit->patient->medical_history ?? null, '');
            $previousTests = $this->scalarForReceiptView($patientData['previous_tests'] ?? $visit->patient->previous_tests ?? null, '');
            $patientGender = $visit->patient->gender ?? '';
            
            // Calculate day names from actual dates (fallback to stored values if dates not available)
            $attendanceDay = $patientData['attendance_day'] ?? $this->getArabicDayName($attendanceDate);
            $deliveryDay = $patientData['delivery_day'] ?? $this->getArabicDayName($deliveryDate);
            
            $doctorName = $this->scalarForReceiptView($patientData['doctor'] ?? null);
            if ($doctorName === '') {
                $doctorName = $this->scalarForReceiptView($patientData['referring_doctor'] ?? null);
            }
            if ($doctorName === '' && $visit->patient) {
                $doctorName = $this->scalarForReceiptView($visit->patient->doctor);
            }
            if ($doctorName === '' && $visit->patient) {
                $doctorName = $this->scalarForReceiptView($visit->patient->getAttributes()['doctor_id'] ?? null);
            }
            
            $expectedDeliveryFinal = $visit->getExpectedDeliveryDate();
            $expectedDeliveryFinalStr = $expectedDeliveryFinal instanceof \Carbon\CarbonInterface
                ? $expectedDeliveryFinal->format('Y-m-d')
                : $this->scalarForReceiptView($expectedDeliveryFinal, '');

            $receiptData = [
                'receipt_number' => $visit->visit_number,
                'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
                'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'),
                'patient_name' => $visit->patient->name ?: 'N/A',
                'patient_age' => $this->scalarForReceiptView($patientAge, 'N/A') ?: 'N/A',
                'patient_phone' => $visit->patient->phone ?: 'N/A',
                'patient_gender' => $patientGender,
                'attendance_date' => $attendanceDate,
                'attendance_day' => $attendanceDay,
                'delivery_date' => $deliveryDate,
                'delivery_day' => $deliveryDay,
                'organization' => $organization,
                'sample_type' => $sampleType,
                'sample_size' => $sampleSize,
                'number_of_samples' => $numberOfSamples,
                'medical_history' => $medicalHistory,
                'previous_tests' => $previousTests,
                'referring_doctor' => $doctorName,
                'doctor_name' => $doctorName,
                'tests' => $this->getTestsForReceipt($visit),
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'upfront_payment' => $paidAmount,
                'remaining_balance' => $remainingBalance,
                'payment_method' => $this->getPaymentMethod($visit, $payments),
                'billing_status' => 'PAYMENT COMPLETED',
                'expected_delivery_date' => $expectedDeliveryFinalStr,
                'barcode' => $barcodeData,
                'barcode_text' => $barcodeText ?: 'N/A',
                'check_in_by' => $visit->check_in_by ?: 'N/A',
                'check_in_at' => $visit->check_in_at ?: 'N/A',
                'payment_breakdown' => $paymentBreakdown,
                'visit_id' => $visit->id,
                'patient_credentials' => $visit->patient->getPortalCredentials(),
                'printed_by' => $printedBy,
                'printed_at' => now()->format('Y-m-d H:i:s'),
            ];
            
            // Debug: Log the receipt data
            \Log::info('Final Payment Receipt Data:', $receiptData);
            
            try {
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8', 
                    'format' => [210, 130], // Reduced height to fit content better
                    'orientation' => 'P',
                    'margin_left' => 5, 
                    'margin_right' => 5, 
                    'margin_top' => 3, 
                    'margin_bottom' => 0,
                    'margin_header' => 0,
                    'margin_footer' => 0,
                    'tempDir' => storage_path('app/temp'),
                    'default_font_size' => 7, 
                    'default_font' => 'dejavusans',
                    'autoPageBreak' => false,
                    'setAutoTopMargin' => false,
                    'setAutoBottomMargin' => false,
                    'shrink_tables_to_fit' => 1,
                    'use_kwt' => true,
                    'keep_table_proportions' => true,
                    'useSubstitutions' => false,
                ]);
                
                $mpdf->autoScriptToLang = true;
                $mpdf->autoLangToFont = true;
                $mpdf->showImageErrors = false;
                
                // Disable automatic page breaks
                $mpdf->SetHTMLHeader('');
                $mpdf->SetHTMLFooter('');
                
                // Render the view
                $html = view('receipts.unpaid_invoice_receipt', [
                    'receiptData' => $receiptData,
                    'backgroundImage' => $backgroundImage,
                    'labBranding' => $labBranding,
                ])->render();
                
                // Log HTML length for debugging
                \Log::info('HTML length: ' . strlen($html) . ' characters');
                
                // Write HTML with strict page break control
                $mpdf->WriteHTML($html, 0);
                
                $filename = 'final_payment_receipt_' . ($visit->visit_number ?? $visit->id) . '.pdf';
                $pdfContent = $mpdf->Output('', 'S');
                
                \Log::info("Final Payment Receipt PDF generated successfully. Size: " . strlen($pdfContent) . " bytes");
                
                // Create response with binary content and CORS headers
                $response = response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                    'Content-Length' => strlen($pdfContent),
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Expose-Headers' => 'Content-Type, Content-Disposition, Content-Length',
                ]);
                
                // Ensure headers are set using headers->set() as well (for compatibility)
                $response->headers->set('Access-Control-Allow-Origin', '*', true);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS', true);
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin', true);
                $response->headers->set('Access-Control-Allow-Credentials', 'true', true);
                $response->headers->set('Access-Control-Expose-Headers', 'Content-Type, Content-Disposition, Content-Length', true);
                
                return $response;
            } catch (\Exception $e) {
                \Log::error('Error generating final payment receipt: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['error' => 'Failed to generate receipt', 'message' => $e->getMessage()], 500, [
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                    'Access-Control-Allow-Credentials' => 'true',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Final Payment Receipt PDF generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'PDF generation failed', 'message' => $e->getMessage()], 500, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
    }

    public function searchPatients(Request $request)
    {
        $query = trim((string) $request->get('query', ''));

        if (strlen($query) < 2) {
            return response()->json(['patients' => []]);
        }

        $labId = $this->currentLabId();

        $patientIdsFromLabNumbers = collect();
        if ($labId) {
            $patientIdsFromLabNumbers = LabRequest::withoutGlobalScope('lab')
                ->where('lab_id', $labId)
                ->where(function ($q) use ($query) {
                    $q->where('lab_no', 'like', "%{$query}%")
                        ->orWhereRaw('CONCAT(lab_no, COALESCE(suffix, \'\')) LIKE ?', ["%{$query}%"])
                        ->orWhereRaw('REPLACE(lab_no, \'-\', \'\') LIKE ?', ['%'.str_replace('-', '', $query).'%'])
                        ->orWhereRaw('REPLACE(CONCAT(lab_no, COALESCE(suffix, \'\')), \'-\', \'\') LIKE ?', ['%'.str_replace('-', '', $query).'%']);
                })
                ->pluck('patient_id')
                ->filter()
                ->unique()
                ->values();
        }

        $patients = Patient::where(function ($outer) use ($query, $patientIdsFromLabNumbers) {
            $outer->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('whatsapp_number', 'like', "%{$query}%")
                    ->orWhere('lab', 'like', "%{$query}%")
                    ->orWhere('sender', 'like', "%{$query}%");
                if (ctype_digit($query)) {
                    $q->orWhere('id', (int) $query);
                }
            });
            if ($patientIdsFromLabNumbers->isNotEmpty()) {
                $outer->orWhereIn('id', $patientIdsFromLabNumbers);
            }
        })
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
        $labId = $this->currentLabId() ?? 1;

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patient,id',
            'catalog_tests' => 'nullable|array',
            'catalog_tests.*.offering_id' => [
                'required',
                'integer',
                Rule::exists('lab_test_offerings', 'id')->where(fn ($q) => $q->where('lab_id', $labId)->where('is_active', true)),
            ],
            'catalog_tests.*.test_name' => 'nullable|string|max:255',
            'catalog_packages' => 'nullable|array',
            'catalog_packages.*.package_id' => [
                'required',
                'integer',
                Rule::exists('lab_packages', 'id')->where(fn ($q) => $q->where('lab_id', $labId)->where('is_active', true)),
            ],
            'catalog_packages.*.price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $catalogTests = $request->input('catalog_tests', []);
        $catalogPackages = $request->input('catalog_packages', []);
        if (count($catalogTests) + count($catalogPackages) < 1) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['catalog_tests' => ['Provide at least one catalog test (offering) or package.']],
            ], 422);
        }

        try {
            foreach ($catalogPackages as $pkgRow) {
                $pid = (int) ($pkgRow['package_id'] ?? 0);
                if ($pid > 0) {
                    $this->catalogVisitTestWriter->assertPackageResolvableForLab($labId, $pid);
                }
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $patient = Patient::findOrFail($request->patient_id);

        $totalAmount = $this->catalogVisitTestWriter->previewSubtotal($labId, $catalogTests, $catalogPackages);
        if ($totalAmount <= 0) {
            return response()->json([
                'message' => 'No billable catalog lines could be resolved for this lab.',
            ], 422);
        }

        $insuranceDiscount = $patient->getInsuranceDiscountAmount($totalAmount);
        $finalAmount = max(0, round($totalAmount - $insuranceDiscount, 2));
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
            'catalog_tests' => $catalogTests,
            'catalog_packages' => $catalogPackages,
        ]);
    }

    public function getTestCategories()
    {
        $categories = \App\Models\TestCategory::active()
            ->orderByRaw('COALESCE(sort_order, 9999)')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'code', 'sort_order']);
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    private function calculatePaidAmount($visit, $invoice = null)
    {
        // Priority: patient.amount_paid (same as UnpaidInvoicesController) > visit upfront_payment > invoice > metadata
        // Use patient.amount_paid first (same as UnpaidInvoicesController)
        $patientPaid = floatval($visit->patient->amount_paid ?? 0);
        if ($patientPaid > 0) {
            return $patientPaid;
        }
        
        // Fallback to visit upfront_payment
        $visitPaid = floatval($visit->upfront_payment ?? 0);
        if ($visitPaid > 0) {
            return $visitPaid;
        }
        
        // Fallback to invoice
        if ($invoice) {
            $invoicePaid = floatval($invoice->amount_paid ?? $invoice->paid ?? 0);
            if ($invoicePaid > 0) {
                return $invoicePaid;
            }
        }
        
        // Get payment data from visit metadata
        $metadata = $this->parseMetadata($visit);
        $paymentDetails = $metadata['payment_details'] ?? [];
        $patientData = $metadata['patient_data'] ?? [];
        
        // Get paid amount from metadata
        $paidAmount = floatval($paymentDetails['total_paid'] ?? $patientData['amount_paid'] ?? 0);
        
        // If still 0, calculate from payment breakdown
        if ($paidAmount == 0) {
            $cashPaid = floatval($paymentDetails['amount_paid_cash'] ?? $patientData['amount_paid_cash'] ?? 0);
            $cardPaid = floatval($paymentDetails['amount_paid_card'] ?? $patientData['amount_paid_card'] ?? 0);
            $paidAmount = $cashPaid + $cardPaid;
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
     * Generate thermal-style receipt PDF (80 mm wide, roll length).
     */
    public function generateUnpaidInvoiceReceipt($visitId)
    {
        \Log::info('generateUnpaidInvoiceReceipt called for visit ID: ' . $visitId);
        
        // Check authentication - if not authenticated, try token from request
        $request = request();
        if (!auth()->user() && $request->has('token')) {
            try {
                $token = $request->get('token');
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($personalAccessToken && $personalAccessToken->tokenable) {
                    auth()->setUser($personalAccessToken->tokenable);
                    \Log::info('Authenticated user via token parameter: ' . $personalAccessToken->tokenable->id);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to authenticate via token parameter: ' . $e->getMessage());
            }
        }
        
        // Log current authentication status
        $currentUser = auth()->user();
        \Log::info('Current authenticated user: ' . ($currentUser ? $currentUser->id . ' (' . $currentUser->email . ')' : 'None'));
        
        // If still no authentication, try to continue without auth for public receipts
        if (!$currentUser) {
            \Log::warning('No authenticated user found for PDF generation, proceeding without authentication for visit: ' . $visitId);
        }
        
        try {
            $visit = Visit::with(['patient', 'visitTests.labTest.category', 'labRequest', 'lab'])->findOrFail($visitId);
            // Refresh the visit to ensure we have the latest data, especially metadata
            $visit->refresh();
            \Log::info('Visit found: ' . $visit->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Visit not found in generateUnpaidInvoiceReceipt: ' . $e->getMessage());
            return response()->json(['error' => 'Visit not found', 'message' => $e->getMessage()], 404, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading visit in generateUnpaidInvoiceReceipt: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading visit', 'message' => $e->getMessage()], 500, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
        
        try {
            // Read background image and convert to base64
            $backgroundImagePath = public_path('templete/b2.jpg');
            $backgroundImage = null;
            
            if (file_exists($backgroundImagePath)) {
                $imageData = file_get_contents($backgroundImagePath);
                $backgroundImage = base64_encode($imageData);
            }

            $receiptPresentation = $this->buildLabReceiptPresentation($visit);
            $labBranding = $receiptPresentation['labBranding'];
            
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
                    $barcodeData = $this->normalizeReceiptBarcodeForHtml(
                        $this->generateBase64Barcode($barcodeText)
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to generate barcode for receipt', [
                        'barcode_text' => $barcodeText,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Get payment breakdown from visit metadata
            // Handle metadata - it might be an array (from cast) or a JSON string
            $metadata = [];
            if ($visit->metadata) {
                if (is_array($visit->metadata)) {
                    $metadata = $visit->metadata;
                } elseif (is_string($visit->metadata)) {
                    try {
                        $metadata = json_decode($visit->metadata, true) ?? [];
                    } catch (\Exception $e) {
                        \Log::error('Failed to decode metadata in generateUnpaidInvoiceReceipt: ' . $e->getMessage());
                        $metadata = [];
                    }
                }
            }
            
            // Log metadata for debugging
            \Log::info('Metadata in generateUnpaidInvoiceReceipt for visit ' . $visit->id . ':', [
                'metadata_type' => gettype($visit->metadata),
                'metadata_keys' => is_array($metadata) ? array_keys($metadata) : 'not_array',
                'has_patient_data' => isset($metadata['patient_data']),
                'has_financial_data' => isset($metadata['financial_data']),
                'has_payment_details' => isset($metadata['payment_details']),
            ]);
            
            $financialData = $metadata['financial_data'] ?? [];
            $paymentDetails = $metadata['payment_details'] ?? [];
            $patientData = $metadata['patient_data'] ?? [];
            
            // Log patient data for debugging
            \Log::info('Patient data from metadata:', [
                'name' => $patientData['name'] ?? 'not_set',
                'age' => $patientData['age'] ?? 'not_set',
                'phone' => $patientData['phone'] ?? 'not_set',
                'organization' => $patientData['organization'] ?? 'not_set',
                'doctor' => $patientData['doctor'] ?? 'not_set',
            ]);
            
            // Get attendance date and delivery date from patient or visit metadata
            $patientData = $metadata['patient_data'] ?? [];
            
            // Get attendance date - priority: metadata > visit > patient
            $attendanceDate = null;
            if (isset($patientData['attendance_date']) && !empty($patientData['attendance_date'])) {
                try {
                    $attendanceDate = \Carbon\Carbon::parse($patientData['attendance_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse attendance_date from metadata: ' . $e->getMessage());
                }
            }
            if (!$attendanceDate && $visit->visit_date) {
                $attendanceDate = \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d');
            }
            if (!$attendanceDate && $visit->patient->attendance_date) {
                $attendanceDate = \Carbon\Carbon::parse($visit->patient->attendance_date)->format('Y-m-d');
            }
            if (!$attendanceDate) {
                $attendanceDate = now()->format('Y-m-d');
            }
            
            // Get delivery date - priority: metadata > visit > patient
            $deliveryDate = null;
            if (isset($patientData['delivery_date']) && !empty($patientData['delivery_date'])) {
                try {
                    $deliveryDate = \Carbon\Carbon::parse($patientData['delivery_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    \Log::warning('Failed to parse delivery_date from metadata: ' . $e->getMessage());
                }
            }
            if (!$deliveryDate && $visit->expected_delivery_date) {
                $deliveryDate = \Carbon\Carbon::parse($visit->expected_delivery_date)->format('Y-m-d');
            }
            if (!$deliveryDate && $visit->patient->delivery_date) {
                $deliveryDate = \Carbon\Carbon::parse($visit->patient->delivery_date)->format('Y-m-d');
            }
            if (!$deliveryDate) {
                $deliveryDate = now()->addDays(1)->format('Y-m-d');
            }
            
            // Calculate financial values with proper fallbacks
            // Priority: patient.total_amount (same as UnpaidInvoicesController) > visit fields > invoice fields > metadata
            $totalAmount = floatval($visit->patient->total_amount ?? 0);
            if ($totalAmount == 0) {
                $totalAmount = floatval($visit->final_amount ?? $visit->total_amount ?? 0);
            }
            if ($totalAmount == 0 && $invoice) {
                $totalAmount = floatval($invoice->total_amount ?? $invoice->total ?? 0);
            }
            if ($totalAmount == 0 && isset($financialData['total_amount'])) {
                $totalAmount = floatval($financialData['total_amount']);
            }
            if ($totalAmount == 0 && isset($patientData['total_amount'])) {
                $totalAmount = floatval($patientData['total_amount']);
            }
            
            $finalAmount = floatval($visit->patient->total_amount ?? 0);
            if ($finalAmount == 0) {
                $finalAmount = floatval($visit->final_amount ?? $visit->total_amount ?? 0);
            }
            if ($finalAmount == 0) {
                $finalAmount = $totalAmount; // Use total_amount if final_amount is not set
            }
            if ($finalAmount == 0 && $invoice) {
                $finalAmount = floatval($invoice->total_amount ?? $invoice->total ?? 0);
            }
            
            $discountAmount = floatval($visit->discount_amount ?? 0);
            
            // Get paid amount - priority: patient.amount_paid (same as UnpaidInvoicesController) > calculatePaidAmount
            $paidAmount = $this->calculatePaidAmount($visit, $invoice);
            
            $remainingBalance = max(0, $finalAmount - $paidAmount);
            
            // Build payment breakdown - ensure it matches total_paid
            $paymentBreakdown = [];
            $totalPaidFromBreakdown = 0;
            
            if (isset($paymentDetails['amount_paid_cash']) && $paymentDetails['amount_paid_cash'] > 0) {
                $cashAmount = floatval($paymentDetails['amount_paid_cash']);
                $totalPaidFromBreakdown += $cashAmount;
                $paymentBreakdown['cash'] = $cashAmount;
            }
            if (isset($paymentDetails['amount_paid_card']) && $paymentDetails['amount_paid_card'] > 0) {
                $cardAmount = floatval($paymentDetails['amount_paid_card']);
                $totalPaidFromBreakdown += $cardAmount;
                $paymentBreakdown['card'] = $cardAmount;
                $paymentBreakdown['card_method'] = $paymentDetails['additional_payment_method'] ?? 'Card';
            }
            
            // If breakdown exists but doesn't match total_paid, normalize it
            if (!empty($paymentBreakdown) && $totalPaidFromBreakdown > 0 && abs($totalPaidFromBreakdown - $paidAmount) > 0.01) {
                // Scale down the breakdown to match total_paid
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
                $currentPaymentMethod = $this->getPaymentMethod($visit, $payments);
                
                if ($currentPaymentMethod === 'cash' || !$currentPaymentMethod) {
                    $paymentBreakdown['cash'] = $paidAmount;
                } else {
                    $paymentBreakdown['card'] = $paidAmount;
                    $paymentBreakdown['card_method'] = $currentPaymentMethod;
                }
            }
            
            \Log::info('Financial calculations for visit ' . $visit->id . ':', [
                'visit_total_amount' => $visit->total_amount,
                'visit_final_amount' => $visit->final_amount,
                'visit_upfront_payment' => $visit->upfront_payment,
                'calculated_total_amount' => $totalAmount,
                'calculated_final_amount' => $finalAmount,
                'calculated_paid_amount' => $paidAmount,
                'calculated_remaining_balance' => $remainingBalance,
                'payment_breakdown' => $paymentBreakdown,
                'invoice_total' => $invoice ? ($invoice->total_amount ?? $invoice->total) : null,
            ]);
            
            // Get additional patient data from metadata (priority: metadata > visit->patient)
            // Get patient name, age, phone, and gender from metadata first, then fallback to visit->patient
            $patientName = $patientData['name'] ?? $visit->patient->name ?? 'N/A';
            
            // Get patient age - priority: metadata > visit->patient->age > calculate from birth_date
            $patientAge = null;
            if (isset($patientData['age']) && $patientData['age'] > 0) {
                $patientAge = $patientData['age'];
            } elseif ($visit->patient->age) {
                $patientAge = $visit->patient->age;
            } elseif ($visit->patient->birth_date) {
                $patientAge = $visit->patient->birth_date->age;
            }
            
            $patientPhone = $patientData['phone'] ?? $visit->patient->phone ?? 'N/A';
            $patientGender = $patientData['gender'] ?? $visit->patient->gender ?? '';
            
            $organization = $this->scalarForReceiptView($patientData['organization'] ?? null);
            if ($organization === '' && $visit->patient) {
                $organization = $this->scalarForReceiptView($visit->patient->organization);
            }
            if ($organization === '' && $visit->patient) {
                $organization = $this->scalarForReceiptView($visit->patient->getAttributes()['organization_id'] ?? null);
            }
            $sampleType = $this->scalarForReceiptView($patientData['sample_type'] ?? null, '');
            if ($sampleType === '' && $visit->patient && $visit->patient->sample_type) {
                $sampleType = $this->scalarForReceiptView($visit->patient->sample_type, '');
            }
            $sampleSize = $this->scalarForReceiptView($patientData['sample_size'] ?? null, '');
            if ($sampleSize === '' && $visit->patient && $visit->patient->sample_size) {
                $sampleSize = $this->scalarForReceiptView($visit->patient->sample_size, '');
            }
            $numberOfSamples = $this->scalarForReceiptView($patientData['number_of_samples'] ?? null, '');
            if ($numberOfSamples === '' && $visit->patient && $visit->patient->number_of_samples !== null && $visit->patient->number_of_samples !== '') {
                $numberOfSamples = $this->scalarForReceiptView($visit->patient->number_of_samples, '');
            }
            $medicalHistory = $this->scalarForReceiptView($patientData['medical_history'] ?? $visit->patient->medical_history ?? null, '');
            $previousTests = $this->scalarForReceiptView($patientData['previous_tests'] ?? $visit->patient->previous_tests ?? null, '');
            
            // Get lab number from metadata first
            $labNumber = $patientData['lab_number'] ?? ($visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?? 'N/A'));
            
            // Calculate day names from actual dates (fallback to stored values if dates not available)
            $attendanceDay = $patientData['attendance_day'] ?? $this->getArabicDayName($attendanceDate);
            $deliveryDay = $patientData['delivery_day'] ?? $this->getArabicDayName($deliveryDate);
            
            $doctorName = $this->scalarForReceiptView($patientData['doctor'] ?? null);
            if ($doctorName === '') {
                $doctorName = $this->scalarForReceiptView($patientData['referring_doctor'] ?? null);
            }
            if ($doctorName === '' && $visit->patient) {
                $doctorName = $this->scalarForReceiptView($visit->patient->doctor);
            }
            if ($doctorName === '' && $visit->patient) {
                $doctorName = $this->scalarForReceiptView($visit->patient->getAttributes()['doctor_id'] ?? null);
            }

            $expectedDelivery = $visit->getExpectedDeliveryDate();
            $expectedDeliveryStr = $expectedDelivery instanceof \Carbon\CarbonInterface
                ? $expectedDelivery->format('Y-m-d')
                : $this->scalarForReceiptView($expectedDelivery, '');
            
            $receiptData = [
                'receipt_number' => $visit->visit_number,
                'lab_number' => $labNumber,
                'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'),
                'patient_name' => $patientName,
                'patient_age' => $this->scalarForReceiptView($patientAge, 'N/A') ?: 'N/A',
                'patient_phone' => $patientPhone,
                'patient_gender' => $patientGender,
                'attendance_date' => $attendanceDate,
                'attendance_day' => $attendanceDay,
                'delivery_date' => $deliveryDate,
                'delivery_day' => $deliveryDay,
                'organization' => $organization,
                'sample_type' => $sampleType,
                'sample_size' => $sampleSize,
                'number_of_samples' => $numberOfSamples,
                'medical_history' => $medicalHistory,
                'previous_tests' => $previousTests,
                'referring_doctor' => $doctorName,
                'doctor_name' => $doctorName,
                'tests' => $this->getTestsForReceipt($visit),
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'upfront_payment' => $paidAmount,
                'remaining_balance' => $remainingBalance,
                'payment_method' => $this->getPaymentMethod($visit, $payments),
                'billing_status' => $financialData['payment_status'] ?? $this->getPaymentStatus($invoice, $visit),
                'expected_delivery_date' => $expectedDeliveryStr,
                'barcode' => $barcodeData,
                'barcode_text' => $barcodeText ?: 'N/A',
                'check_in_by' => $visit->check_in_by ?: 'N/A',
                'check_in_at' => $visit->check_in_at ?: 'N/A',
                'payment_breakdown' => $paymentBreakdown,
                'visit_id' => $visit->id,
                'patient_credentials' => $visit->patient->getPortalCredentials(),
                'printed_by' => $printedBy,
                'printed_at' => now()->format('Y-m-d H:i:s'),
            ];
            
            // Debug: Log the receipt data
            \Log::info('Unpaid Invoice Receipt Data:', $receiptData);
            
            try {
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => [80, 200],
                    'orientation' => 'P',
                    'margin_left' => 3,
                    'margin_right' => 5,
                    'margin_top' => 3,
                    'margin_bottom' => 3,
                    'margin_header' => 0,
                    'margin_footer' => 0,
                    'tempDir' => storage_path('app/temp'),
                    'default_font_size' => 7,
                    'default_font' => 'dejavusans',
                    'autoPageBreak' => true,
                    'setAutoTopMargin' => false,
                    'setAutoBottomMargin' => false,
                    'ignore_invalid_utf8' => true,
                    'allow_charset_conversion' => false,
                    'shrink_tables_to_fit' => 1,
                    'use_kwt' => true,
                    'keep_table_proportions' => true,
                    'useSubstitutions' => false,
                ]);
                
                $mpdf->autoScriptToLang = true;
                $mpdf->autoLangToFont = true;
                $mpdf->showImageErrors = false;
                
                // Disable automatic page breaks
                $mpdf->SetHTMLHeader('');
                $mpdf->SetHTMLFooter('');
                
                // Render the view
                $html = view('receipts.unpaid_invoice_receipt', [
                    'receiptData' => $receiptData,
                    'backgroundImage' => $backgroundImage,
                    'labBranding' => $labBranding,
                ])->render();
                
                // Log HTML length for debugging
                \Log::info('HTML length: ' . strlen($html) . ' characters');
                
                // Write HTML with strict page break control
                $mpdf->WriteHTML($html, 0);
                
                $filename = 'receipt_' . ($visit->visit_number ?? $visit->id) . '.pdf';
                $pdfContent = $mpdf->Output('', 'S');
                
                \Log::info("Receipt PDF generated successfully. Size: " . strlen($pdfContent) . " bytes");
                
                // Create response with binary content and CORS headers
                $response = response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                    'Content-Length' => strlen($pdfContent),
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Expose-Headers' => 'Content-Type, Content-Disposition, Content-Length',
                ]);
                
                // Ensure headers are set using headers->set() as well (for compatibility)
                $response->headers->set('Access-Control-Allow-Origin', '*', true);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS', true);
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin', true);
                $response->headers->set('Access-Control-Allow-Credentials', 'true', true);
                $response->headers->set('Access-Control-Expose-Headers', 'Content-Type, Content-Disposition, Content-Length', true);
                
                \Log::info('Response created. Status: ' . $response->getStatusCode() . ', Content-Length: ' . strlen($pdfContent));
                \Log::info('Response headers: ' . json_encode($response->headers->all()));
                return $response;
            } catch (\Exception $e) {
                \Log::error('Unpaid Invoice Receipt PDF generation error: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                return response()->json(['error' => 'PDF generation failed', 'message' => $e->getMessage()], 500, [
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                    'Access-Control-Allow-Credentials' => 'true',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Unexpected error in generateUnpaidInvoiceReceipt: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Unexpected error', 'message' => $e->getMessage()], 500, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
    }

    public function getUnpaidInvoiceReceiptData($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest.category', 'labRequest', 'lab'])->findOrFail($visitId);
        
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
        $metadata = $this->parseMetadata($visit);
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
        
        $eddJson = $visit->getExpectedDeliveryDate();
        $eddJsonStr = $eddJson instanceof \Carbon\CarbonInterface
            ? $eddJson->format('Y-m-d')
            : $this->scalarForReceiptView($eddJson, '');

        $receiptData = [
            'receipt_number' => $visit->visit_number,
            'lab_number' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
            'date' => $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('Y-m-d') : now()->format('Y-m-d'),
            'patient_name' => $visit->patient->name ?: 'N/A',
            'patient_age' => $this->scalarForReceiptView($patientAge, 'N/A') ?: 'N/A',
            'patient_phone' => $visit->patient->phone ?: 'N/A',
            'tests' => $this->getTestsForReceipt($visit),
            'total_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->total_amount ?: 0)),
            'discount_amount' => $visit->discount_amount ?: 0,
            'final_amount' => $financialData['total_amount'] ?? ($invoice ? $invoice->total_amount : ($visit->final_amount ?: 0)),
            'upfront_payment' => $financialData['amount_paid'] ?? $this->calculatePaidAmount($visit, $invoice),
            'remaining_balance' => $financialData['remaining_balance'] ?? $this->calculateRemainingBalance($visit, $invoice),
            'payment_method' => $this->getPaymentMethod($visit, $payments),
            'billing_status' => $financialData['payment_status'] ?? $this->getPaymentStatus($invoice, $visit),
            'expected_delivery_date' => $eddJsonStr,
            'barcode_text' => $visit->labRequest ? $visit->labRequest->full_lab_no : ($visit->patient->lab ?: 'N/A'),
            'check_in_by' => $visit->check_in_by ?: 'N/A',
            'check_in_at' => $visit->check_in_at ?: 'N/A',
            'payment_breakdown' => $paymentBreakdown,
            'visit_id' => $visit->id,
            'patient_credentials' => $visit->patient->getPortalCredentials(),
            'printed_by' => $printedBy,
            'printed_at' => now()->format('Y-m-d H:i:s'),
        ];

        $pres = $this->buildLabReceiptPresentation($visit);
        
        return response()->json([
            'visit' => $visit,
            'receipt_data' => $receiptData,
            'lab_branding' => $pres['labBranding'],
        ]);
    }

    public function generateA4Receipt($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest.category', 'labRequest'])->findOrFail($visitId);
        
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
        $metadata = $this->parseMetadata($visit);
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