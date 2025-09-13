<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\VisitTest;
use App\Models\Patient;
use App\Models\TestCategory;
use App\Models\Report;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LabInsightsController extends Controller
{
    /**
     * Get comprehensive lab insights data
     */
    public function getInsights(Request $request)
    {
        $period = $request->get('period', '30'); // days
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();

        try {
            // Start with basic data to test
            $insights = [
                'overview' => $this->getOverviewStats($startDate, $endDate),
                'revenue' => $this->getRevenueStats($startDate, $endDate),
                'tests' => $this->getTestStats($startDate, $endDate),
                'patients' => $this->getPatientStats($startDate, $endDate),
                'performance' => $this->getPerformanceStats($startDate, $endDate),
                'trends' => $this->getTrendsData($startDate, $endDate),
                'categories' => $this->getCategoryStats($startDate, $endDate),
                'top_tests' => $this->getTopTests($startDate, $endDate),
                'recent_activity' => $this->getRecentActivity($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $insights,
                'period' => $period,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Lab Insights Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'period' => $period,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch insights data',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats($startDate, $endDate)
    {
        $totalVisits = Visit::whereBetween('visit_date', [$startDate, $endDate])->count();
        $totalPatients = Patient::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalTests = VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        })->count();
        $totalRevenue = Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->sum('final_amount');

        // Previous period comparison
        $prevStartDate = $startDate->copy()->subDays($endDate->diffInDays($startDate));
        $prevEndDate = $startDate->copy();
        
        $prevVisits = Visit::whereBetween('visit_date', [$prevStartDate, $prevEndDate])->count();
        $prevPatients = Patient::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
        $prevTests = VisitTest::whereHas('visit', function($query) use ($prevStartDate, $prevEndDate) {
            $query->whereBetween('visit_date', [$prevStartDate, $prevEndDate]);
        })->count();
        $prevRevenue = Visit::whereBetween('visit_date', [$prevStartDate, $prevEndDate])
            ->sum('final_amount');

        return [
            'visits' => [
                'current' => $totalVisits,
                'previous' => $prevVisits,
                'change' => $this->calculatePercentageChange($prevVisits, $totalVisits),
            ],
            'patients' => [
                'current' => $totalPatients,
                'previous' => $prevPatients,
                'change' => $this->calculatePercentageChange($prevPatients, $totalPatients),
            ],
            'tests' => [
                'current' => $totalTests,
                'previous' => $prevTests,
                'change' => $this->calculatePercentageChange($prevTests, $totalTests),
            ],
            'revenue' => [
                'current' => $totalRevenue,
                'previous' => $prevRevenue,
                'change' => $this->calculatePercentageChange($prevRevenue, $totalRevenue),
            ],
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats($startDate, $endDate)
    {
        $revenue = Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->selectRaw('
                SUM(total_amount) as total_revenue,
                SUM(discount_amount) as total_discounts,
                SUM(final_amount) as net_revenue,
                SUM(upfront_payment) as upfront_payments,
                SUM(remaining_balance) as outstanding_balance,
                AVG(final_amount) as average_visit_value
            ')
            ->first();

        $paymentMethods = Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(final_amount) as amount')
            ->groupBy('payment_method')
            ->get();

        return [
            'total_revenue' => $revenue->total_revenue ?? 0,
            'total_discounts' => $revenue->total_discounts ?? 0,
            'net_revenue' => $revenue->net_revenue ?? 0,
            'upfront_payments' => $revenue->upfront_payments ?? 0,
            'outstanding_balance' => $revenue->outstanding_balance ?? 0,
            'average_visit_value' => $revenue->average_visit_value ?? 0,
            'payment_methods' => $paymentMethods,
        ];
    }

    /**
     * Get test statistics
     */
    private function getTestStats($startDate, $endDate)
    {
        $testStatuses = VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        })
        ->selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->get();

        $avgTurnaroundTime = VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        })
        ->whereNotNull('performed_at')
        ->join('lab_tests', 'visit_tests.lab_test_id', '=', 'lab_tests.id')
        ->avg('lab_tests.turnaround_time_hours');

        return [
            'status_breakdown' => $testStatuses,
            'average_turnaround_hours' => $avgTurnaroundTime ?? 0,
            'total_tests' => VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
            })->count(),
        ];
    }

    /**
     * Get patient statistics
     */
    private function getPatientStats($startDate, $endDate)
    {
        $genderDistribution = Patient::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->get();

        $ageGroups = Patient::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('birth_date')
            ->get()
            ->groupBy(function($patient) {
                $age = $patient->birth_date->age;
                if ($age < 18) return 'Under 18';
                if ($age < 30) return '18-29';
                if ($age < 50) return '30-49';
                if ($age < 65) return '50-64';
                return '65+';
            })
            ->map(function($group) {
                return $group->count();
            });

        $insuranceStats = Patient::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('has_insurance, COUNT(*) as count')
            ->groupBy('has_insurance')
            ->get();

        return [
            'gender_distribution' => $genderDistribution,
            'age_groups' => $ageGroups,
            'insurance_stats' => $insuranceStats,
            'new_patients' => Patient::whereBetween('created_at', [$startDate, $endDate])->count(),
            'returning_patients' => Patient::whereHas('visits', function($query) use ($startDate, $endDate) {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
            })->where('created_at', '<', $startDate)->count(),
        ];
    }

    /**
     * Get performance statistics
     */
    private function getPerformanceStats($startDate, $endDate)
    {
        $completedTests = VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        })->where('status', 'completed')->count();

        $totalTests = VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        })->count();

        $completionRate = $totalTests > 0 ? ($completedTests / $totalTests) * 100 : 0;

        // For SQLite compatibility, calculate average processing time manually
        $visitTests = VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        })
        ->whereNotNull('performed_at')
        ->get();

        $totalHours = 0;
        $count = 0;
        foreach ($visitTests as $test) {
            if ($test->created_at && $test->performed_at) {
                $totalHours += $test->created_at->diffInHours($test->performed_at);
                $count++;
            }
        }
        $avgProcessingTime = $count > 0 ? $totalHours / $count : 0;

        return [
            'completion_rate' => round($completionRate, 2),
            'average_processing_time_hours' => round($avgProcessingTime ?? 0, 2),
            'completed_tests' => $completedTests,
            'total_tests' => $totalTests,
        ];
    }

    /**
     * Get trends data for charts
     */
    private function getTrendsData($startDate, $endDate)
    {
        // For SQLite compatibility, use date() function instead of DATE()
        $dailyVisits = Visit::whereBetween('visit_date', [$startDate, $endDate])
            ->selectRaw('date(visit_date) as date, COUNT(*) as count, SUM(final_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyTests = VisitTest::whereHas('visit', function($query) use ($startDate, $endDate) {
            $query->whereBetween('visit_date', [$startDate, $endDate]);
        })
        ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
        ->selectRaw('date(visits.visit_date) as date, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        return [
            'daily_visits' => $dailyVisits,
            'daily_tests' => $dailyTests,
        ];
    }

    /**
     * Get category statistics
     */
    private function getCategoryStats($startDate, $endDate)
    {
        try {
            return TestCategory::withCount(['labTests' => function($query) use ($startDate, $endDate) {
                $query->whereHas('visitTests', function($subQuery) use ($startDate, $endDate) {
                    $subQuery->whereHas('visit', function($visitQuery) use ($startDate, $endDate) {
                        $visitQuery->whereBetween('visit_date', [$startDate, $endDate]);
                    });
                });
            }])
            ->get()
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'test_count' => $category->lab_tests_count,
                    'revenue' => 0, // Simplified for now
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Category stats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top performing tests
     */
    private function getTopTests($startDate, $endDate)
    {
        try {
            return LabTest::whereHas('visitTests', function($query) use ($startDate, $endDate) {
                $query->whereHas('visit', function($subQuery) use ($startDate, $endDate) {
                    $subQuery->whereBetween('visit_date', [$startDate, $endDate]);
                });
            })
            ->withCount(['visitTests' => function($query) use ($startDate, $endDate) {
                $query->whereHas('visit', function($subQuery) use ($startDate, $endDate) {
                    $subQuery->whereBetween('visit_date', [$startDate, $endDate]);
                });
            }])
            ->with(['category'])
            ->orderBy('visit_tests_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($test) {
                return [
                    'id' => $test->id,
                    'name' => $test->name,
                    'code' => $test->code,
                    'category' => $test->category->name ?? 'Uncategorized',
                    'count' => $test->visit_tests_count,
                    'revenue' => 0, // Simplified for now
                    'price' => $test->price,
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Top tests error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity($startDate, $endDate)
    {
        try {
            $recentVisits = Visit::with(['patient', 'visitTests'])
                ->whereBetween('visit_date', [$startDate, $endDate])
                ->orderBy('visit_date', 'desc')
                ->limit(10)
                ->get()
                ->map(function($visit) {
                    return [
                        'id' => $visit->id,
                        'patient_name' => $visit->patient->name ?? 'Unknown',
                        'visit_date' => $visit->visit_date->format('Y-m-d'),
                        'test_count' => $visit->visitTests->count(),
                        'total_amount' => $visit->final_amount,
                        'status' => $visit->status,
                    ];
                });

            $recentReports = Report::with(['labRequest.patient'])
                ->whereBetween('generated_at', [$startDate, $endDate])
                ->orderBy('generated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($report) {
                    return [
                        'id' => $report->id,
                        'patient_name' => $report->labRequest->patient->name ?? 'Unknown',
                        'generated_at' => $report->generated_at->format('Y-m-d H:i'),
                        'status' => $report->status,
                    ];
                });

            return [
                'recent_visits' => $recentVisits,
                'recent_reports' => $recentReports,
            ];
        } catch (\Exception $e) {
            \Log::error('Recent activity error: ' . $e->getMessage());
            return [
                'recent_visits' => [],
                'recent_reports' => [],
            ];
        }
    }

    /**
     * Calculate percentage change
     */
    private function calculatePercentageChange($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }
}