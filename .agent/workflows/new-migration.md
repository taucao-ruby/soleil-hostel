# Workflow: New Migration

Portable procedure for creating a safe, production-ready Laravel migration.
Follow these steps in order. Each step must complete before the next begins.

## STOP Conditions (check before starting)

```
STOP if any of the following are true:
- The migration touches the booking EXCLUDE constraint and the task scope does not include booking overlap changes
- You cannot run php artisan migrate:rollback --step=1 locally (no DB access)
```

## Steps

### 1. LOAD rule

LOAD `.agent/rules/migration-safety.md`

Confirm all Required Invariants are understood before writing any code.

### 2. READ canonical constraint facts (if touching booking table)

READ `docs/agents/ARCHITECTURE_FACTS.md` § "Booking Domain — Overlap Prevention" and § "DB Constraints Added"

Required if migration touches: `bookings`, `rooms`, `locations`, or any constraint/index referenced in ARCHITECTURE_FACTS.md.

### 3. GENERATE the migration file

RUN `php artisan make:migration <descriptive_name>`

Naming: `YYYY_MM_DD_HHMMSS_<verb>_<subject>_<context>.php`

### 4. WRITE up() with explicit names

Requirements:
- All constraint and index names must be explicit strings — never rely on auto-generated names.
- PostgreSQL-only features (`EXCLUDE`, `daterange`, `btree_gist`, partial indexes, PG ENUMs) require guard:
  ```php
  if (DB::getDriverName() === 'pgsql') { ... }
  ```
- Use idempotency checks (`Schema::hasColumn`, `Schema::hasIndex`) when modifying existing tables.

### 5. WRITE down() that fully reverses up()

Requirements:
- `down()` must exist and must safely reverse every change in `up()`.
- Empty body or comment-only body → STOP, do not commit.
- Reversal must drop objects in reverse order to avoid FK/constraint errors.

### 6. RUN forward migration

```bash
cd backend && php artisan migrate
```

Confirm 0 errors. Check `php artisan migrate:status` — new migration shows as Ran.

### 7. RUN rollback and re-migrate

```bash
cd backend && php artisan migrate:rollback --step=1
cd backend && php artisan migrate
```

Both must complete without error or schema corruption.

### 8. RUN test suite

```bash
cd backend && php artisan test
```

Expected: 0 failures. New behavior requires new tests — do not skip.

## Expected Output

- Migration file at `backend/database/migrations/` with explicit constraint/index names
- `migrate:rollback --step=1` passes cleanly
- Full test suite passes
- If touching booking constraint: booking overlap tests pass (see `skills/laravel/booking-overlap-skill.md` for test list)
