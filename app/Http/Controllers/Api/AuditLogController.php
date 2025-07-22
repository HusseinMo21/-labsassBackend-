<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->latest()->paginate(20);
        return response()->json($logs);
    }

    public function show($id)
    {
        $log = AuditLog::with('user')->findOrFail($id);
        return response()->json($log);
    }

    public function getActivityByUser($userId)
    {
        $logs = AuditLog::with('user')
                       ->where('user_id', $userId)
                       ->latest()
                       ->paginate(20);

        return response()->json($logs);
    }

    public function getActivityByModel($modelType, $modelId)
    {
        $logs = AuditLog::with('user')
                       ->where('model_type', $modelType)
                       ->where('model_id', $modelId)
                       ->latest()
                       ->get();

        return response()->json($logs);
    }

    public function getStats(Request $request)
    {
        $query = AuditLog::query();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stats = [
            'total_actions' => $query->count(),
            'actions_by_type' => $query->selectRaw('action, COUNT(*) as count')
                                     ->groupBy('action')
                                     ->pluck('count', 'action'),
            'actions_by_model' => $query->selectRaw('model_type, COUNT(*) as count')
                                      ->groupBy('model_type')
                                      ->pluck('count', 'model_type'),
            'actions_by_user' => $query->selectRaw('users.name, COUNT(audit_logs.id) as count')
                                     ->join('users', 'audit_logs.user_id', '=', 'users.id')
                                     ->groupBy('users.id', 'users.name')
                                     ->orderBy('count', 'desc')
                                     ->limit(10)
                                     ->pluck('count', 'name'),
        ];

        return response()->json($stats);
    }

    public function export(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        $logs = $query->latest()->get();

        $csvData = [];
        $csvData[] = ['Date', 'User', 'Action', 'Model Type', 'Model ID', 'Description', 'IP Address'];

        foreach ($logs as $log) {
            $csvData[] = [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user ? $log->user->name : 'System',
                $log->action,
                $log->model_type,
                $log->model_id,
                $log->description,
                $log->ip_address,
            ];
        }

        $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
        
        return response($this->arrayToCsv($csvData))
               ->header('Content-Type', 'text/csv')
               ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function arrayToCsv($data)
    {
        $output = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
} 