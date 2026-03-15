<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /**
     * Handle an incoming request.
     * Only platform admins (users with lab_id = null) can access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->lab_id !== null) {
            return response()->json([
                'message' => 'Only platform administrators can perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
