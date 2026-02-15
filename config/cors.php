<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS Options
    |--------------------------------------------------------------------------
    |
    | The allowed_origins, allowed_headers and allowed_methods options are
    | set to accept all by default. You can adjust these settings as needed.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Environment-driven allowed origins
    |--------------------------------------------------------------------------
    | In production, set CORS_ALLOWED_ORIGIN to your frontend's URL (e.g.
    | https://your-frontend.vercel.app). For convenience during development we
    | fall back to allowing all origins when the env is unset.
    */
    'allowed_origins' => env('CORS_ALLOWED_ORIGIN') ? [env('CORS_ALLOWED_ORIGIN')] : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', false),

];
