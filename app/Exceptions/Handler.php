<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle authentication exceptions for API routes
        if ($request->is('api/*') && $e instanceof \Illuminate\Auth\AuthenticationException) {
            \Log::info('Handling AuthenticationException for API route: ' . $request->getUri());
            
            $response = response()->json([
                'message' => 'Unauthenticated',
                'error' => 'authentication_required'
            ], 401);
            
            // Add CORS headers
            $response->headers->set('Access-Control-Allow-Origin', '*', true);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS', true);
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin', true);
            $response->headers->set('Access-Control-Allow-Credentials', 'true', true);
            
            return $response;
        }
        
        // Add CORS headers to all API error responses
        if ($request->is('api/*')) {
            $response = parent::render($request, $e);
            
            // Add CORS headers to the response
            if ($response->headers) {
                $response->headers->set('Access-Control-Allow-Origin', '*', true);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS', true);
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin', true);
                $response->headers->set('Access-Control-Allow-Credentials', 'true', true);
            }
            
            return $response;
        }
        
        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, \Illuminate\Auth\AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            \Log::info('Handling unauthenticated for API request: ' . $request->getUri());
            
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'authentication_required'
            ], 401, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }

        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }
} 