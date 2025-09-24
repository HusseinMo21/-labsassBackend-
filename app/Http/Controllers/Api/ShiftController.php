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
        $user = Auth::user();
        
        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Only staff members can access shift management'
            ], 403);
        }

        $currentShift = Shift::where('staff_id', $user->id)
            ->where('status', 'open')
            ->whereDate('opened_at', today())
            ->first();

        if ($currentShift) {
            // Calculate real-time statistics
            $visitsCount = \App\Models\Visit::where('shift_id', $currentShift->id)->count();
            $paymentsCount = \App\Models\Payment::where('shift_id', $currentShift->id)->count();
            $totalCollected = \App\Models\Payment::where('shift_id', $currentShift->id)->sum('amount');
            $patientsCount = \App\Models\Visit::where('shift_id', $currentShift->id)
                ->distinct('patient_id')
                ->count('patient_id');
            
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
                    'total_collected' => $totalCollected,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => null
        ]);
    }

    /**
     * Open a new shift
     */
    public function openShift(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Only staff members can open shifts'
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
        
        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Only staff members can close shifts'
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
        $payments = $currentShift->payments()->sum('amount');
        $patients = $currentShift->visits()->distinct('patient_id')->count();

        $currentShift->update([
            'visits_processed' => $visits,
            'payments_processed' => $currentShift->payments()->count(),
            'total_collected' => $payments,
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
            'total_collected' => $payments,
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
                'total_collected' => $payments,
            ]
        ]);
    }

    /**
     * Get shift closing report
     */
    public function getShiftReport(Request $request, $shiftId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Only staff members can access shift reports'
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
                    'patients_served' => $shift->patients_served,
                    'visits_processed' => $shift->visits_processed,
                    'payments_processed' => $shift->payments_processed,
                    'total_collected' => $shift->total_collected,
                    'notes' => $shift->notes,
                ],
                'patients' => $reportData,
            ]
        ]);
    }

    /**
     * Get shift history for the current user
     */
    public function getShiftHistory(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Only staff members can access shift history'
            ], 403);
        }

        $shifts = Shift::where('staff_id', $user->id)
            ->where('status', 'closed')
            ->orderBy('closed_at', 'desc')
            ->get();

        $shiftHistory = $shifts->map(function ($shift) {
            return [
                'id' => $shift->id,
                'date' => $shift->opened_at->format('Y-m-d'),
                'type' => $shift->shift_type,
                'duration' => $shift->duration,
                'patients_served' => $shift->patients_served,
                'visits_processed' => $shift->visits_processed,
                'payments_processed' => $shift->payments_processed,
                'total_collected' => $shift->total_collected,
                'status' => $shift->status,
                'opened_at' => $shift->opened_at,
                'closed_at' => $shift->closed_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $shiftHistory
        ]);
    }
}
