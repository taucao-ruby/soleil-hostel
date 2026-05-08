# 📅 Booking System

> Double-booking prevention with pessimistic locking, PostgreSQL exclusion constraint, deposit FSM, immutable cancellation actor snapshot, and stay-cancellation propagation
>
> **Last Updated:** May 8, 2026

## Overview

The booking system uses **pessimistic locking** (`SELECT FOR UPDATE`) inside a DB transaction to serialise overlapping writes, plus a PostgreSQL `EXCLUDE USING gist` constraint as the irrevocable defense-in-depth guard. Cancellation captures an **immutable actor snapshot** so attribution survives user deletion, and propagates synchronously to the operational stay layer (OPS-004).

---

## Key Features

| Feature                    | Implementation                                                              |
| -------------------------- | --------------------------------------------------------------------------- |
| Pessimistic Locking        | `SELECT FOR UPDATE` in transaction                                          |
| PG exclusion constraint    | `no_overlapping_bookings` — `EXCLUDE USING gist (room_id =, daterange &&)` filtered to active + not soft-deleted; pre-deploy gate `php artisan db:assert-schema-constraints` (`92f1ad1`) |
| Half-Open Intervals        | `[check_in, check_out)` allows same-day turnover                            |
| Deadlock Retry             | 3 retries with exponential backoff                                          |
| Soft Deletes               | `deleted_at` + `deleted_by` audit trail                                     |
| Cancellation actor snapshot | `cancelled_by_email` / `cancelled_by_role` / `cancelled_by_display` (immutable, populated synchronously by `CancellationService` — `048e40b`, May 1) |
| Stay propagation (OPS-004) | `BookingCancelled` synchronously cancels the non-terminal `stays` row (`7027adb`, May 2) |
| Payment-hold (Apr 22)      | `POST /v1/bookings` creates a Stripe PaymentIntent in `requires_capture` mode; pending-limit enforcement prevents resource exhaustion (`ae2d070`)  |
| Deposit FSM (CONC-005/006) | `Deposit::transitionTo()` is the only legal mutation; every transition appends to `deposit_events`; `chk_bookings_deposit_status` extended with `partial_refund` and `forfeited` (`b69a7a0`/`2026_05_02_000001`) |
| Refund idempotency         | Durable `stripe_refund_events.stripe_refund_id` UNIQUE — DB INSERT before booking lookup eliminates the application-layer TOCTOU window (`abc3959`); supersedes the in-memory `IdempotencyGuard` |
| XSS Protection             | HTML Purifier auto-sanitises `guest_name`                                   |
| Admin Restore              | Admins can restore soft-deleted bookings (transaction + `FOR UPDATE`, TOCTOU-safe — Mar 29)  |

---

## Double-Booking Prevention

### How It Works

```
1. Request arrives: POST /api/v1/bookings
2. Begin DB transaction
3. Lock overlapping bookings: SELECT ... FOR UPDATE
4. Check for conflicts
5. If conflict → 422 error, rollback
6. If no conflict → Create booking, commit
7. If deadlock → Retry với exponential backoff (3 lần)
```

### CreateBookingService Implementation

```php
class CreateBookingService
{
    private const DEADLOCK_RETRY_ATTEMPTS = 3;
    private const DEADLOCK_RETRY_DELAY_MS = 100; // 100ms, 200ms, 400ms

    public function create(
        int $roomId,
        $checkIn,
        $checkOut,
        string $guestName,
        string $guestEmail,
        ?int $userId = null
    ): Booking {
        return $this->createWithDeadlockRetry(/* ... */);
    }

    private function createBookingWithLocking(/* ... */): Booking
    {
        return DB::transaction(function () {
            // 1. Lock any overlapping bookings
            $hasOverlap = Booking::overlappingBookings(
                $roomId, $checkIn, $checkOut
            )->lockForUpdate()->exists();

            // 2. Check for conflicts
            if ($hasOverlap) {
                throw new RuntimeException(
                    'Phòng đã được đặt cho ngày này.'
                );
            }

            // 3. Create booking
            return Booking::create([
                'room_id' => $roomId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'user_id' => $userId,
                'status' => 'pending',
            ]);
        });
    }
}
```

### Half-Open Interval Logic

```php
// Booking Model - scopeOverlappingBookings
// Logic: a1 < b2 AND a2 < b1 (overlap detection)

return $query
    ->where('room_id', $roomId)
    ->whereIn('status', ['pending', 'confirmed'])
    ->where('check_in', '<', $checkOut)   // existing.start < new.end
    ->where('check_out', '>', $checkIn);  // existing.end > new.start
```

```
✅ Allowed: Booking A (Jan 1-5) + Booking B (Jan 5-10)
   → Guest A checkout morning Jan 5, Guest B checkin afternoon Jan 5

❌ Blocked: Booking A (Jan 1-5) + Booking B (Jan 3-8)
   → Overlap Jan 3-5
```

---

## Booking Model

### BookingStatus Enum

The booking system uses a dedicated `BookingStatus` enum for type-safe status management:

```php
// App\Enums\BookingStatus

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REFUND_PENDING = 'refund_pending';
    case CANCELLED = 'cancelled';
    case REFUND_FAILED = 'refund_failed';

    // Helper methods
    public function isCancellable(): bool;      // Can this status be cancelled?
    public function isTerminal(): bool;         // Is this a final state?
    public function isRefundInProgress(): bool; // Is refund being processed?
    public function isActive(): bool;           // Is this an active booking?
    public function canTransitionTo(self $target): bool; // Valid transitions
    public function label(): string;            // Human-readable label
    public function color(): string;            // CSS color class
}
```

### State Machine

```
┌─────────┐    ┌───────────┐    ┌────────────────┐    ┌───────────┐
│ PENDING │───▶│ CONFIRMED │───▶│ REFUND_PENDING │───▶│ CANCELLED │
└─────────┘    └───────────┘    └────────────────┘    └───────────┘
     │              │                   │                    ▲
     └──────────────┴───────────────────┘                    │
     (no-payment fast path: skip refund)                     │
                                        ▼                    │
                                ┌───────────────┐            │
                                │ REFUND_FAILED │────────────┘
                                └───────────────┘
```

### Model Configuration

```php
class Booking extends Model
{
    use SoftDeletes, Purifiable;

    protected $casts = [
        'status' => BookingStatus::class,  // Enum casting
        'check_in' => 'date',
        'check_out' => 'date',
    ];

    protected $fillable = [
        'room_id', 'location_id', 'check_in', 'check_out',
        'guest_name', 'guest_email', 'status',
        'user_id', 'deleted_by',
        // Payment + refund fields
        'payment_intent_id', 'amount', 'refund_id',
        'refund_status', 'refund_amount', 'refund_error',
        // Deposit lifecycle (CONC-005/006)
        'deposit_amount', 'deposit_collected_at', 'deposit_status',
        // Cancellation + immutable actor snapshot (May 1, 048e40b)
        'cancelled_at', 'cancelled_by',
        'cancelled_by_email', 'cancelled_by_role', 'cancelled_by_display',
        'cancellation_reason',
    ];

    // Legacy constants (deprecated - use BookingStatus enum)
    /** @deprecated Use BookingStatus::PENDING */
    public const STATUS_PENDING = 'pending';
    /** @deprecated Use BookingStatus::CONFIRMED */
    public const STATUS_CONFIRMED = 'confirmed';
    /** @deprecated Use BookingStatus::CANCELLED */
    public const STATUS_CANCELLED = 'cancelled';

    // XSS Protection: auto-purify guest_name
    public function getPurifiableFields() {
        return ['guest_name'];
    }
}
```

### Query Scopes

```php
// Overlap detection
Booking::overlappingBookings($roomId, $checkIn, $checkOut);

// Filter by status (use enum)
Booking::where('status', BookingStatus::CONFIRMED);
Booking::active();        // pending + confirmed
Booking::cancelled();     // cancelled only

// Eager loading (N+1 prevention)
Booking::withCommonRelations()->get();
```

### Accessors

```php
$booking->isExpired();      // check_out < today
$booking->isStarted();      // check_in <= today
$booking->nights;           // số đêm (check_out - check_in)
$booking->isValidDateRange; // check_in < check_out
```

---

## Soft Deletes

### Schema

```sql
ALTER TABLE bookings ADD deleted_at TIMESTAMP NULL;
ALTER TABLE bookings ADD deleted_by BIGINT NULL REFERENCES users(id);
```

### Behavior

| Action                    | Result                                         |
| ------------------------- | ---------------------------------------------- |
| `$booking->delete()`      | Sets `deleted_at`, booking hidden from queries |
| `Booking::withTrashed()`  | Include soft-deleted bookings                  |
| `Booking::onlyTrashed()`  | Only soft-deleted bookings                     |
| `$booking->restore()`     | Clear `deleted_at`                             |
| `$booking->forceDelete()` | Permanent deletion (GDPR)                      |

---

## API Endpoints

### User Endpoints

| Method | Endpoint                | Description          |
| ------ | ----------------------- | -------------------- |
| GET    | `/api/v1/bookings`      | List user's bookings |
| POST   | `/api/v1/bookings`      | Create booking       |
| GET    | `/api/v1/bookings/{id}` | View booking         |
| PUT    | `/api/v1/bookings/{id}` | Update booking       |
| DELETE | `/api/v1/bookings/{id}` | Cancel (soft delete) |

### Admin Endpoints

| Method | Endpoint                              | Description                 |
| ------ | ------------------------------------- | --------------------------- |
| GET    | `/api/v1/admin/bookings`              | All bookings (with trashed) |
| GET    | `/api/v1/admin/bookings/trashed`      | Only trashed                |
| POST   | `/api/v1/admin/bookings/{id}/restore` | Restore booking             |
| DELETE | `/api/v1/admin/bookings/{id}/force`   | Permanent delete            |

---

## Request/Response Examples

### Create Booking

**Request:**

```http
POST /api/v1/bookings
Content-Type: application/json
Authorization: Bearer <token>

{
  "room_id": 1,
  "guest_name": "John Doe",
  "guest_email": "john@example.com",
  "check_in": "2025-12-20",
  "check_out": "2025-12-25"
}
```

**Success (201):**

```json
{
  "data": {
    "id": 42,
    "room_id": 1,
    "guest_name": "John Doe",
    "check_in": "2025-12-20",
    "check_out": "2025-12-25",
    "status": "pending"
  }
}
```

**Conflict (422):**

```json
{
  "message": "Room already booked for the specified dates."
}
```

### Trashed Booking (Admin)

```json
{
  "data": {
    "id": 42,
    "guest_name": "John Doe",
    "is_trashed": true,
    "deleted_at": "2025-12-18T10:30:00Z",
    "deleted_by": {
      "id": 1,
      "name": "Admin User"
    }
  }
}
```

---

## Validation Rules

```php
// StoreBookingRequest
'room_id' => 'required|exists:rooms,id',
'guest_name' => 'required|string|max:255',
'check_in' => 'required|date|after_or_equal:today',
'check_out' => 'required|date|after:check_in',

// XSS protection: guest_name is auto-purified
```

---

## Deposit Lifecycle (CONC-005/006)

`bookings.deposit_status` is governed by an FSM with **only one legal mutation surface**: `Deposit::transitionTo()`. Every transition is captured as an append-only row in `deposit_events` (no `updated_at`, no UPDATE/DELETE path). The DB CHECK `chk_bookings_deposit_status` (`2026_05_02_000001`) accepts:

```
none → collected → applied | refunded | partial_refund | forfeited
```

`partial_refund` and `forfeited` are required by `CancellationService` so a cancelled booking's deposit can never linger in `collected` (held) state. **Null-user reconciliation** (CONC-006): when the cancelling user has been deleted, the FSM transition still completes via system-actor snapshot (`actor_id = NULL`, `actor_email = "system:reconciliation"`).

## Stay Cancellation Propagation (OPS-004)

When a `BookingCancelled` event fires, `CancellationService` synchronously cancels the non-terminal `stays` row tied to that booking. `StayStatus::CANCELLED` is a terminal FSM state (added 2026-05-03 via `2026_05_03_000001` extending `chk_stays_stay_status`). The actor context is propagated end-to-end so both the booking row, the stay row, and the `admin_audit_logs` entry carry the same `actor_id` + denormalised actor snapshot.

## Refund Idempotency (durable, replaces `IdempotencyGuard`)

Stripe webhook replay protection is delegated to the database. `StripeWebhookController::handleChargeRefunded`:

1. `INSERT` into `stripe_refund_events (stripe_refund_id, …)` — UNIQUE constraint catches replays at the storage layer
2. Only after successful insert: lookup booking and apply refund mutation
3. `amount_refunded` is sourced from `charge.amount_refunded` (cumulative), not `refunds[0].amount` — correctly handles partial-then-full refund event sequences
4. `booking_id` FK is nullable + `nullOnDelete` — refund audit decouples from booking lifecycle

This eliminates the TOCTOU window in the prior application-layer state check. `IdempotencyGuard` was deleted in commit `abc3959` (-515 LOC).

---

## Rate Limiting

| Action         | Limit                 |
| -------------- | --------------------- |
| Create booking | 10 per minute per user (throttle:10,1)             |
| Cancel/Update  | 10 per minute per user                             |

---

## Tests

```bash
# All booking tests
php artisan test tests/Feature/Booking/
php artisan test tests/Feature/CreateBookingConcurrencyTest.php

# Specific suites
php artisan test tests/Feature/Booking/ConcurrentBookingTest.php
php artisan test tests/Feature/Booking/BookingSoftDeleteTest.php
```

> Per-suite test counts moved to [PROJECT_STATUS.md](../../../PROJECT_STATUS.md). Apr–May added `BookingPaymentHoldTest` (`ae2d070`), `RefundIdempotencyTest` (`abc3959`), `BookingStateMachineInvariantTest` (`ac7275b`), `AiProposalEventActorPreservationTest` (`048e40b`), no-overlap pre-deploy assertion test (`92f1ad1`), deposit FSM tests (`b69a7a0`), and stay cancellation propagation tests (`7027adb`).
