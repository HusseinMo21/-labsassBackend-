<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ShiftController extends Controller
{
    /**
     * Get current user's active shift
     */
    public function getCurrentShift(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            if (!in_array($user->role, ['staff', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only staff and admin members can access shift management'
                ], 403);
            }

            $currentShift = Shift::where('staff_id', $user->id)
                ->where('status', 'open')
                ->whereNotNull('opened_at')
                ->whereDate('opened_at', today())
                ->first();

            if ($currentShift) {
                try {
                    // Calculate real-time statistics
                    $visitsCount = \App\Models\Visit::where('shift_id', $currentShift->id)->count();
                    $paymentsCount = \App\Models\Payment::where('shift_id', $currentShift->id)->count();
                    $patientsCount = \App\Models\Visit::where('shift_id', $currentShift->id)
                        ->distinct('patient_id')
                        ->count('patient_id');
                    
                    // Calculate payment breakdown with error handling
                    try {
                        $paymentBreakdown = $currentShift->calculatePaymentBreakdown();
                    } catch (\Exception $e) {
                        Log::error('Error calculating payment breakdown: ' . $e->getMessage());
                        // Return default values if calculation fails
                        $paymentBreakdown = [
                            'total_collected' => 0,
                            'cash_collected' => 0,
                            'other_payments_collected' => 0,
                            'payment_breakdown' => [],
                        ];
                    }
                    
                    // Calculate expenses breakdown
                    try {
                        $expensesBreakdown = $currentShift->calculateExpensesBreakdown();
                    } catch (\Exception $e) {
                        Log::error('Error calculating expenses breakdown: ' . $e->getMessage());
                        $expensesBreakdown = [
                            'total_expenses' => 0,
                            'expenses_breakdown' => [],
                            'expenses_list' => [],
                        ];
                    }
                    
                    // Calculate final cash in bucket (cash collected - expenses)
                    $cashCollected = $paymentBreakdown['cash_collected'] ?? 0;
                    $totalExpenses = $expensesBreakdown['total_expenses'] ?? 0;
                    $finalCashInBucket = max(0, $cashCollected - $totalExpenses);
                    
                    // Ensure we have valid numbers
                    $visitsCount = $visitsCount ?? 0;
                    $paymentsCount = $paymentsCount ?? 0;
                    $patientsCount = $patientsCount ?? 0;
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $currentShift->id,
                            'shift_type' => $currentShift->shift_type,
                            'opened_at' => $currentShift->opened_at,
                            'closed_at' => $currentShift->closed_at,
                            'duration' => $currentShift->duration,
                            'status' => $currentShift->status,
                            'patients_served' => $patientsCount,
                            'visits_processed' => $visitsCount,
                            'payments_processed' => $paymentsCount,
                            'total_collected' => $paymentBreakdown['total_collected'] ?? 0,
                            'cash_collected' => $cashCollected,
                            'other_payments_collected' => $paymentBreakdown['other_payments_collected'] ?? 0,
                            'payment_breakdown' => $paymentBreakdown['payment_breakdown'] ?? [],
                            'total_expenses' => $totalExpenses,
                            'expenses_breakdown' => $expensesBreakdown['expenses_breakdown'] ?? [],
                            'expenses_list' => $expensesBreakdown['expenses_list'] ?? [],
                            'final_cash_in_bucket' => $finalCashInBucket,
                        ]
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error in getCurrentShift when shift exists: ' . $e->getMessage(), [
                        'shift_id' => $currentShift->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Error processing shift data: ' . $e->getMessage()
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'data' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getCurrentShift: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Open a new shift
     */
    public function openShift(Request $request)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['staff', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only staff and admin members can open shifts'
            ], 403);
        }

        // Check if user already has an open shift today
        $existingShift = Shift::where('staff_id', $user->id)
            ->where('status', 'open')
            ->whereDate('opened_at', today())
            ->first();

        if ($existingShift) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an open shift. Please close it before opening a new one.'
            ], 422);
        }

        $request->validate([
            'shift_type' => 'required|in:AM,PM,Night'
        ]);

        $shift = Shift::create([
            'staff_id' => $user->id,
            'shift_type' => $request->shift_type,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        Log::info('Shift opened', [
            'shift_id' => $shift->id,
            'staff_id' => $user->id,
            'staff_name' => $user->name,
            'shift_type' => $request->shift_type,
            'opened_at' => $shift->opened_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift opened successfully',
            'data' => [
                'id' => $shift->id,
                'shift_type' => $shift->shift_type,
                'opened_at' => $shift->opened_at,
                'duration' => $shift->duration,
            ]
        ]);
    }

    /**
     * Close the current shift
     */
    public function closeShift(Request $request)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['staff', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only staff and admin members can close shifts'
            ], 403);
        }

        $currentShift = Shift::where('staff_id', $user->id)
            ->where('status', 'open')
            ->whereDate('opened_at', today())
            ->first();

        if (!$currentShift) {
            return response()->json([
                'success' => false,
                'message' => 'No open shift found to close'
            ], 404);
        }

        // Calculate final statistics
        $visits = $currentShift->visits()->count();
        $patients = $currentShift->visits()->distinct('patient_id')->count();
        
        // Calculate payment breakdown
        $paymentBreakdown = $currentShift->calculatePaymentBreakdown();

        $currentShift->update([
            'visits_processed' => $visits,
            'payments_processed' => $currentShift->payments()->count(),
            'total_collected' => $paymentBreakdown['total_collected'],
            'cash_collected' => $paymentBreakdown['cash_collected'],
            'other_payments_collected' => $paymentBreakdown['other_payments_collected'],
            'payment_breakdown' => $paymentBreakdown['payment_breakdown'],
            'patients_served' => $patients,
            'notes' => $request->notes,
        ]);

        $currentShift->closeShift();

        Log::info('Shift closed', [
            'shift_id' => $currentShift->id,
            'staff_id' => $user->id,
            'staff_name' => $user->name,
            'shift_type' => $currentShift->shift_type,
            'closed_at' => $currentShift->closed_at,
            'patients_served' => $patients,
            'visits_processed' => $visits,
            'total_collected' => $paymentBreakdown['total_collected'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift closed successfully',
            'data' => [
                'id' => $currentShift->id,
                'shift_type' => $currentShift->shift_type,
                'opened_at' => $currentShift->opened_at,
                'closed_at' => $currentShift->closed_at,
                'duration' => $currentShift->duration,
                'patients_served' => $patients,
                'visits_processed' => $visits,
                'payments_processed' => $currentShift->payments()->count(),
                'total_collected' => $paymentBreakdown['total_collected'],
            ]
        ]);
    }

    /**
     * Get shift closing report
     */
    public function getShiftReport(Request $request, $shiftId)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['staff', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only staff and admin members can access shift reports'
            ], 403);
        }

        $shift = Shift::where('id', $shiftId)
            ->where('staff_id', $user->id)
            ->first();

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift not found'
            ], 404);
        }

        // Calculate real-time statistics (like getCurrentShift does)
        $visitsCount = \App\Models\Visit::where('shift_id', $shift->id)->count();
        $paymentsCount = \App\Models\Payment::where('shift_id', $shift->id)->count();
        $patientsCount = \App\Models\Visit::where('shift_id', $shift->id)
            ->distinct('patient_id')
            ->count('patient_id');
        
        // Calculate payment breakdown
        $paymentBreakdown = $shift->calculatePaymentBreakdown();
        
        // Calculate expenses breakdown
        $expensesBreakdown = $shift->calculateExpensesBreakdown();
        
        $reportData = $shift->getShiftReportData();

        return response()->json([
            'success' => true,
            'data' => [
                'shift_info' => [
                    'id' => $shift->id,
                    'staff_name' => $shift->staff->name,
                    'shift_type' => $shift->shift_type,
                    'opened_at' => $shift->opened_at,
                    'closed_at' => $shift->closed_at,
                    'duration' => $shift->duration,
                    'patients_served' => $patientsCount,
                    'visits_processed' => $visitsCount,
                    'payments_processed' => $paymentsCount,
                    'total_collected' => $paymentBreakdown['total_collected'],
                    'cash_collected' => $paymentBreakdown['cash_collected'],
                    'other_payments_collected' => $paymentBreakdown['other_payments_collected'],
                    'payment_breakdown' => $paymentBreakdown['payment_breakdown'],
                    'total_expenses' => $expensesBreakdown['total_expenses'] ?? 0,
                    'expense_breakdown' => $expensesBreakdown['expense_breakdown'] ?? [],
                    'expenses_list' => $expensesBreakdown['expenses_list'] ?? [],
                    'notes' => $shift->notes,
                ],
                'patients' => $reportData,
            ]
        ]);
    }

    /**
     * Get shifts by date for the current user
     */
    public function getShiftsByDate(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            if (!in_array($user->role, ['staff', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only staff and admin members can access shifts'
                ], 403);
            }

            $validator = \Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format',
                    'errors' => $validator->errors()
                ], 422);
            }

            $date = \Carbon\Carbon::parse($request->date)->format('Y-m-d');

            $shifts = Shift::where('staff_id', $user->id)
                ->whereDate('opened_at', $date)
                ->orderBy('opened_at', 'asc')
                ->get();

            $shiftsData = $shifts->map(function ($shift) {
                // Calculate payment breakdown
                $paymentBreakdown = $shift->calculatePaymentBreakdown();
                
                // Calculate expenses breakdown
                $expensesBreakdown = $shift->calculateExpensesBreakdown();
                
                // Calculate final cash in bucket
                $cashCollected = $paymentBreakdown['cash_collected'] ?? 0;
                $totalExpenses = $expensesBreakdown['total_expenses'] ?? 0;
                $finalCashInBucket = max(0, $cashCollected - $totalExpenses);

                return [
                    'id' => $shift->id,
                    'shift_type' => $shift->shift_type,
                    'opened_at' => $shift->opened_at,
                    'closed_at' => $shift->closed_at,
                    'duration' => $shift->duration,
                    'status' => $shift->status,
                    'patients_served' => $shift->patients_served ?? 0,
                    'visits_processed' => $shift->visits_processed ?? 0,
                    'payments_processed' => $shift->payments_processed ?? 0,
                    'total_collected' => $paymentBreakdown['total_collected'] ?? 0,
                    'cash_collected' => $cashCollected,
                    'other_payments_collected' => $paymentBreakdown['other_payments_collected'] ?? 0,
                    'payment_breakdown' => $paymentBreakdown['payment_breakdown'] ?? [],
                    'total_expenses' => $totalExpenses,
                    'expenses_breakdown' => $expensesBreakdown['expenses_breakdown'] ?? [],
                    'expenses_list' => $expensesBreakdown['expenses_list'] ?? [],
                    'final_cash_in_bucket' => $finalCashInBucket,
                    'notes' => $shift->notes,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $shiftsData,
                'date' => $date
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getShiftsByDate: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'date' => $request->date ?? 'not provided'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shift history for the current user
     */
    public function getShiftHistory(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            if (!in_array($user->role, ['staff', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only staff and admin members can access shift history'
                ], 403);
            }

            $shifts = Shift::where('staff_id', $user->id)
                ->where('status', 'closed')
                ->whereNotNull('closed_at')
                ->orderBy('closed_at', 'desc')
                ->get();

            $shiftHistory = $shifts->map(function ($shift) {
                return [
                    'id' => $shift->id,
                    'shift_type' => $shift->shift_type,
                    'opened_at' => $shift->opened_at,
                    'closed_at' => $shift->closed_at,
                    'duration' => $shift->duration,
                    'status' => $shift->status,
                    'patients_served' => $shift->patients_served ?? 0,
                    'visits_processed' => $shift->visits_processed ?? 0,
                    'payments_processed' => $shift->payments_processed ?? 0,
                    'total_collected' => $shift->total_collected ?? 0,
                    'cash_collected' => $shift->cash_collected ?? 0,
                    'other_payments_collected' => $shift->other_payments_collected ?? 0,
                    'payment_breakdown' => $shift->payment_breakdown ?? [],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $shiftHistory
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getShiftHistory: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
