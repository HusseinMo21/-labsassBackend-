<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip rate limiting for authenticated users
        if ($request->user()) {
            return $next($request);
        }

        // Rate limit based on IP address
        $key = 'rate_limit:' . $request->ip();
        $maxAttempts = 60; // 60 requests per minute
        $decayMinutes = 1;

        $attempts = cache()->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'error' => 'rate_limit_exceeded',
                'retry_after' => $decayMinutes * 60
            ], 429);
        }

        // Increment attempts
        cache()->put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $attempts - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addMinutes($decayMinutes)->timestamp);

        return $response;
    }
}
