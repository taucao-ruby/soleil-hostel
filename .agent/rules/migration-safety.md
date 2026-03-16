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

## Booking Constraint Invariant (do not lose)

- If touching the booking EXCLUDE constraint: `deleted_at IS NULL` must remain in the WHERE clause
- `btree_gist` extension must remain enabled — required for the EXCLUDE constraint

## STOP Conditions

```
STOP — do not commit if any of these are true:
- down() is absent or body is empty / comment-only
- PG-only feature is used without DB::getDriverName() guard
- php artisan migrate:rollback --step=1 fails or corrupts schema state
- Constraint or index name is auto-generated (not explicit string)
- Booking EXCLUDE constraint is modified and deleted_at IS NULL is removed from WHERE
```
