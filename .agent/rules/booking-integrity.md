---
verified-against: docs/agents/ARCHITECTURE_FACTS.md
section: "Booking Domain — Overlap Prevention, Concurrency Control, Booking Status"
last-verified: 2026-03-17
maintained-by: docs-sync
---

# Booking Integrity — Fast-Load Rule

Load this rule at the start of any task touching booking overlap detection, status transitions,
soft-delete behavior, cancellation flows, or the PostgreSQL exclusion constraint.

Full specification: `docs/agents/ARCHITECTURE_FACTS.md` § "Booking Domain" + § "Concurrency Control"
Exact SQL and migration evidence: read ARCHITECTURE_FACTS.md directly — do not reproduce here.

## Overlap Invariants

- Half-open interval `[check_in, check_out)` — same-day turnover is valid; `check_out = check_in` is NOT a conflict
- Active overlap statuses: `pending` and `confirmed` ONLY — `refund_pending`, `cancelled`, `refund_failed` must never participate
- Soft-delete filter: `deleted_at IS NULL` required in BOTH app-layer query AND DB constraint WHERE clause
- App-layer scope (`Booking::ACTIVE_STATUSES`) and DB constraint must use the same status set — divergence is a bug
- `bookings.location_id` is set by trigger `trg_booking_set_location`, not by application code

## Locking Invariants

- `lockForUpdate()` / `withLock()` required for ALL booking write paths — existing AND newly added
- Overlap check must execute inside the same transaction as the write, under lock
- `lock_version` on `rooms` (NOT NULL, default 1) and `locations` (default 1) — optimistic locking; must not be silently skipped

### Canonical booking write entry points (authoritative list)

Every method below creates or mutates a booking row under pessimistic lock. Verify `lockForUpdate()` is present in each before commit.

| Method | File | Lock context |
|--------|------|-------------|
| `CreateBookingService::create()` | `app/Services/CreateBookingService.php:62` | Delegates to `createBookingWithLocking()` |
| `CreateBookingService::createBookingWithLocking()` | `app/Services/CreateBookingService.php:267` | Holds `lockForUpdate()` for overlap check + `Booking::create()` |
| `CreateBookingService::update()` | `app/Services/CreateBookingService.php:332` | Date-range update under `withLock()` + `DB::transaction()` |
| `BookingService::confirmBooking()` | `app/Services/BookingService.php:86` | Status transition in `DB::transaction()` (no row-level lock — status-only, no date change) |
| `CancellationService` cancel flows | `app/Services/CancellationService.php` | Cancellation under `lockForUpdate()` |

If a new method that inserts or updates booking rows is added in a PR, it MUST be added to this list in the same PR. A booking write method not on this list = STOP (do not commit).

Grep patterns (`Booking::create(`, `DB::table('bookings')->insert(`, `$booking->save()`, `Booking::insert(`, `Booking::upsert(`) are a secondary discovery aid only — they do not substitute for the canonical list. Repository wrappers, raw SQL via `DB::statement()`, and bulk helpers may not appear in grep results.

## Schema Facts (common mistake sources)

- `bookings.status` is VARCHAR, NOT a PostgreSQL ENUM — values enforced at application level AND DB CHECK `chk_bookings_status` on PostgreSQL (migration `2026_03_17_000003`)
- Status values: `pending`, `confirmed`, `refund_pending`, `cancelled`, `refund_failed`
- `Booking::ACTIVE_STATUSES` is a constant array — grep for inline strings will NOT find the status list

## STOP Conditions

```
STOP — do not commit if any of these are true:
- Code queries bookings with any status beyond pending/confirmed for overlap detection
- Overlap check runs outside a transaction or is not under lock
- App-layer status filter diverges from DB constraint WHERE clause
- lockForUpdate() / withLock() is absent from any booking write path — whether existing (being modified) or newly added in this PR
- deleted_at IS NULL is absent from the overlap query
- DB constraint WHERE clause is modified without updating the app-layer scope in the same PR
```
