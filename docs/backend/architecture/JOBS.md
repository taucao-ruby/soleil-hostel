# ⚙️ Queue Jobs

> Background job processing for high-load scenarios
>
> **Last Updated:** May 31, 2026

## Overview

Soleil Hostel uses Laravel Queues for:

1. **High-load booking creation** — defer to queue when deadlock retries exceed threshold
2. **Event-driven cache invalidation** — non-blocking cache updates
3. **Email notifications** — async notification delivery (queued listeners with `afterCommit()`)
4. **Refund reconciliation** — `ReconcileRefundsJob` reconciles `refund_status` against Stripe for any booking stuck in `refund_pending`
5. **Pending booking expiry** — TTL sweep against the `BOOKING_PENDING_TTL_MINUTES` invariant; cache TTL=0 is the implicit kill switch (per `docs/ROLLOUT_AND_KILL_SWITCH.md`)
6. **Operational stay backfill** — `BackfillOperationalStays` artisan command (Mar 20)

---

## CreateBookingJob

Handles booking creation with automatic retry for high-concurrency scenarios.

```php
// App\Jobs\CreateBookingJob

class CreateBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;        // Retry 3 times
    public string $queue = 'bookings';  // Dedicated queue

    public function __construct(
        private int $userId,
        private int $roomId,
        private string $checkIn,
        private string $checkOut,
        private string $guestName,
        private string $guestEmail,
        private array $additionalData = [],
    ) {}

    public function handle(CreateBookingService $bookingService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            throw new RuntimeException("User not found: {$this->userId}");
        }

        $booking = $bookingService->create(
            roomId: $this->roomId,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            guestName: $this->guestName,
            guestEmail: $this->guestEmail,
            userId: $this->userId,
            additionalData: $this->additionalData,
        );

        Log::info('Booking created via job', [
            'booking_id' => $booking->id,
            'user_id' => $this->userId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Booking job failed', [
            'user_id' => $this->userId,
            'room_id' => $this->roomId,
            'error' => $exception->getMessage(),
        ]);

        // Notify user of failure (planned)
        // Notification::send($user, new BookingFailedNotification());
    }
}
```

---

## Usage

### Direct Dispatch

```php
use App\Jobs\CreateBookingJob;

CreateBookingJob::dispatch(
    userId: $user->id,
    roomId: 1,
    checkIn: '2025-12-01',
    checkOut: '2025-12-05',
    guestName: 'John',
    guestEmail: 'john@example.com'
);
```

### High-Load Fallback

```php
// In BookingController - when deadlock retries exceed threshold

if ($attemptCount > 3) {
    CreateBookingJob::dispatch(
        userId: auth()->id(),
        roomId: $validated['room_id'],
        checkIn: $validated['check_in'],
        checkOut: $validated['check_out'],
        guestName: $validated['guest_name'],
        guestEmail: $validated['guest_email'],
    );

    return response()->json([
        'success' => true,
        'message' => 'Booking request queued, will be processed soon',
    ], 202); // 202 Accepted
}
```

---

## Queue Configuration

### Queue Connections

```php
// config/queue.php

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 5,
    ],
],
```

### Queue Workers

```bash
# Start worker for bookings queue
php artisan queue:work redis --queue=bookings

# Start worker for default queue
php artisan queue:work redis --queue=default

# Production (with Supervisor)
# See /etc/supervisor/conf.d/laravel-worker.conf
```

### Supervisor Configuration

```ini
[program:laravel-bookings]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --queue=bookings --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
```

---

## Queued Listeners

Event listeners that implement `ShouldQueue` run asynchronously:

```php
// App\Listeners\InvalidateCacheOnBookingChange

class InvalidateCacheOnBookingChange implements ShouldQueue
{
    public function handle($event): void
    {
        // Runs in background - doesn't block HTTP response
        $this->roomService->invalidateAvailability($event->booking->room_id);
    }
}
```

---

## Monitoring

### Failed Jobs

```bash
# View failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry {id}

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Database Table

Failed jobs are stored in `failed_jobs` table:

```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC;
```

---

## ReconcileRefundsJob

Scheduled **every 5 minutes** (`reconcile-refunds`, `withoutOverlapping`,
`onOneServer` — `routes/console.php`; `tries = 3`, `backoff = [60, 300, 900]`).
Dispatched with **no constructor args** — it sweeps *all* eligible bookings in
chunks, it is **not** a per-booking job. Two passes:

1. **`reconcilePendingRefunds()`** — bookings in `refund_pending` older than
   `booking.reconciliation.stale_threshold_minutes` (default 5). Reads the real
   refund status from Stripe (PaymentIntent → `latest_charge.refunds`) and
   finalizes: `succeeded → cancelled`, `failed → refund_failed`, `pending → leave`.
2. **`retryFailedRefunds()`** — `refund_failed` bookings with no `refund_id`,
   capped at `booking.reconciliation.max_attempts` (default 5). **PAY-01**: an
   atomic compare-and-swap claim on `updated_at` leases the row (no double-refund
   under concurrency) and a pre-check discovers any existing Stripe refund before
   issuing a new one (ambiguous matches → flagged for manual review).

Hardening / invariants:

- `2e120c0` (Apr 26) — guard `null` booking after `fresh()` (concurrent force-delete window).
- Stripe charge-shape guard: the expanded payload may be `null` / `Stripe\Charge` / `Stripe\StripeObject`; the type narrow runs before any property read.
- Refund writes go through `StripeRefundEventRecorder::record()`; the
  `stripe_refund_events.stripe_refund_id` UNIQUE fence (`abc3959`) serialises
  concurrent webhook + reconciler deliveries at the storage layer (PAY-04).
- Null-user safe (CONC-006): a deleted guest (`user_id = NULL`) falls back to the
  application-level `Cashier::stripe()` client.

> Full design (state machine, webhook, complete idempotency stack) lives in
> [`BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md`](BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md).

---

## Other Booking Jobs

| Job / command | Trigger | Purpose |
| --- | --- | --- |
| `ExpireStaleBookings` | every 5 min (`expire-stale-bookings`) | Auto-cancels unconfirmed `pending` bookings past TTL (`booking.pending_ttl_minutes`, default 30) so the held room frees up; records a PaymentIntent-cancellation outbox row in the same transaction (PAY-03). |
| `ProcessPaymentCancellationOutbox` | every 5 min (`process-payment-cancellation-outbox`) | Drains the Stripe PaymentIntent cancellation outbox **off** the booking lock, with bounded retry/backoff (PAY-03). |
| `ProcessDepositRefund` | dispatched on cancel | Issues the Stripe deposit refund asynchronously when the deposit FSM transitions to a refunding state (CONC-005). |

---

## Job Flow

```
High load detected (deadlock retries > 3)
       ↓
CreateBookingJob::dispatch()
       ↓
Return 202 Accepted immediately
       ↓
Queue worker picks up job
       ↓
CreateBookingService::create()
       ↓
Success → Log info
Failure → Log error + Queue retry
       ↓
After 3 failures → Move to failed_jobs table
```
