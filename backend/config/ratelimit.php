<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Disable application rate limiting (E2E ONLY)
    |--------------------------------------------------------------------------
    |
    | When true AND the app is not in production, the auth/booking rate limiters
    | (login, booking, refresh-token, csrf-token) become Limit::none(). This is
    | set ONLY by the E2E backend bootstrap (.github/workflows/e2e.yml) via
    | DISABLE_RATE_LIMITING: the nightly Playwright run executes every flow across
    | 4 browser projects sharing one runner IP and one seeded user, which trips
    | the 5/min-per-email + 20/min-per-IP login limits.
    |
    | The `php artisan test` suite does NOT set this flag, so its rate-limit
    | feature tests keep their limits. RateLimiterServiceProvider double-gates on
    | app()->isProduction() so a leaked production env var can never disable the
    | brute-force protection.
    |
    */
    'disable' => env('DISABLE_RATE_LIMITING', false),
];
