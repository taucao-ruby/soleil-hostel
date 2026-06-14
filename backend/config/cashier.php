<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | Soleil Hostel prices and settles in Vietnamese đồng. Laravel Cashier's
    | merged vendor config defaults this key to 'usd', which silently makes
    | config('cashier.currency', 'vnd') resolve to 'usd' when CASHIER_CURRENCY
    | is unset — the in-code 'vnd' fallback never wins because the key is always
    | present from the vendor merge. Defining it here (loaded before Cashier's
    | mergeConfigFrom) makes 'vnd' the source-of-truth default for every
    | environment, with CASHIER_CURRENCY still available as a per-env override.
    |
    | This default flows into bookings.payment_currency (column default baked at
    | migrate time + CreateBookingService) and Cashier's Stripe charge currency.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'vnd'),

];
