<?php

use Laravel\Sanctum\Sanctum;

return [
    // Domains that receive stateful SPA authentication cookies.
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    // Safety net for routes using raw auth:sanctum.
    'expiration' => 60 * 24,

    'short_lived_token_expiration_minutes' => (int) env(
        'SANCTUM_SHORT_LIVED_EXPIRATION_MINUTES',
        60
    ),

    'long_lived_token_expiration_days' => (int) env(
        'SANCTUM_LONG_LIVED_EXPIRATION_DAYS',
        30
    ),

    'max_refresh_count_per_hour' => (int) env(
        'SANCTUM_MAX_REFRESH_COUNT_PER_HOUR',
        10
    ),

    'single_device_login' => (bool) env(
        'SANCTUM_SINGLE_DEVICE_LOGIN',
        true
    ),

    'delete_old_tokens_after_days' => (int) env(
        'SANCTUM_DELETE_OLD_TOKENS_AFTER_DAYS',
        7
    ),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'soleil_'),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    // httpOnly cookie auth options.
    'cookie_name' => env('SANCTUM_COOKIE_NAME', 'soleil_token'),
    'cookie_secure' => env('APP_ENV') === 'production',
    'cookie_http_only' => true,
    'cookie_same_site' => 'strict',
    'cookie_domain' => env('SESSION_DOMAIN', null),

    'verify_device_fingerprint' => (bool) env(
        'SANCTUM_VERIFY_DEVICE_FINGERPRINT',
        true
    ),
];
