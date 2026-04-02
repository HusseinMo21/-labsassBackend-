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

        /*
         * Local SPA (localhost): ResolveLab defaults current_lab_id to 1 when X-Lab-ID is missing.
         * After refresh, /api/auth/user runs with Bearer token only → false 403 for lab_id !== 1.
         * Align context to the authenticated user's lab when header is omitted.
         */
        $hostBase = explode(':', $request->getHost())[0] ?? '';
        if (in_array($hostBase, ['localhost', '127.0.0.1'], true) && ! $request->header('X-Lab-ID')) {
            app()->instance('current_lab_id', (int) $user->lab_id);
            $currentLabId = (int) $user->lab_id;
        }

        // User must belong to the current lab
        if ($currentLabId && (int) $user->lab_id !== (int) $currentLabId) {
            return response()->json([
                'message' => 'You do not have access to this lab.',
            ], 403);
        }

        return $next($request);
    }
}
