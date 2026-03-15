<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * List subscriptions with optional filters.
     */
    public function index(Request $request)
    {
        $query = Subscription::with(['lab', 'plan']);

        if ($request->filled('lab_id')) {
            $query->where('lab_id', $request->lab_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $subscriptions = $query->latest()->paginate($perPage);

        return response()->json($subscriptions);
    }

    /**
     * Create a subscription for any lab (including free trial).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lab_id' => 'required|exists:labs,id',
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:active,expired,cancelled,trial',
            'starts_at' => 'required|date',
            'expires_at' => 'required|date|after_or_equal:starts_at',
            'amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'add_initial_payment' => 'boolean',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'transaction_id' => 'nullable|string|max:255',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $amount = $validated['amount'] ?? $plan->price;

        $subscription = Subscription::create([
            'lab_id' => $validated['lab_id'],
            'plan_id' => $validated['plan_id'],
            'status' => $validated['status'],
            'starts_at' => $validated['starts_at'],
            'expires_at' => $validated['expires_at'],
            'amount' => $amount,
            'notes' => $validated['notes'] ?? null,
        ]);

        if (!empty($validated['add_initial_payment']) && !empty($validated['payment_amount'])) {
            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'amount' => $validated['payment_amount'],
                'paid_at' => now(),
                'payment_method' => $validated['payment_method'] ?? null,
                'transaction_id' => $validated['transaction_id'] ?? null,
            ]);
        }

        $subscription->load(['lab', 'plan']);

        return response()->json($subscription, 201);
    }

    /**
     * Show a single subscription.
     */
    public function show(Subscription $subscription)
    {
        $subscription->load(['lab', 'plan', 'payments']);

        return response()->json($subscription);
    }

    /**
     * Update a subscription.
     */
    public function update(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:active,expired,cancelled,trial',
            'starts_at' => 'sometimes|date',
            'expires_at' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        $subscription->update($validated);

        $subscription->load(['lab', 'plan']);

        return response()->json($subscription);
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled']);

        $subscription->load(['lab', 'plan']);

        return response()->json($subscription);
    }

    /**
     * Add a payment to a subscription.
     */
    public function addPayment(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'] ?? now(),
            'payment_method' => $validated['payment_method'] ?? null,
            'transaction_id' => $validated['transaction_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($payment, 201);
    }

    /**
     * Stats: total income, income per lab, labs with subscriptions.
     */
    public function stats(Request $request)
    {
        $totalIncome = SubscriptionPayment::sum('amount');

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
                    'lab_slug' => $lab?->slug ?? null,
                    'total' => (float) $row->total,
                ];
            });

        $labsWithActiveSubscription = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('lab:id,name,slug', 'plan:id,name,price')
            ->get()
            ->map(function ($sub) {
                return [
                    'subscription_id' => $sub->id,
                    'lab_id' => $sub->lab_id,
                    'lab_name' => $sub->lab->name ?? 'Unknown',
                    'lab_slug' => $sub->lab->slug ?? null,
                    'plan_name' => $sub->plan->name ?? 'Unknown',
                    'plan_price' => (float) $sub->plan->price,
                    'status' => $sub->status,
                    'expires_at' => $sub->expires_at->toIso8601String(),
                ];
            });

        return response()->json([
            'total_income' => (float) $totalIncome,
            'income_by_lab' => $incomeByLab,
            'labs_with_active_subscription' => $labsWithActiveSubscription,
            'active_subscriptions_count' => $labsWithActiveSubscription->count(),
        ]);
    }
}
