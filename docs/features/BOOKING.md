# 📅 Booking System

> Double-booking prevention, soft deletes, and audit trail

## Overview

The booking system uses **pessimistic locking** to prevent double-booking and **soft deletes** for data preservation.

---

## Key Features

| Feature             | Description                                      |
| ------------------- | ------------------------------------------------ |
| Pessimistic Locking | `SELECT FOR UPDATE` prevents race conditions     |
| Half-Open Intervals | `[check_in, check_out)` allows same-day turnover |
| Soft Deletes        | Cancelled bookings preserved with audit trail    |
| Admin Restore       | Admins can restore accidentally deleted bookings |

---

## Double-Booking Prevention

### How It Works

```
1. Request arrives: POST /api/bookings
2. Begin transaction
3. Lock overlapping bookings: SELECT ... FOR UPDATE
4. Check for conflicts
5. If conflict → 422 error, rollback
6. If no conflict → Create booking, commit
```

### Code Implementation

```php
// CreateBookingService.php
public function create(array $data): Booking
{
    return DB::transaction(function () use ($data) {
        // Lock any overlapping bookings
        $hasOverlap = Booking::overlappingBookings(
            $data['room_id'],
            $data['check_in'],
            $data['check_out']
        )->lockForUpdate()->exists();

        if ($hasOverlap) {
            throw new BookingOverlapException(
                'Room already booked for the specified dates.'
            );
        }

        return Booking::create($data);
    });
}
```

### Half-Open Interval Logic

```
Booking A: Jan 1-5 (guest leaves morning of Jan 5)
Booking B: Jan 5-10 (guest arrives afternoon of Jan 5)
Result: ✅ No conflict (same-day turnover allowed)
```

```sql
-- Overlap check: check_in < new_checkout AND check_out > new_checkin
WHERE check_in < '2025-01-10' AND check_out > '2025-01-05'
```

---

## Soft Deletes

### Schema

```sql
ALTER TABLE bookings ADD deleted_at TIMESTAMP NULL;
ALTER TABLE bookings ADD deleted_by BIGINT NULL;
```

### Model

```php
class Booking extends Model
{
    use SoftDeletes;

    public function softDeleteWithAudit(?int $userId = null): bool
    {
        $this->deleted_by = $userId ?? auth()->id();
        $this->save();
        return $this->delete();
    }
}
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
