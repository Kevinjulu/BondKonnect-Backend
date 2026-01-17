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

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:4000',           // Development (Next.js)
        'http://127.0.0.1:4000',           // Development localhost
        'https://bondkonnect.dev',         // Staging domain
        'https://app.bondkonnect.com',     // Production domain
    ],

    'allowed_origins_patterns' => [
        '#^https:\/\/[\w-]+\.bondkonnect\.com$#',  // Subdomains of bondkonnect.com
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 3600,  // Cache preflight for 1 hour

    'supports_credentials' => true,  // Allow cookies in CORS requests

];
