<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Handle OPTIONS requests for CORS preflight
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }
        
        if (!$request->user()) {
            $response = response()->json(['message' => 'Unauthorized'], 401);
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
            return $response;
        }

        $userRole = $this->mapRole($request->user()->role);
        
        if (!in_array($userRole, $roles)) {
            $response = response()->json(['message' => 'Access denied'], 403);
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
            return $response;
        }

        return $next($request);
    }

    /**
     * Map old role values to new role values for frontend compatibility
     */
    private function mapRole($role)
    {
        $roleMapping = [
            'admin' => 'admin',
            'lab_tech' => 'staff',
            'accountant' => 'staff',
            'patient' => 'patient',
        ];
        return $roleMapping[$role] ?? 'staff';
    }
} 