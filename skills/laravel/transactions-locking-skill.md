# Laravel Transactions and Locking Skill

Use this skill for concurrency-sensitive changes in rooms, bookings, cancellation, or refund workflows.

## When to Use This Skill

- You change room update/delete flows with version checks.
- You edit booking creation/update/cancellation transactions.
- You touch `lockForUpdate`, retry behavior, or deadlock handling.
- You debug race conditions, stale writes, or duplicate booking conflicts.

## Canonical rules

- `.agent/rules/booking-integrity.md`
- `.agent/rules/migration-safety.md`

Column and schema facts (which tables carry `lock_version`, column defaults): `docs/agents/ARCHITECTURE_FACTS.md` § "Concurrency Control".

## Implementation Checklist

1. Choose lock strategy by domain:
   - Room write API: optimistic (`lock_version` compare-and-swap).
   - Booking conflict window: pessimistic (`lockForUpdate`).
2. Keep transaction boundaries tight.
   - Acquire lock, validate invariant, write, commit.
3. Preserve stale-version conflict path for room writes.
   - Include expected vs actual version details where already exposed.
4. Preserve overlap lock checks for bookings.
   - `overlappingBookings(...)->withLock()` before write.
5. Keep deadlock retry logic intact for booking creation.
   - Retry only transient DB errors.
6. Expand tests whenever lock semantics change.

## Verification / DoD

```bash
# Locking and concurrency tests
cd backend && php artisan test tests/Feature/RoomOptimisticLockingTest.php
cd backend && php artisan test tests/Feature/Room/RoomConcurrencyTest.php
cd backend && php artisan test tests/Feature/CreateBookingConcurrencyTest.php
cd backend && php artisan test tests/Feature/Database/TransactionIsolationIntegrationTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

## Common Failure Modes

- Replacing atomic version-checked update with read-then-write logic.
- Running overlap check outside transaction then writing later.
- Holding DB locks across network calls (payment/email/external APIs).
- Changing lock order between code paths, increasing deadlock risk.
- Swallowing concurrency exceptions and returning false success.

## References

- `../../AGENTS.md`
- `../../backend/app/Services/RoomService.php`
- `../../backend/app/Repositories/EloquentRoomRepository.php`
- `../../backend/app/Http/Requests/RoomRequest.php`
- `../../backend/app/Exceptions/OptimisticLockException.php`
- `../../backend/app/Services/CreateBookingService.php`
- `../../backend/app/Models/Booking.php`
- `../../backend/app/Services/CancellationService.php`
- `../../backend/tests/Feature/RoomOptimisticLockingTest.php`
- `../../backend/tests/Feature/Room/RoomConcurrencyTest.php`
- `../../backend/tests/Feature/CreateBookingConcurrencyTest.php`
- `../../backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php`
