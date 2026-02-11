# AUDIT_FIX_PROMPTS_V1.md

**Scope:** AUDIT v1 issues only  
**Last Updated:** February 11, 2026  
**v1 Baseline:** 61 issues identified, 54 fixed, 7 deferred

## Purpose

This playbook is for finishing or replaying AUDIT v1 remediation only. It is structured for copy/paste batch execution with strict commit naming that matches existing batch language.

## Operating Rules

- Work only on v1 issue IDs (`BE-*`, `FE-*`, `DV-*`, `SEC-*`, `TST-*`, `DOC-*` from v1 audit scope).
- Keep commits small and batch-scoped.
- Verify each batch before commit.
- Do not mix v1 and v2 issue IDs in one prompt or commit.

## Safety Rails

- Minimal diffs only; no unrelated refactor.
- No secret values in code, docs, or prompts.
- Prefer config/env keys over hardcoded credentials.
- Stop and report if a batch requires broad architectural change outside its scope.

## Commit Convention

Use this format for every v1 batch:

`fix(<scope>): batch <n> - <summary> [<issue IDs>]`

## v1 Batch to Commit Map

| Batch | Focus | Commit Convention |
| --- | --- | --- |
| 1 | Config hardening (`env()` to `config()`) | `fix(config): batch 1 - env() to config() hardening [BE-023,BE-024,BE-025,SEC-001]` |
| 2 | DB default/runtime alignment | `fix(db): batch 2 - align runtime to PostgreSQL defaults [BE-034]` |
| 3 | Redis + Docker security baseline | `fix(infra): batch 3 - Redis and Docker security baseline [DV-001,DV-002,DV-003,DV-009,DV-010,SEC-002]` |
| 4 | CI security job activation | `fix(ci): batch 4 - restore security job execution [DV-012]` |
| 5 | Frontend dependency/API consolidation | `fix(frontend): batch 5 - remove bogus deps and unify API client [FE-001,FE-005,FE-006]` |
| 6 | Dead code + unified route auth | `fix(api): batch 6 - remove dead code and secure unified routes [BE-009,BE-017,BE-029,BE-030,FE-008]` |
| 7 | Admin booking + auth response hardening | `fix(backend): batch 7 - admin booking and auth response hardening [BE-011,BE-012,BE-013,BE-014,BE-026]` |
| 8 | Service deduplication | `fix(backend): batch 8 - consolidate cancellation and availability services [BE-018,BE-019,BE-020]` |
| 9 | Frontend route/env/CSP fixes | `fix(frontend): batch 9 - routing env and CSP corrections [FE-002,FE-007,FE-020,FE-021,SEC-004]` |
| 10 | Session/docker/FK security hardening | `fix(security): batch 10 - session docker and FK hardening [SEC-003,SEC-004,DV-004,DV-019,BE-035,BE-038]` |
| 11 | Model cast/relationship/fillable corrections | `fix(models): batch 11 - model casts relationships fillable cleanup [BE-001,BE-002,BE-003,BE-004,BE-005,BE-006,BE-007,BE-008]` |
| 12 | API response standardization | `fix(api): batch 12 - standardize API response envelope [BE-015]` |
| 13 | Frontend duplicate/type cleanup | `fix(frontend): batch 13 - remove duplicates and consolidate types [FE-009,FE-010,FE-017,FE-018,FE-019]` |
| 14 | Frontend test baseline | `test(frontend): batch 14 - add core unit test baseline [TST-001,TST-002]` |
| 15 | Docker multi-stage + CI consolidation | `fix(ci): batch 15 - Docker multi-stage and CI consolidation [DV-006,DV-013,DV-014,DV-015]` |
| 16 | Low-priority cleanup sweep | `fix(cleanup): batch 16 - low-priority cleanup sweep [BE-037,BE-040,DV-008,DV-020,FE-003,FE-004,FE-014,FE-015,FE-022,FE-023,SEC-006,SEC-007,SEC-009,SEC-010]` |

## Domain Grouping

- Security/Auth: batches 1, 7, 9, 10, 16
- DB/Integrity: batches 2, 8, 11
- API: batches 6, 12
- CI/Docker: batches 3, 4, 15
- Frontend: batches 5, 9, 13, 14, 16
- Docs/Ops: batch 16

---

## Batch 1 - Config hardening (`env()` to `config()`)

**Goal / DoD**
- Replace runtime `env()` usage in middleware/controllers with `config()` lookups.
- Add missing config keys required by those lookups.

**Inspect**
- `backend/config/sanctum.php`
- `backend/config/cors.php`
- Middleware and auth controllers that currently call `env()` directly

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Changing `env()` inside config files is not required.
- Missing defaults can break config cache behavior.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 1.
Constraints: minimal diffs, no unrelated refactor, no secret values.
Tasks:
1) Add needed config keys (`sanctum.cookie_name`, `cors.allowed_origins`).
2) Replace direct runtime `env()` access with `config()` in middleware/controllers.
3) Keep fallback defaults identical.
Verify: cd backend && php artisan test
Commit: fix(config): batch 1 - env() to config() hardening [BE-023,BE-024,BE-025,SEC-001]
Do not push.
```

## Batch 2 - DB default/runtime alignment

**Goal / DoD**
- Align runtime defaults with PostgreSQL expectations in compose and backend config.

**Inspect**
- `docker-compose.yml`
- `backend/config/database.php`
- `backend/.env.example`

**Verify**
- `docker compose config`
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Breaking test environment defaults.
- Leaving MySQL-specific keys active in runtime paths.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 2.
Constraints: minimal diffs, no unrelated refactor.
Tasks:
1) Align compose DB service and backend defaults to PostgreSQL runtime expectations.
2) Update .env.example DB defaults accordingly.
3) Keep test env behavior intact.
Verify: docker compose config && cd backend && php artisan test
Commit: fix(db): batch 2 - align runtime to PostgreSQL defaults [BE-034]
Do not push.
```

## Batch 3 - Redis + Docker security baseline

**Goal / DoD**
- Secure Redis/Docker exposure model without embedding secret values.

**Inspect**
- `redis.conf`
- `docker-compose.yml`
- `backend/config/database.php`
- `backend/.env.example`

**Verify**
- `docker compose config`
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Hardcoded credentials.
- Incorrect bind/port settings for container-to-container traffic.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 3.
Constraints: no hardcoded secrets, minimal scope.
Tasks:
1) Harden Redis and Docker security posture.
2) Externalize sensitive config via env keys.
3) Keep container connectivity functional.
Verify: docker compose config && cd backend && php artisan test
Commit: fix(infra): batch 3 - Redis and Docker security baseline [DV-001,DV-002,DV-003,DV-009,DV-010,SEC-002]
Do not push.
```

## Batch 4 - CI security job activation

**Goal / DoD**
- Ensure security scanning job is executed by workflow engine.

**Inspect**
- `.github/workflows/tests.yml`

**Verify**
- Validate YAML syntax
- Trigger CI or run equivalent local lint command if available

**Risks / Pitfalls**
- Wrong indentation under `jobs:` can silently disable job execution.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 4.
Constraints: fix workflow structure only, no logic rewrite.
Tasks:
1) Move misplaced security job definitions under jobs.
2) Ensure YAML remains valid.
Verify: YAML validation for .github/workflows/tests.yml
Commit: fix(ci): batch 4 - restore security job execution [DV-012]
Do not push.
```

## Batch 5 - Frontend dependency/API consolidation

**Goal / DoD**
- Remove unsafe/unused dependencies and converge to one API client.

**Inspect**
- `frontend/package.json`
- Frontend API client modules
- Legacy auth service imports

**Verify**
- `cd frontend && npx tsc --noEmit`
- `cd frontend && npx vitest run`

**Risks / Pitfalls**
- Breaking imports after deleting duplicate clients.
- Leaving token storage in legacy paths.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 5.
Constraints: keep one canonical API client; no unrelated UI refactor.
Tasks:
1) Remove bogus frontend deps.
2) Delete duplicate API/auth service files.
3) Rewrite imports to canonical API client.
Verify: cd frontend && npx tsc --noEmit && npx vitest run
Commit: fix(frontend): batch 5 - remove bogus deps and unify API client [FE-001,FE-005,FE-006]
Do not push.
```

## Batch 6 - Dead code + unified route auth

**Goal / DoD**
- Remove known dead files and protect unified auth routes with auth middleware.

**Inspect**
- Dead backend/frontend files from v1 findings
- `backend/routes/api.php`

**Verify**
- `cd backend && php artisan route:list`
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Deleting files still referenced by routes/imports.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 6.
Constraints: verify references before deleting files.
Tasks:
1) Remove confirmed dead code files.
2) Add auth middleware to unified auth route group.
3) Keep routing behavior stable.
Verify: cd backend && php artisan route:list && php artisan test
Commit: fix(api): batch 6 - remove dead code and secure unified routes [BE-009,BE-017,BE-029,BE-030,FE-008]
Do not push.
```

## Batch 7 - Admin booking + auth response hardening

**Goal / DoD**
- Fix admin booking pagination/validation and harden auth data exposure.

**Inspect**
- `backend/app/Http/Controllers/AdminBookingController.php`
- `backend/app/Http/Middleware/VerifyBookingOwnership.php`
- `backend/app/Http/Controllers/AuthController.php`

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Returning raw user model payloads.
- Missing validation for bulk actions.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 7.
Constraints: minimal changes, preserve API compatibility where possible.
Tasks:
1) Fix admin booking pagination and bulk restore validation.
2) Add ownership middleware admin bypass logic.
3) Limit auth response user fields to safe explicit keys.
Verify: cd backend && php artisan test
Commit: fix(backend): batch 7 - admin booking and auth response hardening [BE-011,BE-012,BE-013,BE-014,BE-026]
Do not push.
```

## Batch 8 - Service deduplication

**Goal / DoD**
- Remove duplicated business logic across booking/cancellation/availability services.

**Inspect**
- `backend/app/Services/CancellationService.php`
- `backend/app/Services/BookingService.php`
- `backend/app/Services/RoomService.php`
- `backend/app/Services/RoomAvailabilityService.php`

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Behavior drift when moving shared logic.
- Null-pointer paths in availability lookups.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 8.
Constraints: keep behavior equivalent; remove duplication safely.
Tasks:
1) Consolidate refund, cancellation, and room availability logic.
2) Ensure one canonical path for cancellation flow.
3) Fix null-safe availability checks.
Verify: cd backend && php artisan test
Commit: fix(backend): batch 8 - consolidate cancellation and availability services [BE-018,BE-019,BE-020]
Do not push.
```

## Batch 9 - Frontend route/env/CSP fixes

**Goal / DoD**
- Fix routing fallback, Vite env usage, unsafe sanitize-on-submit, and CSP fallback behavior.

**Inspect**
- `frontend/src/app/router.tsx`
- Frontend env usage paths
- Booking/auth submission code
- CSP plugin file

**Verify**
- `cd frontend && npx tsc --noEmit`
- `cd frontend && npx vitest run`

**Risks / Pitfalls**
- Double encoding user input before API submission.
- Weak CSP fallback introducing `unsafe-inline`.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 9.
Constraints: no style refactor; keep behavior-focused fixes.
Tasks:
1) Add/verify catch-all 404 route.
2) Replace invalid env access with Vite-compatible usage.
3) Remove sanitize-on-submit usage for API payloads.
4) Remove unsafe CSP meta fallback injection.
Verify: cd frontend && npx tsc --noEmit && npx vitest run
Commit: fix(frontend): batch 9 - routing env and CSP corrections [FE-002,FE-007,FE-020,FE-021,SEC-004]
Do not push.
```

## Batch 10 - Session/docker/FK security hardening

**Goal / DoD**
- Harden sessions, container runtime user model, and DB FK enforcement strategy.

**Inspect**
- `backend/config/session.php`
- Dockerfiles
- `.gitignore`
- FK migration state

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Breaking local dev by over-constraining secure cookie defaults.
- FK migration behavior differences in sqlite test env.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 10.
Constraints: security-first defaults, keep test env operational.
Tasks:
1) Enable secure session defaults.
2) Run containers as non-root where applicable.
3) Ensure FK constraints are enforced via migration strategy.
Verify: cd backend && php artisan test
Commit: fix(security): batch 10 - session docker and FK hardening [SEC-003,SEC-004,DV-004,DV-019,BE-035,BE-038]
Do not push.
```

## Batch 11 - Model cast/relationship/fillable corrections

**Goal / DoD**
- Correct model contracts: casts, fillable fields, and relationships.

**Inspect**
- `backend/app/Models/*.php` files touched by v1 model findings

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Mass-assignment gaps due missing fillable fields.
- Enum/string mismatch regressions.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 11.
Constraints: model-level changes only; no service/controller scope creep.
Tasks:
1) Fix casts, fillable fields, and missing relationships.
2) Remove deprecated model constants where replaced by enums.
Verify: cd backend && php artisan test
Commit: fix(models): batch 11 - model casts relationships fillable cleanup [BE-001,BE-002,BE-003,BE-004,BE-005,BE-006,BE-007,BE-008]
Do not push.
```

## Batch 12 - API response standardization

**Goal / DoD**
- Normalize API response envelope across auth and related controllers.

**Inspect**
- Auth controllers
- Shared API response trait/helper

**Verify**
- `cd backend && php artisan test`

**Risks / Pitfalls**
- Breaking client compatibility if envelope keys are removed abruptly.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 12.
Constraints: preserve compatibility while standardizing response format.
Tasks:
1) Apply shared response trait/envelope to targeted controllers.
2) Keep existing payload keys where compatibility requires.
Verify: cd backend && php artisan test
Commit: fix(api): batch 12 - standardize API response envelope [BE-015]
Do not push.
```

## Batch 13 - Frontend duplicate/type cleanup

**Goal / DoD**
- Remove duplicate/temp frontend files and converge on canonical types.

**Inspect**
- Duplicate utility/type files
- Temp artifacts in frontend root

**Verify**
- `cd frontend && npx tsc --noEmit`
- `cd frontend && npx vitest run`

**Risks / Pitfalls**
- Import path breakage after file deletion.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 13.
Constraints: delete only confirmed duplicates/temp files.
Tasks:
1) Remove duplicate utility/type artifacts.
2) Consolidate room/auth types to one source of truth.
3) Update imports and ignore patterns.
Verify: cd frontend && npx tsc --noEmit && npx vitest run
Commit: fix(frontend): batch 13 - remove duplicates and consolidate types [FE-009,FE-010,FE-017,FE-018,FE-019]
Do not push.
```

## Batch 14 - Frontend test baseline

**Goal / DoD**
- Establish baseline unit test suite for frontend core components/utilities.

**Inspect**
- `frontend/src/test/setup.ts`
- Utility/component/API test files
- Vitest config

**Verify**
- `cd frontend && npx vitest run`

**Risks / Pitfalls**
- Flaky tests due router/network mocking gaps.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 14.
Constraints: add reliable unit tests; avoid brittle implementation-coupled assertions.
Tasks:
1) Set up frontend test harness.
2) Add utility, validation, API, and component baseline tests.
Verify: cd frontend && npx vitest run
Commit: test(frontend): batch 14 - add core unit test baseline [TST-001,TST-002]
Do not push.
```

## Batch 15 - Docker multi-stage + CI consolidation

**Goal / DoD**
- Improve build/runtime separation and reduce CI duplication.

**Inspect**
- Backend/Frontend Dockerfiles
- `.dockerignore` files
- CI workflow overlap

**Verify**
- `docker compose config`
- Relevant CI YAML validation

**Risks / Pitfalls**
- Removing required workflow paths unintentionally.
- Breaking local developer flow while optimizing CI.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 15.
Constraints: keep deploy/runtime behavior stable while reducing build waste.
Tasks:
1) Apply multi-stage Docker improvements.
2) Add/clean .dockerignore files.
3) Consolidate redundant CI workflow steps/jobs safely.
Verify: docker compose config and CI YAML validation
Commit: fix(ci): batch 15 - Docker multi-stage and CI consolidation [DV-006,DV-013,DV-014,DV-015]
Do not push.
```

## Batch 16 - Low-priority cleanup sweep

**Goal / DoD**
- Resolve remaining low-priority v1 cleanup/security/docs items in one controlled sweep.

**Inspect**
- Low-priority backend/frontend/devops/security/docs locations tied to v1 IDs

**Verify**
- `cd backend && php artisan test`
- `cd frontend && npx tsc --noEmit && npx vitest run`

**Risks / Pitfalls**
- Bundling too many unrelated changes into one commit.
- Accidentally introducing v2 issue IDs into v1 batch messages.

**Ready-to-paste prompt**

```text
You are a fix-forward agent working only on AUDIT v1 batch 16.
Constraints: strictly v1 low-priority IDs only; keep changes mechanical and isolated.
Tasks:
1) Apply low-priority cleanup items across backend/frontend/devops/docs.
2) Keep each change traceable to listed v1 IDs.
Verify: cd backend && php artisan test && cd ../frontend && npx tsc --noEmit && npx vitest run
Commit: fix(cleanup): batch 16 - low-priority cleanup sweep [BE-037,BE-040,DV-008,DV-020,FE-003,FE-004,FE-014,FE-015,FE-022,FE-023,SEC-006,SEC-007,SEC-009,SEC-010]
Do not push.
```