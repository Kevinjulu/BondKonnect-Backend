<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    | CORS_ALLOWED_ORIGINS (or the legacy CORS_ALLOWED_ORIGIN alias) controls
    | which origins may send credentialed cross-origin requests to this API.
    |
    | The default list covers local development ports and both Railway
    | production domains:
    |   - https://bondkonnect.up.railway.app        (frontend SPA)
    |   - https://laravel-backend-api.up.railway.app (this API, for same-origin
    |     tool calls and Railway's internal health-check proxy)
    |
    | Override via the CORS_ALLOWED_ORIGINS env var in Railway's variable panel
    | when adding custom domains without redeploying.
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', env('CORS_ALLOWED_ORIGIN', implode(',', [
        // ── Local development ────────────────────────────────────────────────
        'http://localhost:3000',
        'http://localhost:4000',
        'http://localhost:5173',

        // ── Railway production ───────────────────────────────────────────────
        'https://bondkonnect.up.railway.app',
        'https://laravel-backend-api.up.railway.app',
    ])))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
