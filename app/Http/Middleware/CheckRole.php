<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userRole = $this->mapRole($request->user()->role);
        
        if (!in_array($userRole, $roles)) {
            return response()->json(['message' => 'Access denied'], 403);
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