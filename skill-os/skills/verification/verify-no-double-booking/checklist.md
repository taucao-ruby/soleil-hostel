# Double-Booking Verification Checklist

> Binary pass/fail. Every item must be YES to pass. Any NO = finding to investigate.

## Schema Layer

1. [ ] The `no_overlapping_bookings` exclusion constraint exists in migrations and is not reverted by any subsequent migration
2. [ ] The constraint uses `EXCLUDE USING gist` (not a regular UNIQUE or CHECK)
3. [ ] The constraint interval is `daterange(check_in, check_out, '[)')` — half-open, bracket-paren notation
4. [ ] The constraint WHERE clause is exactly `status IN ('pending', 'confirmed') AND deleted_at IS NULL`
5. [ ] `CREATE EXTENSION IF NOT EXISTS btree_gist` precedes the constraint creation
6. [ ] The `CHECK (check_out > check_in)` constraint exists on the `bookings` table
7. [ ] No migration drops or modifies the exclusion constraint without adding a replacement

## Query Logic Layer

8. [ ] The PHP overlap scope uses `existing.check_in < new.check_out` (strict less-than, not `<=`)
9. [ ] The PHP overlap scope uses `existing.check_out > new.check_in` (strict greater-than, not `>=`)
10. [ ] The PHP overlap scope filters status to exactly `['pending', 'confirmed']` — no other statuses
11. [ ] The PHP overlap scope excludes soft-deleted records (via `whereNull('deleted_at')` or `SoftDeletes` global scope)
12. [ ] The overlap query is parameterized (no raw string interpolation of dates or IDs)

## Application Layer

13. [ ] Booking creation is wrapped in a database transaction (`DB::transaction()` or equivalent)
14. [ ] `lockForUpdate()` is called on existing bookings or the room before the overlap check
15. [ ] No code path exists between lock acquisition and insert that releases the lock early
16. [ ] Cancellation changes `status` to `cancelled` (does not hard-delete the booking row)
17. [ ] `CancellationService` acquires `lockForUpdate()` before status change
18. [ ] The application catches the PostgreSQL exclusion constraint violation and returns a user-friendly error (not a 500)

## Test Coverage

19. [ ] At least one test verifies overlapping bookings for the same room are rejected
20. [ ] At least one test verifies same-day turnover is allowed (check_out = next check_in)
21. [ ] At least one test verifies cancelled bookings do not block new bookings for the same dates
22. [ ] At least one test verifies soft-deleted bookings do not block new bookings
23. [ ] At least one test verifies pending bookings block overlapping confirmed bookings (and vice versa)

## Operational

24. [ ] The `btree_gist` extension is included in the production database provisioning/init script
25. [ ] No feature flag or conditional can disable the exclusion constraint at runtime
26. [ ] Error monitoring/alerting exists for constraint violation exceptions (catches unexpected overlap attempts)
