# 📅 Booking System

> Double-booking prevention with pessimistic locking, soft deletes, and audit trail

## Overview

The booking system uses **pessimistic locking** (SELECT FOR UPDATE) to prevent double-booking and **soft deletes** for data preservation with complete audit trail.

---

## Key Features

| Feature             | Implementation                                     |
| ------------------- | -------------------------------------------------- |
| Pessimistic Locking | `SELECT FOR UPDATE` trong transaction              |
| Half-Open Intervals | `[check_in, check_out)` cho phép same-day turnover |
| Deadlock Retry      | 3 lần retry với exponential backoff                |
| Soft Deletes        | `deleted_at` + `deleted_by` audit trail            |
| XSS Protection      | HTML Purifier auto-sanitize `guest_name`           |
| Admin Restore       | Admins có thể restore booking đã xóa               |

---

## Double-Booking Prevention

### How It Works

```
1. Request arrives: POST /api/bookings
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

### Status Constants

```php
class Booking extends Model
{
    use SoftDeletes, Purifiable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const ACTIVE_STATUSES = ['pending', 'confirmed'];

    protected $fillable = [
        'room_id', 'check_in', 'check_out',
        'guest_name', 'guest_email', 'status',
        'user_id', 'deleted_by'
    ];

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

// Filter by status
Booking::active();        // pending + confirmed
Booking::cancelled();     // cancelled only
Booking::byStatus('confirmed');

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

| Method | Endpoint             | Description          |
| ------ | -------------------- | -------------------- |
| GET    | `/api/bookings`      | List user's bookings |
| POST   | `/api/bookings`      | Create booking       |
| GET    | `/api/bookings/{id}` | View booking         |
| PUT    | `/api/bookings/{id}` | Update booking       |
| DELETE | `/api/bookings/{id}` | Cancel (soft delete) |

### Admin Endpoints

| Method | Endpoint                           | Description                 |
| ------ | ---------------------------------- | --------------------------- |
| GET    | `/api/admin/bookings`              | All bookings (with trashed) |
| GET    | `/api/admin/bookings/trashed`      | Only trashed                |
| POST   | `/api/admin/bookings/{id}/restore` | Restore booking             |
| DELETE | `/api/admin/bookings/{id}/force`   | Permanent delete            |

---

## Request/Response Examples

### Create Booking

**Request:**

```http
POST /api/bookings
Content-Type: application/json
Authorization: Bearer <token>

{
  "room_id": 1,
  "guest_name": "John Doe",
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

## Rate Limiting

| Action         | Limit                 |
| -------------- | --------------------- |
| Create booking | 3 per minute per user |

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

| Test Category        | Count  |
| -------------------- | ------ |
| Overlap Prevention   | 14     |
| Soft Deletes         | 19     |
| Policy/Authorization | 15     |
| Service Unit Tests   | 12     |
| **Total**            | **60** |
