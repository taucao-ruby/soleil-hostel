# SKILL: verify-no-double-booking

> Category: Verification | Priority: P0 | Blast Radius: CRITICAL
> Last updated: 2026-03-22

## Purpose

Verify that the three-layer overlap prevention system (PHP application logic, SQL queries, PostgreSQL exclusion constraint) is intact and consistent. A failure in any single layer means the remaining layers are the only defense against double-booking — the highest-severity business failure in this system.

## Trigger Conditions

Run this skill when ANY of the following occur:

1. A migration touches the `bookings` table (any column)
2. Code changes modify `Booking.php` model scopes or `scopeActive`, `scopeOverlapping`
3. Code changes modify `CancellationService.php` or `BookingService.php`
4. Code changes modify booking status values or the `BookingStatus` enum
5. The exclusion constraint migration is modified or a new constraint migration is added
6. A new booking status is introduced
7. Soft-delete behavior on bookings is modified
8. Before any release that includes booking-related changes
9. After a reported double-booking incident

## Required Inputs

- Access to `backend/database/migrations/` (specifically the exclusion constraint migration)
- Access to `backend/app/Models/Booking.php`
- Access to `backend/app/Services/BookingService.php`
- Access to `backend/app/Services/CancellationService.php`
- Access to `backend/app/Enums/BookingStatus.php`
- Access to `backend/tests/` (booking overlap test files)
- Ability to run `php artisan test --filter=overlap` or equivalent

## Execution Steps

1. **Read the exclusion constraint migration** (`2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`). Verify the constraint SQL matches this exact pattern:
   ```sql
   EXCLUDE USING gist (
       room_id WITH =,
       daterange(check_in, check_out, '[)') WITH &&
   )
   WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL)
   ```
   Confirm: `btree_gist` extension is created. Confirm: `'[)'` denotes half-open interval. Confirm: `deleted_at IS NULL` filter is present. Confirm: status list matches INV-2 exactly (`pending`, `confirmed` — no more, no less).

2. **Read `BookingStatus.php` enum**. List all cases. Confirm that only `pending` and `confirmed` are treated as overlap-blocking in application code. If any new status has been added, verify it is NOT included in overlap checks unless it represents an active reservation.

3. **Read `Booking.php` model**. Find the overlap query scope (typically `scopeOverlapping` or equivalent). Verify:
   - Uses `existing.check_in < new.check_out AND existing.check_out > new.check_in` (half-open semantics)
   - Filters to `status IN ('pending', 'confirmed')` only
   - Excludes soft-deleted records (either via global scope or explicit `whereNull('deleted_at')`)
   - The scope matches the constraint's WHERE clause

4. **Read `BookingService.php`**. Find the booking creation flow. Verify:
   - Transaction wraps the creation
   - `lockForUpdate()` is called on the room or existing bookings before the overlap check
   - The overlap check uses the scope from step 3
   - No gap exists between the lock acquisition and the insert

5. **Read `CancellationService.php`**. Verify:
   - Cancellation runs inside a transaction with `lockForUpdate()`
   - Status is changed to `cancelled` (not deleted) so the exclusion constraint correctly excludes it
   - Soft-delete (if applied) sets `deleted_at`, which the constraint filters on

6. **Run overlap tests**. Execute `php artisan test --filter=overlap` or `php artisan test --filter=double` and verify 0 failures. Note the test count — fewer than 5 overlap-specific tests is a coverage gap.

7. **Cross-reference constraint and application logic**. Build a comparison table:

   | Check | Constraint (SQL) | Application (PHP) | Match? |
   |---|---|---|---|
   | Interval semantics | `[)` half-open | `< / >` comparison | |
   | Active statuses | `pending, confirmed` | scope filter | |
   | Soft-delete exclusion | `deleted_at IS NULL` | global scope or explicit | |
   | Room scoping | `room_id WITH =` | `where('room_id', ...)` | |

   Any mismatch is a **finding**. Document it.

8. **Check for regression patterns** (see Anti-Patterns below). Grep for:
   - `<=` or `>=` in overlap comparisons (closed-interval regression)
   - `whereIn('status', ...)` with more than `['pending', 'confirmed']`
   - Overlap queries missing `whereNull('deleted_at')` or `withoutTrashed()`

## Invariant Check

| Invariant | What to verify | Pass criteria |
|---|---|---|
| **INV-1** (half-open `[check_in, check_out)`) | Constraint uses `'[)'`; PHP uses strict `<`/`>` (not `<=`/`>=`) | Interval semantics identical in SQL and PHP |
| **INV-2** (active statuses = `{pending, confirmed}`) | Constraint WHERE clause and PHP scope both use exactly these two statuses | No extra statuses included; no status missing |
| **INV-3** (soft-deleted excluded) | Constraint has `deleted_at IS NULL`; PHP scope excludes soft-deleted | Both layers filter identically |
| **INV-4** (exclusion constraint is last defense) | Constraint exists, is not commented out, uses `EXCLUDE USING gist` | Constraint is active in migration and matches application logic |

**Verdict:** ALL FOUR must pass. Any single failure = SKILL FAILS.

## Expected Output

A structured report containing:
- Pass/fail for each invariant
- The comparison table from step 7
- Test count and results from step 6
- Any findings or mismatches
- Risk assessment: CLEAR / DEGRADED (one layer weakened) / CRITICAL (multiple layers misaligned)

## Verification Checklist

1. [ ] Exclusion constraint migration exists and is not reverted
2. [ ] Constraint uses `EXCLUDE USING gist` with `daterange(..., '[)')` syntax
3. [ ] Constraint WHERE clause filters `status IN ('pending', 'confirmed')` — exactly these two
4. [ ] Constraint WHERE clause includes `deleted_at IS NULL`
5. [ ] `btree_gist` extension is created before constraint
6. [ ] PHP overlap scope uses strict `<` / `>` comparisons (not `<=` / `>=`)
7. [ ] PHP overlap scope filters to exactly `pending` and `confirmed` statuses
8. [ ] PHP overlap scope excludes soft-deleted bookings
9. [ ] Booking creation wraps in a database transaction
10. [ ] `lockForUpdate()` is called before overlap check in creation flow
11. [ ] Cancellation changes status (not hard-delete) so constraint correctly recalculates
12. [ ] `CancellationService` uses `lockForUpdate()` in its transaction
13. [ ] `BookingStatus` enum has not added new statuses to overlap-blocking set without constraint update
14. [ ] Overlap-specific tests exist and pass (minimum 5 tests)
15. [ ] Same-day turnover test exists (check_out day N = check_in day N for different booking)

## Anti-Patterns

### AP-1: Closed-interval overlap check
**What:** Using `<=` or `>=` in the overlap comparison instead of `<` / `>`.
**Why it fails:** Breaks same-day turnover. Guest checking out on March 5 blocks a guest checking in on March 5. With half-open intervals `[check_in, check_out)`, March 5 checkout means the room is free starting March 5.

### AP-2: Including non-blocking statuses in overlap filter
**What:** Adding `refund_pending` or `cancelled` to the overlap status filter.
**Why it fails:** Cancelled bookings should free inventory immediately. A `refund_pending` booking has already released the room. Including these statuses blocks rebooking during refund processing.

### AP-3: Missing soft-delete filter in PHP but present in constraint
**What:** Application overlap query uses `withTrashed()` or omits `whereNull('deleted_at')`, but constraint has `deleted_at IS NULL`.
**Why it fails:** PHP returns false "overlap detected" for soft-deleted bookings, preventing valid new bookings. The constraint would allow them, but the application blocks first. Layers disagree.

### AP-4: Gap between lock and insert
**What:** Acquiring `lockForUpdate()`, releasing the lock (e.g., by ending the transaction), then inserting.
**Why it fails:** Race condition window between lock release and insert allows concurrent booking of the same room.

### AP-5: Constraint migration behind a feature flag or conditional
**What:** Wrapping the exclusion constraint creation in `if (config('features.overlap_constraint'))`.
**Why it fails:** Constraint may not exist in production if flag is off. The database layer of defense is silently disabled.

## Edge Cases

### EC-1: Same-day turnover
Booking A: check_in=Mar 1, check_out=Mar 5. Booking B: check_in=Mar 5, check_out=Mar 8. **Must be allowed.** Half-open `[Mar 1, Mar 5)` and `[Mar 5, Mar 8)` do not overlap. Verify with `daterange('2026-03-01', '2026-03-05', '[)') && daterange('2026-03-05', '2026-03-08', '[)')` returning `false`.

### EC-2: Cancelled booking, then rebooked
Booking A: room 1, Mar 1–5, status=`cancelled`. Booking B: room 1, Mar 3–7, status=`pending`. **Must be allowed.** Constraint filters `status IN ('pending', 'confirmed')`, so cancelled Booking A is invisible. PHP scope must also exclude cancelled.

### EC-3: Soft-deleted booking, then rebooked
Booking A: room 1, Mar 1–5, `deleted_at` set. Booking B: room 1, Mar 1–5, status=`pending`. **Must be allowed.** Constraint filters `deleted_at IS NULL`. PHP must also exclude soft-deleted.

### EC-4: Pending booking overlaps confirmed booking
Booking A: room 1, Mar 1–5, status=`confirmed`. Booking B: room 1, Mar 3–7, status=`pending`. **Must be rejected.** Both statuses are in the overlap-blocking set.

### EC-5: Single-night booking
Booking: check_in=Mar 5, check_out=Mar 6. `[Mar 5, Mar 6)` = one night. Must work. Edge: check_in=check_out is invalid (enforced by `CHECK (check_out > check_in)` constraint).

### EC-6: Concurrent booking attempts
Two requests for room 1, Mar 1–5, arriving simultaneously. `lockForUpdate()` serializes them. First succeeds; second hits either the PHP overlap check or the exclusion constraint. **Both layers must reject the second.** If only the constraint catches it, application error handling must translate the constraint violation into a user-friendly message.

## References

| Reference | Path |
|---|---|
| Exclusion constraint migration | `backend/database/migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php` |
| Booking model | `backend/app/Models/Booking.php` |
| BookingService | `backend/app/Services/BookingService.php` |
| CancellationService | `backend/app/Services/CancellationService.php` |
| BookingStatus enum | `backend/app/Enums/BookingStatus.php` |
| Date check constraint | Migration `2026_02_09_000000` or equivalent (CHECK check_out > check_in) |
| btree_gist extension | Same migration as exclusion constraint |
| Overlap tests | `backend/tests/Feature/` (search for "overlap" or "double") |
| Architecture facts | `docs/agents/ARCHITECTURE_FACTS.md` §Booking Domain |

## Changelog

| Date | Change |
|---|---|
| 2026-03-22 | Initial skill creation. Covers all four booking invariants (INV-1 through INV-4). |
