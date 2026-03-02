# COMPACT — Soleil Hostel (AI Session Memory)

## 1) Current Snapshot (keep under 12 lines)

- Date updated: 2026-03-02
- Current branch: `refactor/batch-3-backend-quality` (from `dev`)
- Latest verified commands: `cd frontend && npx tsc --noEmit` (0 errors), `cd frontend && npx vitest run` (218 tests, 20 suites) — verified 2026-03-01
- Backend test baseline: `cd backend && php artisan test` (857 tests, 2430 assertions) — verified 2026-03-02
- Pint baseline: `cd backend && vendor/bin/pint --test` (275 files, 0 violations) — verified 2026-03-02
- Progress summary: OPS-001, PAY-001, I18N-001+TD-003, DevSecOps, Batch 2+3 Fixes, docs sync complete
- Deployment status: Not asserted here; validate pipeline/runbook status before release

## 2) What matters (invariants / guardrails)

- Booking overlap must remain half-open: `[check_in, check_out)`.
- Active overlap statuses are only `pending` and `confirmed`.
- Production invariant: PostgreSQL exclusion constraint uses `daterange(check_in, check_out, '[)')` with `deleted_at IS NULL`.
- Keep application overlap logic aligned with production constraint behavior.
- Test baseline runs on SQLite in-memory (`backend/phpunit.xml`); verify Postgres-only behavior against PostgreSQL before merge.
- Booking soft delete audit fields must be preserved: `deleted_at`, `deleted_by`.
- Cancellation audit fields must be preserved: `cancelled_at`, `cancelled_by`, `cancellation_reason`.
- Auth uses Sanctum plus custom token columns: `token_identifier`, `token_hash`, `device_id`, `device_fingerprint`, `expires_at`, `revoked_at`, `refresh_count`, `last_rotated_at`.
- HttpOnly cookie path must resolve `token_identifier` to DB `token_hash`, and must enforce expiry/revocation/refresh-abuse checks.
- Concurrency guardrails: optimistic locking via `lock_version` for rooms; pessimistic locking via `SELECT ... FOR UPDATE` for booking conflict/cancel flows.
- Never commit or paste secrets (`APP_KEY`, tokens, passwords, sensitive stack values).
- Keep runtime security/config reads through `config()`, not ad hoc `env()` usage in app logic.

## 3) Active work (Now / Next)

### Now

- `dev` and `main` synced; all March 1 work committed and pushed
- GitHub CLI (`gh`) installed via winget (v2.87.3) — authenticate with `gh auth login` before use
- Open findings: F-21 (LoginPage English), F-22 (Indonesian string), F-23 (MD lint), F-24 (HasUuids conflict)

### Next

- PAY-001 Phase 2: Stripe checkout session + frontend payment UI
- Frontend i18n (I18N-002): wire translation keys for frontend strings
- F-21: Translate LoginPage + RegisterPage to Vietnamese
- F-24: Fix PersonalAccessToken HasUuids + integer PK conflict
- E2E tests (Playwright): blocked on stable staging environment
- Deployment: SSH-based deploy step + post-deploy health check

## 4) Verification checklist (copy/paste)

Required baseline:

```bash
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

Useful lint/format/static checks:

```bash
cd frontend && pnpm lint
cd frontend && pnpm format
cd backend && vendor/bin/pint --test
cd backend && vendor/bin/phpstan analyse
cd backend && vendor/bin/psalm
```

## 5) Known warnings / noise (non-blocking)

- PHPUnit doc-comment metadata deprecation warnings can appear; treat as non-blocking when `php artisan test` is PASS.
- Vitest can emit `act(...)` and non-boolean DOM attribute warnings; treat as non-blocking when `npx vitest run` is PASS.
- Any new warning pattern or warning volume increase should be treated as a change signal and reviewed.

## 6) Key pointers (docs / important files)

- [Project Status](../PROJECT_STATUS.md)
- [Audit Report](../AUDIT_REPORT.md)
- [Remediation Playbook](../PROMPT_AUDIT_FIX.md)
- [Docs Index](./README.md)
- [Operational Playbook](./OPERATIONAL_PLAYBOOK.md)
- [Database Notes](./DATABASE.md)
- [DB Facts (Invariants)](./DB_FACTS.md)
- [Agent Instructions](../AGENTS.md)
- [Agent Framework](./agents/README.md)
- [AI Governance](./AI_GOVERNANCE.md)
- [Commands & Gates](./COMMANDS_AND_GATES.md)
- [MCP Server](./MCP.md)
- [Hooks](./HOOKS.md)
- [Skills Folder](../skills/)
- [Audit 2026-02-21](./AUDIT_2026_02_21.md)
- [Findings Backlog](./FINDINGS_BACKLOG.md)

## 7) Update protocol (how to maintain COMPACT)

- When to update:
  - after each PR/merge
  - after each batch of agent changes
  - when invariants change
- How to update:
  - edit sections 1, 3, and 5
  - append an entry to WORKLOG (if enabled)
- Format rules:
  - short lines, no essays, no secrets

## 2026-02-21 — Repo-wide Docs Audit

### What changed

- Created `docs/agents/` directory: `README.md`, `CONTRACT.md`, `ARCHITECTURE_FACTS.md`, `COMMANDS.md`
- Created `docs/AUDIT_2026_02_21.md` — full audit findings
- Created `docs/FINDINGS_BACKLOG.md` — 14 code issues logged (not fixed)
- Created `docs/COMMANDS_AND_GATES.md` — verified commands + CI gate mapping
- Created `docs/AI_GOVERNANCE.md` — operational checklists for AI agents
- Created `docs/MCP.md` — MCP server documentation
- Created `docs/HOOKS.md` — hook enforcement docs
- Updated `docs/README.md` — added AI agents section, high-risk callouts, all-docs index, fixed Laravel version
- Updated `skills/README.md` — added "Adding a New Skill" section + governance links
- Updated `docs/COMPACT.md` — added key pointers + this audit entry

### Confirmed Architecture Truths

- Booking exclusion constraint with `daterange(check_in, check_out, '[)')` + `deleted_at IS NULL` — verified
- Dual auth mode (Bearer + HttpOnly cookie) — verified
- Custom token columns across 2 migrations — verified (8 columns total)
- UserRole enum: `user | moderator | admin` — verified (PG ENUM + PHP backed enum)
- Optimistic locking: `lock_version` on rooms + locations — verified
- Pessimistic locking: `lockForUpdate()` in CancellationService + Booking model — verified
- Brand tokens match: 6 colors confirmed in `tailwind.config.js`
- BottomNav: 4 tabs confirmed
- "Cuộn xuống" absent from rendered UI — confirmed (regression test guards it)

### Docs Drift Fixed

- `docs/README.md`: "Laravel 11 + PHP 8.3" corrected to "Laravel 12 + PHP 8.2+"
- `docs/README.md`: test count updated from 142 to 145 frontend tests
- `docs/README.md`: date updated from Feb 12 to Feb 21

### Code Issues Found (backlog)

See `docs/FINDINGS_BACKLOG.md` (14 items):

- F-04 (High): CI triggers `develop` branch but repo uses `dev`
- F-01 (Medium): DATABASE.md claims room_status PG ENUM but it's VARCHAR
- F-05 (Medium): CI uses pnpm, local docs reference npm
- F-06/F-07/F-08 (Medium): Missing CHECK constraints
- Others: Low severity docs drift

### Gates

- MCP run_verify not available in this environment
- Commands for human to verify locally:
  - `cd backend && php artisan test`
  - `cd frontend && npx tsc --noEmit`
  - `cd frontend && npx vitest run`
  - `docker compose config`

### Warnings

- Audit complete — all 15 tier-2 searches executed, all tier-3 reads done
- No code was modified — docs-only changes

### Completed (2026-02-22) — Audit v3 Remediation

- PR-1: F-04 (CI `develop`→`dev`) + F-14 (Redis conditional requirepass)
- PR-2: F-06/F-07/F-08 (CHECK constraints migration)
- PR-3: F-09 (FK `reviews.booking_id → bookings.id`)
- PR-4: F-01/F-05/F-10–F-13 (docs sync)
- Post-fix: Pint style violations + minimatch pnpm override

### Completed (2026-02-23) — Audit v4 Remediation

- Batch-1: F-16 (CI quality gates non-blocking → Pint + Composer Audit now blocking), F-20 (docker compose validate job added)
- Batch-2: F-15 (untrack `.env.test`), F-17 (clear committed APP_KEY in `.env.testing`)
- Batch-3: F-18 (remove `console.log` from SearchCard.tsx)
- Batch-4: F-19 (update 142→145 test count across 6 files), F-02/F-03 confirmed fixed
- All 20 findings (F-01–F-20) now resolved

### Completed (2026-02-23) — Dashboard Phase 0 + Phase 1

- Phase 0: Replaced inline /dashboard placeholder with lazy-loaded DashboardPage.tsx
- Phase 1: Guest Dashboard "My Bookings" feature
  - New files: `bookingViewModel.ts`, `bookingViewModel.test.ts` (12 tests), `useMyBookings.ts`, `GuestDashboard.tsx`, `ConfirmDialog.tsx`
  - Modified: `booking.types.ts` (added API response types), `booking.api.ts` (fetchMyBookings + cancelBooking), `DashboardPage.tsx` (wired GuestDashboard)
  - Features: booking list with filter tabs (All/Upcoming/Past), cancel with confirm modal, skeleton/empty/error states, toast notifications
  - No React Query (not in package.json) — used existing useState+useEffect pattern
  - CSRF: auto-attached by existing interceptor on cancel POST
  - Frontend tests: 157 pass (14 suites), tsc --noEmit: 0 errors

### Completed (2026-02-25) — Frontend Phases 2-4 + Docs Sync

- Phase 2: SearchCard wired to live `GET /v1/locations`; navigates to `/locations/:slug?check_in=&check_out=&guests=`
- Phase 3: AdminDashboard with 3 tabs (Đặt phòng / Đã xóa / Liên hệ), lazy-per-tab fetch, `useAdminFetch<T>` hook
- Phase 4: BookingForm — URL params pre-fill (`check_in`, `check_out`, `guests`), Vietnamese UI, `/v1/bookings` endpoint, `/v1/rooms` endpoint; `AvailabilityResponse` dead type removed
- vi.hoisted fix in BookingForm.test.tsx (Vitest 2.x hoisting bug); 194/194 tests passing (19 suites)
- Docs sync: COMPACT, WORKLOG, README, frontend/\* all updated to reflect actual code state
- Git: committed on dev → pushed → merged to main (pre-push: ESLint, Prettier, tsc, 194 tests)

### Completed (2026-02-25) — CLAUDE.md creation + docs sync

- Created `CLAUDE.md` — Claude Code CLI master context file (6 sections, 221 lines)
  - §1 Project Overview (verified stack, branching, health baseline, key dirs)
  - §2 Non-Negotiable Standards (TS strict, no console.log, feature-sliced, CSRF, auth)
  - §3 Workflow Protocol (12-step checklist, Conventional Commits format, scope rules)
  - §4 AI Governance (evidence-first, no path guessing, out-of-scope logging, COMPACT discipline, high-risk table)
  - §5 File-Specific Rules (verified: booking.api.ts, room.api.ts, api.ts, router.tsx, COMPACT.md, backend general)
  - §6 Quickstart (skill selection table, task prompt template, 3 examples, MCP connection, STOP rules)
  - Governance Docs table linking all related framework files
- Edited `docs/README.md` — added CLAUDE.md row to Agent & Governance table
- Edited `docs/agents/README.md` — added CLAUDE.md bullet to Related Docs
- Edited `PROJECT_STATUS.md` — corrected frontend: 192→194 tests, 13→19 files, date Feb 25

### Completed (2026-02-26) — Auth Redirect Loop Fix

- Root cause: `AuthContext.tsx` accessed `response.data.user` but backend wraps in `{ success, data: { user, csrf_token }, message }` — correct path is `response.data.data.user`
- Fixed 3 extraction points: `loginHttpOnly`, `useEffect` validateToken, `me()` function
- Updated test mocks in `AuthContext.test.tsx` to match real backend response shape
- Logged F-21 (LoginPage/RegisterPage English UI) to FINDINGS_BACKLOG.md
- Files: `AuthContext.tsx`, `AuthContext.test.tsx`, `FINDINGS_BACKLOG.md`, `COMPACT.md`
- Gates: `tsc --noEmit` 0 errors, `vitest run` 194/194 pass (19 suites)

### Completed (2026-02-26) — Auth 401 Fix (EncryptCookies mismatch)

- Root cause: `POST /api/auth/login-httponly` has `['web']` middleware → `EncryptCookies` encrypts `soleil_token` cookie before browser receives it. All subsequent routes run under `api` middleware only (no `EncryptCookies`) → `$request->cookie('soleil_token')` returns raw encrypted string → `hash('sha256', encryptedString)` ≠ stored `hash('sha256', plainUUID)` → token lookup returns null → 401 on every protected call.
- Fix 1: `backend/bootstrap/app.php` — added `$middleware->encryptCookies(except: ['soleil_token'])` to exclude the custom auth token cookie from encryption. Cookie security unchanged: httpOnly + SameSite=Strict + random UUID.
- Fix 2: `backend/.env.example` — added `SESSION_SECURE_COOKIE=false` to prevent session cookie from being Secure=true on HTTP localhost (secondary: ensures `$request->session()` is usable in local dev).
- Gates: `php artisan test` 737/737 ✅, `tsc --noEmit` 0 errors ✅, `vitest run` 194/194 ✅, `docker compose config` valid ✅
- Residual risk: if test user's `email_verified_at` is null, `GET /v1/bookings` will return 403 (not 401) after this fix — verify test user is email-verified.

### Completed (2026-02-26) — Rollup path traversal fix (GHSA-mw96-cpmx-2vgc)

- Added `"rollup": ">=4.59.0"` to `pnpm.overrides` in `frontend/package.json`
- Ran `pnpm install` to regenerate `frontend/pnpm-lock.yaml` (rollup resolved to 4.59.0)
- Gates: `pnpm audit --audit-level=high` PASS ✅, `tsc --noEmit` 0 errors ✅, `vitest run` 194/194 ✅
- Remaining moderate advisories (esbuild via vitest@2.1.9→vite@5.4.21, ajv via eslint) are below high threshold and out of scope
- Files: `frontend/package.json`, `frontend/pnpm-lock.yaml`, `docs/COMPACT.md`

### Next steps (prioritized)

- F-21: Translate LoginPage + RegisterPage to Vietnamese
- Frontend i18n: wire react-i18next or equivalent for frontend strings
- H-06: Switch test DB to PostgreSQL (blocked: no documented process, needs Docker for all devs)
- M-11: Migration squashing (blocked: no documented squash process, deploy risk)
- F-24: Fix PersonalAccessToken HasUuids + integer PK conflict
- Dashboard Phase 5+: Booking detail panel enhancements

## 2026-02-27 — FE-001: Booking Detail Panel

- Added `BookingDetailPanel.tsx` — modal panel (no Drawer primitive available) opened by clicking "Xem chi tiết" on any BookingCard
- Panel fields: room name + number, check-in/out dates, nights, status badge, guest name/email, amount, refund amount, cancelled_at, created_at
- Added `getBookingById(id, signal?)` to `booking.api.ts` + `BookingDetailRaw` / `BookingDetailRoom` / `BookingDetailResponse` types to `booking.types.ts`
- Modified `GuestDashboard.tsx`: BookingCard gets `onViewDetail` prop; panel wired with `selectedBookingId` + `isPanelOpen` state
- Panel behaviour: Escape key closes, backdrop click closes, "Đóng" button closes; loading skeleton / error+retry / success states
- Added `BookingDetailPanel.test.tsx` (14 tests): closed renders nothing, API not called when closed, loading skeleton, success fields, error+retry, Escape closes, backdrop closes, Đóng closes, cancelled_at shown, cancelled_at hidden for non-cancelled, refund amount shown, reopen fetches again
- Limitation: panel is a modal (not drawer) — Drawer component not in codebase. Room location not shown (room relation in getBookingById does not load location). Number of guests not shown (absent from BookingResource/BookingApiRaw).
- Files: `frontend/src/features/bookings/BookingDetailPanel.tsx` (new), `frontend/src/features/bookings/BookingDetailPanel.test.tsx` (new), `frontend/src/features/bookings/GuestDashboard.tsx` (modified), `frontend/src/features/booking/booking.api.ts` (modified), `frontend/src/features/booking/booking.types.ts` (modified)
- Gates: `tsc --noEmit` 0 errors ✅, `vitest run` 208/208 ✅ (20 suites, was 194/194)

## 2026-02-27 — EncryptCookies Exception Regression Tests

- Added `SoleilTokenCookieEncryptionTest.php` (9 tests, 43 assertions) verifying the `encryptCookies(except: ['soleil_token'])` config does not introduce security regressions
- TEST-1: soleil_token cookie is plain UUID (not encrypted), matches DB token_identifier, hash matches token_hash
- TEST-2: Control cookie (laravel_session) remains encrypted (not plain UUID, length > 36)
- TEST-3: Cookie UUID resolves user via CheckHttpOnlyTokenValid on me-httponly endpoint
- TEST-3b: Cookie fallback in CheckTokenNotRevokedAndNotExpired passes auth on v1 endpoints (not 401)
- TEST-3c/d/e: Unknown UUID → 401, revoked token → 401 TOKEN_REVOKED, expired → 401 TOKEN_EXPIRED
- TEST-4: Security headers (HSTS, X-Frame-Options, CSP, etc.) still present on both public and authenticated endpoints
- Finding: `auth()->id()` returns null on v1 cookie-auth requests because CheckHttpOnlyTokenValid sets `$request->setUserResolver()` but not `auth()->guard('sanctum')->setUser()` — controllers using `auth()->id()` will fail with 500 on cookie fallback path. Not fixed here (out of scope).
- Files: `backend/tests/Feature/Auth/SoleilTokenCookieEncryptionTest.php` (new), `docs/COMPACT.md`
- Gates: `php artisan test` 746/746 ✅, `pint --test` PASS ✅

## 2026-02-27 — FE-002/FE-003: Admin Trashed Actions + Pagination

- FE-002: Trashed tab restore (POST /v1/admin/bookings/:id/restore) + force delete (DELETE /v1/admin/bookings/:id/force) with ConfirmDialog
- FE-003: Paginated fetch hook (`useAdminPaginatedFetch`), PaginationControls (Trước/Sau), boundary disabling, hidden when last_page<=1
- Trashed tab wrapper adapts non-paginated endpoint to paginated shape
- AdminBookingCard: action buttons (Khôi phục / Xóa vĩnh viễn) with pending state disabling
- Toast feedback on success/error for restore and force-delete
- Tests: 10 new tests (trashed actions + pagination) in AdminDashboard.test.tsx
- Files: `admin.api.ts` (+restoreBooking, +forceDeleteBooking), `admin.types.ts` (+PaginationMeta, +AdminBookingsPaginatedResult), `AdminDashboard.tsx` (paginated hooks, actions, PaginationControls), `AdminDashboard.test.tsx`
- Gates: `tsc --noEmit` 0 errors ✅, `vitest run` 218/218 ✅ (20 suites)

## 2026-02-27 — TD-001: Standardize API Error Format

- Added `trace_id` field to all `ApiResponse` responses (reads `correlation_id` from request attributes set by `AddCorrelationId` middleware)
- Added `ApiResponse::conflict()` method for 409 responses
- Fixed `OptimisticLockException` handler in `bootstrap/app.php` to use `ApiResponse::conflict()` instead of legacy `{ error, message }` format
- Added generic `HttpException` handler (405, 429, etc.) using `ApiResponse::error()`
- Added catch-all `\Throwable` handler: logs unhandled exceptions, returns standardized JSON, no stack trace in prod (debug mode shows message)
- Excluded `HttpResponseException` from catch-all (used internally by Laravel for rate limiting)
- Updated `EnsureUserHasRole` middleware to use `ApiResponse` for 401/403/500 responses
- Updated 5 existing optimistic lock tests (`RoomTestAssertions.php`, `RoomOptimisticLockingTest.php`, `RoomConcurrencyTest.php`) from legacy `{ error }` to `{ success }` assertions
- New test file: `ApiErrorFormatTest.php` (10 tests, 57 assertions) covering: 404/401/403/422 format, trace_id propagation, auto-generation, no stack trace leak, JSON content-type
- Gates: `php artisan test` 756/756 ✅ (2171 assertions), `pint --test` 252 files 0 violations ✅, `tsc --noEmit` 0 errors ✅, `vitest run` 218/218 ✅, `docker compose config` PASS ✅

## 2026-02-28 — Phase 5: Audit & Friday Clean-up (TD-002 + Security + Ship Script)

TD-002: translated all `//`, `/* */`, and PHPDoc comments from Vietnamese to English across 13 `backend/app/**` PHP files. String literals (user-facing messages) preserved intentionally. Out-of-scope finding logged as F-22 (Indonesian string in `HttpOnlyTokenController.php:290`). Security rollup override already done 2026-02-26. New `scripts/ship.sh` runs 3 CI gates and prints `READY TO SHIP` or exits with `[FAIL] <step>`. `PROJECT_STATUS.md` updated with Phase 5 block, new test counts (756/218), and Q2-2026 roadmap.

Files touched: `backend/app/` (13 PHP files, comments only), `scripts/ship.sh` (new), `PROJECT_STATUS.md`, `docs/FINDINGS_BACKLOG.md` (F-22), `docs/COMPACT.md`.

Gates: no app logic changed — regressions not expected; run locally to confirm baseline held.

---

## 2026-02-25 — Fix GET /api/v1/locations 500

- Root cause: Two Vite config files existed (`vite.config.js` + `vite.config.ts`). Vite loads `.js` first — so `vite.config.js` was the active config. That file had the proxy target as `http://backend:8000` (Docker-only hostname, ENOTFOUND locally) → Vite returned 500. Backend was healthy throughout (200 OK confirmed directly). The `.ts` fix alone was insufficient.
- Fix: Changed proxy fallback target in **both** `vite.config.js` and `vite.config.ts` from `http://backend:8000` to `http://127.0.0.1:8000`. Changed `api.ts` BASE_URL fallback from `http://localhost:8000/api` to `/api` (proxy path, eliminates CORS on local dev). Cleared `VITE_API_URL` in `.env.example`.
- Files: `frontend/vite.config.js`, `frontend/vite.config.ts`, `frontend/src/shared/lib/api.ts`, `frontend/.env.example`, `docs/COMPACT.md`
- Gates: `tsc --noEmit` 0 errors ✅, `vitest run` 194/194 ✅ (backend not touched)
- Commits: `f53a3cb fix(frontend)`, `df8c706 docs(frontend)` + this commit

## 2026-02-28 — 4-PR Batch: OPS-001 + PAY-001 + I18N-001 + TD-003

### PR-1: OPS-001 Critical (branch: `fix/ops-001-critical`, commit: `5df1d98`)

- Created `docker-compose.prod.yml` (db, redis, backend, frontend services with healthchecks)
- Created `backend/.env.production.example` (pgsql, no secrets — actual .env.production is gitignored)
- Added multi-stage production build to `frontend/Dockerfile` (nginx:1.27-alpine)
- Created `frontend/nginx.conf` for SPA routing
- Files: 4 new/modified

### PR-2: OPS-001 High/Med (branch: `fix/ops-001-proxy-health-rollback`, commit: `a086004`)

- Created `Caddyfile` for auto-HTTPS reverse proxy (api/_ → backend, _ → frontend)
- Added optional Caddy proxy service to `docker-compose.prod.yml` (--profile proxy)
- Added Docker rollback + HTTPS setup sections to `docs/OPERATIONAL_PLAYBOOK.md`
- Files: 3 new/modified

### PR-3: PAY-001 Bootstrap (branch: `feat/pay-001-cashier-bootstrap`, commit: `b856216`)

- Installed `laravel/cashier ^16.3`
- Added `Billable` trait to `User` model
- Created Cashier migration (stripe_id, pm_type, pm_last_four, trial_ends_at)
- Created `StripeWebhookController` extending Cashier's WebhookController (stub handlers)
- Added `POST /api/webhooks/stripe` route
- Updated `.env.example` with Stripe env vars
- Created `StripeWebhookTest` (4 tests: signature verification, invalid payloads)
- Gates: 760 backend tests ✅, pint ✅
- Files: 7 new/modified

### PR-4: I18N-001 + TD-003 (branch: `chore/i18n-001-td-003`, commit: `ad80c15`)

- Created `lang/en/booking.php` (30 keys), `lang/vi/booking.php`, `lang/en/messages.php` (17 keys), `lang/vi/messages.php`
- Replaced hardcoded strings with `__()` in 5 controllers (BookingController, RoomController, LocationController, AdminBookingController, ContactController)
- Set `APP_LOCALE=vi` in `.env.example`
- Added `expired()`, `cancelledByAdmin()`, `multiDay()` factory methods to `BookingFactory`
- Created `LocaleTest` (5 tests) + `BookingFactoryMethodsTest` (4 tests)
- Gates: 765 backend tests ✅, pint 258 files ✅
- Files: 13 new/modified

## 2026-03-01 — Batch 1 DevSecOps Hardening

### PR-1: `infra/redis-caddy-docker-hardening` (branch: `infra/redis-caddy-docker-hardening`)

#### Changed
- `Caddyfile`: Added HSTS, CSP, Permissions-Policy, X-XSS-Protection headers; documented rate_limit module requirement
- `redis.conf`: Added `protected-mode yes`
- `backend/Dockerfile`: Non-root production stage (USER www-data); nginx listens on 8080
- `backend/docker/nginx.conf`: Listen port 80 → 8080
- `docker-compose.yml`: DB_DATABASE default `homestay` → `soleil_hostel`; backend port mapping `8000:8080`; `user: root` for dev
- `docker-compose.prod.yml`: Backend healthcheck updated for port 8080; redis ulimits added

#### Issues fixed
C-03, H-12, H-13, M-22, M-23, M-24, M-25, L-12, L-13

#### Gates
- `docker compose config` PASS ✅
- `docker compose -f docker-compose.prod.yml config` PASS ✅
- Backend/frontend tests: no app code changed — [REQUIRES LOCAL VERIFICATION]

#### Notes
- Rate limiting blocked: standard `caddy:2-alpine` image lacks `caddy-ext/ratelimit` module. Requires custom Caddy build.
- Backend nginx port changed from 80→8080 for non-root; all compose port mappings and Caddyfile updated accordingly.

### PR-2: `ci/gates-and-deploy-fix` (branch: `ci/gates-and-deploy-fix`)

#### Changed
- `.github/workflows/tests.yml`: Added `frontend-typecheck` job (tsc --noEmit); fixed hardcoded VITE_API_URL → `/api`
- `.github/workflows/deploy.yml`: Fixed hardcoded VITE_API_URL → `vars.VITE_API_URL || '/api'`; pinned trivy-action@0.29.0; updated codeql-action to @v3; added SSH-based migration step (gated by DEPLOY_HOST secret); documented Trivy continue-on-error justification

#### Issues fixed
C-04, H-10, H-11, H-14, M-26, M-27, M-28

#### Gates
- `docker compose config` PASS ✅
- CI workflows: YAML valid (no syntax errors) — [REQUIRES LOCAL VERIFICATION on GitHub Actions]

#### Notes
- No `scripts/deploy-forge.sh` exists — migration handled via deploy.yml SSH step instead
- Trivy remains non-blocking (continue-on-error: true) with documented justification

## 2026-03-01 — Batch 2: Backend Critical Bugs & Data Integrity

### What changed

#### PR-1: Fix Review FormRequest broken purify() calls (C-01, C-02)
- **Root cause**: `StoreReviewRequest` and `UpdateReviewRequest` called `$this->purify()` which doesn't exist on `FormRequest` — the `Purifiable` trait is Model-only. This would crash at runtime with `BadMethodCallException`.
- **Fix**: Replaced `$this->purify()` with `HtmlPurifierService::purify()` applied to each validated field.
- **Tests**: 8 new tests in `tests/Unit/Requests/ReviewRequestPurificationTest.php` — validates no crash, XSS stripping, single-key access, rating bounds.
- **Files**: `app/Http/Requests/StoreReviewRequest.php`, `app/Http/Requests/UpdateReviewRequest.php`, test file (new)

#### PR-2: Add cancellation_reason to Booking $fillable (H-01)
- **Root cause**: `Booking::$fillable` included `cancelled_at` and `cancelled_by` but NOT `cancellation_reason`. Mass assignment via `$booking->update(['cancellation_reason' => '...'])` silently dropped the field.
- **Fix**: Added `'cancellation_reason'` to `$fillable` array.
- **Tests**: 3 new tests in `tests/Unit/Models/BookingFillableTest.php` — validates field is fillable and persists via mass assignment.
- **H-02 deferred**: Auth controllers use `DB::table()->insertGetId()` for token creation, but switching to Eloquent fails because `PersonalAccessToken` has `HasUuids` trait with integer PK. Logged as F-24 in FINDINGS_BACKLOG.md.
- **Files**: `app/Models/Booking.php`, `docs/FINDINGS_BACKLOG.md`, test file (new)

#### PR-3: Implement Stripe webhook handlers (H-03)
- **Root cause**: `handlePaymentIntentSucceeded()` and `handleChargeRefunded()` were TODO stubs that only logged.
- **Fix**: Implemented full handler logic:
  - `handlePaymentIntentSucceeded`: finds booking by `payment_intent_id`, confirms via `BookingService::confirmBooking()`, idempotent for already-confirmed bookings
  - `handleChargeRefunded`: finds booking by charge's `payment_intent`, updates `refund_id/refund_status/refund_amount/refund_error`, transitions to `cancelled` or `refund_failed`
  - `handlePaymentIntentPaymentFailed`: new handler, logs failure, booking remains pending for retry
  - All handlers include idempotency checks and error handling
- **Tests**: 10 new tests in `tests/Feature/Payment/StripeWebhookHandlerTest.php`
- **Files**: `app/Http/Controllers/Payment/StripeWebhookController.php`, test file (new)

### Gates
- `php artisan test`: 790 tests, 2245 assertions ✅ (was 769/2192)
- `vendor/bin/pint --test`: 264 files, 0 violations ✅
- Frontend: not touched — [SKIPPED]
- `docker compose config`: not touched — [SKIPPED]

### Residual
- F-24: Auth controllers use Query Builder for token creation; requires `HasUuids` fix before switching to Eloquent (see FINDINGS_BACKLOG.md)
- M-06, M-07, L-06: Validation rules already meet standards (verified: `password min:8`, `guest_name min:2`, `max_guests integer|min:1`)

## 2026-03-02 — Batch 3: Backend Refactoring & Testing Coverage

### PR-1: refactor/health-service (M-01, M-12)
- Extracted 464-line HealthController into HealthService + thin controller (~80 lines)
- Service methods: basicCheck(), readinessCheck(), detailedCheck(), checkComponent()
- Added 15 feature tests + 15 unit tests for health endpoints
- Files: `app/Services/HealthService.php` (new), `app/Http/Controllers/HealthController.php`, `tests/Feature/Health/HealthEndpointTest.php` (new), `tests/Unit/Services/HealthServiceTest.php` (new)

### PR-2: refactor/controllers-formrequests (M-02..M-05, L-03)
- Created 4 FormRequest classes: BulkRestoreBookingsRequest, StoreContactRequest, ShowLocationRequest, LocationAvailabilityRequest
- Updated AdminBookingController, ContactController, LocationController to use FormRequests
- StoreContactRequest includes HTML purification in validated() override
- RoomController already compliant (L-03 verified as resolved)
- Added 15 validation tests
- Files: 4 new FormRequests, 3 controllers modified, 1 test file (new)

### PR-3: cleanup/routes-cors-debug (M-08, M-09)
- Removed debug /test route from web.php (M-09)
- Removed custom Cors middleware from global stack; switched to Laravel's built-in HandleCors via config/cors.php (M-08)
- Updated config/cors.php with explicit methods/headers matching prior behavior
- Updated CORS tests for HandleCors 204 preflight + added disallowed-origin test
- Added DebugRouteTest verifying /test returns 404
- Files: `routes/web.php`, `config/cors.php`, `bootstrap/app.php`, 2 test files

### PR-4: quality/static-analysis + tests (H-04, H-05)
- Installed phpstan/phpstan ^2.1 + larastan/larastan ^3.9
- Updated phpstan.neon with Larastan extension; generated baseline (151 errors)
- Added 10 Contact endpoint tests (store, XSS, admin index/read)
- Added 9 Review model tests (scopes, relationships, purification)
- H-06 (PostgreSQL test DB) DEFERRED: no documented process; needs Docker running

### PR-5: migrations-squash — BLOCKED
- No documented migration-squash process in repo
- Cannot prove deploy safety without coordination
- Recommendation: use `php artisan schema:dump --prune` after verifying all environments are aligned

### Gates
- `php artisan test`: 857 tests, 2430 assertions ✅ (was 790/2245)
- `vendor/bin/pint --test`: 275 files, 0 violations ✅
- `vendor/bin/phpstan analyse`: installed, baseline generated (151 pre-existing errors)
- Frontend: not touched — [SKIPPED]
- `docker compose config`: not touched — [SKIPPED]

### Residual
- H-06: PostgreSQL test DB switch — needs documented process + Docker requirement
- M-11: Migration squashing — needs schema:dump workflow + deploy coordination
- L-01, L-02, L-04, L-05, L-07, L-08: Low-priority items without clear code evidence in current scan; may need specific issue descriptions to locate
- Custom `app/Http/Middleware/Cors.php` file retained (not deleted) for reference; no longer registered in middleware stack
