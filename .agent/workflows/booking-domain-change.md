# Workflow: Booking Domain Change

Portable procedure for safely modifying booking overlap logic, status transitions,
cancellation flows, soft-delete behavior, or the PostgreSQL exclusion constraint.

## STOP Conditions (check before starting)

```
STOP if any of the following are true:
- The change removes deleted_at IS NULL from the DB constraint WHERE clause
- The change adds a status beyond pending/confirmed to overlap detection
- The overlap check will run outside a transaction or without lock
- App-layer status filter and DB constraint WHERE clause will diverge after the change
```

## Steps

### 1. LOAD rules

LOAD `.agent/rules/booking-integrity.md`

Review all Overlap Invariants, Locking Invariants, and STOP conditions. Confirm understanding before writing code.

### 2. READ canonical facts

READ `docs/agents/ARCHITECTURE_FACTS.md` § "Booking Domain — Overlap Prevention" and § "Concurrency Control"

Note: `Booking::ACTIVE_STATUSES` is a PHP constant — it is the single source for active status values. Grep for `ACTIVE_STATUSES` to find usages; inline status strings in queries are a bug.

### 3. READ current source files

READ the files you will modify. Minimum:
- `backend/app/Models/Booking.php` — `scopeOverlappingBookings`, `ACTIVE_STATUSES` constant
- `backend/app/Services/CreateBookingService.php` — transaction, `withLock()` pattern
- `backend/app/Services/CancellationService.php` — `lockForUpdate()` at lines 118, 318

READ the relevant migration if touching the DB constraint:
- `backend/database/migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`

### 4. USE skill

USE `skills/laravel/booking-overlap-skill.md` for implementation patterns and edge-case checklist.
USE `skills/laravel/transactions-locking-skill.md` if modifying locking strategy.

### 5. IMPLEMENT the change

Requirements:
- Half-open interval `[check_in, check_out)` preserved in both app query and DB constraint.
- `Booking::ACTIVE_STATUSES` constant used — do not hardcode status strings.
- `deleted_at IS NULL` in both app-layer scope and DB constraint WHERE clause.
- `lockForUpdate()` or `withLock()` present on all write paths being modified.
- Overlap check executes inside the same transaction as the write.

### 6. WRITE or UPDATE tests

Required test coverage:
- Adjacent dates (same-day turnover): `check_out = new_check_in` → allowed
- Real overlap: `check_in < new_check_out AND check_out > new_check_in` → blocked
- Soft-deleted booking: does not block availability
- Cancelled / refund_pending booking: does not block availability
- Concurrent request: second booking blocked by lock, not by race

### 7. RUN validation gates

```bash
cd backend && php artisan test tests/Unit/CreateBookingServiceTest.php
cd backend && php artisan test tests/Feature/Booking/
cd backend && php artisan test tests/Feature/CreateBookingConcurrencyTest.php
cd backend && php artisan test
```

Expected: 0 failures.

### 8. VERIFY constraint alignment

Confirm app-layer `ACTIVE_STATUSES` and DB constraint `WHERE (status IN (...))` use the same status set.
If either was changed, both must be updated in the same commit.

## Expected Output

- Modified source files with no inline status strings
- All overlap edge-case tests pass
- Full test suite passes
- If DB constraint changed: migration with explicit names + rollback tested
