# ⚙️ Queue Jobs

> Background job processing for high-load scenarios
>
> **Last Updated:** May 8, 2026

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

Background reconciliation for any booking stuck in `refund_pending` whose Stripe-side state may have advanced. The job has been hardened across May 2026:

- `2e120c0` (Apr 26) — guard `null` booking after `fresh()` (concurrent force-delete window).
- `1441edb` (May 6) — Stripe charge type guard: payload from the API may be `null`, `Stripe\Charge`, or `Stripe\StripeObject` depending on expansion; the type narrow runs before any property read so a missing-charge payload no longer throws `Error: Cannot read property 'amount_refunded' of null`.
- Refund mutation is mediated through the durable `stripe_refund_events` UNIQUE replay fence (`abc3959`); `INSERT` precedes booking lookup so concurrent webhook + reconciliation deliveries are serialised at the storage layer.

```php
// App\Jobs\ReconcileRefundsJob (excerpt)
public function handle(StripeClient $stripe): void
{
    $booking = Booking::query()->whereKey($this->bookingId)->lockForUpdate()->first();
    if ($booking === null || $booking->refund_id === null) {
        return; // booking force-deleted between dispatch and handle; nothing to reconcile
    }

    $charge = $stripe->charges->retrieve($booking->charge_id, ['expand' => ['refunds']]);

    if (! $charge instanceof Charge) {
        // Stripe returned an unexpected payload shape; bail loudly.
        Log::warning('ReconcileRefundsJob: non-Charge payload', ['booking_id' => $booking->id]);
        return;
    }

    // INSERT into stripe_refund_events (UNIQUE on stripe_refund_id) BEFORE applying mutation.
    $this->refundEventLedger->record($charge);
    $this->bookingRefundService->reconcile($booking, $charge);
}
```

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
