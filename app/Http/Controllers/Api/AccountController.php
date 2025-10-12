<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of accounts
     */
    public function index(Request $request)
    {
        $query = Account::with(['transactions' => function ($q) {
            $q->orderBy('transaction_date', 'desc');
        }]);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $accounts = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($accounts);
    }

    /**
     * Store a newly created account
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'initial_amount' => 'nullable|numeric|min:0',
            'initial_paid' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $initialAmount = $request->initial_amount ?? 0;
            $initialPaid = $request->initial_paid ?? 0;
            $remainingBalance = max(0, $initialAmount - $initialPaid);

            // Create the account
            $account = Account::create([
                'name' => $request->name,
                'description' => $request->description,
                'total_amount' => $initialAmount,
                'total_paid' => $initialPaid,
                'remaining_balance' => $remainingBalance,
                'status' => $remainingBalance > 0 ? 'active' : 'completed',
            ]);

            // Create initial transaction if there's an initial amount
            if ($initialAmount > 0) {
                AccountTransaction::create([
                    'account_id' => $account->id,
                    'transaction_date' => now()->toDateString(),
                    'amount' => $initialAmount,
                    'paid_amount' => $initialPaid,
                    'remaining_amount' => $remainingBalance,
                    'type' => 'purchase',
                    'description' => 'Initial transaction',
                    'notes' => $request->description,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Account created successfully',
                'account' => $account->load('transactions')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to create account: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified account
     */
    public function show(Account $account)
    {
        $account->load(['transactions' => function ($q) {
            $q->orderBy('transaction_date', 'desc');
        }]);

        return response()->json($account);
    }

    /**
     * Update the specified account
     */
    public function update(Request $request, Account $account)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
        ]);

        $account->update($request->only(['name', 'description', 'status']));

        return response()->json([
            'message' => 'Account updated successfully',
            'account' => $account->load('transactions')
        ]);
    }

    /**
     * Remove the specified account
     */
    public function destroy(Account $account)
    {
        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /**
     * Add a new transaction to an account
     */
    public function addTransaction(Request $request, Account $account)
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'type' => ['required', Rule::in(['purchase', 'payment'])],
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $transactionDate = $request->transaction_date;
            $amount = $request->amount;
            $paidAmount = $request->paid_amount;
            $type = $request->type;

            // Calculate new totals based on transaction type
            if ($type === 'purchase') {
                // Adding to the debt
                $newTotalAmount = $account->total_amount + $amount;
                $newTotalPaid = $account->total_paid + $paidAmount;
            } else {
                // Making a payment
                $newTotalAmount = $account->total_amount; // Total amount doesn't change for payments
                $newTotalPaid = $account->total_paid + $paidAmount;
            }

            $newRemainingBalance = max(0, $newTotalAmount - $newTotalPaid);

            // Update account totals
            $account->update([
                'total_amount' => $newTotalAmount,
                'total_paid' => $newTotalPaid,
                'remaining_balance' => $newRemainingBalance,
                'status' => $newRemainingBalance <= 0 ? 'completed' : 'active',
            ]);

            // Create the transaction record
            $transaction = AccountTransaction::create([
                'account_id' => $account->id,
                'transaction_date' => $transactionDate,
                'amount' => $amount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $newRemainingBalance,
                'type' => $type,
                'description' => $request->description,
                'notes' => $request->notes,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaction added successfully',
                'transaction' => $transaction,
                'account' => $account->fresh()->load('transactions')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to add transaction: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a payment to an existing account
     */
    public function addPayment(Request $request, Account $account)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $paymentDate = $request->payment_date;
            $paymentAmount = $request->amount;

            // Calculate new totals
            $newTotalPaid = $account->total_paid + $paymentAmount;
            $newRemainingBalance = max(0, $account->total_amount - $newTotalPaid);

            // Update account totals
            $account->update([
                'total_paid' => $newTotalPaid,
                'remaining_balance' => $newRemainingBalance,
                'status' => $newRemainingBalance <= 0 ? 'completed' : 'active',
            ]);

            // Create the payment transaction record
            $transaction = AccountTransaction::create([
                'account_id' => $account->id,
                'transaction_date' => $paymentDate,
                'amount' => 0, // No amount added to debt for payments
                'paid_amount' => $paymentAmount,
                'remaining_amount' => $newRemainingBalance,
                'type' => 'payment',
                'description' => $request->description ?? 'Payment made',
                'notes' => $request->notes,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment added successfully',
                'transaction' => $transaction,
                'account' => $account->fresh()->load('transactions')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to add payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark account as completed (fully paid)
     */
    public function markCompleted(Account $account)
    {
        DB::beginTransaction();
        try {
            $account->update([
                'status' => 'completed',
                'remaining_balance' => 0,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Account marked as completed',
                'account' => $account->fresh()->load('transactions')
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to mark account as completed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get account summary statistics
     */
    public function getSummary()
    {
        $totalAccounts = Account::count();
        $activeAccounts = Account::where('status', 'active')->count();
        $completedAccounts = Account::where('status', 'completed')->count();
        $totalDebt = Account::sum('remaining_balance');
        $totalPaid = Account::sum('total_paid');
        $totalAmount = Account::sum('total_amount');

        return response()->json([
            'total_accounts' => $totalAccounts,
            'active_accounts' => $activeAccounts,
            'completed_accounts' => $completedAccounts,
            'total_debt' => $totalDebt,
            'total_paid' => $totalPaid,
            'total_amount' => $totalAmount,
        ]);
    }
}