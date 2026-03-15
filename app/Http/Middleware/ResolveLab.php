<?php

namespace App\Http\Middleware;

use App\Models\Lab;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ResolveLab
{
    /**
     * Reserved subdomains that are not lab identifiers.
     */
    protected array $reservedSubdomains = ['www', 'api', 'patient', 'admin'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Local development: localhost, 127.0.0.1 (with or without port)
        $hostBase = explode(':', $host)[0] ?? $host;
        if (in_array($hostBase, ['localhost', '127.0.0.1'])) {
            // Allow X-Lab-ID header so users from any lab can use their context
            $headerLabId = $request->header('X-Lab-ID');
            $labId = $headerLabId && ctype_digit($headerLabId) ? (int) $headerLabId : 1;
            if ($labId > 0 && Lab::where('id', $labId)->exists()) {
                app()->instance('current_lab_id', $labId);
            } else {
                app()->instance('current_lab_id', 1);
            }
            return $next($request);
        }

        $subdomain = explode('.', $host)[0] ?? null;

        if (!$subdomain || in_array(strtolower($subdomain), $this->reservedSubdomains)) {
            // For reserved subdomains, use default lab (1) for backward compatibility
            app()->instance('current_lab_id', 1);
            return $next($request);
        }

        $lab = Cache::remember("lab:{$subdomain}", 3600, function () use ($subdomain) {
            return Lab::where('subdomain', $subdomain)
                ->orWhere('slug', $subdomain)
                ->where('is_active', true)
                ->first();
        });

        if (!$lab) {
            return response()->json(['message' => 'Lab not found'], 404);
        }

        app()->instance('current_lab', $lab);
        app()->instance('current_lab_id', $lab->id);

        return $next($request);
    }
}
