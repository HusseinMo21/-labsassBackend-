<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PdfCorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('PdfCorsMiddleware processing request: ' . $request->getUri());
        
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            \Log::info('Handling OPTIONS request for CORS');
            return response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400',
            ]);
        }
        
        // Check for token in query parameters and set auth user
        if ($request->has('token')) {
            try {
                $token = $request->get('token');
                \Log::info('PdfCorsMiddleware: Found token in query: ' . substr($token, 0, 10) . '...');
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($personalAccessToken && $personalAccessToken->tokenable) {
                    auth()->setUser($personalAccessToken->tokenable);
                    \Log::info('PdfCorsMiddleware: Authenticated user via token: ' . $personalAccessToken->tokenable->id . ' (' . $personalAccessToken->tokenable->email . ')');
                } else {
                    \Log::warning('PdfCorsMiddleware: No valid token found for: ' . substr($token, 0, 10) . '...');
                }
            } catch (\Exception $e) {
                \Log::warning('PdfCorsMiddleware: Failed to authenticate via token: ' . $e->getMessage());
            }
        } elseif ($authHeader = $request->header('Authorization')) {
            try {
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    \Log::info('PdfCorsMiddleware: Found token in Authorization header: ' . substr($token, 0, 10) . '...');
                    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                    if ($personalAccessToken && $personalAccessToken->tokenable) {
                        auth()->setUser($personalAccessToken->tokenable);
                        \Log::info('PdfCorsMiddleware: Authenticated user via Authorization header: ' . $personalAccessToken->tokenable->id . ' (' . $personalAccessToken->tokenable->email . ')');
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('PdfCorsMiddleware: Failed to authenticate via Authorization header: ' . $e->getMessage());
            }
        }

        // Process the request
        $response = $next($request);

        // Force CORS headers to the response - override any existing ones
        // Always use headers->set() with replace=true to ensure headers are set correctly
        $response->headers->set('Access-Control-Allow-Origin', '*', true);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS', true);
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin', true);
        $response->headers->set('Access-Control-Allow-Credentials', 'true', true);
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Type, Content-Disposition, Content-Length', true);
        
        // Also try to set headers using header() method if available (for compatibility)
        if (method_exists($response, 'header')) {
            try {
                $response->header('Access-Control-Allow-Origin', '*');
                $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
                $response->header('Access-Control-Allow-Credentials', 'true');
                $response->header('Access-Control-Expose-Headers', 'Content-Type, Content-Disposition, Content-Length');
            } catch (\Exception $e) {
                // Ignore if header() method fails
            }
        }

        return $response;
    }
}
