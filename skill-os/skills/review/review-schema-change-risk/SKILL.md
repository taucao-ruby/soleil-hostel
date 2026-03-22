# SKILL: review-schema-change-risk

> Category: Review | Priority: P0 | Blast Radius: HIGH
> Last updated: 2026-03-22

## Purpose

Assess the risk of a database migration before it is merged. Classify risk into tiers (LOW / MEDIUM / HIGH / BLOCK) based on the migration's impact on booking-critical invariants, FK integrity, constraint correctness, and rollback safety. Prevent migrations that silently weaken the booking system's structural guarantees.

## Trigger Conditions

Run this skill when ANY of the following occur:

1. A new migration file is added to `backend/database/migrations/`
2. An existing migration is modified (even comments or formatting)
3. A PR includes schema changes to `bookings`, `rooms`, `locations`, `reviews`, `stays`, `room_assignments`, or `personal_access_tokens` tables
4. A column is added, dropped, renamed, or has its type/nullability changed on any booking-domain table
5. A FK is added, dropped, or has its cascade policy changed
6. A constraint (CHECK, UNIQUE, EXCLUDE) is added, dropped, or modified
7. An index is added or dropped on a booking-domain table

## Required Inputs

- The migration file(s) under review
- `docs/agents/ARCHITECTURE_FACTS.md` — current schema invariants
- `docs/DB_FACTS.md` — current index and constraint catalog
- Access to the full migration history in `backend/database/migrations/`
- Knowledge of which tables are booking-critical (see Escalation Columns below)

## Execution Steps

1. **Read the migration file.** Identify:
   - Target table(s)
   - Operation type: add column, drop column, rename column, change type, change nullability, add/drop FK, add/drop constraint, add/drop index
   - Whether the migration has a `down()` method (rollback)
   - Whether the migration has PG-only guards (`DB::getDriverName() === 'pgsql'`)

2. **Classify the target table.** Risk tiers by table:
   - **CRITICAL tables:** `bookings`, `rooms`, `personal_access_tokens` — changes here require full invariant review
   - **HIGH tables:** `locations`, `reviews`, `stays`, `room_assignments` — changes affect domain but not core overlap logic
   - **STANDARD tables:** all others — standard review process

3. **Check FK changes.** For each FK added, dropped, or modified:
   - Document the old and new cascade policy (`CASCADE`, `SET NULL`, `RESTRICT`, `NO ACTION`)
   - Assess: does the new policy preserve booking history? (INV-5, hardening migrations)
   - Flag: `CASCADE` on any FK pointing to `bookings` or from `bookings` to `users` — this deletes booking records when parent is deleted
   - Cross-reference with `docs/agents/ARCHITECTURE_FACTS.md` §DB Hardening

4. **Check nullability changes.** For each column with nullability change:
   - If column becomes nullable: can existing queries handle NULL? Does the overlap constraint still work?
   - If column becomes NOT NULL: is there a default? Will existing rows fail?
   - **Escalation columns** (see below): any nullability change = automatic HIGH risk

5. **Check constraint changes.** For each constraint added, dropped, or modified:
   - If the exclusion constraint is touched (DROP CONSTRAINT, ALTER CONSTRAINT, RENAME, or any ALTER TABLE that causes PostgreSQL to rebuild the table): **automatic BLOCK** — requires verify-no-double-booking skill execution before merge
   - If an index is dropped: check whether it is the GIST index backing the `no_overlapping_bookings` exclusion constraint. The exclusion constraint requires a GIST index to function — dropping that index makes the constraint unenforceable. If the dropped index supports the exclusion constraint: **automatic BLOCK** (same as dropping the constraint itself)
   - If a CHECK constraint is modified: verify the new values match application-level enum
   - If a UNIQUE constraint is dropped: verify no application code assumes uniqueness

6. **Check rollback safety.** Verify:
   - `down()` method exists and reverses the `up()` correctly
   - `down()` does not drop data-bearing columns without backup
   - If migration is irreversible (e.g., drops a column with data), document this explicitly
   - Rollback does not leave the database in an inconsistent state with the exclusion constraint

7. **Check PG/SQLite compatibility.** Verify:
   - PG-only features (EXCLUDE USING, CHECK constraints, custom types) are guarded with `DB::getDriverName()`
   - Migration works in both test (SQLite) and production (PostgreSQL) environments
   - No raw SQL without driver guard

8. **Check soft-delete impact.** If the migration changes columns used in soft-delete queries:
   - Verify `deleted_at` column is not renamed, dropped, or made non-nullable
   - Verify exclusion constraint's `deleted_at IS NULL` filter is not invalidated
   - Verify `SoftDeletes` trait still functions correctly with schema change

9. **Fill in the migration risk review template** (`skill-os/templates/migration-risk-review.md`) with findings.

10. **Assign risk tier.** Apply the criteria below.

## Invariant Check

| Invariant | What to verify | Trigger for escalation |
|---|---|---|
| **INV-1** (half-open intervals) | Migration does not change `check_in`/`check_out` column types or add conflicting date columns | Type change on date columns |
| **INV-3** (soft-delete exclusion) | `deleted_at` column not dropped, renamed, or semantics changed | Any change to `deleted_at` |
| **INV-4** (exclusion constraint) | Constraint not dropped, modified, or conflicted by new columns | Any constraint modification = BLOCK |
| **INV-5** (location denormalization) | `bookings.location_id` not changed to NOT NULL or given wrong FK policy | FK or nullability change on `bookings.location_id` |
| **INV-6** (locking columns) | `lock_version` columns not dropped or defaulted incorrectly | Any change to `lock_version` columns |

## Expected Output

A completed `migration-risk-review.md` template with:
- Risk tier verdict (LOW / MEDIUM / HIGH / BLOCK)
- Specific findings for each check
- Required actions before merge (if any)
- Rollback plan assessment

## Risk Tier Criteria

### BLOCK — Do not merge without resolution
- Exclusion constraint is dropped, modified, or conflicted
- GIST index backing the `no_overlapping_bookings` exclusion constraint is dropped (makes constraint unenforceable)
- FK cascade change would delete booking records (`CASCADE` on `bookings` parent FK)
- `deleted_at` column dropped or renamed on `bookings` table
- `check_in` or `check_out` column type changed
- Migration has no `down()` method AND modifies a CRITICAL table
- `lock_version` column dropped

### HIGH — Requires senior review + additional testing
- FK cascade policy changed on any booking-domain table
- Nullability changed on an escalation column
- New status value added to `bookings.status` without constraint update
- Index dropped on a booking-query-critical column
- PG-only features without driver guard

### MEDIUM — Standard review with documented assessment
- New column added to a CRITICAL table
- New index added
- CHECK constraint values updated (matching application enum)
- New FK added with appropriate cascade policy

### LOW — Routine change
- Column added to STANDARD table
- Index added to non-critical table
- Comment or formatting change in migration
- New migration for a new table unrelated to booking domain

## Escalation Columns

Changes to these columns require **immediate escalation** (minimum HIGH risk):

| Table | Column | Why |
|---|---|---|
| `bookings` | `room_id` | Core of overlap constraint |
| `bookings` | `check_in` | Core of overlap constraint |
| `bookings` | `check_out` | Core of overlap constraint |
| `bookings` | `status` | Determines overlap-blocking set |
| `bookings` | `deleted_at` | Exclusion constraint filter |
| `bookings` | `location_id` | Denormalized FK, analytics dependency |
| `bookings` | `user_id` | FK cascade affects booking history |
| `rooms` | `location_id` | Source of truth for location; trigger dependency |
| `rooms` | `lock_version` | Optimistic locking integrity |
| `rooms` | `status` | Room availability logic |
| `personal_access_tokens` | `token_hash` | Auth lookup path |
| `personal_access_tokens` | `token_identifier` | Cookie-based auth |
| `personal_access_tokens` | `revoked_at` | Token lifecycle enforcement |
| `personal_access_tokens` | `expires_at` | Token lifecycle enforcement |

## Verification Checklist

1. [ ] Migration file has both `up()` and `down()` methods
2. [ ] Target table classified as CRITICAL / HIGH / STANDARD
3. [ ] FK cascade policies documented (old → new)
4. [ ] No FK uses `CASCADE` toward `bookings` from a parent table
5. [ ] Nullability changes on escalation columns flagged
6. [ ] Exclusion constraint not modified (or BLOCK raised if it is)
7. [ ] `deleted_at` column unchanged on `bookings`
8. [ ] PG-only SQL wrapped in driver guard
9. [ ] Rollback (`down()`) tested or assessed for safety
10. [ ] New column has appropriate default value if NOT NULL
11. [ ] CHECK constraint values match application enum values
12. [ ] Index changes do not remove booking-query-critical indexes
13. [ ] Soft-delete queries still valid after schema change
14. [ ] Risk tier assigned with justification
15. [ ] Migration risk review template completed

## Anti-Patterns

### AP-1: Adding CASCADE to booking FKs
**What:** Setting `onDelete('cascade')` on `bookings.user_id` or `bookings.room_id`.
**Why it fails:** Deleting a user or room cascade-deletes all their bookings, destroying financial and audit history. The codebase hardened these to SET NULL and RESTRICT respectively (migration `2026_03_17_000001`).

### AP-2: Dropping the exclusion constraint "temporarily"
**What:** Dropping `no_overlapping_bookings` in one migration, planning to re-add in a later one.
**Why it fails:** Between migrations, the database has no overlap defense. If the re-add migration fails or is delayed, production runs without the constraint. Never drop it — modify in place or create a replacement before dropping.

### AP-3: Changing column type without data migration
**What:** Changing `check_in` from `date` to `timestamp` without converting existing data.
**Why it fails:** Existing `daterange()` in the exclusion constraint expects `date` type. Type mismatch breaks the constraint silently or throws at runtime.

### AP-4: Missing driver guard on PG-only SQL
**What:** Using raw `ALTER TABLE ... ADD CONSTRAINT ...` without checking `DB::getDriverName() === 'pgsql'`.
**Why it fails:** Migration crashes on SQLite in test environment. Tests cannot run, blocking CI.

### AP-5: Rollback that drops data columns
**What:** `down()` method that drops a column containing production data.
**Why it fails:** If rollback is executed in production, data is lost permanently. Rollbacks should reverse structural changes, not delete data.

## Edge Cases

### EC-1: Migration adds a new booking status
New status `no_show` added. Must check: is it overlap-blocking? If yes, the exclusion constraint WHERE clause must be updated. If no, verify it's not accidentally included in PHP overlap scope.

### EC-2: Migration renames `deleted_at` to `archived_at`
The exclusion constraint references `deleted_at IS NULL`. Rename breaks the constraint. `SoftDeletes` trait expects `deleted_at`. This is a BLOCK.

### EC-3: Migration adds composite index that conflicts with exclusion constraint
Adding an index on `(room_id, check_in, check_out)` is fine. Adding a UNIQUE constraint on the same columns would conflict with the EXCLUDE constraint's semantics.

### EC-4: Migration changes `rooms.location_id` to nullable
The `trg_booking_set_location` trigger copies `rooms.location_id` to `bookings.location_id`. If rooms.location_id becomes nullable, the trigger may set NULL on bookings, breaking location-based queries.

### EC-5: Migration on `stays` table affects booking relationship
`stays` has a UNIQUE `booking_id` FK. Dropping this FK or making it nullable would allow multiple stays per booking, violating the one-stay-per-booking invariant.

## References

| Reference | Path |
|---|---|
| Exclusion constraint migration | `backend/database/migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php` |
| FK hardening migration | `backend/database/migrations/2026_03_17_000001_harden_fk_delete_policies.php` |
| CHECK constraints migration | `backend/database/migrations/2026_03_17_000002_add_rooms_max_guests_check.php` |
| Status CHECK constraint | `backend/database/migrations/2026_03_17_000003_add_bookings_status_check.php` |
| Architecture facts | `docs/agents/ARCHITECTURE_FACTS.md` |
| DB facts | `docs/DB_FACTS.md` |
| Migration risk template | `skill-os/templates/migration-risk-review.md` |

## Changelog

| Date | Change |
|---|---|
| 2026-03-22 | Initial skill creation. Covers FK, nullability, constraint, and rollback risk assessment. |
