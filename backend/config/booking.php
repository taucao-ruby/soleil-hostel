<?php

declare(strict_types=1);

return [

    'business_timezone' => env(
        'BOOKING_BUSINESS_TIMEZONE',
        env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh')
    ),

    /*
    |--------------------------------------------------------------------------
    | Pending Booking Expiry (TTL)
    |--------------------------------------------------------------------------
    |
    | Pending bookings that have not been confirmed within this window are
    | auto-cancelled by ExpireStaleBookings so the room they reserved becomes
    | available for other guests. A pending booking blocks the room via
    | Booking::ACTIVE_STATUSES; without a TTL a forgotten pending booking
    | would hold the room indefinitely.
    |
    | Unit: minutes from the booking's created_at.
    | Default: 30 minutes.
    |
    */

    'pending_ttl_minutes' => (int) env('BOOKING_PENDING_TTL_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Payment Policy
    |--------------------------------------------------------------------------
    |
    | Default policy for new booking holds. `prepaid` is the v1 production
    | policy: a booking stays pending until Stripe confirms automatic capture.
    | `pay_at_property` is explicit offline payment and creates no PaymentIntent.
    |
    */

    'payment_policy' => env('BOOKING_PAYMENT_POLICY', 'prepaid'),

    /*
    |--------------------------------------------------------------------------
    | Pending Expiry Batch Size
    |--------------------------------------------------------------------------
    |
    | Maximum number of pending bookings ExpireStaleBookings will process
    | in a single scheduled run. Protects a cold-start backlog from DoS'ing
    | the DB.
    |
    */

    'pending_expiry_batch_size' => (int) env('BOOKING_PENDING_EXPIRY_BATCH_SIZE', 100),

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

        /*
        |--------------------------------------------------------------------------
        | Webhook Reaper Maximum Claim Attempts
        |--------------------------------------------------------------------------
        |
        | Distinct from max_attempts above (which governs ReconcileRefundsJob):
        | the number of times webhook:reconcile-stuck-events will re-claim a
        | single stuck stripe_webhook_events row before giving up. A row that
        | keeps deferring (persistent transient Stripe error, network blackhole,
        | misconfigured PaymentIntent) is auto-marked failed once it crosses
        | this threshold so it surfaces to an operator instead of being
        | re-claimed forever. The last error context is preserved on the row.
        | Default: 12
        |
        */
        'webhook_max_attempts' => env('BOOKING_WEBHOOK_RECONCILE_MAX_ATTEMPTS', 12),

        /*
        |--------------------------------------------------------------------------
        | Webhook Backlog Alert Threshold
        |--------------------------------------------------------------------------
        |
        | webhook:reconcile-stuck-events emits structured log metrics for the
        | current stuck webhook backlog on every run. The high-backlog signal
        | uses this formula:
        |
        | backlog_high = stuck_webhook_count >=
        |     webhook_backlog_alert_baseline * webhook_backlog_alert_multiplier
        |
        | The intended debounce lives in the SIEM rule, not here — the reaper
        | only emits the current gauge values for each run.
        | Default threshold: 20 (10 baseline x 2 multiplier).
        |
        */
        'webhook_backlog_alert_baseline' => env('BOOKING_WEBHOOK_BACKLOG_ALERT_BASELINE', 10),

        'webhook_backlog_alert_multiplier' => env('BOOKING_WEBHOOK_BACKLOG_ALERT_MULTIPLIER', 2),

        /*
        |--------------------------------------------------------------------------
        | Stripe PaymentIntent Cancellation Outbox (PAY-03)
        |--------------------------------------------------------------------------
        |
        | ExpireStaleBookings records a durable payment_cancellation_tasks row
        | inside its expiry transaction and ProcessPaymentCancellationOutbox
        | drains it off the booking lock. These tune that drainer:
        |
        | - batch_size               : tasks claimed per run.
        | - max_attempts             : claims before a task is failed permanently
        |                              and surfaced for operator review.
        | - stale_processing_minutes : how long a 'processing' row may sit before
        |                              a crashed worker is assumed and it is re-claimed.
        | - initial_backoff_seconds  : first transient-retry delay (doubles per attempt).
        | - max_backoff_seconds      : cap on the exponential backoff.
        |
        */

        'payment_cancellation' => [
            'batch_size' => (int) env('BOOKING_PAYMENT_CANCEL_BATCH_SIZE', 50),
            'max_attempts' => (int) env('BOOKING_PAYMENT_CANCEL_MAX_ATTEMPTS', 10),
            'stale_processing_minutes' => (int) env('BOOKING_PAYMENT_CANCEL_STALE_MINUTES', 5),
            'initial_backoff_seconds' => (int) env('BOOKING_PAYMENT_CANCEL_INITIAL_BACKOFF', 60),
            'max_backoff_seconds' => (int) env('BOOKING_PAYMENT_CANCEL_MAX_BACKOFF', 3600),
        ],

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
