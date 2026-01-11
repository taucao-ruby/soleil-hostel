<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cancellation Policy Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the cancellation and refund policies for bookings.
    | All time values are in hours relative to the check-in date.
    |
    */

    'cancellation' => [

        /*
        |--------------------------------------------------------------------------
        | Full Refund Window (hours)
        |--------------------------------------------------------------------------
        |
        | Number of hours before check-in when a full refund is available.
        | Default: 48 hours (2 days before check-in)
        |
        */
        'full_refund_hours' => env('BOOKING_FULL_REFUND_HOURS', 48),

        /*
        |--------------------------------------------------------------------------
        | Partial Refund Window (hours)
        |--------------------------------------------------------------------------
        |
        | Number of hours before check-in when a partial refund is available.
        | Guests cancelling within this window but after full_refund_hours
        | receive a partial refund.
        | Default: 24 hours (1 day before check-in)
        |
        */
        'partial_refund_hours' => env('BOOKING_PARTIAL_REFUND_HOURS', 24),

        /*
        |--------------------------------------------------------------------------
        | Partial Refund Percentage
        |--------------------------------------------------------------------------
        |
        | Percentage of the booking amount to refund for late cancellations.
        | Default: 50% refund
        |
        */
        'partial_refund_pct' => env('BOOKING_PARTIAL_REFUND_PCT', 50),

        /*
        |--------------------------------------------------------------------------
        | Enable Cancellation Fee
        |--------------------------------------------------------------------------
        |
        | Whether to charge a cancellation fee on refunds.
        | If enabled, fee_pct is deducted from the refund amount.
        | Default: false (no fee)
        |
        */
        'allow_fee' => env('BOOKING_ALLOW_CANCELLATION_FEE', false),

        /*
        |--------------------------------------------------------------------------
        | Cancellation Fee Percentage
        |--------------------------------------------------------------------------
        |
        | Percentage fee to deduct from refund (if allow_fee is true).
        | Default: 0%
        |
        */
        'fee_pct' => env('BOOKING_CANCELLATION_FEE_PCT', 0),

        /*
        |--------------------------------------------------------------------------
        | Allow Post-Check-In Cancellation
        |--------------------------------------------------------------------------
        |
        | Whether to allow cancellation after check-in date has passed.
        | For early checkout requests, this should typically be false.
        | Default: false
        |
        */
        'allow_after_checkin' => env('BOOKING_ALLOW_CANCEL_AFTER_CHECKIN', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the refund reconciliation job that recovers orphaned states.
    |
    */

    'reconciliation' => [

        /*
        |--------------------------------------------------------------------------
        | Stale Threshold (minutes)
        |--------------------------------------------------------------------------
        |
        | How long a booking can stay in refund_pending before reconciliation
        | attempts to verify and fix its state.
        | Default: 5 minutes
        |
        */
        'stale_threshold_minutes' => env('BOOKING_RECONCILE_STALE_MINUTES', 5),

        /*
        |--------------------------------------------------------------------------
        | Batch Size
        |--------------------------------------------------------------------------
        |
        | Number of bookings to process per reconciliation job run.
        | Default: 50
        |
        */
        'batch_size' => env('BOOKING_RECONCILE_BATCH_SIZE', 50),

        /*
        |--------------------------------------------------------------------------
        | Maximum Retry Attempts
        |--------------------------------------------------------------------------
        |
        | Maximum number of reconciliation retry attempts before marking
        | a booking for manual intervention.
        | Default: 5
        |
        */
        'max_attempts' => env('BOOKING_RECONCILE_MAX_ATTEMPTS', 5),

    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Settings for cancellation-related notifications.
    |
    */

    'notifications' => [

        /*
        |--------------------------------------------------------------------------
        | Send Cancellation Email
        |--------------------------------------------------------------------------
        |
        | Whether to send email notification on successful cancellation.
        | Default: true
        |
        */
        'send_cancellation_email' => env('BOOKING_SEND_CANCELLATION_EMAIL', true),

        /*
        |--------------------------------------------------------------------------
        | Notify Admin on Refund Failure
        |--------------------------------------------------------------------------
        |
        | Whether to notify administrators when a refund fails.
        | Default: true
        |
        */
        'notify_admin_on_refund_failure' => env('BOOKING_NOTIFY_ADMIN_REFUND_FAILURE', true),

    ],

];
