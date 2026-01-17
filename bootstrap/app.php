<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Apply security headers to all API requests
        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Hide detailed error information in production
        // Log errors internally but return generic response to users
        $exceptions->shouldRenderJsonWhen(function ($request, $exception) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        // Customize error responses for API requests
        $exceptions->render(function (\Throwable $e, $request) {
            // Only process API requests
            if (! $request->is('api/*')) {
                return null;
            }

            // Log the full error internally
            \Illuminate\Support\Facades\Log::error('Exception caught', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            // Return generic error response in production
            if (! config('app.debug')) {
                return response()->json([
                    'error' => 'An error occurred while processing your request',
                    'status' => 'error',
                ], 500);
            }

            // In development, return full error details
            return response()->json([
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        });
    })->create();
