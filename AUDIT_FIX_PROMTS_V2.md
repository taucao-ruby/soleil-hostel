# AUDIT_FIX_PROMTS_V2.md

**Scope:** AUDIT v2 issues only (`*-NEW-*`)  
**Last Updated:** February 11, 2026  
**v2 Baseline:** 98 issues identified (deep code-level review)

## Purpose

This playbook is the v2-only remediation and replay guide. It preserves the existing `batch 1..10` model used in commit history and provides copy/paste prompts per batch.

## Operating Rules

- Use only v2 issue IDs (`BE-NEW-*`, `FE-NEW-*`, `DV-NEW-*`, `SEC-NEW-*`, `TST-NEW-*`, `DOC-NEW-*`).
- Keep each batch independent and commit-scoped.
- Verify each batch before committing.
- Do not mix any v1 issue IDs into v2 prompts or commit messages.

## Safety Rails

- Minimal, targeted changes only.
- No secret values in source or docs.
- Keep migrations/config changes reversible and explicit.
- Avoid large refactors unless required to close a listed v2 issue.

## Commit Convention

Use batch-style commit messages:

`fix(<scope>): batch <n> - <summary> [<issue IDs>]`

## Domain Grouping

- Security/Auth: batches 3, 4, 5, 7, 8
- DB/Integrity: batches 2, 5, 7
- API: batches 5, 7
- CI/Docker: batches 1, 2, 9
- Frontend: batches 6, 8
- Docs/Ops: batches 9, 10

## v2 Batch Map (Preserved Naming)

| Batch | Focus | Commit Convention |
| --- | --- | --- |
| 1 | Docker + Redis + backend container baseline | `fix(infra): batch 1 - Docker key:generate, Redis password, phpredis, bind [DV-NEW-01,SEC-NEW-02,DV-NEW-03,DV-NEW-04,DV-NEW-05,DV-NEW-06]` |
| 2 | CI/CD + deploy hygiene | `fix(ci): batch 2 - PostgreSQL CI, Gitleaks scope, URL typo, HTTP migration [DV-NEW-02,DV-NEW-14,DV-NEW-15,DV-NEW-16,DV-NEW-20,DOC-NEW-11,TST-NEW-02]` |
| 3 | Auth security chain | `fix(auth): batch 3 - cookie lifetime, unified token check, middleware order, sanctum expiry` |
| 4 | Token model/auth flow consolidation | `fix(auth): batch 4 - PAT fillable, token expiry, auth consolidation, refresh race, Cashier import` |
| 5 | Middleware + services + booking schema | `fix(backend): batch 5 - rate limit ordering, cancellation_reason, health auth, service cleanup` |
| 6 | Frontend core API/types/tests/docs | `fix(frontend): batch 6 - refresh mutex, User type, Zod cleanup, data-testid, MySQL refs [FE-NEW-01,FE-NEW-06,FE-NEW-17,TST-NEW-01,DOC-NEW-01]` |
| 7 | Backend quality and security consistency | `fix(backend): batch 7 - model types, dead code, controller consistency, route cleanup [BE-NEW-06..10,16..23,29,30,36,40..43,SEC-NEW-06,SEC-NEW-07]` |
| 8 | Middleware + frontend feature/dependency cleanup | `fix(mixed): batch 8 - middleware fixes, frontend features, dead code, deps [BE-NEW-33..38,FE-NEW-02..25,SEC-NEW-08]` |
| 9 | Docker hardening + test coverage + docs | `fix(mixed): batch 9 - Docker hardening, test coverage, docs [DV-NEW-07..10,TST-NEW-03..06,DOC-NEW-02..09]` |
| 10 | Low-priority v2 sweep | `fix(cleanup): batch 10 - all low priority items [29 issues]` |

---

## Batch 1 - Docker + Redis + backend container baseline

**Goal / DoD**
- Resolve critical infra issues in compose/redis/backend image startup and security.

**Inspect**
- `docker-compose.yml`
- `redis.conf`
- `backend/Dockerfile`

**Verify**
- `docker compose config`
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Hardcoding Redis secrets.
- Breaking startup command parsing.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 1 only.
Constraints: minimal diffs, no unrelated refactor, no hardcoded secrets.
Tasks:
1) Make APP_KEY generation conditional in compose startup.
2) Externalize Redis password handling and secure redis healthcheck usage.
3) Ensure backend image has required redis/php extensions.
4) Apply Docker/Redis bind and runtime fixes from v2 batch 1 scope.
Verify: docker compose config && cd backend && php artisan test
Commit: fix(infra): batch 1 - Docker key:generate, Redis password, phpredis, bind [DV-NEW-01,SEC-NEW-02,DV-NEW-03,DV-NEW-04,DV-NEW-05,DV-NEW-06]
Do not push.
```

## Batch 2 - CI/CD + deploy hygiene

**Goal / DoD**
- Align CI DB engine, scan full repo for leaks, and remove insecure deploy pathing.

**Inspect**
- `.github/workflows/tests.yml`
- `.github/workflows/deploy.yml`
- `deploy-forge.sh`

**Verify**
- CI YAML validation
- `git grep -n "solelhotel"`

**Risks / Pitfalls**
- Leaving MySQL references in PostgreSQL CI path.
- Keeping HTTP migration endpoint logic.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 2 only.
Constraints: workflow/deploy scope only.
Tasks:
1) Move CI test DB service/env to PostgreSQL.
2) Expand secret scanning scope to repository root.
3) Fix domain typo references in deploy paths.
4) Remove/replace HTTP migration trigger with safer deploy pattern.
Verify: YAML validation + grep checks for deprecated values
Commit: fix(ci): batch 2 - PostgreSQL CI, Gitleaks scope, URL typo, HTTP migration [DV-NEW-02,DV-NEW-14,DV-NEW-15,DV-NEW-16,DV-NEW-20,DOC-NEW-11,TST-NEW-02]
Do not push.
```

## Batch 3 - Auth security chain

**Goal / DoD**
- Correct token lifetime behavior and close unified route validation gaps.

**Inspect**
- `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php`
- `backend/routes/api.php`
- `backend/app/Http/Middleware/CheckTokenNotRevokedAndNotExpired.php`
- `backend/config/sanctum.php`

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Middleware order can still allow expired token auth.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 3 only.
Constraints: auth middleware/controller scope only.
Tasks:
1) Fix cookie lifetime units in httpOnly auth controller.
2) Apply token-validity middleware to unified auth routes.
3) Reorder token middleware checks so expiration/revocation are evaluated before auth context.
4) Set Sanctum expiration safety net for raw auth:sanctum usage.
Verify: cd backend && php artisan test
Commit: fix(auth): batch 3 - cookie lifetime, unified token check, middleware order, sanctum expiry
Do not push.
```

## Batch 4 - Token model/auth flow consolidation

**Goal / DoD**
- Make token persistence and refresh flows consistent and safe.

**Inspect**
- `backend/app/Models/PersonalAccessToken.php`
- `backend/app/Models/User.php`
- Auth controllers
- `backend/app/Services/CancellationService.php`

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Race conditions in refresh logic.
- Legacy auth path drift.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 4 only.
Constraints: keep compatibility while hardening token flows.
Tasks:
1) Add missing token model fillable fields.
2) Enforce default token expiration where legacy creation paths exist.
3) Consolidate overlapping auth controller behavior and deprecate legacy overlap.
4) Fix refresh counter race condition with proper threshold handling.
5) Resolve optional dependency import risk in cancellation service.
Verify: cd backend && php artisan test
Commit: fix(auth): batch 4 - PAT fillable, token expiry, auth consolidation, refresh race, Cashier import
Do not push.
```

## Batch 5 - Middleware + services + booking schema

**Goal / DoD**
- Fix request throttling order, schema mismatch, health route exposure, and service-level duplication.

**Inspect**
- `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php`
- `backend/app/Services/BookingService.php`
- `backend/app/Services/CreateBookingService.php`
- `backend/routes/api.php`
- booking migration for `cancellation_reason`

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Running business logic before throttle check.
- Missing column regressions in strict DB modes.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 5 only.
Constraints: do not refactor unrelated services.
Tasks:
1) Ensure rate-limit checks occur before request execution.
2) Add/fix booking schema field alignment for cancellation reason.
3) Restrict sensitive health endpoints behind auth/role gates.
4) Clean duplicated service patterns and date validation edge cases for update flows.
Verify: cd backend && php artisan test
Commit: fix(backend): batch 5 - rate limit ordering, cancellation_reason, health auth, service cleanup
Do not push.
```

## Batch 6 - Frontend core API/types/tests/docs

**Goal / DoD**
- Stabilize frontend auth retry behavior, consolidate user typing, and align E2E selectors/docs.

**Inspect**
- `frontend/src/shared/lib/api.ts`
- `frontend/src/types/api.ts`
- Auth context/API files
- UI components referenced by E2E test IDs
- `README.dev.md`

**Verify**
- `cd frontend && npx tsc --noEmit`
- `cd frontend && npx vitest run`

**Risks / Pitfalls**
- Token refresh race causing duplicate refresh requests.
- Type duplication across auth layers.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 6 only.
Constraints: frontend core scope only.
Tasks:
1) Add refresh mutex/queue handling in API interceptor.
2) Consolidate User type to one canonical definition.
3) Remove dead Zod schema paths where no runtime usage exists.
4) Add missing data-testid attributes expected by E2E suite.
5) Remove stale MySQL references from README.dev.md.
Verify: cd frontend && npx tsc --noEmit && npx vitest run
Commit: fix(frontend): batch 6 - refresh mutex, User type, Zod cleanup, data-testid, MySQL refs [FE-NEW-01,FE-NEW-06,FE-NEW-17,TST-NEW-01,DOC-NEW-01]
Do not push.
```

## Batch 7 - Backend quality and security consistency

**Goal / DoD**
- Address model/controller/middleware consistency, remove dead code, and tighten route policy.

**Inspect**
- Backend models/controllers/middleware in v2 batch 7 scope
- `backend/routes/api.php`
- legacy route files

**Verify**
- `cd backend && php artisan route:list`
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Deleting code still referenced by routes.
- Inconsistent response envelopes and language handling.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 7 only.
Constraints: preserve behavior unless directly tied to listed issues.
Tasks:
1) Fix model scope/type/status inconsistencies.
2) Remove confirmed dead backend controllers/middleware.
3) Standardize controller response/security handling.
4) Clean route duplication and add missing throttle/security middleware.
Verify: cd backend && php artisan route:list && php artisan test
Commit: fix(backend): batch 7 - model types, dead code, controller consistency, route cleanup [BE-NEW-06..10,16..23,29,30,36,40..43,SEC-NEW-06,SEC-NEW-07]
Do not push.
```

## Batch 8 - Middleware + frontend feature/dependency cleanup

**Goal / DoD**
- Complete remaining middleware tuning and frontend feature/dependency correctness changes.

**Inspect**
- Remaining middleware files in v2 scope
- Frontend feature modules and dependency config

**Verify**
- `cd backend && php artisan test`
- `cd frontend && npx tsc --noEmit && npx vitest run`

**Risks / Pitfalls**
- Breaking auth/session UX with aggressive dead-code cleanup.
- Introducing dependency drift between scripts and lockfile.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 8 only.
Constraints: keep diffs focused to listed IDs.
Tasks:
1) Apply remaining middleware correctness and security header updates.
2) Fix frontend routing/form/validation mismatches.
3) Remove confirmed dead frontend files/functions.
4) Align dependency and script configuration with actual usage.
Verify: cd backend && php artisan test && cd ../frontend && npx tsc --noEmit && npx vitest run
Commit: fix(mixed): batch 8 - middleware fixes, frontend features, dead code, deps [BE-NEW-33..38,FE-NEW-02..25,SEC-NEW-08]
Do not push.
```

## Batch 9 - Docker hardening + test coverage + docs

**Goal / DoD**
- Finalize runtime hardening, coverage gaps, and doc accuracy updates.

**Inspect**
- `docker-compose.yml`
- backend/frontend tests added for v2 gaps
- docs files listed in v2 batch 9 scope

**Verify**
- `docker compose config`
- `cd backend && php artisan test`
- `cd frontend && npx vitest run`

**Risks / Pitfalls**
- Overstating counts without verification.
- Mismatched runtime docs vs actual compose/runtime behavior.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 9 only.
Constraints: verify every numeric claim before documenting it.
Tasks:
1) Harden compose dev exposure/resource defaults.
2) Close test gaps identified in v2 (coverage and FK/CSRF-related checks).
3) Update docs to match actual runtime and database state.
Verify: docker compose config && cd backend && php artisan test && cd ../frontend && npx vitest run
Commit: fix(mixed): batch 9 - Docker hardening, test coverage, docs [DV-NEW-07..10,TST-NEW-03..06,DOC-NEW-02..09]
Do not push.
```

## Batch 10 - Low-priority v2 sweep

**Goal / DoD**
- Resolve remaining low-priority v2 items and close the audit backlog.

**Inspect**
- Remaining low-priority v2 files across backend/frontend/devops/docs

**Verify**
- `cd backend && php artisan test`
- `cd frontend && npx tsc --noEmit && npx vitest run`

**Risks / Pitfalls**
- Scope creep by mixing medium/high follow-up work into low-priority sweep.

**Ready-to-paste prompt**

```text
You are a fix-forward agent for AUDIT v2 batch 10 only.
Constraints: low-priority v2 IDs only, no cross-batch scope expansion.
Tasks:
1) Apply low-priority cleanup items across app/devops/docs.
2) Keep each change traceable to a low-priority v2 issue.
Verify: cd backend && php artisan test && cd ../frontend && npx tsc --noEmit && npx vitest run
Commit: fix(cleanup): batch 10 - all low priority items [29 issues]
Do not push.
```

## Targeted v2 Follow-up Set (Post batch 10)

These were tracked after the main 10-batch run and are now resolved in current branch history:

- `DV-NEW-05` (compose/backend runtime path completed with php-fpm + nginx command flow)
- `BE-NEW-14` (auth controller overlap resolution)
- `BE-NEW-28` (booking update date-validation edge case)
- `SEC-NEW-05` (`detectAuthMode` defense-in-depth hardening)

Use targeted prompts only when replaying from an older pre-follow-up snapshot.