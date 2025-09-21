<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Patient;
use App\Models\LabTest;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function revenue(Request $request)
    {
        $period = $request->get('period', 'month');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (!$startDate) {
            $startDate = now()->startOfMonth();
        } else {
            $startDate = Carbon::parse($startDate);
        }

        if (!$endDate) {
            $endDate = now()->endOfMonth();
        } else {
            $endDate = Carbon::parse($endDate);
        }

        // Daily revenue
        $dailyRevenue = Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->selectRaw('DATE(visit_date) as date, SUM(final_amount) as revenue, COUNT(*) as visits')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Monthly revenue
        $monthlyRevenue = Visit::whereBetween('visit_date', [$startDate->startOfYear(), $endDate->endOfYear()])
            ->selectRaw('YEAR(visit_date) as year, MONTH(visit_date) as month, SUM(final_amount) as revenue, COUNT(*) as visits')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Payment method breakdown (simplified since payment_method column doesn't exist)
        $paymentMethods = collect([
            (object)['payment_method' => 'Cash', 'count' => Invoice::whereBetween('created_at', [$startDate, $endDate])->count(), 'total' => Invoice::whereBetween('created_at', [$startDate, $endDate])->sum('paid')]
        ]);

        // Summary stats
        $summary = [
            'total_revenue' => Visit::whereBetween('visit_date', [$startDate, $endDate])->sum('final_amount'),
            'total_visits' => Visit::whereBetween('visit_date', [$startDate, $endDate])->count(),
            'average_revenue_per_visit' => Visit::whereBetween('visit_date', [$startDate, $endDate])->avg('final_amount'),
            'total_invoices' => Invoice::whereBetween('created_at', [$startDate, $endDate])->count(),
            'paid_invoices' => Invoice::whereBetween('created_at', [$startDate, $endDate])->where('remaining', 0)->count(),
            'pending_amount' => Invoice::whereBetween('created_at', [$startDate, $endDate])->sum('remaining'),
        ];

        return response()->json([
            'daily_revenue' => $dailyRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'payment_methods' => $paymentMethods,
            'summary' => $summary,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    public function patients(Request $request)
    {
        try {
            $period = $request->get('period', 'month');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            if (!$startDate) {
                $startDate = now()->startOfMonth();
            } else {
                $startDate = Carbon::parse($startDate);
            }

            if (!$endDate) {
                $endDate = now()->endOfMonth();
            } else {
                $endDate = Carbon::parse($endDate);
            }

        // New patients per day
        $newPatients = Patient::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Patient demographics
        $demographics = Patient::selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->get();

        // Age distribution (simplified)
        $ageDistribution = Patient::selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->get();

        // Top patients by visits (simplified query)
        $topPatients = Patient::withCount('visits')
            ->withSum('visits', 'final_amount')
            ->orderByDesc('visits_count')
            ->limit(10)
            ->get();

        // Summary stats (simplified)
        $summary = [
            'total_patients' => Patient::count(),
            'new_patients' => Patient::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_patients' => Patient::whereHas('visits')->count(),
            'average_visits_per_patient' => Patient::withCount('visits')->get()->avg('visits_count'),
        ];

            return response()->json([
                'new_patients' => $newPatients,
                'demographics' => $demographics,
                'age_distribution' => $ageDistribution,
                'top_patients' => $topPatients,
                'summary' => $summary,
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Reports patients error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Failed to fetch patient reports',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function tests(Request $request)
    {
        $period = $request->get('period', 'month');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (!$startDate) {
            $startDate = now()->startOfMonth();
        } else {
            $startDate = Carbon::parse($startDate);
        }

        if (!$endDate) {
            $endDate = now()->endOfMonth();
        } else {
            $endDate = Carbon::parse($endDate);
        }

        // Most requested tests
        $popularTests = LabTest::withCount(['visitTests' => function ($query) use ($startDate, $endDate) {
            $query->whereHas('visit', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('visit_date', [$startDate, $endDate]);
            });
        }])
        ->withSum(['visitTests' => function ($query) use ($startDate, $endDate) {
            $query->whereHas('visit', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('visit_date', [$startDate, $endDate]);
            });
        }], 'price')
        ->orderByDesc('visit_tests_count')
        ->limit(10)
        ->get();

        // Tests by category
        $testsByCategory = LabTest::with('category')
            ->withCount(['visitTests' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('visit', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('visit_date', [$startDate, $endDate]);
                });
            }])
            ->get()
            ->groupBy('category.name')
            ->map(function ($tests) {
                return [
                    'total_tests' => $tests->sum('visit_tests_count'),
                    'total_revenue' => $tests->sum('visit_tests_sum_price'),
                    'tests' => $tests,
                ];
            });

        // Test completion status
        $testStatus = DB::table('visit_tests')
            ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
            ->whereBetween('visits.visit_date', [$startDate, $endDate])
            ->selectRaw('visit_tests.status, COUNT(*) as count')
            ->groupBy('visit_tests.status')
            ->get();

        // Daily test volume
        $dailyTests = DB::table('visit_tests')
            ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
            ->whereBetween('visits.visit_date', [$startDate, $endDate])
            ->selectRaw('DATE(visits.visit_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Summary stats
        $summary = [
            'total_tests_ordered' => DB::table('visit_tests')
                ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
                ->whereBetween('visits.visit_date', [$startDate, $endDate])
                ->count(),
            'completed_tests' => DB::table('visit_tests')
                ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
                ->whereBetween('visits.visit_date', [$startDate, $endDate])
                ->where('visit_tests.status', 'completed')
                ->count(),
            'pending_tests' => DB::table('visit_tests')
                ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
                ->whereBetween('visits.visit_date', [$startDate, $endDate])
                ->where('visit_tests.status', 'pending')
                ->count(),
            'total_test_revenue' => DB::table('visit_tests')
                ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
                ->whereBetween('visits.visit_date', [$startDate, $endDate])
                ->sum('visit_tests.price'),
        ];

        return response()->json([
            'popular_tests' => $popularTests,
            'tests_by_category' => $testsByCategory,
            'test_status' => $testStatus,
            'daily_tests' => $dailyTests,
            'summary' => $summary,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    public function financial(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (!$startDate) {
            $startDate = now()->startOfMonth();
        } else {
            $startDate = Carbon::parse($startDate);
        }

        if (!$endDate) {
            $endDate = now()->endOfMonth();
        } else {
            $endDate = Carbon::parse($endDate);
        }

        // Revenue data
        $totalRevenue = Visit::whereBetween('visit_date', [$startDate, $endDate])->sum('final_amount');
        $totalVisits = Visit::whereBetween('visit_date', [$startDate, $endDate])->count();
        
        // Expense data (using income table as fallback since expenses table doesn't exist)
        $totalExpenses = 0; // No expenses table available
        $totalExpenseCount = 0;
        
        // Net profit/loss
        $netProfit = $totalRevenue - $totalExpenses;
        
        // Daily financial data
        $dailyFinancial = DB::table('visits')
            ->selectRaw('DATE(visit_date) as date, SUM(final_amount) as revenue, COUNT(*) as visits')
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Expenses by category (no expenses table available)
        $expensesByCategory = collect([]);

        // Payment methods for revenue (simplified since payment_method column doesn't exist)
        $revenueByPaymentMethod = collect([
            (object)['payment_method' => 'Cash', 'count' => Invoice::count(), 'total' => Invoice::sum('paid')]
        ]);

        // Summary stats
        $summary = [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
            'total_visits' => $totalVisits,
            'total_expense_count' => $totalExpenseCount,
            'average_revenue_per_visit' => $totalVisits > 0 ? round($totalRevenue / $totalVisits, 2) : 0,
            'average_expense_per_item' => $totalExpenseCount > 0 ? round($totalExpenses / $totalExpenseCount, 2) : 0,
        ];

        return response()->json([
            'summary' => $summary,
            'daily_financial' => $dailyFinancial,
            'expenses_by_category' => $expensesByCategory,
            'revenue_by_payment_method' => $revenueByPaymentMethod,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'revenue');
        $format = $request->get('format', 'json');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        switch ($type) {
            case 'revenue':
                $data = $this->getRevenueData($startDate, $endDate);
                break;
            case 'patients':
                $data = $this->getPatientsData($startDate, $endDate);
                break;
            case 'tests':
                $data = $this->getTestsData($startDate, $endDate);
                break;
            case 'inventory':
                $data = $this->getInventoryData();
                break;
            default:
                return response()->json(['message' => 'Invalid report type'], 400);
        }

        if ($format === 'csv') {
            return $this->exportToCsv($data, $type);
        }

        return response()->json($data);
    }

    private function getRevenueData($startDate, $endDate)
    {
        return Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->with(['patient', 'createdBy'])
            ->get()
            ->map(function ($visit) {
                return [
                    'visit_number' => $visit->visit_number,
                    'patient_name' => $visit->patient->name,
                    'visit_date' => $visit->visit_date,
                    'total_amount' => $visit->total_amount,
                    'discount_amount' => $visit->discount_amount,
                    'final_amount' => $visit->final_amount,
                    'status' => $visit->status,
                    'created_by' => $visit->createdBy->name,
                ];
            });
    }

    private function getPatientsData($startDate, $endDate)
    {
        return Patient::whereBetween('created_at', [$startDate, $endDate])
            ->withCount(['visits' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
            }])
            ->withSum(['visits' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
            }], 'final_amount')
            ->get()
            ->map(function ($patient) {
                return [
                    'name' => $patient->name,
                    'gender' => $patient->gender,
                    'birth_date' => $patient->birth_date,
                    'phone' => $patient->phone,
                    'total_visits' => $patient->visits_count,
                    'total_spent' => $patient->visits_sum_final_amount,
                    'created_at' => $patient->created_at,
                ];
            });
    }

    private function getTestsData($startDate, $endDate)
    {
        return DB::table('visit_tests')
            ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
            ->join('lab_tests', 'visit_tests.lab_test_id', '=', 'lab_tests.id')
            ->join('patients', 'visits.patient_id', '=', 'patients.id')
            ->whereBetween('visits.visit_date', [$startDate, $endDate])
            ->select([
                'lab_tests.name as test_name',
                'lab_tests.code as test_code',
                'patients.name as patient_name',
                'visits.visit_date',
                'visit_tests.result_value',
                'visit_tests.result_status',
                'visit_tests.status',
                'visit_tests.price',
            ])
            ->get();
    }

    private function getInventoryData()
    {
        return InventoryItem::with('updatedBy')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'minimum_quantity' => $item->minimum_quantity,
                    'unit_price' => $item->unit_price,
                    'total_value' => $item->total_value,
                    'supplier' => $item->supplier,
                    'expiry_date' => $item->expiry_date,
                    'status' => $item->status,
                    'updated_by' => $item->updatedBy->name ?? 'N/A',
                    'updated_at' => $item->updated_at,
                ];
            });
    }

    private function exportToCsv($data, $type)
    {
        $filename = "{$type}_report_" . now()->format('Y-m-d_H-i-s') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            if (!empty($data)) {
                // Write headers
                fputcsv($file, array_keys((array) $data[0]));
                
                // Write data
                foreach ($data as $row) {
                    fputcsv($file, (array) $row);
                }
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function doctorReports(Request $request)
    {
        // Doctors can only see pending and under_review tests
        $visits = Visit::with(['patient', 'visitTests.labTest'])
            ->whereHas('visitTests', function ($query) {
                $query->whereIn('status', ['pending', 'under_review']);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($visits);
    }

    public function getDoctorReport($reportId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest'])
            ->whereHas('visitTests', function ($query) {
                $query->whereIn('status', ['pending', 'under_review']);
            })
            ->findOrFail($reportId);

        return response()->json($visit);
    }

    public function approveReport(Request $request, $reportId)
    {
        $visit = Visit::findOrFail($reportId);
        
        $visit->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['message' => 'Report approved successfully']);
    }

    public function fillReportData(Request $request, $reportId)
    {
        $visit = Visit::findOrFail($reportId);
        
        $request->validate([
            'clinical_data' => 'nullable|string',
            'microscopic_description' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'referred_doctor' => 'nullable|string',
        ]);

        $visit->update($request->only([
            'clinical_data',
            'microscopic_description', 
            'diagnosis',
            'recommendations',
            'referred_doctor'
        ]));

        return response()->json(['message' => 'Report data updated successfully']);
    }

    public function generateProfessionalReport($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest', 'labRequest'])
            ->findOrFail($visitId);
        
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
        ]);
        
        // Set font for Arabic support
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        
        $html = view('reports.professional_pathology_report', [
            'visit' => $visit,
        ])->render();
        
        $mpdf->WriteHTML($html);
        
        $filename = 'pathology_report_' . ($visit->labRequest->full_lab_no ?? $visit->visit_number) . '.pdf';
        
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

    public function generateReportWithHeader($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest', 'labRequest.reports'])
            ->findOrFail($visitId);
        
        // Read background image and convert to base64
        $backgroundImagePath = public_path('templete/background.jpg');
        $backgroundImage = null;
        
        if (file_exists($backgroundImagePath)) {
            $imageData = file_get_contents($backgroundImagePath);
            $backgroundImage = base64_encode($imageData);
        }
        
        // Configure MPDF for Arabic support with proper margins for printing
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => storage_path('app/temp'),
        ]);
        
        // Set font for Arabic support
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        
        $html = view('reports.pathology_report_with_header', [
            'visit' => $visit,
            'backgroundImage' => $backgroundImage,
        ])->render();
        
        $mpdf->WriteHTML($html);
        
        $filename = 'pathology_report_with_header_' . ($visit->labRequest->full_lab_no ?? $visit->visit_number) . '.pdf';
        
        // Get PDF content as string
        $pdfContent = $mpdf->Output('', 'S');
        
        // Return JSON response with base64-encoded content like seafrance
        return response()->json([
            'content' => base64_encode($pdfContent),
            'filename' => $filename
        ]);
    }

    public function generateReportWithoutHeader($visitId)
    {
        $visit = Visit::with(['patient', 'visitTests.labTest', 'labRequest.reports'])
            ->findOrFail($visitId);
        
        // Configure MPDF for Arabic support with proper margins for printing
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => storage_path('app/temp'),
        ]);
        
        // Set font for Arabic support
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        
        $html = view('reports.pathology_report_without_header', [
            'visit' => $visit,
        ])->render();
        
        $mpdf->WriteHTML($html);
        
        $filename = 'pathology_report_without_header_' . ($visit->labRequest->full_lab_no ?? $visit->visit_number) . '.pdf';
        
        // Get PDF content as string
        $pdfContent = $mpdf->Output('', 'S');
        
        // Return JSON response with base64-encoded content like seafrance
        return response()->json([
            'content' => base64_encode($pdfContent),
            'filename' => $filename
        ]);
    }

    public function saveReport(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        
        $request->validate([
            'patient_name' => 'nullable|string|max:255',
            'referred_by' => 'nullable|string|max:255',
            'lab_no' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'age' => 'nullable|string|max:50',
            'sex' => 'nullable|string|in:Male,Female',
            'receiving_date' => 'nullable|date',
            'discharge_date' => 'nullable|date',
            'clinical_data' => 'nullable|string',
            'nature_of_specimen' => 'nullable|string',
            'gross_pathology' => 'nullable|string',
            'microscopic_examination' => 'nullable|string',
            'conclusion' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'type_of_analysis' => 'nullable|string|max:255',
            'test_status' => 'nullable|string|in:pending,completed,under_review',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        // Update visit status only
        $visit->update([
            'status' => $request->test_status ?? 'pending',
        ]);

        // Update patient data if provided
        if ($visit->patient) {
            $patientUpdates = [];
            if ($request->patient_name) $patientUpdates['name'] = $request->patient_name;
            if ($request->age) $patientUpdates['age'] = $request->age;
            if ($request->sex) $patientUpdates['gender'] = strtolower($request->sex);
            
            if (!empty($patientUpdates)) {
                $visit->patient->update($patientUpdates);
            }
        }

        // Handle image upload
        $imageData = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('pathology_images', $imageName, 'public');
            
            $imageData = [
                'image_path' => $imagePath,
                'image_filename' => $imageName,
                'image_mime_type' => $image->getMimeType(),
                'image_size' => $image->getSize(),
                'image_uploaded_at' => now(),
                'image_uploaded_by' => auth()->id(),
            ];
        }

        // Find or create report for this visit
        $report = \App\Models\Report::where('lab_request_id', $visit->lab_request_id)->first();
        
        if (!$report) {
            // Create new report
            $reportData = [
                'lab_request_id' => $visit->lab_request_id,
                'title' => $request->type_of_analysis ?? 'Pathology Report',
                'content' => $this->buildReportContent($request),
                'status' => $request->test_status ?? 'draft',
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ];
            
            // Add image data if available
            if ($imageData) {
                $reportData = array_merge($reportData, $imageData);
            }
            
            $report = \App\Models\Report::create($reportData);
        } else {
            // Update existing report
            $updateData = [
                'title' => $request->type_of_analysis ?? $report->title,
                'content' => $this->buildReportContent($request),
                'status' => $request->test_status ?? $report->status,
            ];
            
            // Add image data if available
            if ($imageData) {
                $updateData = array_merge($updateData, $imageData);
            }
            
            $report->update($updateData);
        }

        return response()->json([
            'message' => 'Report saved successfully',
            'visit' => $visit->load(['patient', 'visitTests.labTest', 'labRequest']),
            'report' => $report
        ]);
    }

    private function buildReportContent($request)
    {
        $content = [];
        
        if ($request->clinical_data) {
            $content['clinical_data'] = $request->clinical_data;
        }
        if ($request->nature_of_specimen) {
            $content['nature_of_specimen'] = $request->nature_of_specimen;
        }
        if ($request->gross_pathology) {
            $content['gross_pathology'] = $request->gross_pathology;
        }
        if ($request->microscopic_examination) {
            $content['microscopic_examination'] = $request->microscopic_examination;
        }
        if ($request->conclusion) {
            $content['conclusion'] = $request->conclusion;
        }
        if ($request->recommendations) {
            $content['recommendations'] = $request->recommendations;
        }
        if ($request->referred_by) {
            $content['referred_by'] = $request->referred_by;
        }
        
        return json_encode($content);
    }
} 