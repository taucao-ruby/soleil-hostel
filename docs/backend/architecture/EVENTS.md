# 🔔 Events & Listeners

> Event-driven architecture for cache invalidation and system monitoring

## Overview

Soleil Hostel uses Laravel Events for:

1. **Cache Invalidation** - Automatically invalidate caches when data changes
2. **Monitoring** - Track rate limiting and system degradation
3. **N+1 Detection** - Auto-detect query performance issues

---

## Events

### BookingCreated

Fired when a new booking is created.

```php
// App\Events\BookingCreated
class BookingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}
}
```

**Dispatched by:** `BookingController::store()`

**Listeners:**

- `InvalidateCacheOnBookingChange` - Invalidates room availability + user bookings cache
- `InvalidateRoomAvailabilityCache` - Invalidates specific room availability cache

---

### BookingUpdated

Fired when a booking is updated.

```php
// App\Events\BookingUpdated
class BookingUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public object $booking,
        public object $oldBooking
    ) {}
}
```

**Dispatched by:** `BookingController::update()`

**Listeners:**

- `InvalidateCacheOnBookingUpdated` - Handles room change + date change scenarios

---

### BookingDeleted

Fired when a booking is soft-deleted.

```php
// App\Events\BookingDeleted
class BookingDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}
}
```

**Dispatched by:** `BookingController::destroy()`

**Listeners:**

- `InvalidateCacheOnBookingDeleted` - Restores room availability cache

---

### RateLimiterDegraded

Fired when rate limiter falls back to in-memory storage (Redis down).

```php
// App\Events\RateLimiterDegraded
class RateLimiterDegraded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data  // Contains error details
    ) {}
}
```

**Dispatched by:** `RateLimitService` when Redis connection fails

**Use case:** Alerting + monitoring for production health

---

### RequestThrottled

Fired when a request exceeds rate limits.

```php
// App\Events\RequestThrottled
class RequestThrottled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data  // Contains IP, user_id, endpoint
    ) {}
}
```

**Dispatched by:** `AdvancedRateLimitMiddleware`

**Use case:** Security monitoring, abuse detection

---

## Listeners

### InvalidateCacheOnBookingChange

Handles cache invalidation for all booking events.

```php
// App\Listeners\InvalidateCacheOnBookingChange
class InvalidateCacheOnBookingChange implements ShouldQueue
{
    public function __construct(
        private RoomService $roomService,
        private BookingService $bookingService
    ) {}

    public function handle($event): void
    {
        if ($event instanceof BookingCreated) {
            $this->handleCreated($event->booking);
        } elseif ($event instanceof BookingUpdated) {
            $this->handleUpdated($event->booking, $event->oldBooking);
        } elseif ($event instanceof BookingDeleted) {
            $this->handleDeleted($event->booking);
        }
    }

    private function handleCreated($booking): void
    {
        // Invalidate room availability (less rooms available)
        $this->roomService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
    }

    private function handleUpdated($booking, $oldBooking): void
    {
        // If room changed, invalidate old room
        if ($booking->room_id !== $oldBooking->room_id) {
            $this->roomService->invalidateAvailability($oldBooking->room_id);
        }
        // Invalidate new room
        $this->roomService->invalidateAvailability($booking->room_id);
        // Invalidate this booking's cache
        $this->bookingService->invalidateBooking($booking->id, $booking->user_id);
    }

    private function handleDeleted($booking): void
    {
        // Invalidate room availability (more rooms available)
        $this->roomService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
    }
}
```

**Queue:** Runs async via `ShouldQueue` for non-blocking performance

---

### QueryDebuggerListener

Detects N+1 query problems automatically.

```php
// App\Listeners\QueryDebuggerListener
class QueryDebuggerListener
{
    private array $queries = [];

    public function handle(QueryExecuted $event): void
    {
        if (!config('query-detector.enabled')) {
            return;
        }

        // Track query
        $this->queries[] = [
            'sql' => $this->formatSql($event->sql, $event->bindings),
            'time' => $event->time,
        ];

        // Alert if threshold exceeded
        if (count($this->queries) > config('query-detector.threshold')) {
            Log::warning('⚠️ N+1 QUERY DETECTED!', [
                'total_queries' => count($this->queries),
                'threshold' => config('query-detector.threshold'),
            ]);

            // Fail tests if N+1 detected
            if (app()->runningUnitTests()) {
                throw new \RuntimeException(
                    "N+1 Query Detected: {$count} queries"
                );
            }
        }
    }
}
```

**Listens to:** `Illuminate\Database\Events\QueryExecuted`

**Configuration:** `config/query-detector.php`

---

## Event Registration

Events are registered in `EventServiceProvider`:

```php
protected $listen = [
    BookingCreated::class => [
        InvalidateCacheOnBookingChange::class,
    ],
    BookingUpdated::class => [
        InvalidateCacheOnBookingUpdated::class,
    ],
    BookingDeleted::class => [
        InvalidateCacheOnBookingDeleted::class,
    ],
    QueryExecuted::class => [
        QueryDebuggerListener::class,
    ],
];
```

---

## Cache Invalidation Flow

```
User creates booking
       ↓
BookingController::store()
       ↓
CreateBookingService::create()
       ↓
event(new BookingCreated($booking))
       ↓
InvalidateCacheOnBookingChange::handle()
       ↓
RoomService::invalidateAvailability($roomId)
BookingService::invalidateUserBookings($userId)
       ↓
Next request → Cache miss → Fresh data
```

---

## Monitoring Integration

### Production Setup

```php
// Slack notification for rate limiter degradation
Event::listen(RateLimiterDegraded::class, function ($event) {
    Notification::route('slack', config('services.slack.webhook'))
        ->notify(new SystemDegradedNotification($event->data));
});

// Log throttled requests for security audit
Event::listen(RequestThrottled::class, function ($event) {
    Log::channel('security')->warning('Request throttled', $event->data);
});
```
