<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Basic health check endpoint
     */
    public function health()
    {
        $status = 'healthy';
        $checks = [];
        $timestamp = now()->toISOString();

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $status = 'unhealthy';
        }

        // Cache check
        try {
            Cache::put('health_check', 'ok', 60);
            $cacheResult = Cache::get('health_check');
            $checks['cache'] = $cacheResult === 'ok' ? 'ok' : 'error';
        } catch (\Exception $e) {
            $checks['cache'] = 'error: ' . $e->getMessage();
            $status = 'unhealthy';
        }

        // Storage check
        try {
            Storage::disk('local')->put('health_check.txt', 'ok');
            $storageResult = Storage::disk('local')->get('health_check.txt');
            Storage::disk('local')->delete('health_check.txt');
            $checks['storage'] = $storageResult === 'ok' ? 'ok' : 'error';
        } catch (\Exception $e) {
            $checks['storage'] = 'error: ' . $e->getMessage();
            $status = 'unhealthy';
        }

        // Application info
        $info = [
            'status' => $status,
            'timestamp' => $timestamp,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
        ];

        $httpStatus = $status === 'healthy' ? 200 : 503;

        return response()->json($info, $httpStatus);
    }

    /**
     * Detailed health check with more information
     */
    public function detailed()
    {
        $status = 'healthy';
        $details = [];
        $timestamp = now()->toISOString();

        // Database details
        try {
            $dbConnection = DB::connection();
            $details['database'] = [
                'status' => 'ok',
                'driver' => $dbConnection->getDriverName(),
                'version' => $dbConnection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            ];
        } catch (\Exception $e) {
            $details['database'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
            $status = 'unhealthy';
        }

        // Cache details
        try {
            $cacheDriver = config('cache.default');
            $details['cache'] = [
                'status' => 'ok',
                'driver' => $cacheDriver,
            ];
        } catch (\Exception $e) {
            $details['cache'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
            $status = 'unhealthy';
        }

        // Queue details
        try {
            $queueDriver = config('queue.default');
            $details['queue'] = [
                'status' => 'ok',
                'driver' => $queueDriver,
            ];
        } catch (\Exception $e) {
            $details['queue'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // System info
        $details['system'] = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'uptime' => sys_getloadavg(),
        ];

        $info = [
            'status' => $status,
            'timestamp' => $timestamp,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'details' => $details,
        ];

        $httpStatus = $status === 'healthy' ? 200 : 503;

        return response()->json($info, $httpStatus);
    }
}
