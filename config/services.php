<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_NOTIFICATIONS_CHANNEL'),
        ],
    ],

    'mpesa' => [
        'env' => env('MPESA_ENVIRONMENT', 'sandbox'),
        'key' => env('MPESA_CONSUMER_KEY'),
        'secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
        'callback_url' => env('MPESA_STK_PUSH_CALLBACK_URL'),
        // Header name that M-Pesa will use to sign callbacks (if provided by provider)
        'signature_header' => env('MPESA_SIGNATURE_HEADER', 'X-Mpesa-Signature'),
        // Optional: a secret used to validate HMAC signatures. If unset, passkey will be used.
        'signature_secret' => env('MPESA_SIGNATURE_SECRET'),
        // Optional: comma-separated list of allowed IPs for callbacks
        'allowed_ips' => explode(',', env('MPESA_ALLOWED_IPS', '')),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        // The webhook ID configured in PayPal for server-side verification
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

];
