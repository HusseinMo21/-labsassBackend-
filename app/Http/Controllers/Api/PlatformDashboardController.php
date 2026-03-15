<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformDashboardController extends Controller
{
    /**
     * Platform-wide stats for the system owner.
     */
    public function stats(Request $request)
    {
        $labs = Lab::count();
        $activeLabs = Lab::where('is_active', true)->count();

        $totalSubscriptionIncome = (float) SubscriptionPayment::sum('amount');
        $activeSubscriptionsCount = Subscription::where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();
        $trialsCount = Subscription::where('status', 'trial')->count();
        $expiringSoonCount = Subscription::where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();

        $totalUsers = User::whereNotNull('lab_id')->count();
        $activeUsers = User::whereNotNull('lab_id')->where('is_active', true)->count();

        $totalPatients = DB::table('patient')->count();
        $totalVisits = DB::table('visits')->count();
        $totalInvoices = DB::table('invoices')->count();
        $totalRevenue = (float) DB::table('invoices')->sum('amount_paid');

        $visitsThisMonth = DB::table('visits')
            ->whereYear('visit_date', now()->year)
            ->whereMonth('visit_date', now()->month)
            ->count();
        $patientsThisMonth = DB::table('patient')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
        $subscriptionIncomeThisMonth = (float) SubscriptionPayment::whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');

        $incomeByLab = SubscriptionPayment::query()
            ->join('subscriptions', 'subscription_payments.subscription_id', '=', 'subscriptions.id')
            ->select('subscriptions.lab_id', DB::raw('SUM(subscription_payments.amount) as total'))
            ->groupBy('subscriptions.lab_id')
            ->get()
            ->map(function ($row) {
                $lab = Lab::find($row->lab_id);
                return [
                    'lab_id' => $row->lab_id,
                    'lab_name' => $lab?->name ?? 'Unknown',
                    'total' => (float) $row->total,
                ];
            });

        $visitsByLab = DB::table('visits')
            ->select('lab_id', DB::raw('COUNT(*) as count'))
            ->groupBy('lab_id')
            ->get()
            ->map(function ($row) {
                $lab = Lab::find($row->lab_id);
                return [
                    'lab_id' => $row->lab_id,
                    'lab_name' => $lab?->name ?? 'Unknown',
                    'visits_count' => (int) $row->count,
                ];
            })
            ->sortByDesc('visits_count')
            ->values()
            ->take(10);

        $patientsByLab = DB::table('patient')
            ->select('lab_id', DB::raw('COUNT(*) as count'))
            ->groupBy('lab_id')
            ->get()
            ->map(function ($row) {
                $lab = Lab::find($row->lab_id);
                return [
                    'lab_id' => $row->lab_id,
                    'lab_name' => $lab?->name ?? 'Unknown',
                    'patients_count' => (int) $row->count,
                ];
            })
            ->sortByDesc('patients_count')
            ->values()
            ->take(10);

        $revenueByLab = DB::table('invoices')
            ->select('lab_id', DB::raw('SUM(amount_paid) as total'))
            ->groupBy('lab_id')
            ->get()
            ->map(function ($row) {
                $lab = Lab::find($row->lab_id);
                return [
                    'lab_id' => $row->lab_id,
                    'lab_name' => $lab?->name ?? 'Unknown',
                    'revenue' => (float) $row->total,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(10);

        $labsWithoutSubscription = Lab::where('is_active', true)
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('status', 'active')->where('expires_at', '>', now()))
            ->select('id', 'name', 'slug')
            ->get();

        return response()->json([
            'labs' => $labs,
            'active_labs' => $activeLabs,
            'total_subscription_income' => $totalSubscriptionIncome,
            'active_subscriptions_count' => $activeSubscriptionsCount,
            'trials_count' => $trialsCount,
            'expiring_soon_count' => $expiringSoonCount,
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_patients' => $totalPatients,
            'total_visits' => $totalVisits,
            'total_invoices' => $totalInvoices,
            'total_revenue' => $totalRevenue,
            'visits_this_month' => $visitsThisMonth,
            'patients_this_month' => $patientsThisMonth,
            'subscription_income_this_month' => $subscriptionIncomeThisMonth,
            'income_by_lab' => $incomeByLab,
            'visits_by_lab' => $visitsByLab,
            'patients_by_lab' => $patientsByLab,
            'revenue_by_lab' => $revenueByLab,
            'labs_without_subscription' => $labsWithoutSubscription,
        ]);
    }
}
