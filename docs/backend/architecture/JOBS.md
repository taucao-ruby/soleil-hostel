# ⚙️ Queue Jobs

> Background job processing for high-load scenarios

## Overview

Soleil Hostel uses Laravel Queues for:

1. **High-load booking creation** - Defer to queue when deadlock retries exceed threshold
2. **Event-driven cache invalidation** - Non-blocking cache updates
3. **Email notifications** - Async notification delivery (planned)

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
