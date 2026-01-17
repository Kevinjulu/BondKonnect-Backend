<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks - disallow framing in any context
        $response->header('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing - force browser to respect Content-Type
        $response->header('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection in older browsers
        $response->header('X-XSS-Protection', '1; mode=block');

        // Control referrer information sent to other sites
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser feature access (geolocation, microphone, camera, etc.)
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');

        // Force HTTPS connections (Strict-Transport-Security)
        if (config('app.env') === 'production') {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Content Security Policy - restrict resource loading
        $csp = "default-src 'self'; ".
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".  // unsafe-inline for Laravel error pages, remove in strict mode
               "style-src 'self' 'unsafe-inline'; ".
               "img-src 'self' data: https:; ".
               "font-src 'self' data:; ".
               "connect-src 'self' ".config('app.url').'; '.
               "frame-ancestors 'none'; ".
               "base-uri 'self'; ".
               "form-action 'self'";

        $response->header('Content-Security-Policy', $csp);

        return $response;
    }
}
