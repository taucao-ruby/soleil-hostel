# Laravel Booking Overlap Skill

Use this skill when changing booking date logic, conflict detection, booking status filters, or related DB constraints.

## When to Use This Skill

- You modify booking creation/update/cancellation flows.
- You touch overlap query scopes, room availability, or booking status transitions.
- You change booking indexes, constraints, or soft-delete behavior.
- You investigate double-booking bugs or false conflict bugs.

## Non-negotiables

- Preserve half-open interval semantics: `[check_in, check_out)`.
  - Same-day turnover is allowed: existing `check_out == new_check_in` is not a conflict.
- Keep overlap condition exactly:

```sql
check_in < new_check_out AND check_out > new_check_in
```

- Only active statuses count for conflicts: `pending`, `confirmed`.
- Soft-deleted bookings must not block new bookings.
  - App layer: `SoftDeletes` excludes `deleted_at IS NOT NULL` by default.
  - DB layer (PostgreSQL): exclusion constraint includes `deleted_at IS NULL`.
- Preserve production constraint shape:
  - `EXCLUDE USING gist (room_id WITH =, daterange(check_in, check_out, '[)') WITH &&)`
  - `WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL)`
- Keep concurrency protection for conflicts.
  - Booking create/update checks run in transaction with `SELECT ... FOR UPDATE` (`lockForUpdate`).

## Implementation Checklist

1. Inspect current overlap source of truth in model scope.
   - `Booking::scopeOverlappingBookings(...)`.
2. Keep status and date predicates aligned with domain rules.
   - Never widen to cancelled/refunded statuses.
3. Preserve soft-delete semantics in both app and DB layers.
   - If changing constraint, update `up()` and `down()` safely.
4. Keep transaction + lock pattern in create/update flows.
   - Conflict checks must happen under lock before write.
5. Update tests for edge cases.
   - Adjacent dates (same-day turnover) allowed.
   - Real overlap blocked.
   - Soft-deleted booking does not block.
   - Pending/confirmed block; cancelled does not.
6. Validate both logical and constraint-based safety.
   - App query correctness and PostgreSQL exclusion semantics must match.

## Verification / DoD

```bash
# Booking overlap and concurrency tests
cd backend && php artisan test tests/Unit/CreateBookingServiceTest.php
cd backend && php artisan test tests/Feature/CreateBookingConcurrencyTest.php
cd backend && php artisan test tests/Feature/Booking/ConcurrentBookingTest.php
cd backend && php artisan test tests/Feature/Database/TransactionIsolationIntegrationTest.php
cd backend && php artisan test tests/Feature/Booking/BookingSoftDeleteTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

## Common Failure Modes

- Using `<=` or `>=` in overlap checks, which breaks same-day turnover.
- Forgetting status filter, causing cancelled/refund states to block availability.
- Ignoring soft deletes in constraints, causing false conflicts after cancellation.
- Running overlap checks outside transaction/lock under concurrent requests.
- Mismatch between app-level overlap query and PostgreSQL exclusion constraint.

## References

- `../../AGENTS.md`
- `../../backend/app/Models/Booking.php`
- `../../backend/app/Services/CreateBookingService.php`
- `../../backend/app/Services/CancellationService.php`
- `../../backend/database/migrations/2025_12_18_000000_optimize_booking_indexes.php`
- `../../backend/database/migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`
- `../../backend/tests/Feature/CreateBookingConcurrencyTest.php`
- `../../backend/tests/Feature/Booking/ConcurrentBookingTest.php`
- `../../backend/tests/Feature/Booking/BookingSoftDeleteTest.php`
- `../../backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php`
- `../../backend/tests/Unit/CreateBookingServiceTest.php`
