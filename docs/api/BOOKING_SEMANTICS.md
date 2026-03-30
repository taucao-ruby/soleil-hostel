# Booking API Semantics

Authoritative reference for booking update and restore response contracts as shipped.
Derived from `BookingController`, `AdminBookingController`, `UpdateBookingRequest`, and
`BookingService`. Last verified: 2026-03-29.

---

## 1. Booking Update — PUT / PATCH `/api/v1/bookings/{booking}`

### Verb equivalence

Both `PUT` and `PATCH` are registered to the same controller method (`BookingController::update`).
The server does NOT distinguish full replacement from partial update. Both verbs are treated
equivalently: the request must always include `check_in`, `check_out`, and all required guest
fields. There is no partial-update shortcut.

### Required fields (always)

| Field         | Type            | Constraint                                      |
|---------------|-----------------|------------------------------------------------|
| `check_in`    | date (Y-m-d)    | Required. Must be ≥ today.                      |
| `check_out`   | date (Y-m-d)    | Required. Must be after `check_in`.             |
| `guest_name`  | string          | Required. 2–255 chars. HTML-sanitized (XSS).   |
| `guest_email` | email string    | Required. Max 255 chars.                        |

### Optional fields

| Field     | Type    | Constraint                                  |
|-----------|---------|---------------------------------------------|
| `room_id` | integer | Optional (`sometimes`). Must exist in rooms.|

If `room_id` is omitted, the booking keeps its current room.

### Authorization

Policy: booking owner OR admin can update. A non-owner user receives `403 Forbidden`.
Only `pending` or `confirmed` bookings are updateable (policy enforced by `BookingPolicy`).

### Availability re-validation

`check_in`/`check_out` changes re-run the overlap check (half-open interval `[check_in, check_out)`,
active-status filter `pending|confirmed`, excludes current booking from the scan).

### Success response — HTTP 200

```json
{
  "success": true,
  "message": "Booking updated successfully.",
  "data": { /* BookingResource with room relationship loaded */ }
}
```

### Error responses

| Status | Trigger                                                    |
|--------|------------------------------------------------------------|
| 403    | Caller not authorized (policy check)                       |
| 422    | Validation failure OR date-overlap conflict (RuntimeException from service) |
| 500    | Unexpected server error                                    |

---

## 2. Single Restore — POST `/api/v1/admin/bookings/{id}/restore`

### Authorization

Admin only (route middleware `role:admin` + `Gate::authorize('admin')`).

### Atomicity

The overlap check and the restore execute together inside a DB transaction with a pessimistic
lock (`SELECT ... FOR UPDATE`). If any step fails, the transaction is rolled back and the
booking remains soft-deleted.

### Success response — HTTP 200

```json
{
  "success": true,
  "message": "Booking restored successfully.",
  "data": { /* BookingResource with room and user relationships loaded */ }
}
```

### Error responses

| Status | Trigger                                                                         |
|--------|---------------------------------------------------------------------------------|
| 404    | Booking not found in trashed state                                              |
| 422    | Sequential overlap detected inside the transaction (before restore committed)   |
| 409    | Concurrent overlap detected by PostgreSQL exclusion constraint (error 23P01)    |
| 500    | Service layer returned false (unexpected failure)                               |

**409 vs 422 distinction:** 422 is returned when the PHP-level overlap query detects a conflict;
409 is returned when the PostgreSQL `no_overlapping_bookings` exclusion constraint fires (race
condition between concurrent restore attempts).

---

## 3. Bulk Restore — POST `/api/v1/admin/bookings/restore-bulk`

### Authorization

Admin only (route middleware `role:admin` + `Gate::authorize('admin')`).

### Atomicity

Each booking is restored independently inside its own transaction. A conflict on one item does
**not** roll back successfully restored items. The operation returns a partial-success response.

### Request body

```json
{ "ids": [101, 102, 103] }
```

`ids` must be a non-empty array of integers (validated by `BulkRestoreBookingsRequest`).

### Success response — HTTP 200

The response is always HTTP 200 regardless of how many items failed (partial success is
represented in the body, not via status code).

```json
{
  "success": true,
  "message": "X booking(s) restored.",
  "data": {
    "success_count": 2,
    "failure_count": 1,
    "restored_count": 2,
    "failed": [
      { "id": 103, "reason": "Date conflict: room already booked for these dates." }
    ]
  }
}
```

### Response fields

| Field           | Type    | Notes                                                               |
|-----------------|---------|---------------------------------------------------------------------|
| `success_count` | int     | Number of bookings successfully restored                            |
| `failure_count` | int     | Number of bookings not restored (= `count(failed)`)                |
| `restored_count`| int     | Alias for `success_count`. Present for backward compatibility only. |
| `failed`        | array   | Per-item failure details. Empty array `[]` when all succeed.        |
| `failed[].id`   | int     | ID of the booking that failed                                       |
| `failed[].reason`| string | Human-readable failure reason (localized)                          |

### Per-item failure reasons

| Condition                                         | `reason` value (localized key)     |
|---------------------------------------------------|------------------------------------|
| Booking not found in trashed state                | `booking.bulk_not_found`           |
| Date overlap (PHP-level or PostgreSQL constraint) | `booking.bulk_date_conflict`       |
| Service returned false                            | `booking.bulk_restore_failed`      |
