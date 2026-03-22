# Release Readiness Report

> Complete all sections before rendering verdict.

## Release Scope

- **From:** `_______________` (tag/commit)
- **To:** `_______________` (tag/commit)
- **Total commits:** ___
- **Files changed:** ___
- **Breakdown:** ___ backend / ___ frontend / ___ migrations / ___ docs / ___ infra / ___ tests

## Quality Gate Results

| Gate | Command | Result | Notes |
|---|---|---|---|
| Backend tests | `cd backend && php artisan test` | PASS / FAIL (___/___ tests) | |
| TypeScript | `cd frontend && npx tsc --noEmit` | PASS / FAIL | |
| Frontend tests | `cd frontend && npx vitest run` | PASS / FAIL (___/___ tests) | |
| Docker Compose | `docker compose config` | VALID / INVALID | |

**Any gate FAIL = RELEASE BLOCKED. No exceptions.**

## Booking-Flow Impact

- [ ] Booking-related code changed in this release: YES / NO
- If YES:
  - [ ] `verify-no-double-booking` skill executed — result: PASS / FAIL
  - [ ] Overlap tests pass — count: ___
  - [ ] `lockForUpdate()` pattern intact
  - [ ] `BookingStatus` enum unchanged / changed (if changed: constraint updated? YES / NO)

## Migration Assessment

- **New migrations in release:** ___
- **CRITICAL table migrations:** ___
- **Migration risk reviews completed:** ___/___

| Migration File | Target Table | Risk Tier | Notes |
|---|---|---|---|
| | | | |

## RBAC & Auth

- [ ] New endpoints added: YES / NO
- If YES: all have API-layer authorization middleware: YES / NO
- [ ] Auth code changed: YES / NO
- If YES: both Bearer + HttpOnly cookie paths verified: YES / NO

## Documentation Currency

- [ ] `verify-docs-vs-code` executed on critical docs
- DANGEROUS drift found: YES / NO
- MISLEADING drift found: ___ items
- STALE drift found: ___ items

## Cache & API Compatibility

- [ ] Cache invalidation aligned with state mutations: YES / NO / N/A
- [ ] API v1 backward compatibility preserved: YES / NO / N/A

## Last 10 Commits Risk Signal

- Fix commits in last 10: ___/10
- Revert commits in last 10: ___/10
- Commits touching same file: ___/10 (which file: _______________)
- **Elevated risk?** YES / NO

## RELEASE BLOCKED Flags

> If ANY flag is raised, release MUST NOT proceed.

| # | Flag | Status |
|---|---|---|
| 1 | Quality gate failure | CLEAR / BLOCKED |
| 2 | `verify-no-double-booking` failure | CLEAR / BLOCKED / N/A |
| 3 | Exclusion constraint modified without review | CLEAR / BLOCKED / N/A |
| 4 | DANGEROUS documentation drift | CLEAR / BLOCKED |
| 5 | Admin endpoint without API authorization | CLEAR / BLOCKED / N/A |
| 6 | `lockForUpdate()` removed from booking flow | CLEAR / BLOCKED / N/A |
| 7 | BookingStatus changed, constraint not updated | CLEAR / BLOCKED / N/A |
| 8 | FK CASCADE added to booking-domain table | CLEAR / BLOCKED / N/A |
| 9 | Unexplained revert in release scope | CLEAR / BLOCKED / N/A |

## RELEASE CONDITIONAL Flags

> Release may proceed; items must be resolved within 48 hours.

| # | Flag | Status | Resolution Deadline |
|---|---|---|---|
| 1 | STALE documentation | CLEAR / CONDITIONAL | |
| 2 | MISLEADING documentation (non-critical) | CLEAR / CONDITIONAL | |
| 3 | Thin test coverage (<3 tests) for new behavior | CLEAR / CONDITIONAL | |
| 4 | Cache invalidation not fully tested | CLEAR / CONDITIONAL | |
| 5 | Permission matrix not updated for new endpoint | CLEAR / CONDITIONAL | |

## Verdict

**RELEASE** / **RELEASE CONDITIONAL** / **RELEASE BLOCKED**

**Summary:** _______________

## Sign-Off

- **Prepared by:** _______________
- **Date:** _______________
- **Reviewed by:** _______________
- **Approved:** YES / NO
