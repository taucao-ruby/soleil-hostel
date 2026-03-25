---
verified-against: skills/laravel/migrations-postgres-skill.md
secondary-source: docs/agents/ARCHITECTURE_FACTS.md
section: "DB Constraints Added, Key Indexes — project migration conventions"
last-verified: 2026-03-16
maintained-by: docs-sync
note: "This rule codifies project conventions. Primary verification source is migrations-postgres-skill.md."
---

# Migration Safety

## Purpose
Keep schema changes reversible, production-safe on PostgreSQL, and aligned with the booking-domain invariants they can silently break.

## Rule
- Every migration keeps a real rollback path: `down()` must safely reverse `up()`.
- PostgreSQL-only features stay guarded by `DB::getDriverName() === 'pgsql'` and use explicit constraint/index names.
- Table-altering migrations use idempotent existence checks where production state may vary.
- New tables referencing critical domain tables keep explicit FK constraints.
- Any change touching booking overlap enforcement keeps `btree_gist`, the backing GIST support, and `deleted_at IS NULL` in the exclusion-constraint path.

## Why it exists
Unsafe migrations create irreversible rollbacks, environment-specific failures, orphaned data, and broken overlap protection.

## Applies to
Agents, humans, skills, commands, and reviews creating or editing files in `backend/database/migrations/`.

## Violations
- Empty or comment-only `down()` methods.
- PostgreSQL-only DDL without a driver guard.
- Auto-generated production constraint names.
- Rebuilding or dropping booking overlap support without recreating the protection.
- Adding FK-bearing tables without explicit foreign keys to the critical tables they reference.

## Enforcement
- Canonical sources: `skills/laravel/migrations-postgres-skill.md`, `docs/agents/ARCHITECTURE_FACTS.md`, `docs/DB_FACTS.md`.
- Validation: `php artisan migrate:rollback --step=1`, `tests/Feature/Database/FkDeletePolicyTest.php`, `tests/Feature/Database/CheckConstraintTest.php`, `.agent/scripts/check-migration-safety.sh`.
- Review and escalation: `.claude/commands/fix-backend.md`, `.claude/commands/review-pr.md`, `.claude/commands/ship.md`.

## Linked skills / hooks
- `skills/laravel/migrations-postgres-skill.md`
- `skills/laravel/booking-overlap-skill.md`
