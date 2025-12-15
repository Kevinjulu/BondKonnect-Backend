<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => match (env('APP_ENV')) {
                'production' => env('MAIL_HOST_PROD'),
                'uat' => env('MAIL_HOST_UAT'),
                'dev' => env('MAIL_HOST_DEV'),
                default => env('MAIL_HOST', '127.0.0.1'),
            },
            'port' => match (env('APP_ENV')) {
                'production' => env('MAIL_PORT_PROD'),
                'uat' => env('MAIL_PORT_UAT'),
                'dev' => env('MAIL_PORT_DEV'),
                default => env('MAIL_PORT', 2525),
            },
            'username' => match (env('APP_ENV')) {
                'production' => env('MAIL_USERNAME_PROD'),
                'uat' => env('MAIL_USERNAME_UAT'),
                'dev' => env('MAIL_USERNAME_DEV'),
                default => env('MAIL_USERNAME'),
            },
            'password' => match (env('APP_ENV')) {
                'production' => env('MAIL_PASSWORD_PROD'),
                'uat' => env('MAIL_PASSWORD_UAT'),
                'dev' => env('MAIL_PASSWORD_DEV'),
                default => env('MAIL_PASSWORD'),
            },
            'timeout' => null,
            'local_domain' => match (env('APP_ENV')) {
                'production' => env('MAIL_EHLO_DOMAIN_PROD', parse_url(env('APP_URL_PROD', 'http://localhost'), PHP_URL_HOST)),
                'uat' => env('MAIL_EHLO_DOMAIN_UAT', parse_url(env('APP_URL_UAT', 'http://localhost'), PHP_URL_HOST)),
                'dev' => env('MAIL_EHLO_DOMAIN_DEV', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
                default => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            },
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */
    'from' => [
        'address' => match (env('APP_ENV')) {
            'production' => env('MAIL_FROM_ADDRESS_PROD'),
            'uat' => env('MAIL_FROM_ADDRESS_UAT'),
            'dev' => env('MAIL_FROM_ADDRESS'),
            default => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        },
        'name' => match (env('APP_ENV')) {
            'production' => env('MAIL_FROM_NAME_PROD'),
            'uat' => env('MAIL_FROM_NAME_UAT'),
            'dev' => env('MAIL_FROM_NAME'),
            default => env('MAIL_FROM_NAME', 'Example'),
        },
    ],

];
