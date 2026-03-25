<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\Patient;
use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitTest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabInsightsController extends Controller
{
    /**
     * Lab-scoped analytics: every metric is limited to the current user's lab.
     */
    public function getInsights(Request $request)
    {
        $periodDays = max(1, min(1095, (int) $request->get('period', 30)));
        $labId = $this->currentLabId();

        if (! $labId) {
            return response()->json([
                'success' => false,
                'message' => 'Lab context is required for lab insights.',
            ], 403);
        }

        $rangeEnd = Carbon::today();
        $rangeStart = Carbon::today()->copy()->subDays($periodDays);
        $prevRangeEnd = $rangeStart->copy()->subDay();
        $prevRangeStart = $rangeStart->copy()->subDays($periodDays);

        try {
            $lab = Lab::query()->whereKey($labId)->first(['id', 'name', 'slug']);

            $insights = [
                'overview' => $this->getOverviewStats($labId, $rangeStart, $rangeEnd, $prevRangeStart, $prevRangeEnd),
                'revenue' => $this->getSimpleRevenueStats($labId, $rangeStart, $rangeEnd),
                'tests' => $this->getSimpleTestStats($labId, $rangeStart, $rangeEnd),
                'patients' => $this->getSimplePatientStats($labId, $rangeStart, $rangeEnd),
                'performance' => $this->getSimplePerformanceStats($labId, $rangeStart, $rangeEnd),
                'trends' => $this->getSimpleTrendsData($labId),
                'categories' => $this->getSimpleCategoryStats($labId, $rangeStart, $rangeEnd),
                'top_tests' => $this->getSimpleTopTests($labId, $rangeStart, $rangeEnd),
                'recent_activity' => $this->getSimpleRecentActivity($labId, $rangeStart, $rangeEnd),
            ];

            return response()->json([
                'success' => true,
                'data' => $insights,
                'lab' => $lab ? [
                    'id' => $lab->id,
                    'name' => $lab->name,
                    'slug' => $lab->slug,
                ] : ['id' => $labId, 'name' => null, 'slug' => null],
                'period' => $periodDays,
                'date_range' => [
                    'start' => $rangeStart->format('Y-m-d'),
                    'end' => $rangeEnd->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Lab Insights Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'period' => $periodDays,
                'lab_id' => $labId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch insights data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function visitsForLab(int $labId): Builder
    {
        return Visit::withoutGlobalScope('lab')->where('visits.lab_id', $labId);
    }

    protected function patientsForLab(int $labId): Builder
    {
        return Patient::withoutGlobalScope('lab')->where('patient.lab_id', $labId);
    }

    protected function visitTestsForLab(int $labId): Builder
    {
        return VisitTest::query()->where('visit_tests.lab_id', $labId);
    }

    /** Visits for this lab with visit_date in [from, to] (inclusive, date column). */
    protected function visitsForLabBetween(int $labId, Carbon $from, Carbon $to): Builder
    {
        return $this->visitsForLab($labId)
            ->where('visit_date', '>=', $from->toDateString())
            ->where('visit_date', '<=', $to->toDateString());
    }

    /** Visit-test rows for this lab whose parent visit falls in the date range. */
    protected function visitTestsForLabBetween(int $labId, Carbon $from, Carbon $to): Builder
    {
        return $this->visitTestsForLab($labId)
            ->whereHas('visit', function ($q) use ($labId, $from, $to) {
                $q->withoutGlobalScope('lab')
                    ->where('visits.lab_id', $labId)
                    ->where('visit_date', '>=', $from->toDateString())
                    ->where('visit_date', '<=', $to->toDateString());
            });
    }

    private function getOverviewStats(int $labId, Carbon $rangeStart, Carbon $rangeEnd, Carbon $prevStart, Carbon $prevEnd): array
    {
        try {
            $countVisits = fn (Carbon $a, Carbon $b) => $this->visitsForLabBetween($labId, $a, $b)->count();
            $sumRevenue = fn (Carbon $a, Carbon $b) => (float) ($this->visitsForLabBetween($labId, $a, $b)->sum('final_amount') ?? 0);

            $distinctPatients = fn (Carbon $a, Carbon $b) => (int) DB::table('visits')
                ->where('lab_id', $labId)
                ->where('visit_date', '>=', $a->toDateString())
                ->where('visit_date', '<=', $b->toDateString())
                ->distinct()
                ->count('patient_id');

            $countTests = fn (Carbon $a, Carbon $b) => $this->visitTestsForLabBetween($labId, $a, $b)->count();

            $curV = $countVisits($rangeStart, $rangeEnd);
            $prevV = $countVisits($prevStart, $prevEnd);
            $curP = $distinctPatients($rangeStart, $rangeEnd);
            $prevP = $distinctPatients($prevStart, $prevEnd);
            $curT = $countTests($rangeStart, $rangeEnd);
            $prevT = $countTests($prevStart, $prevEnd);
            $curR = $sumRevenue($rangeStart, $rangeEnd);
            $prevR = $sumRevenue($prevStart, $prevEnd);
        } catch (\Exception $e) {
            return [
                'visits' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'patients' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'tests' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'revenue' => ['current' => 0, 'previous' => 0, 'change' => 0],
            ];
        }

        return [
            'visits' => [
                'current' => $curV,
                'previous' => $prevV,
                'change' => $this->calculatePercentageChange($prevV, $curV),
            ],
            'patients' => [
                'current' => $curP,
                'previous' => $prevP,
                'change' => $this->calculatePercentageChange($prevP, $curP),
            ],
            'tests' => [
                'current' => $curT,
                'previous' => $prevT,
                'change' => $this->calculatePercentageChange($prevT, $curT),
            ],
            'revenue' => [
                'current' => $curR,
                'previous' => $prevR,
                'change' => $this->calculatePercentageChange($prevR, $curR),
            ],
        ];
    }

    /**
     * Revenue aligned with overview cards — all sums come from visits for this lab.
     * (Avoids legacy payments table / model column mismatches that zeroed the whole block.)
     */
    private function getSimpleRevenueStats(int $labId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        try {
            $vq = $this->visitsForLabBetween($labId, $rangeStart, $rangeEnd);

            $totalRevenue = (float) ($vq->clone()->sum('final_amount') ?? 0);
            $totalDiscounts = (float) ($vq->clone()->sum('discount_amount') ?? 0);
            $upfrontPayments = (float) ($vq->clone()->sum('upfront_payment') ?? 0);
            $outstandingBalance = (float) ($vq->clone()->sum('remaining_balance') ?? 0);
            $totalVisits = $vq->clone()->count();

            $pmCase = "CASE WHEN payment_method IS NULL OR TRIM(payment_method) = '' THEN 'unspecified' ELSE payment_method END";

            $paymentMethods = $vq->clone()
                ->selectRaw("{$pmCase} as pm, COUNT(*) as cnt, COALESCE(SUM(final_amount), 0) as amt")
                ->groupBy(DB::raw($pmCase))
                ->get()
                ->map(fn ($r) => [
                    'payment_method' => $r->pm ?? 'unspecified',
                    'count' => (int) $r->cnt,
                    'amount' => (float) $r->amt,
                ])
                ->values()
                ->all();

            return [
                'total_revenue' => $totalRevenue,
                'total_discounts' => round($totalDiscounts, 2),
                'net_revenue' => $totalRevenue,
                'upfront_payments' => round($upfrontPayments, 2),
                'outstanding_balance' => round($outstandingBalance, 2),
                'average_visit_value' => $totalVisits > 0 ? round($totalRevenue / $totalVisits, 2) : 0,
                'payment_methods' => $paymentMethods,
            ];
        } catch (\Exception $e) {
            \Log::warning('Lab insights revenue stats: '.$e->getMessage(), ['lab_id' => $labId]);

            return [
                'total_revenue' => 0,
                'total_discounts' => 0,
                'net_revenue' => 0,
                'upfront_payments' => 0,
                'outstanding_balance' => 0,
                'average_visit_value' => 0,
                'payment_methods' => [],
            ];
        }
    }

    private function getSimpleTestStats(int $labId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        try {
            $base = $this->visitTestsForLabBetween($labId, $rangeStart, $rangeEnd);

            $breakdown = (clone $base)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => ['status' => $r->status ?? 'unknown', 'count' => (int) $r->count])
                ->values()
                ->all();

            if ($breakdown === []) {
                $breakdown = [
                    ['status' => 'completed', 'count' => 0],
                    ['status' => 'pending', 'count' => 0],
                    ['status' => 'in_progress', 'count' => 0],
                ];
            }

            $from = $rangeStart->toDateString();
            $to = $rangeEnd->toDateString();

            $avgTurnaround = DB::table('visit_tests')
                ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
                ->join('lab_tests', 'visit_tests.lab_test_id', '=', 'lab_tests.id')
                ->where('visit_tests.lab_id', $labId)
                ->where('visits.lab_id', $labId)
                ->where('visits.visit_date', '>=', $from)
                ->where('visits.visit_date', '<=', $to)
                ->whereNotNull('lab_tests.turnaround_time_hours')
                ->avg('lab_tests.turnaround_time_hours');

            return [
                'status_breakdown' => $breakdown,
                'average_turnaround_hours' => $avgTurnaround ? round((float) $avgTurnaround, 2) : 0,
                'total_tests' => (clone $base)->count(),
            ];
        } catch (\Exception $e) {
            return [
                'status_breakdown' => [],
                'average_turnaround_hours' => 0,
                'total_tests' => 0,
            ];
        }
    }

    private function getSimplePatientStats(int $labId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        try {
            $patientIds = $this->visitsForLabBetween($labId, $rangeStart, $rangeEnd)
                ->pluck('patient_id')
                ->unique()
                ->filter()
                ->values();

            if ($patientIds->isEmpty()) {
                return [
                    'gender_distribution' => [],
                    'age_groups' => [],
                    'insurance_stats' => [],
                    'new_patients' => 0,
                    'returning_patients' => 0,
                ];
            }

            $pq = $this->patientsForLab($labId)->whereIn('patient.id', $patientIds);

            $genderDistribution = (clone $pq)
                ->selectRaw('gender, COUNT(*) as count')
                ->groupBy('gender')
                ->get()
                ->map(function ($item) {
                    $g = strtolower((string) ($item->gender ?? ''));

                    return [
                        'gender' => $g === 'male' || $g === 'ذكر' ? 'Male' : ($g === 'female' || $g === 'أنثى' ? 'Female' : 'Other'),
                        'count' => (int) $item->count,
                    ];
                });

            $ageGroups = [];
            try {
                $ageGroups = (clone $pq)
                    ->selectRaw("
                        CASE
                            WHEN age <= 18 THEN '0-18'
                            WHEN age <= 35 THEN '19-35'
                            WHEN age <= 50 THEN '36-50'
                            ELSE '51+'
                        END as age_group,
                        COUNT(*) as count
                    ")
                    ->groupBy('age_group')
                    ->pluck('count', 'age_group')
                    ->map(fn ($c) => (int) $c)
                    ->toArray();
            } catch (\Exception $e) {
                $ageGroups = [];
            }

            $insuranceStats = [];
            try {
                $insuranceStats = (clone $pq)
                    ->selectRaw('has_insurance, COUNT(*) as count')
                    ->groupBy('has_insurance')
                    ->get()
                    ->map(fn ($r) => [
                        'has_insurance' => (bool) $r->has_insurance,
                        'count' => (int) $r->count,
                    ])
                    ->toArray();
            } catch (\Exception $e) {
                $insuranceStats = [];
            }

            $distinctWithVisit = $patientIds->count();
            $newPatients = $this->patientsForLab($labId)
                ->whereIn('patient.id', $patientIds)
                ->where('created_at', '>=', $rangeStart->copy()->startOfDay())
                ->where('created_at', '<=', $rangeEnd->copy()->endOfDay())
                ->count();
            $returningPatients = max(0, $distinctWithVisit - $newPatients);

            return [
                'gender_distribution' => $genderDistribution->toArray(),
                'age_groups' => $ageGroups,
                'insurance_stats' => $insuranceStats,
                'new_patients' => $newPatients,
                'returning_patients' => $returningPatients,
            ];
        } catch (\Exception $e) {
            return [
                'gender_distribution' => [],
                'age_groups' => [],
                'insurance_stats' => [],
                'new_patients' => 0,
                'returning_patients' => 0,
            ];
        }
    }

    private function getSimplePerformanceStats(int $labId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        try {
            $base = $this->visitTestsForLabBetween($labId, $rangeStart, $rangeEnd);
            $totalTests = (clone $base)->count();
            $completedTests = (clone $base)->where('status', 'completed')->count();
            $completionRate = $totalTests > 0 ? round(($completedTests / $totalTests) * 100, 1) : 0;

            $visitTests = (clone $base)
                ->whereNotNull('performed_at')
                ->get(['created_at', 'performed_at']);

            $totalHours = 0;
            $count = 0;
            foreach ($visitTests as $test) {
                if ($test->created_at && $test->performed_at) {
                    $totalHours += $test->created_at->diffInHours($test->performed_at);
                    $count++;
                }
            }
            $avgProcessingTime = $count > 0 ? round($totalHours / $count, 2) : 0;

            return [
                'completion_rate' => $completionRate,
                'average_processing_time_hours' => $avgProcessingTime,
                'completed_tests' => $completedTests,
                'total_tests' => $totalTests,
            ];
        } catch (\Exception $e) {
            return [
                'completion_rate' => 0,
                'average_processing_time_hours' => 0,
                'completed_tests' => 0,
                'total_tests' => 0,
            ];
        }
    }

    private function getSimpleTrendsData(int $labId): array
    {
        try {
            $dailyVisits = [];
            $dailyTests = [];

            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dateStr = $date->format('Y-m-d');

                $visitCount = (clone $this->visitsForLab($labId))->whereDate('visit_date', $dateStr)->count();
                $visitRevenue = (float) ((clone $this->visitsForLab($labId))->whereDate('visit_date', $dateStr)->sum('final_amount') ?? 0);

                $testCount = $this->visitTestsForLab($labId)
                    ->whereHas('visit', function ($q) use ($labId, $dateStr) {
                        $q->withoutGlobalScope('lab')
                            ->where('visits.lab_id', $labId)
                            ->whereDate('visits.visit_date', $dateStr);
                    })
                    ->count();

                $dailyVisits[] = [
                    'date' => $date->format('M d'),
                    'count' => $visitCount,
                    'revenue' => $visitRevenue,
                ];

                $dailyTests[] = [
                    'date' => $date->format('M d'),
                    'count' => $testCount,
                ];
            }

            return [
                'daily_visits' => $dailyVisits,
                'daily_tests' => $dailyTests,
            ];
        } catch (\Exception $e) {
            return [
                'daily_visits' => [],
                'daily_tests' => [],
            ];
        }
    }

    private function getSimpleCategoryStats(int $labId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        try {
            $from = $rangeStart->toDateString();
            $to = $rangeEnd->toDateString();

            $rows = DB::table('visit_tests')
                ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
                ->join('lab_tests', 'visit_tests.lab_test_id', '=', 'lab_tests.id')
                ->leftJoin('test_categories', 'lab_tests.category_id', '=', 'test_categories.id')
                ->where('visit_tests.lab_id', $labId)
                ->where('visits.lab_id', $labId)
                ->where('visits.visit_date', '>=', $from)
                ->where('visits.visit_date', '<=', $to)
                ->whereNotNull('lab_tests.category_id')
                ->groupBy('test_categories.id', 'test_categories.name')
                ->selectRaw('
                    test_categories.id,
                    test_categories.name,
                    COUNT(visit_tests.id) as test_count,
                    COALESCE(SUM(COALESCE(visit_tests.final_price, visit_tests.price, 0)), 0) as revenue
                ')
                ->orderByDesc('test_count')
                ->get();

            return $rows->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'name' => $r->name ?? 'Uncategorized',
                    'test_count' => (int) $r->test_count,
                    'revenue' => round((float) $r->revenue, 2),
                ];
            })->values()->all();
        } catch (\Exception $e) {
            \Log::error('Lab insights category stats: '.$e->getMessage());

            return [];
        }
    }

    private function getSimpleTopTests(int $labId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        try {
            $from = $rangeStart->toDateString();
            $to = $rangeEnd->toDateString();

            $rows = DB::table('visit_tests')
                ->join('visits', 'visit_tests.visit_id', '=', 'visits.id')
                ->join('lab_tests', 'visit_tests.lab_test_id', '=', 'lab_tests.id')
                ->leftJoin('test_categories', 'lab_tests.category_id', '=', 'test_categories.id')
                ->where('visit_tests.lab_id', $labId)
                ->where('visits.lab_id', $labId)
                ->whereNotNull('visit_tests.lab_test_id')
                ->where('visits.visit_date', '>=', $from)
                ->where('visits.visit_date', '<=', $to)
                ->groupBy('lab_tests.id', 'lab_tests.name', 'lab_tests.code', 'test_categories.name')
                ->selectRaw('
                    lab_tests.id,
                    lab_tests.name as catalog_name,
                    lab_tests.code,
                    test_categories.name as category_name,
                    COUNT(visit_tests.id) as cnt,
                    COALESCE(SUM(COALESCE(visit_tests.final_price, visit_tests.price, 0)), 0) as line_revenue,
                    MAX(visit_tests.test_name_snapshot) as name_snapshot,
                    MAX(visit_tests.custom_test_name) as custom_name
                ')
                ->orderByDesc('cnt')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) {
                $snap = isset($r->name_snapshot) ? trim((string) $r->name_snapshot) : '';
                $custom = isset($r->custom_name) ? trim((string) $r->custom_name) : '';
                $catalog = trim((string) ($r->catalog_name ?? ''));
                $code = trim((string) ($r->code ?? ''));

                $displayName = $snap !== '' ? $snap : ($custom !== '' ? $custom : $catalog);
                if ($displayName === '' || strtolower($displayName) === 'tests') {
                    $displayName = $code !== '' ? $code : ($catalog !== '' ? $catalog : 'Test #'.(int) $r->id);
                }

                $cnt = (int) $r->cnt;
                $rev = (float) $r->line_revenue;
                $avgPrice = $cnt > 0 ? round($rev / $cnt, 2) : 0;

                return [
                    'id' => (int) $r->id,
                    'name' => $displayName,
                    'code' => $code !== '' ? $code : 'N/A',
                    'category' => $r->category_name ?? 'General',
                    'count' => $cnt,
                    'revenue' => round($rev, 2),
                    'price' => $avgPrice,
                ];
            })->values()->all();
        } catch (\Exception $e) {
            \Log::error('Lab insights top tests: '.$e->getMessage());

            return [];
        }
    }

    private function getSimpleRecentActivity(int $labId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        try {
            $recentVisits = $this->visitsForLabBetween($labId, $rangeStart, $rangeEnd)
                ->with(['patient', 'visitTests'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function (Visit $visit) {
                    return [
                        'id' => $visit->id,
                        'patient_name' => $visit->patient->name ?? 'Unknown',
                        'visit_date' => $visit->visit_date?->format('Y-m-d') ?? '',
                        'test_count' => $visit->visitTests->count(),
                        'total_amount' => (float) ($visit->final_amount ?? 0),
                        'status' => $visit->status ?? 'pending',
                    ];
                });

            $recentReports = Report::query()
                ->whereHas('labRequest', function ($q) use ($labId) {
                    $q->withoutGlobalScope('lab')->where('lab_requests.lab_id', $labId);
                })
                ->where('generated_at', '>=', $rangeStart->copy()->startOfDay())
                ->where('generated_at', '<=', $rangeEnd->copy()->endOfDay())
                ->with(['labRequest.patient'])
                ->orderByDesc('generated_at')
                ->limit(5)
                ->get()
                ->map(function (Report $report) {
                    return [
                        'id' => $report->id,
                        'patient_name' => $report->labRequest?->patient?->name ?? 'Unknown',
                        'generated_at' => $report->generated_at?->format('Y-m-d H:i') ?? '',
                        'status' => $report->status ?? '',
                    ];
                });

            return [
                'recent_visits' => $recentVisits->toArray(),
                'recent_reports' => $recentReports->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'recent_visits' => [],
                'recent_reports' => [],
            ];
        }
    }

    private function calculatePercentageChange($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
