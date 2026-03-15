<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToLab
{
    /**
     * Handle an incoming request.
     * Verify the authenticated user has access to the resolved lab.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $currentLabId = app('current_lab_id');

        // Platform admin (lab_id = null) can access all labs
        if ($user->lab_id === null) {
            return $next($request);
        }

        // User must belong to the current lab
        if ($currentLabId && $user->lab_id != $currentLabId) {
            return response()->json([
                'message' => 'You do not have access to this lab.',
            ], 403);
        }

        return $next($request);
    }
}
