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

        // Payment method breakdown
        $paymentMethods = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount_paid) as total')
            ->groupBy('payment_method')
            ->get();

        // Summary stats
        $summary = [
            'total_revenue' => Visit::whereBetween('visit_date', [$startDate, $endDate])->sum('final_amount'),
            'total_visits' => Visit::whereBetween('visit_date', [$startDate, $endDate])->count(),
            'average_revenue_per_visit' => Visit::whereBetween('visit_date', [$startDate, $endDate])->avg('final_amount'),
            'total_invoices' => Invoice::whereBetween('created_at', [$startDate, $endDate])->count(),
            'paid_invoices' => Invoice::whereBetween('created_at', [$startDate, $endDate])->where('status', 'paid')->count(),
            'pending_amount' => Invoice::whereBetween('created_at', [$startDate, $endDate])->sum('balance'),
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

        // Age distribution
        $ageDistribution = Patient::selectRaw('
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 18 THEN "Under 18"
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 30 THEN "18-30"
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 31 AND 50 THEN "31-50"
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 51 AND 70 THEN "51-70"
                ELSE "Over 70"
            END as age_group,
            COUNT(*) as count
        ')
        ->groupBy('age_group')
        ->get();

        // Top patients by visits
        $topPatients = Patient::withCount(['visits' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        }])
        ->withSum(['visits' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        }], 'final_amount')
        ->orderByDesc('visits_count')
        ->limit(10)
        ->get();

        // Summary stats
        $summary = [
            'total_patients' => Patient::count(),
            'new_patients' => Patient::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_patients' => Patient::whereHas('visits', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
            })->count(),
            'average_visits_per_patient' => Patient::withCount(['visits' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
            }])->avg('visits_count'),
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
        
        // Expense data
        $totalExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');
        $totalExpenseCount = Expense::whereBetween('expense_date', [$startDate, $endDate])->count();
        
        // Net profit/loss
        $netProfit = $totalRevenue - $totalExpenses;
        
        // Daily financial data
        $dailyFinancial = DB::table('visits')
            ->selectRaw('DATE(visit_date) as date, SUM(final_amount) as revenue, COUNT(*) as visits')
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->groupBy('date')
            ->union(
                DB::table('expenses')
                    ->selectRaw('DATE(expense_date) as date, SUM(amount) as expenses, COUNT(*) as expense_count')
                    ->whereBetween('expense_date', [$startDate, $endDate])
                    ->groupBy('date')
            )
            ->orderBy('date')
            ->get();

        // Expenses by category
        $expensesByCategory = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('category, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Payment methods for revenue
        $revenueByPaymentMethod = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount_paid) as total')
            ->groupBy('payment_method')
            ->get();

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
} 