# AGENTS.md - Soleil Hostel AI Agent Onboarding

Purpose: Long-term memory and onboarding guide for coding agents working in this repository.
Scope: Documentation and engineering conventions for the current `dev` branch.

## 1. Quick Project Overview
- System: Monorepo for Soleil Hostel with a Laravel backend API and React TypeScript frontend.
- Core domains:
  - Locations
  - Rooms
  - Bookings
  - Reviews
  - Contact Messages
  - Authentication and personal access tokens
- Primary business risks:
  - Double-booking prevention
  - Token/session security
  - Cancellation/refund flows

Repo map (key paths):
- `./backend/` - Laravel API, migrations, tests, auth, booking logic.
- `./frontend/` - React + TypeScript SPA and unit tests.
- `./docs/` - Architecture, guides, operations, database docs.
- `./docker-compose.yml` - Local multi-service stack.
- `./.github/workflows/tests.yml` - CI (tests/lint/security).
- `./.github/workflows/deploy.yml` - CD pipeline.

## 2. Tech Stack and Tooling
- Backend:
  - Laravel: `laravel/framework:^12.0` (from `./backend/composer.json`)
  - PHP: `^8.2`
  - Auth base: Laravel Sanctum with custom token model/columns
- Frontend:
  - React `^19.0.0`
  - TypeScript `~5.7.x`
  - Vite `^6.x`
  - Vitest `^2.x`
- Infrastructure:
  - Docker Compose stack includes PostgreSQL 16, Redis 7, backend, frontend (`./docker-compose.yml`)
- Databases:
  - Production target: PostgreSQL (required for exclusion constraints and `daterange` logic)
  - Test default: SQLite in-memory (`./backend/phpunit.xml`)
  - Core tables (from migrations): `locations`, `users`, `rooms`, `bookings`, `reviews`, `personal_access_tokens`, `sessions`, `cache`, `jobs`/`job_batches`/`failed_jobs` (queue storage), `contact_messages`
  - Note: CI backend jobs in workflows run PostgreSQL services

## 3. Golden Commands (copy/paste)
Required verification commands:
```bash
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

Useful lint/format/static checks present in repo:
```bash
cd frontend && npm run lint
cd frontend && npm run format
cd backend && vendor/bin/pint --test
cd backend && vendor/bin/phpstan analyse
cd backend && vendor/bin/psalm
```

## 4. Repository Conventions (Do / Don't)
Do:
- Backend:
  - Keep HTTP layer thin in `./backend/app/Http/Controllers/*Controller.php`.
  - Put request validation in `./backend/app/Http/Requests/*Request.php`.
  - Put business logic in `./backend/app/Services/*Service.php`.
  - Put data access in repositories (`./backend/app/Repositories/Eloquent*Repository.php`) behind contracts (`./backend/app/Repositories/Contracts/*Interface.php`).
- Frontend:
  - Keep route/app shell in `./frontend/src/app/`.
  - Keep domain logic in `./frontend/src/features/` (feature-local `*.api.ts`, `*.types.ts`, components/tests).
  - Keep route pages in `./frontend/src/pages/`.
  - Keep reusable UI/libs in `./frontend/src/shared/`.
- Follow existing naming patterns (`*Controller`, `*Request`, `*Service`, `*.api.ts`, `*.types.ts`).

Don't:
- Do not call `env()` in runtime application logic; use `config()` and config files.
- Do not bypass config-backed security settings in auth/session/cookie flows.
- Do not leak secrets or add plaintext credentials to repo/config.
- Do not introduce wide refactors when task scope is narrow.

## 5. Architecture Decisions Snapshot
- Auth model:
  - Sanctum plus custom `personal_access_tokens` fields: `token_identifier`, `token_hash`, `device_id`, `device_fingerprint`, `expires_at`, `revoked_at`, `refresh_count`, `last_rotated_at`.
  - Supports rotation/refresh and revocation checks in middleware/controllers.
- Booking overlap enforcement:
  - Application logic uses half-open intervals `[check_in, check_out)`.
  - PostgreSQL production constraint uses `EXCLUDE USING gist (... daterange(check_in, check_out, '[)') WITH &&)`.
  - Current constraint filter includes `deleted_at IS NULL` for active-booking overlap only.
- Booking auditability:
  - Soft deletes on bookings with audit columns (`deleted_at`, `deleted_by`) and cancellation audit (`cancelled_at`, `cancelled_by`, `cancellation_reason`).
- Concurrency:
  - Optimistic locking for rooms via `lock_version`.
  - Pessimistic locking (`SELECT ... FOR UPDATE`) in booking/cancellation flows.

## 6. Safety and Security Guardrails
- Secrets:
  - Never commit real secrets (`APP_KEY`, Redis passwords, tokens, API keys).
  - Keep `.env*` values sanitized; treat all auth artifacts as sensitive.
- Token/cookie handling:
  - HttpOnly cookie flow must keep cookie flags (`HttpOnly`, secure by env, same-site policy) intact.
  - Cookie auth resolves via `token_identifier` -> hashed lookup (`token_hash`) in DB.
  - All protected flows must enforce `revoked_at` and expiry checks.
- Config discipline:
  - Keep runtime reads through `config()`.
  - Avoid ad-hoc hardcoded security values in controllers/services.
- DB parity:
  - Local/dev changes must respect PostgreSQL production features.
  - If logic depends on Postgres-only behavior, verify against PostgreSQL before merge.

## 7. Testing and Quality Gates
Definition of Done (minimum):
- `cd backend && php artisan test` passes.
- `cd frontend && npx tsc --noEmit` passes.
- `cd frontend && npx vitest run` passes.
- `docker compose config` passes.

When changing booking logic:
- Add/update overlap tests (including edge cases with adjacent dates and soft-deleted bookings).
- Preserve half-open interval behavior `[check_in, check_out)`.
- Keep logic compatible with PostgreSQL exclusion constraint semantics.

When changing auth/token logic:
- Add/update tests for token expiry, revocation, refresh rotation, and suspicious refresh behavior.
- Verify both Bearer and HttpOnly-cookie paths when affected.

When changing migrations/indexes/constraints:
- Validate rollback safety and index/constraint names.
- Verify SQLite test impact and PostgreSQL production behavior.

## 8. High-Risk Areas Checklist (for agents)
Before merging changes in these areas, do an explicit risk pass:
- Booking overlap checks, date boundaries, status transitions.
- Cancellation/refund flows and idempotency behavior.
- Auth rotation/logout/revocation, unified auth mode detection.
- Migrations touching constraints, indexes, or exclusion constraints.
- Docker/CI parity and environment-specific behavior.
- Any change that affects `APP_KEY`, Redis auth, cookie config, or secret handling.

## 9. Current Status Snapshot (Feb 11, 2026)
From `./PROJECT_STATUS.md`:
- Branch context: `dev` (recorded as 8 commits ahead of `main` on Feb 11, 2026).
- Audit history: v1 and v2 marked complete in repo history; current repo health marked green.
- Verified results (Feb 11, 2026):
  - Backend: `722 tests`, `2012 assertions` via `cd backend && php artisan test`.
  - Frontend typecheck: pass via `cd frontend && npx tsc --noEmit`.
  - Frontend unit tests: `145 tests` in `13 files` via `cd frontend && npx vitest run`.
  - Docker compose config validation: pass via `docker compose config`.
- Security/dev fixes recorded as completed:
  - No plaintext Redis secrets
  - Docker compose quoting fix
  - `APP_KEY` not regenerated each start
  - MySQL vs PostgreSQL mismatch addressed

Reference docs:
- Audit report: `./AUDIT_REPORT.md`
- Audit fixes v1: `./AUDIT_FIX_PROMTS_V1.md`
- Audit fixes v2: `./AUDIT_FIX_PROMTS_V2.md`
- Docs index: `./docs/README.md`
- Operational runbooks: `./docs/OPERATIONAL_PLAYBOOK.md`

## 10. How to Work With This Repo (agent instructions)
Before coding:
- Read this file first, then open only the most relevant docs under `./docs/`.
- For backend changes, check related migrations/tests/services before editing.
- For frontend changes, inspect the target feature folder and shared libs first.

During coding:
- Keep diffs small and scoped.
- Preserve existing architecture boundaries (Controller -> Request -> Service -> Repository).
- Avoid broad refactors unless explicitly requested.
- Keep security and config discipline intact.

Before finishing:
- Run the Definition of Done commands.
- Summarize what changed, what was validated, and any residual risk.
- If behavior changed, update docs in the same area (not needed for docs-only tasks unless content is outdated).

Unknown / verify in repo:
- CI branch policy vs `dev` naming alignment (`develop` appears in workflows). Verify expected branch mapping before workflow changes.


