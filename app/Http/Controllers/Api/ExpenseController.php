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
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('amount', 'like', "%{$searchTerm}%")
                  ->orWhereHas('author', function ($authorQuery) use ($searchTerm) {
                      $authorQuery->where('name', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // Date range filter
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->where('date', '<=', $request->end_date);
        }

        $expenses = $query->orderBy('date', 'desc')->paginate(15);

        return response()->json($expenses);
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense = Expense::create([
            'name' => $request->name,
            'amount' => $request->amount,
            'date' => $request->date,
            'author' => Auth::id(),
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
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $expense->update([
            'name' => $request->name,
            'amount' => $request->amount,
            'date' => $request->date,
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
            'this_month' => Expense::whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount'),
            'this_year' => Expense::whereYear('date', now()->year)
                ->sum('amount'),
        ];

        return response()->json($stats);
    }
}