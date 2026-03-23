---
verified-against: skills/laravel/migrations-postgres-skill.md
secondary-source: docs/agents/ARCHITECTURE_FACTS.md
section: "DB Constraints Added, Key Indexes — project migration conventions"
last-verified: 2026-03-16
maintained-by: docs-sync
note: "This rule codifies project conventions. Primary verification source is migrations-postgres-skill.md."
---

# Migration Safety — Fast-Load Rule

Load this rule before writing any new migration file.

Implementation guidance: `skills/laravel/migrations-postgres-skill.md`
Booking constraint exact SQL: `docs/agents/ARCHITECTURE_FACTS.md` § "Booking Domain — Overlap Prevention"

## Required Invariants

All of the following must be true for every migration before commit:

- `down()` method must exist and must safely reverse `up()` — no partial rollback, no empty body
- PostgreSQL-only features (`EXCLUDE`, `daterange(`, `btree_gist`, partial indexes, PG ENUMs) require `DB::getDriverName() === 'pgsql'` guard
- Rollback must be tested locally before commit: `php artisan migrate:rollback --step=1`
- Constraint and index names must be explicit — never rely on auto-generated names in production migrations
- Idempotent patterns (`Schema::hasColumn`, existence checks) required when modifying existing tables
- New tables with columns referencing a CRITICAL table PK must have explicit FK constraints — missing FK creates orphan data risk (see § CRITICAL tables for FK constraint enforcement below)

## CRITICAL tables for FK constraint enforcement

Any new table with a column referencing one of these tables' PKs must have an explicit FK constraint. This list is authoritative for the FK invariant above.

| Table | In scope | Reasoning |
|-------|----------|-----------|
| `bookings` | YES | Core domain — INV-1 through INV-7 apply; orphan rows break overlap integrity |
| `rooms` | YES | `bookings.room_id` references rooms — orphan room = orphan bookings |
| `locations` | YES | `rooms.location_id` references locations — orphan location = orphan room chain |
| `personal_access_tokens` | NO | Auth table, not booking-domain integrity; FK enforcement is auth-layer concern, not migration-safety scope |

## Booking Constraint Invariant (do not lose)

- If touching the booking EXCLUDE constraint: `deleted_at IS NULL` must remain in the WHERE clause
- `btree_gist` extension must remain enabled — required for the EXCLUDE constraint
- The GIST index backing `no_overlapping_bookings` must not be dropped — the EXCLUDE constraint is unenforceable without it
- "Touching" the exclusion constraint means any of: DROP CONSTRAINT, ALTER CONSTRAINT, renaming the constraint, DROP INDEX on its backing GIST index, or any ALTER TABLE that causes PostgreSQL to rebuild the table (e.g., changing column type on a constrained column)

## STOP Conditions

```
STOP — do not commit if any of these are true:
- down() is absent or body is empty / comment-only
- PG-only feature is used without DB::getDriverName() guard
- php artisan migrate:rollback --step=1 fails or corrupts schema state
- Constraint or index name is auto-generated (not explicit string)
- Booking EXCLUDE constraint is modified and deleted_at IS NULL is removed from WHERE
- GIST index backing no_overlapping_bookings is dropped, renamed, or rebuilt without re-creating it — this makes the exclusion constraint unenforceable
```
