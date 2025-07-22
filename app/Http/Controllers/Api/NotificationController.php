<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\VisitTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::with(['visitTest.labTest', 'visitTest.visit.patient']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('recipient_type')) {
            $query->where('recipient_type', $request->recipient_type);
        }

        $notifications = $query->latest()->paginate(15);
        return response()->json($notifications);
    }

    public function show($id)
    {
        $notification = Notification::with(['visitTest.labTest', 'visitTest.visit.patient'])->findOrFail($id);
        return response()->json($notification);
    }

    public function sendResultNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'visit_test_id' => 'required|exists:visit_tests,id',
            'recipient_type' => 'required|in:patient,doctor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $visitTest = VisitTest::with('visit.patient')->findOrFail($request->visit_test_id);
        
        // Check if notification already exists
        $existing = Notification::where('visit_test_id', $request->visit_test_id)
                               ->where('recipient_type', $request->recipient_type)
                               ->where('type', '!=', 'critical_alert')
                               ->first();

        if ($existing) {
            return response()->json(['message' => 'Notification already sent for this test'], 400);
        }

        $notification = Notification::createResultNotification($visitTest, $request->recipient_type);
        
        // Here you would integrate with actual SMS/email service
        // For now, we'll just mark it as sent
        $notification->markAsSent();

        return response()->json($notification->load(['visitTest.labTest', 'visitTest.visit.patient']));
    }

    public function resendNotification($id)
    {
        $notification = Notification::findOrFail($id);
        
        if ($notification->status === 'sent' || $notification->status === 'delivered') {
            return response()->json(['message' => 'Notification already sent'], 400);
        }

        // Here you would integrate with actual SMS/email service
        // For now, we'll just mark it as sent
        $notification->markAsSent();

        return response()->json($notification->load(['visitTest.labTest', 'visitTest.visit.patient']));
    }

    public function getPendingNotifications()
    {
        $notifications = Notification::with(['visitTest.labTest', 'visitTest.visit.patient'])
                                   ->where('status', 'pending')
                                   ->latest()
                                   ->get();

        return response()->json($notifications);
    }

    public function getStats()
    {
        $stats = [
            'total_notifications' => Notification::count(),
            'pending' => Notification::where('status', 'pending')->count(),
            'sent' => Notification::where('status', 'sent')->count(),
            'delivered' => Notification::where('status', 'delivered')->count(),
            'failed' => Notification::where('status', 'failed')->count(),
            'sms' => Notification::where('type', 'sms')->count(),
            'email' => Notification::where('type', 'email')->count(),
            'critical_alerts' => Notification::where('type', 'critical_alert')->count(),
        ];

        return response()->json($stats);
    }

    public function markAsDelivered($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsDelivered();
        
        return response()->json($notification->load(['visitTest.labTest', 'visitTest.visit.patient']));
    }

    public function markAsFailed(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsFailed($request->error_message);
        
        return response()->json($notification->load(['visitTest.labTest', 'visitTest.visit.patient']));
    }
} 