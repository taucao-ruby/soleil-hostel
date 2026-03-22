# Domain Invariants — Soleil Hostel

> Load this file before executing any skill. These are the non-negotiable rules of the booking system.

## INV-1: Half-Open Interval

Booking date range is `[check_in, check_out)`. The check_out date is exclusive — the room is available starting on checkout day. Same-day turnover is valid: one guest checking out on March 5 does not block another guest checking in on March 5.

**Schema:** `daterange(check_in, check_out, '[)')` in exclusion constraint
**PHP:** `existing.check_in < new.check_out AND existing.check_out > new.check_in`
**Test:** Same-day turnover must be allowed

## INV-2: Active Statuses

Only `pending` and `confirmed` bookings block inventory. All other statuses (`refund_pending`, `cancelled`, `refund_failed`) free the room.

**Schema:** Exclusion constraint `WHERE (status IN ('pending', 'confirmed') ...)`
**PHP:** Overlap scope filters to exactly these two statuses
**Enum:** `App\Enums\BookingStatus`

## INV-3: Soft-Delete Exclusion

Soft-deleted bookings (`deleted_at IS NOT NULL`) must not block availability.

**Schema:** Exclusion constraint `WHERE (... AND deleted_at IS NULL)`
**PHP:** `SoftDeletes` trait global scope or explicit `whereNull('deleted_at')`

## INV-4: Exclusion Constraint

The PostgreSQL `EXCLUDE USING gist` constraint on `bookings` is the last line of defense against double-booking. It requires `btree_gist` extension.

**Migration:** `2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`

## INV-5: Location Denormalization

`rooms.location_id` is the source of truth. `bookings.location_id` is a denormalized copy for analytics, set by PostgreSQL trigger `trg_booking_set_location`.

## INV-6: Locking Strategy

Pessimistic locking (`lockForUpdate()`) for booking creation/cancellation. Optimistic locking (`lock_version`) for rooms and locations.

## INV-7: API-Layer RBAC

Authorization must be enforced at the API layer (route middleware + controller gates/policies). UI visibility is not authorization.

## INV-8: One Review Per Booking

`reviews.booking_id` is NOT NULL, UNIQUE. One review per booking, enforced at the database level.

## INV-9: Dual Auth Modes

Bearer token AND HttpOnly cookie auth are both active. Neither may be disabled. Token lifecycle: `token_identifier` → `token_hash` lookup; enforce `revoked_at` + `expires_at`.

## INV-10: Code Wins Over Docs

When documentation contradicts source code or schema, the code is correct. Exception: `ARCHITECTURE_FACTS.md` has elevated authority as a curated truth document.

## CRITICAL Tables for FK Constraint Enforcement

Any new table with a column referencing one of these tables' PKs must have an explicit FK constraint. This list is authoritative for step 3 of `pre-release-verification`.

| Table | In scope | Reason |
|---|---|---|
| `bookings` | YES | Core domain — INV-1 through INV-7 apply |
| `rooms` | YES | `bookings` references `room_id`; orphan room = orphan booking |
| `locations` | YES | `rooms` references `location_id`; orphan location = orphan room chain |
| `personal_access_tokens` | NO | Auth table — not booking-domain integrity |

Missing FK on a column referencing a CRITICAL table PK = CONDITIONAL (must be added within 48h).
