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
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe HTTP client policy
    |--------------------------------------------------------------------------
    |
    | Bounded timeouts and retries applied to the shared Stripe HTTP client
    | (AppServiceProvider). The Stripe SDK defaults are 30s connect / 80s read
    | with 0 retries — far too long for any call that could ever run near a DB
    | lock. Keeping these small is a defense-in-depth backstop for PAY-03: even
    | though Stripe cancellation now runs strictly outside booking/room locks,
    | no Stripe call should hang a worker for over a minute.
    |
    */

    'stripe' => [
        'connect_timeout' => (int) env('STRIPE_CONNECT_TIMEOUT', 2),
        'read_timeout' => (int) env('STRIPE_READ_TIMEOUT', 5),
        'max_network_retries' => (int) env('STRIPE_MAX_NETWORK_RETRIES', 2),
    ],

];
