# Laravel Migrations (PostgreSQL-First) Skill

Use this skill for schema changes, indexes, constraints, enum changes, or DB portability decisions.

## When to Use This Skill

- You add or edit migrations in `backend/database/migrations/`.
- You introduce constraints, partial indexes, or specialized data types.
- You change token, booking, room, or user schema semantics.
- You need to preserve production PostgreSQL behavior while tests run on SQLite.

## Non-negotiables

- Design for PostgreSQL production first.
  - Default runtime DB is `pgsql` in `backend/config/database.php`.
  - CI backend jobs run PostgreSQL services.
- Keep SQLite test caveats explicit.
  - Local test default in `backend/phpunit.xml` is `sqlite` with `:memory:`.
  - Do not assume SQLite fully matches PostgreSQL features.
- For PostgreSQL-specific features, guard by driver and provide safe fallback.
  - Example: `btree_gist` extension and exclusion constraints.
- Use idempotent migration patterns where required.
  - Existing migrations use `Schema::hasColumn(...)` and index existence checks.
- Ensure reversible migrations.
  - `down()` must safely drop constraints/indexes/types added in `up()`.
- Encode booking overlap constraints exactly when touched.
  - `daterange(check_in, check_out, '[)')` exclusion with active status + non-deleted filter.

## Implementation Checklist

1. Confirm target behavior in PostgreSQL terms first.
   - Type selection, indexes, constraints, and operator semantics.
2. Assess SQLite impact for tests.
   - Add guards or alternative behavior for unsupported operations.
3. Implement `up()` with explicit safety.
   - Check existence before create/alter where needed.
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
- `../../.github/workflows/tests.yml`
