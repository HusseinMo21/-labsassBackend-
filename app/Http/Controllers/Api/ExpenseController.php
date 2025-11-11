<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses
     */
    public function index(Request $request)
    {
        $query = Expense::with('author');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', "%{$searchTerm}%")
                  ->orWhere('category', 'like', "%{$searchTerm}%")
                  ->orWhere('amount', 'like', "%{$searchTerm}%")
                  ->orWhereHas('author', function ($authorQuery) use ($searchTerm) {
                      $authorQuery->where('name', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // Date range filter
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->where('expense_date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->where('expense_date', '<=', $request->end_date);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->paginate(15);

        return response()->json($expenses);
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'expense_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense = Expense::create([
            'description' => $request->description,
            'amount' => $request->amount,
            'category' => $request->category ?? 'General',
            'expense_date' => $request->expense_date ?? now()->toDateString(),
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Expense created successfully',
            'expense' => $expense->load('author'),
        ], 201);
    }

    /**
     * Display the specified expense
     */
    public function show($id)
    {
        $expense = Expense::with('author')->findOrFail($id);
        return response()->json($expense);
    }

    /**
     * Update the specified expense
     */
    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'expense_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense->update([
            'description' => $request->description,
            'amount' => $request->amount,
            'category' => $request->category ?? $expense->category ?? 'General',
            'expense_date' => $request->expense_date ?? $expense->expense_date ?? now()->toDateString(),
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Expense updated successfully',
            'expense' => $expense->load('author'),
        ]);
    }

    /**
     * Remove the specified expense
     */
    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }

    /**
     * Get expense statistics
     */
    public function stats()
    {
        $stats = [
            'total_expenses' => Expense::count(),
            'total_amount' => Expense::sum('amount'),
            'this_month' => Expense::whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
            'this_year' => Expense::whereYear('expense_date', now()->year)
                ->sum('amount'),
        ];

        return response()->json($stats);
    }
}