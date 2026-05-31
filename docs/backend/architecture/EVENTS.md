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

### BookingCancelled

Fired when a booking is cancelled (via `CancellationService`). Carries the immutable cancellation actor snapshot so downstream listeners do not need to re-resolve the actor (which may already be deleted).

```php
// App\Events\BookingCancelled
class BookingCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public CancellationActorSnapshot $actor, // email/role/display + actor_id
    ) {}
}
```

**Dispatched by:** `CancellationService::cancel()` (synchronous, inside the cancellation transaction).

**Listeners (synchronous, must succeed before the transaction commits):**

- `PropagateCancellationToStay` — **OPS-004** (`7027adb`, 2026-05-02): cancels the non-terminal `stays` row tied to this booking; sets `stays.stay_status = 'cancelled'` (terminal state added in `2026_05_03_000001`).
- `TransitionDepositOnCancel` — CONC-005/006: routes `bookings.deposit_status` through `Deposit::transitionTo()` to `partial_refund` or `forfeited` per refund-policy decision; appends to `deposit_events`.

**Listeners (queued, post-commit):**

- `SendBookingCancellation` — sends cancellation notification to guest (`afterCommit()`).
- `WriteAdminAuditLog` — appends to `admin_audit_logs` with the actor snapshot.

---

### AiProposalDecided

Fired when a guest confirms or declines an AI proposal at `ProposalConfirmationController::decide`.

```php
// App\AiHarness\Events\AiProposalDecided
class AiProposalDecided
{
    public function __construct(
        public AiProposal $proposal,
        public string $decision, // 'confirmed' | 'declined' | 'errored'
        public User $actor,
    ) {}
}
```

**Listeners:**

- `WriteAiProposalEvent` — appends to `ai_proposal_events` with denormalised actor snapshot (`actor_email`, `actor_role`, `actor_display_name`, added 2026-04-29 via `2026_04_29_000001`). FK `user_id` was relaxed CASCADE → SET NULL so the audit row survives user deletion.

---

### DepositTransitioned

Fired by `Deposit::transitionTo()` (CONC-005). Append-only — every transition is captured in `deposit_events`.

```php
// App\Events\DepositTransitioned
class DepositTransitioned
{
    public function __construct(
        public Booking $booking,
        public DepositStatus $from,
        public DepositStatus $to,
        public int $refundPercent,
        public ?int $refundAmountCents,
        public ?string $reason,
        public ActorSnapshot $actor, // null actor permitted for system-job transitions
    ) {}
}
```

**Listeners:**

- `WriteDepositEventLedger` — synchronous, inside the same transaction as the booking mutation.

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
        SendBookingConfirmation::class,
    ],
    BookingUpdated::class => [
        InvalidateCacheOnBookingUpdated::class,
        SendBookingUpdateNotification::class,
    ],
    BookingCancelled::class => [
        SendBookingCancellation::class,
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

Production rate limiting is provided by Laravel's `RateLimiter` facade as
configured in `app/Providers/RateLimiterServiceProvider.php`. It does not
emit project-defined events; throttled requests surface as HTTP 429
responses and standard Laravel `RateLimitExceeded` exceptions, which can
be observed via the application log channel.
