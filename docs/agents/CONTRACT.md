# Agent Contract — Soleil Hostel

Definition of Done (DoD) for AI agent tasks in this repository.

## DoD: Code Changes

- [ ] All quality gates pass (see [COMMANDS_AND_GATES.md](../COMMANDS_AND_GATES.md)):
  - `cd backend && php artisan test` — 0 failures
  - `cd frontend && npx tsc --noEmit` — 0 errors
  - `cd frontend && npx vitest run` — 0 failures
  - `docker compose config` — valid
- [ ] No new lint errors introduced
- [ ] Architecture invariants preserved (see [ARCHITECTURE_FACTS.md](./ARCHITECTURE_FACTS.md))
- [ ] Existing tests not broken; new tests added for new behavior
- [ ] COMPACT updated with what changed, files touched, gate results
- [ ] PR description includes: summary, test plan, files changed
- [ ] Changes reviewed by human before merge to main
- [ ] Max 25 files changed in one pass; if more, split into batches

## DoD: Documentation Changes

- [ ] No application code changed
- [ ] All new/updated docs pass markdown lint (no unclosed blocks, valid tables)
- [ ] No broken relative links in changed docs (spot-check minimum 5 links)
- [ ] COMPACT updated with today's date and summary
- [ ] AUDIT_[date].md created with findings (if audit task)
- [ ] Changes reviewed by human before merge to main
- [ ] Max 25 files changed in one pass; if more, split into batches

## DoD: Booking Domain Changes

All items from "Code Changes" above, plus:

- [ ] Overlap tests cover: adjacent dates, same-day turnover, soft-deleted bookings
- [ ] Half-open interval `[check_in, check_out)` behavior preserved
- [ ] `lockForUpdate()` used in booking creation/cancellation transactions
- [ ] Exclusion constraint alignment verified (active statuses + deleted_at IS NULL)

## DoD: Auth / Token Changes

All items from "Code Changes" above, plus:

- [ ] Both Bearer and HttpOnly cookie paths tested
- [ ] Token expiry, revocation, and refresh rotation tested
- [ ] Suspicious activity detection (refresh abuse) verified
- [ ] No secrets committed or hardcoded

## DoD: Migration Changes

All items from "Code Changes" above, plus:

- [ ] Rollback tested (`php artisan migrate:rollback --step=1`)
- [ ] SQLite test compatibility considered (PG-only features need guards)
- [ ] Index and constraint names are explicit and production-safe
- [ ] Idempotent patterns used where production state may vary

## Scope Boundaries

- Keep diffs small and scoped to the task
- Do not introduce wide refactors when task scope is narrow
- Do not change files outside the stated task scope
- If a code issue is found outside scope: log in `docs/FINDINGS_BACKLOG.md`, do not fix

## Bypass Policy

- `--no-verify` is allowed only with documented reason in commit message
- Prohibited on `main`/production branches
- Notify team lead when bypassing
