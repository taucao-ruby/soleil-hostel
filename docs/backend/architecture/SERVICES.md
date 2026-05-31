# Service Layer Architecture

> Business logic encapsulation using the Service Pattern

## Overview

The Soleil Hostel backend uses a **Service Layer** to encapsulate business logic, separating it from controllers (HTTP layer) and repositories (data access layer). This provides:

- ✅ **Single Responsibility**: Controllers handle HTTP, services handle business rules
- ✅ **Testability**: Services can be unit tested in isolation
- ✅ **Reusability**: Business logic can be called from multiple controllers/jobs
- ✅ **Transaction Management**: Services coordinate database transactions
- ✅ **Caching**: Centralized cache strategies with tag support

---

## Services Overview

| Service                                             | Purpose                           | Key Features                         |
| --------------------------------------------------- | --------------------------------- | ------------------------------------ |
| [BookingService](#bookingservice)                   | Booking lifecycle management      | Cache, notifications, rate limiting  |
| [CreateBookingService](#createbookingservice)       | New booking creation              | Pessimistic locking, deadlock retry  |
| [CancellationService](#cancellationservice)         | Booking cancellation & refunds    | 3-phase: lock → Stripe refund → deposit FSM |
| [RoomService](#roomservice)                         | Room CRUD operations              | Optimistic locking, cache tags       |
| [RoomAvailabilityService](#roomavailabilityservice) | Room availability queries         | Cached availability checks           |
| [RoomAvailabilityCache](#roomavailabilitycache)     | Cache management for availability | Tag-based invalidation               |
| [HtmlPurifierService](#htmlpurifierservice)         | XSS protection                    | HTML sanitization                    |

---

## BookingService

**Location**: `app/Services/BookingService.php`

Manages the booking lifecycle including confirmation, notifications, and caching.

### Key Methods

```php
// Confirm a pending booking and send notification
public function confirmBooking(Booking $booking): Booking

// Cancel a booking and send notification
public function cancelBooking(Booking $booking): Booking

// Get user's bookings (cached)
public function getUserBookings(int $userId): Collection

// Get single booking (cached)
public function getBooking(int $id): ?Booking

// Invalidate booking cache
public function invalidateBooking(int $bookingId, ?int $userId = null): void
```

### Cache Strategy

| Cache Key                   | TTL    | Tags                            |
| --------------------------- | ------ | ------------------------------- |
| `bookings:user:{userId}`    | 5 min  | `['bookings', 'user-bookings']` |
| `bookings:id:{id}`          | 10 min | `['bookings']`                  |
| `bookings:trashed:{userId}` | 3 min  | `['trashed-bookings']`          |

### Rate Limiting

- **Confirmation emails**: Max 5 per user per minute
- Booking is confirmed even if rate limit hit (email is non-critical)

---

## CreateBookingService

**Location**: `app/Services/CreateBookingService.php`

Handles new booking creation with **pessimistic locking** to prevent double-booking under high load.

### Key Methods

```php
// Create a new booking with overlap prevention
public function create(
    int $roomId,
    Carbon $checkIn,
    Carbon $checkOut,
    string $guestName,
    string $guestEmail,
    ?int $userId = null,
    array $additionalData = []
): Booking
```

### Concurrency Control

Uses `SELECT ... FOR UPDATE` to lock the room during booking creation:

```php
// Inside transaction
$room = Room::lockForUpdate()->findOrFail($roomId);
$hasOverlap = Booking::where('room_id', $roomId)
    ->lockForUpdate()
    ->overlapping($checkIn, $checkOut)
    ->exists();
```

### Deadlock Handling

- **Retry Attempts**: 3
- **Backoff Strategy**: Exponential (100ms, 200ms, 400ms)
- **Error Handling**: Deadlock exceptions trigger automatic retry

---

## CancellationService

**Location**: `app/Services/CancellationService.php`

Handles booking cancellations with optional refund processing via Stripe.

### Key Methods

```php
// Cancel a booking with optional refund (3-phase)
public function cancel(Booking $booking, User $actor): Booking

// Validate cancellation eligibility (ownership + status + started guard)
private function validateCancellation(Booking $booking, User $actor): void

// Phase 2: issue the refund via StripeService, OUTSIDE the transaction
private function processRefund(Booking $booking, User $actor): Booking

// Admin path: cancel without refund, always forfeits a held deposit
public function forceCancel(Booking $booking, User $actor, string $reason): Booking
```

### Cancellation Flow

```
0. Idempotency (BL-6): already-cancelled → return fresh row, no side effects

1. Validate cancellation eligibility
   ├── Check authorization (owner or admin)
   ├── Check status isCancellable (pending / confirmed / refund_failed)
   └── Check if booking has started (unless admin or config-allowed)

2. Lock + transition → refund_pending (if refundable) else cancelled

3. Process refund via StripeService (OUTSIDE transaction; stable idempotency key SH-02)

4. Finalize (re-lock + ledger-first write SH-03)
   ├── Success: status = cancelled, refund_status = succeeded
   └── Failure: status = refund_failed, refund_status = failed (retried by ReconcileRefundsJob)

5. Phase 3 — deposit FSM transition (CONC-005); async ProcessDepositRefund if due

6. Dispatch BookingCancelled event → notification
```

### Idempotency

Already-cancelled bookings return immediately without error (BL-6, idempotent).
Refund idempotency is layered: a stable per-booking Stripe key (SH-02), the
`stripe_refund_events` UNIQUE ledger fence (PAY-04), and webhook-event dedup (BL-3).

> Full as-built design — custom fail-closed webhook, reconciler, and the complete
> idempotency stack — in
> [`BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md`](BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md).

---

## RoomService

**Location**: `app/Services/RoomService.php`

Manages room CRUD operations with **optimistic locking** for concurrent updates.

### Key Methods

```php
// Get all rooms (cached)
public function getAllRoomsWithAvailability(): Collection

// Get room by ID (cached)
public function getRoomById(int $id): ?Room

// Update room with optimistic locking
public function updateRoom(Room $room, array $data): Room

// Create new room
public function createRoom(array $data): Room

// Delete room (soft delete)
public function deleteRoom(Room $room): bool
```

### Optimistic Locking

Room updates require matching `lock_version` to prevent lost updates:

```php
$room->updateWithLock([
    'name' => 'New Name',
    'lock_version' => $currentVersion, // Must match DB version
]);
// Throws OptimisticLockException if version mismatch
```

### Cache Strategy

| Cache Key                 | TTL    | Tags                        |
| ------------------------- | ------ | --------------------------- |
| `rooms:list:all:active`   | 1 min  | `['rooms']`                 |
| `rooms:id:{id}`           | 1 min  | `['rooms', 'room-{id}']`    |
| `rooms:available:{dates}` | 30 sec | `['rooms', 'availability']` |

---

## RoomAvailabilityService

**Location**: `app/Services/RoomAvailabilityService.php`

Specialized service for room availability queries with caching.

### Key Methods

```php
// Get all rooms with availability info
public function getAllRoomsWithAvailability(): Collection

// Get specific room availability
public function getRoomAvailability(int $roomId): ?Room

// Check if dates are available for a room
public function isAvailable(int $roomId, Carbon $checkIn, Carbon $checkOut): bool
```

### Cache Strategy

- **TTL**: 1 hour
- **Tag**: `room-availability`
- **Invalidation**: On booking create/update/delete events

---

## RoomAvailabilityCache

**Location**: `app/Services/Cache/RoomAvailabilityCache.php`

Manages cache invalidation for room availability data.

### Key Methods

```php
// Invalidate all room availability cache
public function invalidateAll(): void

// Invalidate specific room's availability
public function invalidateRoom(int $roomId): void

// Invalidate by date range
public function invalidateDateRange(Carbon $start, Carbon $end): void
```

---

## HtmlPurifierService

**Location**: `app/Services/HtmlPurifierService.php`

XSS protection service using HTML Purifier library.

### Key Methods

```php
// Purify HTML content
public function purify(string $html): string

// Purify with custom config
public function purifyWithConfig(string $html, array $config): string
```

### Usage

```php
// In Review model or service
$review->content = $purifier->purify($request->input('content'));
```

---

## Common Trait: HasCacheTagSupport

All caching services use the `HasCacheTagSupport` trait for consistent tag handling:

```php
trait HasCacheTagSupport
{
    protected function supportsTags(): bool
    {
        return Cache::supportsTags();
    }
}
```

This allows graceful fallback when the cache driver doesn't support tags (e.g., file cache).

---

## Dependency Injection

Services are bound in `AppServiceProvider`:

```php
public function register(): void
{
    // Repository bindings
    $this->app->bind(
        BookingRepositoryInterface::class,
        EloquentBookingRepository::class
    );

    // Services are auto-resolved via container
    // No explicit binding needed for concrete classes
}
```

### Controller Usage

```php
class BookingController extends Controller
{
    public function __construct(
        private readonly CreateBookingService $createBookingService,
        private readonly BookingService $bookingService,
    ) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->createBookingService->create(...);
        // ...
    }
}
```

---

## Testing Services

### Unit Testing

Services can be unit tested by mocking dependencies:

```php
public function test_confirm_booking_sends_notification(): void
{
    Notification::fake();

    $booking = Booking::factory()->pending()->create();
    $service = app(BookingService::class);

    $service->confirmBooking($booking);

    Notification::assertSentTo(
        $booking->user,
        BookingConfirmed::class
    );
}
```

### Integration Testing

For full integration tests, use database transactions:

```php
public function test_create_booking_prevents_overlap(): void
{
    $room = Room::factory()->create();
    $service = app(CreateBookingService::class);

    // First booking succeeds
    $booking1 = $service->create($room->id, '2025-01-01', '2025-01-05', ...);

    // Overlapping booking fails
    $this->expectException(RuntimeException::class);
    $service->create($room->id, '2025-01-03', '2025-01-07', ...);
}
```

---

## Related Documentation

- [REPOSITORIES.md](./REPOSITORIES.md) - Repository pattern for data access
- [CACHING.md](../features/CACHING.md) - Cache configuration and strategies
- [BOOKING.md](../features/BOOKING.md) - Booking feature documentation
- [RATE_LIMITING.md](../security/RATE_LIMITING.md) - Rate limiting configuration
- [OPTIMISTIC_LOCKING.md](../features/OPTIMISTIC_LOCKING.md) - Concurrency control
