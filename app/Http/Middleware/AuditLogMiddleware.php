<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log for authenticated users
        if (!auth()->check()) {
            return $response;
        }

        // Skip logging for certain routes
        $skipRoutes = [
            'api/audit-logs',
            'api/notifications',
            'api/sample-tracking/stats',
            'api/critical-values/stats',
            'api/test-panels/stats',
        ];

        foreach ($skipRoutes as $route) {
            if (str_contains($request->path(), $route)) {
                return $response;
            }
        }

        // Determine action based on HTTP method
        $action = $this->getActionFromRequest($request);
        
        // Get model info from route parameters
        $modelInfo = $this->getModelInfoFromRequest($request);
        
        // Create description
        $description = $this->createDescription($request, $action, $modelInfo);

        // Log the action
        AuditLog::log($action, null, $description);

        return $response;
    }

    private function getActionFromRequest(Request $request)
    {
        switch ($request->method()) {
            case 'GET':
                return 'view';
            case 'POST':
                return 'create';
            case 'PUT':
            case 'PATCH':
                return 'update';
            case 'DELETE':
                return 'delete';
            default:
                return 'other';
        }
    }

    private function getModelInfoFromRequest(Request $request)
    {
        $path = $request->path();
        
        if (str_contains($path, 'patients')) {
            return ['type' => 'Patient', 'id' => $request->route('patient')];
        }
        
        if (str_contains($path, 'tests')) {
            return ['type' => 'LabTest', 'id' => $request->route('test')];
        }
        
        if (str_contains($path, 'visits')) {
            return ['type' => 'Visit', 'id' => $request->route('visit')];
        }
        
        if (str_contains($path, 'invoices')) {
            return ['type' => 'Invoice', 'id' => $request->route('invoice')];
        }
        
        if (str_contains($path, 'users')) {
            return ['type' => 'User', 'id' => $request->route('user')];
        }
        
        if (str_contains($path, 'inventory')) {
            return ['type' => 'InventoryItem', 'id' => $request->route('inventoryItem')];
        }
        
        if (str_contains($path, 'sample-tracking')) {
            return ['type' => 'SampleTracking', 'id' => $request->route('sample_tracking')];
        }
        
        if (str_contains($path, 'critical-values')) {
            return ['type' => 'CriticalValue', 'id' => $request->route('critical_value')];
        }
        
        if (str_contains($path, 'test-panels')) {
            return ['type' => 'TestPanel', 'id' => $request->route('test_panel')];
        }
        
        return null;
    }

    private function createDescription(Request $request, $action, $modelInfo)
    {
        $user = auth()->user()->name;
        $path = $request->path();
        
        if ($modelInfo) {
            $modelName = $modelInfo['type'];
            $modelId = $modelInfo['id'];
            
            switch ($action) {
                case 'view':
                    return "User {$user} viewed {$modelName} #{$modelId}";
                case 'create':
                    return "User {$user} created new {$modelName}";
                case 'update':
                    return "User {$user} updated {$modelName} #{$modelId}";
                case 'delete':
                    return "User {$user} deleted {$modelName} #{$modelId}";
            }
        }
        
        // Fallback descriptions
        switch ($action) {
            case 'view':
                return "User {$user} accessed {$path}";
            case 'create':
                return "User {$user} created new record via {$path}";
            case 'update':
                return "User {$user} updated record via {$path}";
            case 'delete':
                return "User {$user} deleted record via {$path}";
            default:
                return "User {$user} performed action via {$path}";
        }
    }
} 