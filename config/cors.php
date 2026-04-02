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
    | Professional Production Approach
    |--------------------------------------------------------------------------
    | We use CORS_ALLOWED_ORIGINS to dynamically control access.
    | No domains are hardcoded here to ensure security and flexibility.
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', env('CORS_ALLOWED_ORIGIN', 'http://localhost:3000,http://localhost:5173,http://localhost:4000,https://bondkonnect.up.railway.app'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
