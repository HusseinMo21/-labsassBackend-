<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiCsrfMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip CSRF for GET, HEAD, OPTIONS requests
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Skip CSRF for login and logout routes
        if ($request->is('api/auth/login') || $request->is('api/auth/logout')) {
            return $next($request);
        }

        // Check if request is from a stateful domain (SPA)
        if ($this->isStatefulRequest($request)) {
            // Verify CSRF token for stateful requests
            if (!$request->hasValidSignature() && !$this->tokensMatch($request)) {
                return response()->json([
                    'message' => 'CSRF token mismatch.',
                    'error' => 'csrf_token_mismatch'
                ], 419);
            }
        }

        return $next($request);
    }

    /**
     * Determine if the request is from a stateful domain
     */
    protected function isStatefulRequest(Request $request): bool
    {
        $statefulDomains = config('sanctum.stateful', []);
        $referer = $request->headers->get('referer');
        
        if (!$referer) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        
        foreach ($statefulDomains as $domain) {
            if ($refererHost === $domain || str_ends_with($refererHost, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the CSRF tokens match
     */
    protected function tokensMatch(Request $request): bool
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
        
        if (!$token) {
            return false;
        }

        return hash_equals(session()->token(), $token);
    }
}
