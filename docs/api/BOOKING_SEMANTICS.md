# Booking API Semantics

Authoritative reference for booking update and restore response contracts as shipped.
Derived from `BookingController`, `AdminBookingController`, `UpdateBookingRequest`, and
`BookingService`. Last verified: 2026-05-25.

> **Canonical sources** for the underlying invariants this API exposes (half-open intervals, blocking statuses, `lockForUpdate()`, exclusion constraint, `lock_version`):
> - `docs/agents/ARCHITECTURE_FACTS.md` (domain invariants)
> - `docs/DB_FACTS.md` (column + constraint contracts)
>
> This document describes the API surface only. Do not paraphrase those invariants here.

> **UI DESIGN CONTEXT (Google Stitch):**
> Use this document to design booking status badges, timeline steps, and alert variants.
> The booking state machine governs which actions are available in the UI at any point.
> Status color guide (source of truth: `frontend/src/shared/lib/booking.utils.ts` `STATUS_MAP`): `pending` → yellow · `confirmed` → green · `cancelled` → gray/muted · `refund_pending` → blue · `refund_failed` → red (+ escalation alert).
> 409 vs 422 distinction matters for conflict UI: 422 = overlap detected before commit; 409 = concurrent race condition (show "try again" vs "conflict" message accordingly).

---

## 0. Booking State Machine

```
                  ┌─────────────────────────────┐
                  │                             │
  [create] ──► pending ──► confirmed ──► cancelled
                  │              │              │
                  │              ▼              │
                  │        refund_pending        │
                  │              │              │
                  │         ┌───┴───┐           │
                  │         ▼       ▼           │
                  │    refund_failed  cancelled  │
                  │         │                   │
                  └─────────┘ (admin can cancel from refund_failed)
```

### Status Definitions

| Status | Label (Vietnamese) | Color Signal | Cancellable? | Restorable? | Notes |
|---|---|---|---|---|---|
| `pending` | Chờ xác nhận | yellow/amber badge | ✅ (all roles) | N/A | Booking created, awaiting confirmation |
| `confirmed` | Đã xác nhận | green badge | ✅ (time-limited for non-admin) | N/A | Confirmed; guest arrival expected |
| `cancelled` | Đã hủy | gray/muted badge | ❌ | ✅ (admin: restore) | Soft-deleted; shows in trashed view |
| `refund_pending` | Hoàn tiền đang xử lý | blue/info badge | ✅ (admin) | N/A | Stripe refund in progress |
| `refund_failed` | Hoàn tiền thất bại | red badge + escalation | ✅ (admin) | N/A | Refund failed; requires admin action |

### Cancellation Rules (for UI action visibility)

| Condition | User/Moderator | Admin |
|---|---|---|
| Status IN `[pending, confirmed, refund_failed]` | ✅ Can cancel | ✅ Can cancel |
| Status NOT IN above | ❌ Button hidden | ❌ Button hidden |
| After `check_in` date (booking started) | ❌ Button hidden | ✅ Can still cancel |

> **UI note**: "Cancel" button should be hidden (not disabled) when `canCancel = false`. Confirmation dialog required before calling cancel endpoint.

### Restore Rules (admin only)

- Only soft-deleted (cancelled) bookings appear in the trashed view
- Restore re-runs availability overlap check — can fail with 409 or 422
- Show conflict message if restore fails; do not auto-retry

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

| Field              | Type             | Constraint                        |
|--------------------|------------------|-----------------------------------|
| `special_requests` | string \| null   | Optional. Max 2000 characters.    |

### Prohibited fields

| Field              | Reason |
|--------------------|--------|
| `room_id`           | Room movement requires a dedicated flow with availability recheck, lock discipline, `location_id` recalculation, pricing review, and audit trail. |
| `number_of_guests`  | Guest-count changes are outside the generic update contract and must not be silently accepted. |

Sending a prohibited field returns `422 Unprocessable Entity`.

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
