# Decision Memo — Ledger Immutability Triggers + FK Semantics (F-83 / F-89 / F-90)

**Status**: Accepted (user decision 2026-06-12, in-session)
**Date**: 2026-06-12
**Deciders**: Cao Ngoc Tau (FK decisions), Claude (analysis + options)
**Scope**: `deposit_events`, `ai_proposal_events`, `admin_audit_logs`
**Supersedes**: the blanket prevent-mutation trigger drafted under F-83 (rejected by Correction C-2)
**Related**: `docs/FINDINGS_BACKLOG.md` F-83/F-87/F-89/F-90, ADR-009 (Soft Deletes for Bookings), `.agent/rules/migration-safety.md`

> Format note: `docs/decisions/` is archived (`docs/decisions/ARCHIVED.md`); active standalone
> decision docs live at `docs/` root (precedent: `docs/ADR-AI-BOUNDARY.md`). An ADR-index entry
> in `docs/ADR.md` is a follow-up action below.

---

## Decision

How the three append-only ledgers' foreign keys and their new PostgreSQL immutability triggers
interact, given exactly one sanctioned row mutation: "the actor-reference column may be set to
NULL, and nothing else may change."

## Context

The 2026-06-11 DB P0 hardening pass (corrections C-1–C-4) established:

- **F-83** — the three ledgers are append-only *by convention only*: no prevent-mutation trigger
  exists on any of them (verified live: `pg_trigger` returns 0 non-internal rows for all three,
  2026-06-12), and the Laravel runtime role has full DML.
- **F-89** — `deposit_events.booking_id` is `ON DELETE CASCADE`: a booking hard-delete silently
  destroys the deposit financial audit trail.
- **F-90 / Correction C-2** — the originally drafted blanket `BEFORE UPDATE OR DELETE` trigger
  would break user deletion: `ON DELETE SET NULL` is implemented by PostgreSQL as a row-level
  UPDATE on the ledger, which a blanket trigger rejects. Any trigger must allowlist the sanctioned
  mutation, or the FK strategy must change.

If deferred: any code path or operator can UPDATE/DELETE ledger rows undetected, and booking
force-delete (`BookingService::forceDelete`, `bookings:prune-deleted`) keeps erasing deposit
history.

### Correction C-2 — source and conformance flags

C-2 has no standalone document in the repo. Its content exists in two places: the task prompt's
CONTEXT block and the digest in `docs/FINDINGS_BACKLOG.md` rows F-83/F-89/F-90 ("2026-06-11 DB P0
Hardening Pass"). The two sources agree; no conflict between them was found. `[CONFIRMED]`

Two flags raised against C-2's literal wording (per instruction: flagged, not silently resolved):

1. **Actor column naming.** C-2 states the sanctioned mutation as `actor_id → NULL` with check
   `to_jsonb(NEW) - 'actor_id' = to_jsonb(OLD) - 'actor_id'`. On `ai_proposal_events` the actor FK
   column is **`user_id`**, not `actor_id` (verified below). Applying C-2 literally would block
   user deletion on that ledger — the exact failure C-2 exists to prevent. **Resolution adopted:**
   C-2's intent is read as "the actor-reference column per ledger": `deposit_events.actor_id`,
   `admin_audit_logs.actor_id`, `ai_proposal_events.user_id`. `[CONFIRMED — schema; the intent
   reading was surfaced to and ratified by the user with the D1 decision]`
2. **Reattribution gap.** C-2's jsonb-diff check alone permits changing the actor column to *any*
   value (re-pointing a ledger row at a different user), not only NULL. The trigger predicate is
   tightened with `AND NEW.<actor_col> IS NULL`. This strengthens C-2; it does not contradict it.
   `[CONFIRMED — jsonb semantics]`

### Verified schema state (no [INFERRED] FK claims remain)

All shapes verified 2026-06-12 against both migration source and the live catalog
(`pg_get_constraintdef` on `soleil_test`):

| Ledger | FK column | Target | ON DELETE | Migration evidence | Live constraint |
|---|---|---|---|---|---|
| `deposit_events` | `booking_id` | `bookings(id)` | **CASCADE** | `2026_05_02_000002_create_deposit_events_table.php:48-50` | `deposit_events_booking_id_foreign` |
| `deposit_events` | `actor_id` (nullable) | `users(id)` | SET NULL | same file, lines 52-54 | `deposit_events_actor_id_foreign` |
| `ai_proposal_events` | `user_id` (nullable) | `users(id)` | SET NULL | created CASCADE (`2026_04_11_000001:13`), swapped to SET NULL + nullable on pgsql (`2026_04_29_000001:47-53`) | `ai_proposal_events_user_id_foreign` |
| `admin_audit_logs` | `actor_id` (nullable) | `users(id)` | SET NULL | `2026_03_12_000001_create_admin_audit_logs_table.php:22` | `admin_audit_logs_actor_id_foreign` |

The prompt's [INFERRED] shapes for `ai_proposal_events` and `admin_audit_logs` are now
**confirmed**, with one correction: the `ai_proposal_events` actor column is `user_id` (flag 1
above). `[CONFIRMED]`

Supporting facts:

- No triggers exist on any of the three tables today. `[CONFIRMED — live pg_trigger query]`
- All application writers are INSERT-only: `Deposit::transitionTo` → `DepositEvent::create`
  (`app/Models/Deposit.php:150`), `ProcessDepositRefund.php:132`, `AdminAuditService.php:41`,
  `ProposalConfirmationController.php:460,497`. `DepositEvent` additionally throws on Eloquent
  `updating`/`deleting` (`app/Models/DepositEvent.php:71-80`) — app-layer only, bypassable by raw
  SQL. `[CONFIRMED — grep sweep of backend/app; formal re-sweep incl. tests is a Phase 2 gate]`
- `ai_proposal_events` has `updated_at`; the other two ledgers do not. A referential SET NULL
  updates *only* the referencing column (no `updated_at` bump), so the sanctioned-shape check
  passes for FK-driven nulling. An Eloquent `save()` on a dirty row would bump `updated_at` and be
  rejected (two columns changed) — desired. `[CONFIRMED — PG referential-action semantics]`
- Booking hard-delete paths that exist today: admin force-delete
  (`AdminBookingController::forceDelete` → `BookingService::forceDelete`,
  `app/Services/BookingService.php:447`, documented as the GDPR erasure path) and the retention
  prune job (`app/Console/Commands/PruneOldSoftDeletedBookings.php:116`, a single bulk
  `forceDelete()` statement). `[CONFIRMED]`
- `User` has no SoftDeletes; user deletion is a hard DELETE. No in-app endpoint deletes users
  today, but `tests/Feature/Database/FkDeletePolicyTest.php` exercises `$user->forceDelete()` and
  `bookings.user_id`/`reviews.user_id` are SET NULL by the same design. `[CONFIRMED]`
- RESTRICT precedent: `service_recovery_cases.booking_id` is already `ON DELETE RESTRICT`
  (live: `fk_src_booking_id`). `[CONFIRMED — live catalog 2026-06-12]`

---

## Decision point D1 — actor FKs (all three ledgers) × trigger allowlist

The pattern is identical across the three ledgers (all actor FKs are SET NULL to `users`), so one
decision covers all three. Per-ledger divergence was considered and rejected: nothing in the
verified state distinguishes them on this axis.

### Option A: Keep SET NULL; trigger permits exactly "actor column → NULL, nothing else" — **CHOSEN**

UPDATE allowed iff `NEW.<actor_col> IS NULL AND to_jsonb(NEW) - '<actor_col>' = to_jsonb(OLD) - '<actor_col>'`;
all other UPDATEs and all DELETEs rejected.

- **Pros**: user hard-delete keeps working end-to-end `[CONFIRMED — FkDeletePolicyTest pattern]`;
  attribution survives via `actor_email`/`actor_role`/`actor_display_name` snapshots already
  populated at write time `[CONFIRMED — migration docblocks + writer code]`; zero app changes;
  the sanctioned mutation is explicit and machine-checkable `[CONFIRMED]`.
- **Cons**: trigger carries an allowlist branch (more logic than a blanket block) `[CONFIRMED]`;
  a manual `UPDATE ledger SET actor_id = NULL` is indistinguishable from FK-driven nulling at the
  DB layer — anonymization-by-operator remains possible, though it is exactly the mutation user
  deletion would produce anyway `[CONFIRMED]`.
- **Risks**: `to_jsonb` per-row cost on UPDATE is negligible at ledger volumes `[INFERRED]`;
  future column renames must keep the trigger's column literal in sync `[ACTION — covered by
  predicate guard test in Phase 2]`.
- **Alignment**: matches the deliberate 2026_04_29 move of `ai_proposal_events` *away* from
  destructive FK actions, the snapshot-column design, and `bookings.user_id` SET NULL precedent.

### Option B: Actor FKs → RESTRICT; trigger blocks ALL UPDATE/DELETE unconditionally

- **Pros**: strongest immutability; simplest trigger `[CONFIRMED]`.
- **Cons**: a user who ever acted on a ledger can never be hard-deleted `[CONFIRMED — RESTRICT
  semantics]`; contradicts the snapshot-column design and the 2026_04_29 migration's stated
  intent; the (future) GDPR account-deletion flow would require redesign before F-87
  crypto-shredding exists `[INFERRED]`.
- **Risks**: silently converts "delete user" into a hard failure across three tables — poor
  operator UX `[INFERRED]`.
- **Alignment**: conflicts with established "audit survives user deletion" architecture.

### Option C: Keep SET NULL; trigger permits any mutation when `pg_trigger_depth() > 0`

- **Pros**: simple predicate; automatically covers SET NULL; fully blocks direct client DML
  `[CONFIRMED]`.
- **Cons**: over-permits — *any* trigger-cascaded mutation passes, including a future CASCADE
  erasure, silently re-opening the F-89 class of hole; the sanctioned mutation is implicit rather
  than declared `[INFERRED]`.
- **Alignment**: weaker than A against the audit-trail-completeness goal.

**D1 decision: Option A** (user, 2026-06-12).

---

## Decision point D2 — `deposit_events.booking_id` (cascade-erasure hole, F-89)

### Option A: Change to `ON DELETE RESTRICT` — **CHOSEN**

- **Pros**: financial ledger outlives the booking; hard-delete of a booking with deposit history
  fails loudly (SQLSTATE 23503) instead of silently erasing the trail `[CONFIRMED — RESTRICT
  semantics]`; matches the `service_recovery_cases.booking_id` RESTRICT precedent `[CONFIRMED —
  live catalog]`; resolves F-89 rather than deferring it `[CONFIRMED]`.
- **Cons**: `BookingService::forceDelete` (GDPR path) now fails for bookings with deposit events
  `[CONFIRMED — code path verified]`; `bookings:prune-deleted` is a single bulk DELETE statement,
  so one retained ledger row aborts the whole prune batch `[CONFIRMED —
  PruneOldSoftDeletedBookings.php:116]`. Both are follow-up actions below, deliberately not
  silently fixed in this scope.
- **Risks**: true GDPR erasure for bookings with deposit history is blocked until F-87
  crypto-shredding lands (PII leaves via key destruction, rows stay) `[ACTION — F-87]`.
- **Alignment**: ADR-009 (bookings are soft-delete-first); ledger docblock ("durable record").

### Option B: Keep CASCADE; trigger permits DELETE when `pg_trigger_depth() > 0`

- **Pros**: zero behavior change for force-delete/prune `[CONFIRMED]`.
- **Cons**: legitimizes the F-89 hole — booking hard-delete still silently destroys the deposit
  audit trail, now with documentation `[CONFIRMED]`; contradicts the table's own purpose
  statement `[CONFIRMED — migration docblock]`.

### Option C: Keep CASCADE; trigger blocks all DELETE including cascade

- **Pros**: same blocking effect as A `[CONFIRMED]`.
- **Cons**: booking force-delete fails with a confusing trigger exception instead of a clean FK
  violation; the FK definition lies (declares CASCADE that can never complete; PG aborts the
  whole delete mid-cascade) `[CONFIRMED — trigger/RI interaction]`. Dominated by A; listed for
  completeness only.

**D2 decision: Option A** (user, 2026-06-12).

---

## Recommended Decision (finalized)

1. **All three ledgers** get a `BEFORE UPDATE OR DELETE` row-level trigger backed by one shared
   PL/pgSQL function per sanctioned shape:
   - UPDATE passes iff `NEW.<actor_col> IS NULL AND to_jsonb(NEW) - '<actor_col>' = to_jsonb(OLD) - '<actor_col>'`
     where `<actor_col>` is `actor_id` (`deposit_events`, `admin_audit_logs`) / `user_id`
     (`ai_proposal_events`), parameterized via `TG_ARGV[0]`.
   - Every other UPDATE and **every** DELETE raises `ERRCODE P0001` with message
     `"<table> is append-only: <op> rejected"` (tests assert SQLSTATE `P0001` + message).
   - pgsql-only migration (driver-guarded), consistent with every existing constraint migration.
2. **`deposit_events.booking_id`** FK is swapped from CASCADE to **RESTRICT** in a separate
   migration (drop + re-add constraint, same `deposit_events_booking_id_foreign` name).
3. Actor FKs stay `ON DELETE SET NULL` — untouched (D1 leaves them as-is; no FK migration for
   them).

Rationale: A/A is the only combination that simultaneously (a) closes F-83 at the storage layer,
(b) resolves F-89 instead of deferring it, (c) satisfies C-2's constraint that referential actions
fire row-level operations, and (d) preserves the existing user-deletion semantics the snapshot
columns were built for.

### F-89 / F-90 resolution statement (required by scope)

- **F-89 (cascade-erasure hole): resolved by D2-A** — RESTRICT makes booking hard-delete fail
  loudly while deposit history exists. Residual operational impact (prune job, GDPR path) is
  explicitly deferred to follow-ups #4/#5 with rationale: both need product decisions (skip-and-
  report vs. archive-first; crypto-shredding design F-87) that exceed this scope.
- **F-90 (SET NULL-vs-trigger conflict): resolved by D1-A** — the allowlist predicate admits
  exactly the row image SET NULL produces, so user deletion never trips the trigger.

## Consequences

**Short-term**
- Direct UPDATE/DELETE against any of the three ledgers fails at the DB layer regardless of code
  path or DB client — the append-only property stops being conventional. `[CONFIRMED once
  Phase 2 tests pass]`
- Admin force-delete and `bookings:prune-deleted` begin failing for bookings with deposit
  history. This is the decided, intended behavior; operators see SQLSTATE 23503. `[CONFIRMED]`
- `php artisan migrate:fresh` and CI suites pick up two new migrations; SQLite dev runs skip the
  pgsql-only statements (parity with existing constraint migrations). `[INFERRED]`

**Long-term**
- The trigger's column allowlist is schema-coupled: adding/renaming ledger columns requires no
  change (jsonb diff is column-set agnostic), but renaming the *actor* column requires updating
  `TG_ARGV`. Predicate guard tests (CheckConstraintTest pattern, F-80) detect drift. `[INFERRED]`
- GDPR erasure debt is now explicit: full booking erasure with deposit history is impossible
  until F-87 crypto-shredding lands. `[ACTION]`

## Follow-up Actions

| # | Action | Owner | Priority | Deadline |
|---|--------|-------|----------|----------|
| 1 | Phase 2: RESTRICT migration for `deposit_events.booking_id` | Claude (this session) | P0 | immediately, pending user go-ahead |
| 2 | Phase 2: immutability trigger migration (shared function + 3 triggers) | Claude (this session) | P0 | with #1 |
| 3 | Phase 2: tests — user deletion e2e, booking hard-delete vs RESTRICT, sanctioned-mutation pass/reject matrix, predicate guard, ledger-writer sweep | Claude (this session) | P0 | with #2 |
| 4 | `bookings:prune-deleted`: decide skip-and-report vs. archive-first for bookings with deposit events; today one such booking aborts the whole batch | Cao Ngoc Tau (product) | P1 | before next prune run |
| 5 | `BookingService::forceDelete` UX: surface 23503 as a clean 409 + Vietnamese message in `AdminBookingController` | backlog | P2 | with F-87 work |
| 6 | F-87 crypto-shredding design (true GDPR erasure without row deletion) | backlog | P1 | unscheduled (pre-existing) |
| 7 | Update `docs/FINDINGS_BACKLOG.md` F-83/F-89/F-90 statuses + `docs/DB_FACTS.md` after trigger migration merges | Claude | P1 | post-merge |
| 8 | Add ADR-index row in `docs/ADR.md` referencing this memo | Claude | P2 | post-merge |
