<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Organization;
use App\Models\LabRequest;
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
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $patients = $query->with(['visits' => function ($q) {
            $q->latest()->take(5);
        }, 'doctor', 'organization'])
        ->withCount('visits')
        ->latest()
        ->paginate(15);

        return response()->json($patients);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'birth_date' => 'required|date|before:today',
            'phone' => 'required|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'required|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'doctor' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Auto-create user for patient
        $username = 'pt-' . strtolower(Str::random(8));
        $password = Str::random(10);
        $user = User::create([
            'name' => $username,
            'email' => $username . '@patients.local',
            'role' => 'patient',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $patientData = $validator->validated();
        $patientData['user_id'] = $user->id;
        
        // Handle doctor and organization relationships
        if (isset($patientData['doctor'])) {
            $doctor = $this->findOrCreateDoctor($patientData['doctor']);
            $patientData['doctor_id'] = $doctor ? $doctor->id : null;
        }
        
        if (isset($patientData['organization'])) {
            $organization = $this->findOrCreateOrganization($patientData['organization']);
            $patientData['organization_id'] = $organization ? $organization->id : null;
        }
        
        $patient = Patient::create($patientData);

        // Create patient credentials record
        $patient->credentials()->create([
            'username' => $username,
            'original_password' => $password,
            'hashed_password' => Hash::make($password),
            'is_active' => true,
        ]);

        // Note: Lab requests are now created automatically during check & billing process

        return response()->json([
            'message' => 'Patient created successfully',
            'patient' => $patient->load('visits', 'user', 'credentials', 'labRequests'),
            'user_credentials' => [
                'username' => $username,
                'password' => $password,
            ],
        ], 201);
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'visits' => function ($q) {
                $q->with(['visitTests.labTest', 'invoice'])->latest();
            },
            'visits.visitTests.performedBy',
        ]);

        return response()->json($patient);
    }

    public function update(Request $request, Patient $patient)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'birth_date' => 'required|date|before:today',
            'phone' => 'required|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'required|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'doctor' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $patientData = $validator->validated();
        
        // Handle doctor and organization relationships
        if (isset($patientData['doctor'])) {
            $doctor = $this->findOrCreateDoctor($patientData['doctor']);
            $patientData['doctor_id'] = $doctor ? $doctor->id : null;
        }
        
        if (isset($patientData['organization'])) {
            $organization = $this->findOrCreateOrganization($patientData['organization']);
            $patientData['organization_id'] = $organization ? $organization->id : null;
        }
        
        $patient->update($patientData);

        return response()->json([
            'message' => 'Patient updated successfully',
            'patient' => $patient->fresh(),
        ]);
    }

    public function destroy(Patient $patient)
    {
        if ($patient->visits()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete patient with existing visits',
            ], 422);
        }

        $patient->delete();

        return response()->json([
            'message' => 'Patient deleted successfully',
        ]);
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
        $patients = \App\Models\Patient::whereHas('visits.visitTests.labTest', function ($q) use ($testName) {
            $q->where('name', 'like', "%$testName%");
        })
        ->select('id', 'name', 'gender', 'birth_date', 'phone')
        ->get();
        // Add age attribute
        $patients->map(function($p) { $p->age = $p->birth_date ? $p->birth_date->age : null; });
        return response()->json(['patients' => $patients]);
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
        $test = \App\Models\VisitTest::with(['labTest', 'visit.patient'])->findOrFail($reportId);
        
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
        
        $html = view('reports.single_report_pdf', [
            'test' => $test,
        ])->render();
        $mpdf->WriteHTML($html);
        
        $filename = 'report_' . $test->id . '.pdf';
        
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

    public function myReports(Request $request)
    {
        $user = $request->user();
        $patient = Patient::where('user_id', $user->id)->first();
        
        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $reports = [];
        $visits = $patient->visits()->with(['visitTests.labTest.category'])->get();
        
        foreach ($visits as $visit) {
            foreach ($visit->visitTests as $test) {
                // Only show completed reports that are fully paid
                if ($test->status === 'completed' && $visit->payment_status === 'paid') {
                    $reports[] = [
                        'id' => $test->id,
                        'visit_id' => $visit->visit_id,
                        'test_name' => $test->labTest->name ?? '',
                        'test_category' => $test->labTest->category->name ?? '',
                        'result_value' => $test->result_value,
                        'result_status' => $test->result_status,
                        'visit_date' => $visit->visit_date,
                        'report_date' => $test->updated_at,
                        'status' => $test->status,
                        'patient_name' => $patient->name,
                        'clinical_data' => $visit->clinical_data,
                        'microscopic_description' => $visit->microscopic_description,
                        'diagnosis' => $visit->diagnosis,
                        'recommendations' => $visit->recommendations,
                    ];
                }
            }
        }

        return response()->json(['reports' => $reports]);
    }

    public function getMyReport(Request $request, $reportId)
    {
        $user = $request->user();
        $patient = Patient::where('user_id', $user->id)->first();
        
        if (!$patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $test = \App\Models\VisitTest::with(['labTest.category', 'visit'])
            ->where('id', $reportId)
            ->whereHas('visit', function($q) use ($patient) {
                $q->where('patient_id', $patient->id);
            })
            ->first();

        if (!$test) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        // Check if report is completed and fully paid
        if ($test->status !== 'completed' || $test->visit->payment_status !== 'paid') {
            return response()->json(['message' => 'Report not available yet'], 403);
        }

        return response()->json(['report' => $test]);
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

        $invoices = $patient->visits()->with(['invoice.payments'])->get()
            ->filter(function($visit) {
                return $visit->invoice !== null;
            })
            ->map(function($visit) {
                $invoice = $visit->invoice;
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'visit_id' => $visit->visit_id,
                    'patient_name' => $patient->name,
                    'total_amount' => $invoice->total_amount,
                    'discount_amount' => $invoice->discount_amount,
                    'tax_amount' => $invoice->tax_amount,
                    'final_amount' => $invoice->total_amount - $invoice->discount_amount + $invoice->tax_amount,
                    'payment_status' => $invoice->payment_status,
                    'balance_due' => $invoice->balance_due,
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