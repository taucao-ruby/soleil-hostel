# SKILL: pre-release-verification

> Category: Release | Priority: P0 | Blast Radius: CRITICAL (release gate)
> Last updated: 2026-03-22

## Purpose

Execute a structured release gate that aggregates domain-level verification results into an explicit RELEASE / RELEASE CONDITIONAL / RELEASE BLOCKED decision. This skill exists because green CI is not sufficient to catch booking-domain regressions — CI verifies syntax, types, and unit test assertions, but does not verify invariant alignment across layers.

## Trigger Conditions

Run this skill when ANY of the following occur:

1. A merge to `main` is proposed (PR targeting `main`)
2. A release tag is about to be created
3. After merging multiple PRs to `dev` and before promoting `dev` → `main`
4. On demand when confidence in release safety is low
5. After a hotfix that touches booking, auth, or migration code

## Required Inputs

- Git log of all commits since last release (`git log <last-release-tag>..HEAD`)
- List of all changed files since last release (`git diff --name-only <last-release-tag>..HEAD`)
- Results of `php artisan test` (backend, 0 failures required)
- Results of `npx tsc --noEmit` (frontend, 0 errors required)
- Results of `npx vitest run` (frontend, 0 failures required)
- Results of `docker compose config` (valid required)
- Access to migration files added since last release
- Access to `docs/agents/ARCHITECTURE_FACTS.md`

## Execution Steps

1. **Identify release scope.** Run:
   ```bash
   git log --oneline <last-tag>..HEAD
   git diff --name-only <last-tag>..HEAD
   ```
   Categorize changed files into: backend / frontend / migrations / docs / infra / tests.

2. **Run all quality gates.** Execute and record results:
   ```bash
   cd backend && php artisan test
   cd frontend && npx tsc --noEmit
   cd frontend && npx vitest run
   docker compose config
   ```
   Any failure = RELEASE BLOCKED. No exceptions.

3. **Migration safety check.** For each new migration since last release:
   - Verify `down()` method exists
   - Verify PG-only features have driver guards
   - Check if any migration touches CRITICAL tables (`bookings`, `rooms`, `personal_access_tokens`)
   - If yes: run `review-schema-change-risk` skill on each
   - For new table creation: check whether any column references a CRITICAL table PK. CRITICAL tables for FK purposes: `bookings`, `rooms`, `locations` (see `context/INVARIANTS.md` §CRITICAL Tables for FK Constraint Enforcement). If yes, verify an explicit FK constraint exists in the migration. Missing FK on a column referencing a CRITICAL table = CONDITIONAL (must be added within 48h)
   - Verify migration ordering (no timestamp conflicts)

4. **Booking flow verification.** If any changed file touches booking logic:
   - Run `verify-no-double-booking` skill
   - Verify overlap tests pass
   - Verify `lockForUpdate()` is present in every method on the canonical booking entry-point list below. If a new booking creation or cancellation method was added in this release, add it to this list as part of the PR. A method that creates or cancels bookings and is NOT on the canonical list = automatic BLOCK.

     **Canonical booking entry points (update when new paths are added):**
     - `CreateBookingService::create()` — `app/Services/CreateBookingService.php`
     - `CreateBookingService::createBookingWithLocking()` — `app/Services/CreateBookingService.php`
     - `BookingService::confirmBooking()` — `app/Services/BookingService.php`
     - `CancellationService::cancel()` — `app/Services/CancellationService.php`
     - `BookingService::cancelBooking()` — `app/Services/BookingService.php`

     **Secondary check (does not substitute for canonical list):** grep for `Booking::create()`, `DB::table('bookings')->insert()`, `Booking::insert()`, `Booking::upsert()`, `$booking->save()` to discover paths not yet on the list. Grep patterns miss repository wrappers, raw SQL, and bulk helpers — the canonical list is the authoritative source, not grep output.
   - Check: was `BookingStatus` enum modified? If yes, is the exclusion constraint updated?

5. **RBAC completeness check.** If any changed file touches routes, middleware, or controllers:
   - Verify all admin endpoints have `role:admin` or `role:moderator` middleware
   - Verify controller-level `Gate::authorize()` calls are present
   - Cross-reference with `docs/PERMISSION_MATRIX.md`
   - Check: are there new endpoints without authorization middleware?

6. **Auth verification.** If any changed file touches auth controllers, middleware, or token logic:
   - Verify both Bearer and HttpOnly cookie paths work
   - Verify token expiry and revocation enforcement
   - Check: was `personal_access_tokens` schema modified?

7. **Documentation currency.** Run `verify-docs-vs-code` skill on:
   - `docs/agents/ARCHITECTURE_FACTS.md`
   - `docs/PERMISSION_MATRIX.md`
   - Any docs that reference changed code
   - If DANGEROUS drift found: RELEASE BLOCKED

8. **Cache invalidation check.** If booking or room state mutation code changed:
   - Verify cache invalidation events fire on state changes
   - Check: can stale cache serve outdated availability?

9. **API version compatibility.** If API endpoints changed:
   - Verify v1 endpoints maintain backward compatibility
   - Check: are any v1 responses changed in structure?
   - Verify frontend API calls match backend response format

10. **Last 10 commits heuristic.** Review the 10 most recent commits:
    - Count how many touch booking-critical files
    - Count how many are "fix" commits (may indicate instability)
    - If >5 of last 10 are fixes to the same area: flag elevated risk
    - If any commit message contains "revert": investigate what was reverted and why

11. **Fill in release readiness report** (`skill-os/templates/release-readiness-report.md`).

12. **Render verdict.**

## Invariant Check

| Invariant | Release gate check |
|---|---|
| **INV-1** | Overlap tests pass; half-open semantics verified if booking code changed |
| **INV-2** | BookingStatus enum unchanged, or constraint updated to match |
| **INV-3** | Soft-delete filter intact in overlap queries and constraint |
| **INV-4** | Exclusion constraint migration not modified (or BLOCK if modified) |
| **INV-5** | `rooms.location_id` remains source of truth; trigger intact |
| **INV-6** | `lock_version` columns present; `lockForUpdate()` in booking flows |
| **INV-7** | All admin endpoints have API-layer authorization |
| **INV-8** | Review-booking FK intact; one-review-per-booking constraint present |
| **INV-9** | Both auth modes functional |
| **INV-10** | No DANGEROUS documentation drift |

## RELEASE BLOCKED Criteria

The release **MUST NOT proceed** if any of these are true:

1. Any quality gate fails (test, tsc, vitest, docker compose)
2. `verify-no-double-booking` skill fails on any invariant
3. A migration touches the exclusion constraint without full review
4. DANGEROUS documentation drift detected
5. A new admin endpoint exists without API-layer authorization
6. `lockForUpdate()` missing from any booking creation or cancellation path — whether removed from an existing path or absent from a newly added one
7. `BookingStatus` enum modified but exclusion constraint not updated
8. FK cascade policy changed to `CASCADE` on booking-domain tables
9. A "revert" commit exists in the release scope without explanation

## RELEASE CONDITIONAL Criteria

The release **may proceed** with these conditions (must be resolved within 48 hours post-deploy):

1. STALE documentation detected — update docs within 48h
2. MISLEADING documentation detected on non-critical paths — update within 48h
3. Test coverage for new booking behavior exists but is thin (<3 tests) — add tests within 48h
4. Cache invalidation change not fully tested — monitor cache hit rates for 48h
5. New endpoint added with authorization but without permission matrix update — update matrix within 48h
6. New table has a column referencing a CRITICAL table PK without an explicit FK constraint — add FK within 48h

## CI Pipeline Gaps

These checks are NOT covered by the CI pipeline and require manual/skill-based verification:

- **Domain-level overlap correctness:** CI runs tests but cannot verify that the exclusion constraint SQL matches the PHP overlap scope
- **RBAC completeness:** CI does not check that every route has appropriate middleware
- **Documentation currency:** CI does not compare docs to code
- **FK cascade policy correctness:** CI runs migrations but doesn't verify cascade behavior matches business rules
- **Cross-layer consistency:** CI tests each layer in isolation; does not verify constraint ↔ application alignment
- **Cache invalidation correctness:** CI does not test that cache is invalidated on every state mutation path

## Expected Output

A completed `release-readiness-report.md` template with:
- Release scope summary
- Quality gate results (all must pass)
- Booking-flow impact assessment
- Migration count and risk summary
- RELEASE BLOCKED flags (must be empty to release)
- RELEASE CONDITIONAL flags (with resolution deadlines)
- Final verdict: RELEASE / RELEASE CONDITIONAL / RELEASE BLOCKED
- Sign-off line

## Verification Checklist

1. [ ] All four quality gates pass (test, tsc, vitest, docker compose)
2. [ ] Release scope identified (files changed, commits counted)
3. [ ] Each new migration reviewed for rollback safety
4. [ ] No migration touches exclusion constraint (or BLOCK raised)
5. [ ] Booking overlap tests pass if booking code changed
6. [ ] `lockForUpdate()` pattern intact in booking flows
7. [ ] RBAC middleware present on all admin endpoints
8. [ ] Controller-level authorization calls present
9. [ ] Both auth modes verified if auth code changed
10. [ ] `verify-docs-vs-code` run on critical docs — no DANGEROUS drift
11. [ ] Cache invalidation aligned with state mutation code
12. [ ] API v1 backward compatibility preserved
13. [ ] Last 10 commits reviewed for risk signals
14. [ ] Release readiness report completed
15. [ ] Verdict rendered: RELEASE / CONDITIONAL / BLOCKED

## Anti-Patterns

### AP-1: Releasing because CI is green
**What:** Merging to main because all CI checks pass, without domain-level verification.
**Why it fails:** CI checks syntax, types, and test assertions. It does not check whether the exclusion constraint SQL matches the PHP overlap scope, whether RBAC is enforced at the API layer, or whether docs are current. Domain regressions pass CI.

### AP-2: Skipping release gate for "small" changes
**What:** Bypassing pre-release verification because the PR is "just a small fix."
**Why it fails:** A one-line change to an overlap query comparison operator (`<` → `<=`) breaks same-day turnover. Small changes to booking-critical code have outsized blast radius.

### AP-3: Resolving BLOCKED by downgrading to CONDITIONAL
**What:** Reclassifying a BLOCKED item as CONDITIONAL to proceed with the release.
**Why it fails:** BLOCKED criteria exist because the failure mode is immediate and severe (double-booking, data loss, auth bypass). Downgrading doesn't reduce the risk — it just delays the damage.

### AP-4: Ignoring the "last 10 commits" heuristic
**What:** Skipping the commit history review because tests pass.
**Why it fails:** A pattern of fix-revert-fix in the same area indicates instability. Multiple fixes to the same code path suggest the root cause hasn't been found. This pattern often precedes a production regression.

## Edge Cases

### EC-1: Release contains only documentation changes
Quality gates still run (docker compose config, tsc, vitest). Booking-flow and migration checks can be skipped. Documentation currency check becomes the primary gate.

### EC-2: Release contains a database rollback migration
Elevated scrutiny. Verify the rollback migration doesn't drop the exclusion constraint, remove FK hardening, or delete data columns. If it reverses a CRITICAL-table migration, treat as BLOCK until reviewed.

### EC-3: Release contains a hotfix for a production double-booking
Maximum urgency, but do NOT skip the gate. Run `verify-no-double-booking` to confirm the fix is correct. A wrong hotfix is worse than a delayed hotfix.

### EC-4: Release scope spans 50+ commits
For large releases, prioritize: (1) booking-domain changes, (2) migration changes, (3) auth changes, (4) everything else. The last-10-commits heuristic becomes last-20. Consider splitting the release.

## References

| Reference | Path |
|---|---|
| Quality gate commands | `docs/COMMANDS_AND_GATES.md` |
| Architecture facts | `docs/agents/ARCHITECTURE_FACTS.md` |
| Permission matrix | `docs/PERMISSION_MATRIX.md` |
| Verify double-booking skill | `skill-os/skills/verification/verify-no-double-booking/SKILL.md` |
| Verify docs skill | `skill-os/skills/verification/verify-docs-vs-code/SKILL.md` |
| Schema risk skill | `skill-os/skills/review/review-schema-change-risk/SKILL.md` |
| Release report template | `skill-os/templates/release-readiness-report.md` |
| CI workflows | `.github/workflows/` |

## Changelog

| Date | Change |
|---|---|
| 2026-03-22 | Initial skill creation. Covers all 10 invariants as release gate checks. |
