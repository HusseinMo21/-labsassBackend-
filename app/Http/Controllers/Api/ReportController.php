<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Patient;
use App\Models\LabTest;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\Expense;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        // Payment method breakdown - use amount_paid from invoices table
        $invoiceTotal = Invoice::whereBetween('created_at', [$startDate, $endDate])->sum('amount_paid');
        
        $paymentMethods = collect([
            (object)['payment_method' => 'Cash', 'count' => Invoice::whereBetween('created_at', [$startDate, $endDate])->count(), 'total' => $invoiceTotal]
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

            // Only apply date filter if dates are provided
            $applyDateFilter = !empty($startDate) && !empty($endDate);
            
            if ($applyDateFilter) {
                $startDate = Carbon::parse($startDate);
                $endDate = Carbon::parse($endDate);
            } else {
                $startDate = null;
                $endDate = null;
            }

        // New patients per day
        $newPatientsQuery = Patient::query();
        if ($applyDateFilter) {
            $newPatientsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $newPatients = $newPatientsQuery->selectRaw('DATE(created_at) as date, COUNT(*) as count')
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
        $newPatientsCountQuery = Patient::query();
        if ($applyDateFilter) {
            $newPatientsCountQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $summary = [
            'total_patients' => Patient::count(),
            'new_patients' => $newPatientsCountQuery->count(),
            'active_patients' => Patient::whereHas('visits')->count(),
            'average_visits_per_patient' => Patient::withCount('visits')->get()->avg('visits_count'),
        ];

            return response()->json([
                'new_patients' => $newPatients,
                'demographics' => $demographics,
                'age_distribution' => $ageDistribution,
                'top_patients' => $topPatients,
                'summary' => $summary,
                'period' => $applyDateFilter ? [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ] : null,
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

        // Only apply date filter if dates are provided
        $applyDateFilter = !empty($startDate) && !empty($endDate);
        
        if ($applyDateFilter) {
            $startDate = Carbon::parse($startDate);
            $endDate = Carbon::parse($endDate);
        } else {
            // Default to all time if no dates provided
            $startDate = null;
            $endDate = null;
        }

        // Most requested tests
        $popularTests = LabTest::withCount(['visitTests' => function ($query) use ($startDate, $endDate, $applyDateFilter) {
            if ($applyDateFilter) {
                $query->whereHas('visit', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('visit_date', [$startDate, $endDate]);
                });
            }
        }])
        ->withSum(['visitTests' => function ($query) use ($startDate, $endDate, $applyDateFilter) {
            if ($applyDateFilter) {
                $query->whereHas('visit', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('visit_date', [$startDate, $endDate]);
                });
            }
        }], 'price')
        ->orderByDesc('visit_tests_count')
        ->limit(10)
        ->get();

        // Tests by category
        $testsByCategory = LabTest::with('category')
            ->withCount(['visitTests' => function ($query) use ($startDate, $endDate, $applyDateFilter) {
                if ($applyDateFilter) {
                    $query->whereHas('visit', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('visit_date', [$startDate, $endDate]);
                    });
                }
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
        $testStatusQuery = DB::table('visit_tests')
            ->join('visits', 'visit_tests.visit_id', '=', 'visits.id');
        if ($applyDateFilter) {
            $testStatusQuery->whereBetween('visits.visit_date', [$startDate, $endDate]);
        }
        $testStatus = $testStatusQuery->selectRaw('visit_tests.status, COUNT(*) as count')
            ->groupBy('visit_tests.status')
            ->get();

        // Daily test volume
        $dailyTestsQuery = DB::table('visit_tests')
            ->join('visits', 'visit_tests.visit_id', '=', 'visits.id');
        if ($applyDateFilter) {
            $dailyTestsQuery->whereBetween('visits.visit_date', [$startDate, $endDate]);
        }
        $dailyTests = $dailyTestsQuery->selectRaw('DATE(visits.visit_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Summary stats
        $summaryQuery = DB::table('visit_tests')
            ->join('visits', 'visit_tests.visit_id', '=', 'visits.id');
        if ($applyDateFilter) {
            $summaryQuery->whereBetween('visits.visit_date', [$startDate, $endDate]);
        }
        
        $revenueQuery = DB::table('visit_tests')
            ->join('visits', 'visit_tests.visit_id', '=', 'visits.id');
        if ($applyDateFilter) {
            $revenueQuery->whereBetween('visits.visit_date', [$startDate, $endDate]);
        }
        
        $summary = [
            'total_tests' => (clone $summaryQuery)->count(),
            'total_tests_ordered' => (clone $summaryQuery)->count(), // Keep for backward compatibility
            'completed_tests' => (clone $summaryQuery)->where('visit_tests.status', 'completed')->count(),
            'pending_tests' => (clone $summaryQuery)->where('visit_tests.status', 'pending')->count(),
            'total_test_revenue' => $revenueQuery->sum('visit_tests.price'),
        ];

        return response()->json([
            'popular_tests' => $popularTests,
            'tests_by_category' => $testsByCategory,
            'test_status' => $testStatus,
            'daily_tests' => $dailyTests,
            'summary' => $summary,
            'period' => $applyDateFilter ? [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ] : null,
        ]);
    }

    public function financial(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Only apply date filter if dates are provided
        $applyDateFilter = !empty($startDate) && !empty($endDate);
        
        if ($applyDateFilter) {
            $startDate = Carbon::parse($startDate);
            $endDate = Carbon::parse($endDate);
        } else {
            $startDate = null;
            $endDate = null;
        }

        // Revenue data
        $revenueQuery = Visit::query();
        if ($applyDateFilter) {
            $revenueQuery->whereBetween('visit_date', [$startDate, $endDate]);
        }
        $totalRevenue = $revenueQuery->sum('final_amount');
        $totalVisits = (clone $revenueQuery)->count();
        
        // Expense data (using income table as fallback since expenses table doesn't exist)
        $totalExpenses = 0; // No expenses table available
        $totalExpenseCount = 0;
        
        // Net profit/loss
        $netProfit = $totalRevenue - $totalExpenses;
        
        // Daily financial data
        $dailyFinancialQuery = DB::table('visits');
        if ($applyDateFilter) {
            $dailyFinancialQuery->whereBetween('visit_date', [$startDate, $endDate]);
        }
        $dailyFinancial = $dailyFinancialQuery->selectRaw('DATE(visit_date) as date, SUM(final_amount) as revenue, COUNT(*) as visits')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Expenses by category (no expenses table available)
        $expensesByCategory = collect([]);

        // Payment methods for revenue - use amount_paid from invoices table
        $invoiceTotal = Invoice::sum('amount_paid');
        
        $revenueByPaymentMethod = collect([
            (object)['payment_method' => 'Cash', 'count' => Invoice::count(), 'total' => $invoiceTotal]
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
            'period' => $applyDateFilter ? [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ] : null,
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
        try {
            $visit = Visit::with(['patient', 'visitTests.labTest', 'labRequest.reports'])
                ->findOrFail($visitId);
            
            \Log::info('Generating PDF with header for visit', [
                'visit_id' => $visitId,
                'has_lab_request' => $visit->labRequest ? 'yes' : 'no',
                'lab_request_id' => $visit->labRequest ? $visit->labRequest->id : 'null',
                'reports_count' => $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->count() : 0,
                'reports_data' => $visit->labRequest && $visit->labRequest->reports ? $visit->labRequest->reports->map(function($r) {
                    return [
                        'id' => $r->id,
                        'status' => $r->status,
                        'content_preview' => substr($r->content, 0, 100) . '...'
                    ];
                }) : []
            ]);
            
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
            
            // Get attendance date and delivery date - handle metadata safely
            $metadata = [];
            if ($visit->metadata) {
                if (is_array($visit->metadata)) {
                    $metadata = $visit->metadata;
                } elseif (is_string($visit->metadata)) {
                    try {
                        $metadata = json_decode($visit->metadata, true) ?? [];
                    } catch (\Exception $e) {
                        \Log::warning('Failed to parse visit metadata as JSON in generateReportWithHeader', [
                            'visit_id' => $visitId,
                            'error' => $e->getMessage()
                        ]);
                        $metadata = [];
                    }
                }
            }
            $patientData = $metadata['patient_data'] ?? [];
            $attendanceDate = $patientData['attendance_date'] ?? ($visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('d/m/Y') : 'N/A');
            $deliveryDate = $patientData['delivery_date'] ?? ($visit->expected_delivery_date ? \Carbon\Carbon::parse($visit->expected_delivery_date)->format('d/m/Y') : 'N/A');
            
            // Also check patient record directly
            if ($visit->patient) {
                if ($attendanceDate === 'N/A' && $visit->patient->attendance_date) {
                    try {
                        $attendanceDate = \Carbon\Carbon::parse($visit->patient->attendance_date)->format('d/m/Y');
                    } catch (\Exception $e) {
                        \Log::warning('Failed to parse attendance_date', ['error' => $e->getMessage()]);
                    }
                }
                if ($deliveryDate === 'N/A' && $visit->patient->delivery_date) {
                    try {
                        $deliveryDate = \Carbon\Carbon::parse($visit->patient->delivery_date)->format('d/m/Y');
                    } catch (\Exception $e) {
                        \Log::warning('Failed to parse delivery_date', ['error' => $e->getMessage()]);
                    }
                }
            }
            
            $html = view('reports.pathology_report_with_header', [
                'visit' => $visit,
                'backgroundImage' => $backgroundImage,
                'attendance_date' => $attendanceDate,
                'delivery_date' => $deliveryDate,
            ])->render();
            
            $mpdf->WriteHTML($html);
            
            $labNumber = $visit->visit_number;
            if ($visit->labRequest && isset($visit->labRequest->full_lab_no)) {
                $labNumber = $visit->labRequest->full_lab_no;
            }
            
            $filename = 'pathology_report_with_header_' . $labNumber . '.pdf';
            
            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');
            
            // Delete image after PDF generation (only once)
            try {
                $this->deleteReportImageAfterPdf($visit);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete report image after PDF', [
                    'visit_id' => $visitId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Return JSON response with base64-encoded content like seafrance
            return response()->json([
                'content' => base64_encode($pdfContent),
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate report with header', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to generate report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function generateReportWithoutHeader($visitId)
    {
        try {
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
            
            // Get attendance date and delivery date - handle metadata safely
            $metadata = [];
            if ($visit->metadata) {
                if (is_array($visit->metadata)) {
                    $metadata = $visit->metadata;
                } elseif (is_string($visit->metadata)) {
                    try {
                        $metadata = json_decode($visit->metadata, true) ?? [];
                    } catch (\Exception $e) {
                        \Log::warning('Failed to parse visit metadata as JSON in generateReportWithoutHeader', [
                            'visit_id' => $visitId,
                            'error' => $e->getMessage()
                        ]);
                        $metadata = [];
                    }
                }
            }
            $patientData = $metadata['patient_data'] ?? [];
            $attendanceDate = $patientData['attendance_date'] ?? ($visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('d/m/Y') : 'N/A');
            $deliveryDate = $patientData['delivery_date'] ?? ($visit->expected_delivery_date ? \Carbon\Carbon::parse($visit->expected_delivery_date)->format('d/m/Y') : 'N/A');
            
            // Also check patient record directly
            if ($visit->patient) {
                if ($attendanceDate === 'N/A' && $visit->patient->attendance_date) {
                    try {
                        $attendanceDate = \Carbon\Carbon::parse($visit->patient->attendance_date)->format('d/m/Y');
                    } catch (\Exception $e) {
                        \Log::warning('Failed to parse attendance_date', ['error' => $e->getMessage()]);
                    }
                }
                if ($deliveryDate === 'N/A' && $visit->patient->delivery_date) {
                    try {
                        $deliveryDate = \Carbon\Carbon::parse($visit->patient->delivery_date)->format('d/m/Y');
                    } catch (\Exception $e) {
                        \Log::warning('Failed to parse delivery_date', ['error' => $e->getMessage()]);
                    }
                }
            }
            
            $html = view('reports.pathology_report_without_header', [
                'visit' => $visit,
                'attendance_date' => $attendanceDate,
                'delivery_date' => $deliveryDate,
            ])->render();
            
            $mpdf->WriteHTML($html);
            
            $labNumber = $visit->visit_number;
            if ($visit->labRequest && isset($visit->labRequest->full_lab_no)) {
                $labNumber = $visit->labRequest->full_lab_no;
            }
            
            $filename = 'pathology_report_without_header_' . $labNumber . '.pdf';
            
            // Get PDF content as string
            $pdfContent = $mpdf->Output('', 'S');
            
            // Delete image after PDF generation (only once)
            try {
                $this->deleteReportImageAfterPdf($visit);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete report image after PDF', [
                    'visit_id' => $visitId,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Return JSON response with base64-encoded content like seafrance
            return response()->json([
                'content' => base64_encode($pdfContent),
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate report without header', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to generate report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveReport(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        
        \Log::info('saveReport called', [
            'visit_id' => $visitId,
            'request_data' => $request->all(),
            'form_data_keys' => array_keys($request->all()),
            'clinical_data' => $request->clinical_data,
            'nature_of_specimen' => $request->nature_of_specimen,
            'gross_pathology' => $request->gross_pathology,
            'microscopic_examination' => $request->microscopic_examination,
            'conclusion' => $request->conclusion,
            'recommendations' => $request->recommendations,
            'test_status' => $request->test_status
        ]);
        
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

        // Update visit status and referred_doctor
        // Only mark visit as completed if explicitly set to 'completed' (via Mark as Complete button)
        // Don't auto-complete when staff saves/records the report
        $visitStatus = $request->test_status ?? 'pending';
        
        // Only update visit status if test_status is explicitly 'completed'
        // Otherwise, keep the visit status as is (don't change it when saving report)
        if ($visitStatus === 'completed') {
            $visitUpdates = [
                'status' => 'completed',
                'completed_at' => now()
            ];
            
            // Update referred_doctor if provided
            if ($request->referred_by) {
                $visitUpdates['referred_doctor'] = $request->referred_by;
            }
            
            $visit->update($visitUpdates);
        } else {
            // For pending/draft reports, only update referred_doctor, don't change visit status
            if ($request->referred_by) {
                $visit->update(['referred_doctor' => $request->referred_by]);
            }
        }

        // Update patient data if provided
        if ($visit->patient) {
            $patientUpdates = [];
            if ($request->patient_name) $patientUpdates['name'] = $request->patient_name;
            if ($request->age) $patientUpdates['age'] = $request->age;
            if ($request->sex) $patientUpdates['gender'] = strtolower($request->sex);
            
            // Also update patient's doctor_id if referred_by is provided
            if ($request->referred_by) {
                $patientUpdates['doctor_id'] = $request->referred_by;
            }
            
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
                'status' => $request->test_status ?? 'pending', // Default to pending, not draft
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ];
            
            // Add image data if available
            if ($imageData) {
                $reportData = array_merge($reportData, $imageData);
            }
            
            $report = \App\Models\Report::create($reportData);
            
            \Log::info('Report created in saveReport', [
                'report_id' => $report->id,
                'status' => $report->status,
                'lab_request_id' => $report->lab_request_id,
                'visit_id' => $visit->id,
                'content_preview' => substr($report->content, 0, 200) . '...',
                'form_data' => $request->only(['clinical_data', 'nature_of_specimen', 'gross_pathology', 'microscopic_examination', 'conclusion', 'recommendations'])
            ]);
        } else {
            // Update existing report
            $updateData = [
                'title' => $request->type_of_analysis ?? $report->title,
                'content' => $this->buildReportContent($request),
                // Don't update status to completed unless explicitly set
                // Keep existing status if test_status is not provided or is not 'completed'
                'status' => ($request->test_status === 'completed') ? 'completed' : ($request->test_status ?? $report->status),
            ];
            
            // Add image data if available
            if ($imageData) {
                $updateData = array_merge($updateData, $imageData);
            }
            
            $report->update($updateData);
            
            \Log::info('Report updated in saveReport', [
                'report_id' => $report->id,
                'status' => $report->status,
                'lab_request_id' => $report->lab_request_id,
                'visit_id' => $visit->id,
                'content_preview' => substr($report->content, 0, 200) . '...',
                'form_data' => $request->only(['clinical_data', 'nature_of_specimen', 'gross_pathology', 'microscopic_examination', 'conclusion', 'recommendations'])
            ]);
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
        
        // Handle fields - if image replaces a field, don't include text for that field
        $imagePlacement = $request->image_placement ?? 'end_of_report';
        
        if ($request->clinical_data && $imagePlacement !== 'clinical_data') {
            $content['clinical_data'] = $request->clinical_data;
        }
        if ($request->nature_of_specimen && $imagePlacement !== 'nature_of_specimen') {
            $content['nature_of_specimen'] = $request->nature_of_specimen;
        }
        if ($request->gross_pathology && $imagePlacement !== 'gross_pathology') {
            $content['gross_pathology'] = $request->gross_pathology;
        }
        if ($request->microscopic_examination && $imagePlacement !== 'microscopic_examination') {
            $content['microscopic_examination'] = $request->microscopic_examination;
        }
        if ($request->conclusion && $imagePlacement !== 'conclusion') {
            $content['conclusion'] = $request->conclusion;
        }
        if ($request->recommendations) {
            $content['recommendations'] = $request->recommendations;
        }
        if ($request->referred_by) {
            $content['referred_by'] = $request->referred_by;
        }
        
        // Store image placement for PDF generation
        if ($request->image_placement) {
            $content['image_placement'] = $request->image_placement;
        }
        
        return json_encode($content);
    }

    public function getReports(Request $request)
    {
        $labRequestId = $request->get('lab_request_id');
        
        if (!$labRequestId) {
            return response()->json(['data' => []]);
        }
        
        $reports = Report::where('lab_request_id', $labRequestId)->get();
        
        return response()->json($reports);
    }

    /**
     * Delete report image after PDF generation
     * This ensures images are only used once in PDF and then removed
     */
    private function deleteReportImageAfterPdf($visit)
    {
        try {
            if ($visit->labRequest && $visit->labRequest->reports) {
                $report = $visit->labRequest->reports
                    ->where('status', 'completed')
                    ->sortByDesc('id')
                    ->first() 
                    ?? $visit->labRequest->reports->sortByDesc('id')->first();
                
                if ($report && $report->image_path) {
                    $imagePath = storage_path('app/public/' . $report->image_path);
                    
                    // Delete the image file
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                        \Log::info('Image deleted after PDF generation', [
                            'report_id' => $report->id,
                            'image_path' => $report->image_path
                        ]);
                    }
                    
                    // Clear image fields from database
                    $report->update([
                        'image_path' => null,
                        'image_filename' => null,
                        'image_mime_type' => null,
                        'image_size' => null,
                        'image_uploaded_at' => null,
                        'image_uploaded_by' => null,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to delete report image after PDF generation', [
                'visit_id' => $visit->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw exception - image deletion failure shouldn't break PDF generation
        }
    }
} 