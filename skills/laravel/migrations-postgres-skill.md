# Laravel Migrations (PostgreSQL-First) Skill

Use this skill for schema changes, indexes, constraints, enum changes, or DB portability decisions.

## When to Use This Skill

- You add or edit migrations in `backend/database/migrations/`.
- You introduce constraints, partial indexes, or specialized data types.
- You change token, booking, room, or user schema semantics.
- You need to preserve production PostgreSQL behavior while tests run on SQLite.

## Canonical rules

- `.agent/rules/migration-safety.md`
- `.agent/rules/booking-integrity.md`

## Implementation Checklist

1. Confirm target behavior in PostgreSQL terms first.
   - Type selection, indexes, constraints, and operator semantics.
2. Assess SQLite impact for tests.
   - Add guards or alternative behavior for unsupported operations.
3. Implement `up()` with explicit safety.
   - Check existence before create/alter where needed.
   - If creating a new table: verify any column referencing a CRITICAL table PK (`bookings`, `rooms`, `locations` — see `.agent/rules/migration-safety.md` § CRITICAL tables for FK constraint enforcement) has an explicit FK constraint in the migration.
4. Implement `down()` with explicit rollback plan.
   - Drop created objects safely and in correct order.
5. For enum and advanced constraints:
   - Enum example exists in user-role migration.
   - Exclusion constraint example exists in booking migrations.
   - JSONB usage is not currently a dominant pattern; verify before adopting.
6. Update tests that depend on schema behavior.
   - Especially booking overlap, locking, token lifecycle, and authorization data.

## Verification / DoD

```bash
# Migration-sensitive tests
cd backend && php artisan test tests/Feature/Database/TransactionIsolationIntegrationTest.php
cd backend && php artisan test tests/Feature/CreateBookingConcurrencyTest.php
cd backend && php artisan test tests/Feature/TokenExpirationTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

If migration behavior depends on PostgreSQL-only features, also validate against PostgreSQL before merge.

## Common Failure Modes

- Implementing constraints that pass SQLite but fail or behave differently in PostgreSQL.
- Missing `down()` cleanup for added indexes/constraints/types.
- Forgetting `DB::getDriverName()` guards around PostgreSQL-only statements.
- Re-introducing overlap constraint without `deleted_at IS NULL`.
- Dropping or rebuilding the GIST index backing the `no_overlapping_bookings` exclusion constraint — the constraint becomes unenforceable without its index.
- Creating irreversible migration sequences for hotfix rollbacks.

## References

- `../../AGENTS.md`
- `../../backend/config/database.php`
- `../../backend/phpunit.xml`
- `../../backend/database/migrations/2025_12_17_000001_convert_role_to_enum_and_drop_is_admin.php`
- `../../backend/database/migrations/2025_12_18_000000_optimize_booking_indexes.php`
- `../../backend/database/migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`
- `../../backend/database/migrations/2025_11_20_000100_add_token_expiration_to_personal_access_tokens.php`
- `../../backend/database/migrations/2025_11_21_150000_add_token_security_columns.php`
- `../../backend/database/migrations/2026_03_17_000001_harden_fk_delete_policies.php`
- `../../backend/database/migrations/2026_03_17_000002_add_check_constraint_rooms_max_guests.php`
- `../../backend/database/migrations/2026_03_17_000003_add_check_constraint_bookings_status.php`
- `../../backend/tests/Feature/Database/FkDeletePolicyTest.php`
- `../../backend/tests/Feature/Database/CheckConstraintTest.php`
- `../../.github/workflows/tests.yml`
