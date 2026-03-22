# Migration Risk Review

> Fill in all sections. Leave no section blank — write "N/A" if not applicable.

## Migration Summary

- **File:** `backend/database/migrations/____________________________________`
- **Author:** _______________
- **Date:** _______________
- **Target table(s):** _______________
- **Table classification:** CRITICAL / HIGH / STANDARD
- **Operation(s):** add column / drop column / rename column / change type / change nullability / add FK / drop FK / change FK cascade / add constraint / drop constraint / add index / drop index / other: _______________

## Affected Tables and Columns

| Table | Column | Change | Old State | New State |
|---|---|---|---|---|
| | | | | |
| | | | | |
| | | | | |

## Foreign Key Changes

| FK | Old Cascade Policy | New Cascade Policy | Booking History Impact |
|---|---|---|---|
| | | | |

- [ ] No FK uses CASCADE toward `bookings` from a parent table
- [ ] FK changes cross-referenced with `ARCHITECTURE_FACTS.md` §DB Hardening

## Nullability Changes

| Table.Column | Old | New | Default Value | Escalation Column? |
|---|---|---|---|---|
| | | | | |

## Constraint Changes

| Constraint Name | Type | Change | Invariant Impact |
|---|---|---|---|
| | | | |

- [ ] Exclusion constraint NOT modified (if modified: BLOCK)
- [ ] CHECK constraint values match application enum

## Invariant Impact Assessment

| Invariant | Affected? | Detail |
|---|---|---|
| INV-1 (half-open intervals) | YES / NO | |
| INV-2 (active statuses) | YES / NO | |
| INV-3 (soft-delete exclusion) | YES / NO | |
| INV-4 (exclusion constraint) | YES / NO | |
| INV-5 (location denorm) | YES / NO | |
| INV-6 (locking columns) | YES / NO | |

## Rollback Plan

- [ ] `down()` method exists
- [ ] `down()` correctly reverses `up()`
- [ ] `down()` does not drop data-bearing columns without backup
- [ ] Rollback leaves database in consistent state with exclusion constraint

**Rollback notes:** _______________

## PG/SQLite Compatibility

- [ ] PG-only SQL wrapped in `DB::getDriverName() === 'pgsql'` guard
- [ ] Migration runs in SQLite test environment
- [ ] No raw SQL without driver guard

## Test Requirements

- [ ] Existing tests still pass after migration
- [ ] New test(s) needed: YES / NO
- [ ] If YES, describe: _______________

## Risk Tier Verdict

**Tier:** LOW / MEDIUM / HIGH / BLOCK

**Justification:** _______________

**Required actions before merge:**
1. _______________
2. _______________

**Reviewer:** _______________
**Date:** _______________
