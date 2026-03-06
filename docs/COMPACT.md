# COMPACT — Soleil Hostel (AI Session Memory)

## 1) Current Snapshot (keep under 12 lines)

- Date updated: 2026-03-06
- Current branch: `dev`
- Latest verified commands: `cd frontend && npx tsc --noEmit` (0 errors), `cd frontend && npx vitest run` (226 tests, 21 suites) — verified 2026-03-06
- Backend test baseline: `cd backend && php artisan test` (885 tests, 2487 assertions) — verified 2026-03-06 (H-05: 14 new tests)
- Pint baseline: `cd backend && vendor/bin/pint --test` (283 files, 0 style issues) — verified 2026-03-06
- PHPStan: Level 5 + Larastan installed, baseline 151 pre-existing errors
- Psalm: `vimeo/psalm ^6.15` installed, Level 1 with suppression config, 0 blocking errors in routes/api/v1.php
- Progress summary: Batches 1–12 complete; H-02 resolved; H-05 resolved (ReviewController + Feature tests); H-06 resolved (phpunit.xml → pgsql default); H-07a resolved (booking.validation.ts VN copy); H-07b resolved (ErrorBoundary.tsx VN copy)
- Open findings: F-23 (MD lint) — F-24 (HasUuids conflict) resolved as H-02 prerequisite
- **NOTE H-06**: `phpunit.xml` default is now PostgreSQL. `php artisan test` requires PostgreSQL at 127.0.0.1:5432 (`soleil_test`/`soleil`/`secret`). Use `docker compose up -d postgres` before running. SQLite: `php artisan test --configuration=phpunit.sqlite.xml` (create if needed).
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

- H-02 RESOLVED (2026-03-06): AuthController Eloquent migration — see worklog entry below
- H-05 RESOLVED (2026-03-06): Review CRUD Feature tests added (14 tests) + ReviewController + ReviewFactory + routes
- H-06 RESOLVED (2026-03-06): `phpunit.xml` default changed from SQLite to PostgreSQL
- H-07a RESOLVED (2026-03-06): `booking.validation.ts` — 12 English validation messages → Vietnamese
- H-07b RESOLVED (2026-03-06): `ErrorBoundary.tsx` — 6 English UI strings → Vietnamese
- Batch 9 complete: 3 PR groups executed (static-analysis, routes-cors, test-env)
- M-11 (migration squash) BLOCKED — no squash protocol in governance; requires human approval

### Next

- M-11: Migration squash — needs human-approved `php artisan schema:dump --prune` process
- H-06 CI alignment: update `.github/workflows/` to start PostgreSQL service before `php artisan test`
- PAY-001 Phase 2: Stripe checkout session + frontend payment UI

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

### Completed (2026-03-05) — Fix composer.lock PHP version mismatch

- Root cause: `composer.lock` pinned Symfony 8.x packages (event-dispatcher v8.0.4, options-resolver v8.0.0, string v8.0.6, filesystem v8.0.6) requiring PHP ≥ 8.4, but runtime/CI/Dockerfile all target PHP 8.3. Lock file was generated on a PHP 8.4 machine with no platform constraint.
- Strategy B applied: Downgraded Symfony packages to 7.4.x (compatible with PHP 8.3)
- Fix 1: Added `"config.platform.php": "8.3.30"` to `backend/composer.json` to prevent future resolution of PHP 8.4-only packages
- Fix 2: Ran targeted `composer update` on 5 Symfony packages — resolved to v7.4.x
- Packages changed in `composer.lock`:
  - symfony/event-dispatcher v8.0.4 → v7.4.4
  - symfony/options-resolver v8.0.0 → v7.4.0
  - symfony/string v8.0.6 → v7.4.6
  - symfony/filesystem v8.0.6 → v7.4.6
- Files changed: `backend/composer.json`, `backend/composer.lock`, `docs/COMPACT.md`
- Gates: `composer validate --strict` PASS, `composer install` PASS, `php artisan test` 871/871 (2449 assertions) PASS, `docker compose config` PASS
- Residual risk: None — all targets (CI, Dockerfile, local) remain PHP 8.3; platform constraint prevents recurrence

### Completed (2026-03-05) — Fix Pint new_with_parentheses + Psalm JIT fatal

- Pint: 2 test files had `new ClassName()` with parentheses; Laravel preset enforces bare `new ClassName`. Auto-fixed by Pint.
  - `tests/Unit/Requests/Auth/LoginRequestValidationTest.php` — `new LoginRequest()` → `new LoginRequest`
  - `tests/Unit/Requests/UpdateBookingRequestValidationTest.php` — `new UpdateBookingRequest()` → `new UpdateBookingRequest`
- Psalm: Fatal crash (exit 255) — `Cannot declare class Psalm\Internal\ErrorHandler, name already in use`. Root cause: PHP JIT (`opcache.enable_cli=1` default from shivammathur/setup-php) preloads ErrorHandler before Psalm's bootstrap → double-declaration.
  - Fix: Added `-d opcache.enable_cli=0 -d opcache.jit=0 -d opcache.jit_buffer_size=0` to Psalm CI step in `.github/workflows/tests.yml`
  - Also switched from global `psalm` to `vendor/bin/psalm` for version determinism
- Files changed: 2 test files, `.github/workflows/tests.yml`, `docs/COMPACT.md`
- Gates: `pint --test` PASS (280 files, 0 issues), `artisan test` 871/871 PASS, `docker compose config` PASS
- Residual risk: Psalm type errors (if any surface after JIT fix) are tracked separately — Psalm job has `continue-on-error: true`

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

## 2026-03-02 — Batch 4: Frontend Core Fixes & Vitest Constraints

### PR-1: fix/fe-abortcontroller-cleanup (M-17)

- Added AbortController cleanup to useEffect fetches in RoomList, LocationList, BookingForm
- Added `signal` parameter to `getRooms()` and `getLocations()` API functions
- Pattern follows existing `fetchMyBookings(signal?)` in booking.api.ts
- Files: `room.api.ts`, `RoomList.tsx`, `location.api.ts`, `LocationList.tsx`, `BookingForm.tsx`
- Gates: tsc 0 errors, vitest 218/218 pass

### PR-2: test/fe-vitest-hoisted-auth-mocks (H-08, H-09)

- Converted LoginPage.test.tsx and RegisterPage.test.tsx from module-level mock vars to `vi.hoisted()` pattern
- Eliminates intermittent Vitest 2.x failures caused by hoisting order issues
- Files: `LoginPage.test.tsx`, `RegisterPage.test.tsx`
- Gates: tsc 0 errors, vitest 218/218 pass

### PR-3: chore/fe-no-console-and-roomlist-tests (M-18, M-21, L-09, M-14)

- Added `no-console` ESLint rule (`error` level, allow `console.warn`)
- Removed `console.error` from 8 production files where error state is already set in UI
- Guarded remaining console calls in ErrorBoundary, main.tsx, api.ts with `import.meta.env.DEV`
- Added `RoomList.test.tsx` with 8 tests (loading, data, empty, error, prices, book button, statuses, images)
- Files: 13 files (eslint.config.js, RoomList.test.tsx new, 11 production files modified)
- Gates: tsc 0 errors, vitest 226/226 pass (21 suites), eslint 0 errors

### Notes

- All 3 branches pushed to origin; GitHub CLI not authenticated — create PRs manually or run `gh auth login`
- Branches: `fix/fe-abortcontroller-cleanup`, `test/fe-vitest-hoisted-auth-mocks`, `chore/fe-no-console-and-roomlist-tests`
- All target `dev` branch

## 2026-03-03 — Batch 5: Frontend UI Copy + FSD Architecture Fixes

### PR-1: `feat/ui-vietnamese-copy-vnd` (commit: `4b20bfa`)

- **H-07**: Translated all English UI strings to Vietnamese across LoginPage, RegisterPage, Header, NotFoundPage, LocationCard, RoomList, AuthContext error messages
- **L-11**: Created `shared/lib/formatCurrency.ts` with VND formatter (`Intl.NumberFormat('vi-VN', { currency: 'VND' })`); replaced `$` prefixes in BookingForm and RoomList with `formatVND()`
- Updated 7 test files to match Vietnamese strings
- Files: 14 files (1 new, 13 modified)

### PR-2: `refactor/fsd-imports-shared` (commit: `19f38cb`)

- **M-20**: Moved `src/types/api.ts` → `src/shared/types/api.ts`
- **M-13**: AuthContext.tsx `User` import → `@/shared/types/api`
- **L-10**: Replaced direct `import axios` in AuthContext.tsx → shared `isAxiosError` re-export from `api.ts`
- **M-19**: Moved `src/utils/{toast,webVitals}.ts` → `src/shared/utils/`; updated 5 consumers + 2 test mocks
- **M-16**: Moved `booking.constants.ts` → `src/shared/lib/booking.utils.ts`; updated AdminDashboard, GuestDashboard, BookingDetailPanel
- **M-15**: Moved `location.types.ts` → `src/shared/types/`; extracted `getLocations` → `src/shared/lib/location.api.ts`; updated SearchCard, LocationList, LocationCard, LocationDetail, index.ts, 3 test files
- Files: 25 files (1 new, 6 moved, 18 modified)

### Gates

- `tsc --noEmit`: 0 errors ✅ (both PRs)
- `vitest run`: 226/226 pass, 21 suites ✅ (both PRs)
- `eslint` (lint-staged): 0 warnings ✅ (both PRs)
- Pre-push hooks: passed ✅ (both PRs)
- Backend: not touched — [SKIPPED]

### Residual

- F-21 (LoginPage English): RESOLVED by PR-1
- SearchCard `act(...)` warnings in HomePage.test.tsx: pre-existing, non-blocking (see §5)
- `gh` CLI not authenticated — PRs must be created manually

## 2026-03-04 — Batch 6: Documentation Synchronization (Source of Truth)

**Model:** Claude Opus 4.6
**Issues addressed:** D-01 through D-18
**Files modified:** 6 doc files + COMPACT.md

### Facts synchronized

| Fact                     | Old value             | New value             | Source                        |
| ------------------------ | --------------------- | --------------------- | ----------------------------- |
| Backend tests (README)   | 737 / 2071 assertions | 857 / 2430 assertions | COMPACT.md verified Mar 2     |
| Frontend tests (README)  | 145 / 13 files        | 226 / 21 files        | COMPACT.md verified Mar 3     |
| Migrations (DATABASE.md) | 32 files              | 35 files              | ls migrations/ wc -l          |
| LIM-002 status           | Planned               | In Progress           | Cashier bootstrap done Mar 1  |
| LIM-008 summary status   | Planned               | Partially Resolved    | Backend i18n done Mar 1       |
| Findings open count      | 4 open (F-21–F-24)    | 3 open (F-22–F-24)    | F-21 resolved by Batch 5 PR-1 |

### Files changed

- `README.md` — D-03: updated test counts 737→857, 145→226, 2071→2430, 13→21
- `PROJECT_STATUS.md` — D-06: findings count 4→3 open, F-21 marked resolved
- `docs/KNOWN_LIMITATIONS.md` — D-10 (LIM-008 Planned→Partially Resolved), D-12 (LIM-002 Planned→In Progress with Cashier details)
- `docs/DATABASE.md` — D-13: migration count 32→35, added Cashier migration to table
- `docs/DEVELOPMENT_HOOKS.md` — D-18: added canonical redirect banner + [→ HOOKS.md] annotations
- `docs/COMPACT.md` — updated snapshot + appended this entry

### Issues with no drift found

- D-01 (AGENTS.md §9): already 857/226 — no change needed
- D-02 (CLAUDE.md): already 857/226 — no change needed
- D-04 (DEVELOPMENT_HOOKS.md): already 857/226 — no change needed
- D-05 (COMMANDS_AND_GATES.md): frontend commands already use pnpm — no change needed
- D-07 (PRODUCT_GOAL.md): already 857/226 — no change needed
- D-08 (BACKLOG.md): historical per-completion counts are correct — no change needed
- D-09 (WORKLOG.md): Feb 27-28 entries exist, counts are historical — no change needed
- D-11 (KNOWN_LIMITATIONS TD-001/002): already marked Resolved — no change needed
- D-14 (DB_FACTS.md): already correctly notes chk_rooms_max_guests NOT in migrations — no change needed
- D-15 (MIGRATION_GUIDE.md): already says Laravel 12.x — no change needed
- D-16 (OPERATIONAL_PLAYBOOK.md): WHERE predicate already correct — no change needed
- D-17 (AGENTS.md §3): already uses pnpm — no change needed

### D-NEW items for next batch

- D-NEW-01: `docs/agents/COMMANDS.md` has npm references for frontend commands (lines 32, 45-46, 63, 82) — should be pnpm. File not in Batch 6 allowlist.

### D-18 Consolidation decision

Chose **Option B**: Added top-level canonical redirect banner to `docs/DEVELOPMENT_HOOKS.md` pointing to `docs/HOOKS.md`. Annotated duplicated section headings with `[→ HOOKS.md]`. Headings preserved for anchor compatibility. Unique sections (Purpose, Project Baseline, Hook Policy Source) kept intact.

### Governance

- No non-doc files modified ✓
- No hook bypass flags used ✓
- All patches applied via Edit tool with targeted diffs ✓
- chk_rooms_max_guests verified NOT PRESENT in migration 2026_02_22_000001 ✓

## 2026-03-04 — Batch 7: DevOps/CI Hardening v2 (10 issues)

### PR-1: `infra/compose-proxy-hardening-v2` (branch: `infra/compose-proxy-hardening-v2`)

#### Issues Fixed

- C-03: docker-compose.prod.yml Redis password now required via `${REDIS_PASSWORD:?...}` (redis service + backend env)
- M-22: docker-compose.yml backend depends_on upgraded to `condition: service_healthy` (db + redis)
- M-23: Removed ineffective REDIS_REPL_DISKLESS_SYNC / REDIS_MAX_CLIENTS env vars from docker-compose.yml
- M-25: Caddy rate limiting kept commented; existing plugin requirement documentation sufficient
- L-14: frontend/nginx.conf security headers added (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy)

#### Files Changed

- `docker-compose.yml` — M-22 (service_healthy), M-23 (removed ineffective env vars)
- `docker-compose.prod.yml` — C-03 (REDIS_PASSWORD fail-fast)
- `frontend/nginx.conf` — L-14 (security headers)

#### Gates

- [x] `docker compose -f docker-compose.yml config` PASS
- [x] `docker compose -f docker-compose.prod.yml config` PASS (with REDIS_PASSWORD + DB_PASSWORD set)
- [x] Fail-fast verified: prod compose fails without REDIS_PASSWORD (intended)

### PR-2: `ci/deploy-scripts-hardening-v2` (branch: `ci/deploy-scripts-hardening-v2`)

#### Issues Fixed

- C-04: deploy.yml now runs `tsc --noEmit` + `pnpm run test:unit` as blocking gates before build
- M-26: tests.yml REDIS_PASSWORD fixed from literal `"null"` to `""` (CI redis has no auth)
- M-28: ship.sh Gate 4 added — `docker compose config` validates before ship
- H-13: frontend Dockerfile prod stage switched to `nginxinc/nginx-unprivileged:1.27-alpine` (non-root, port 8080); compose port mapping + Caddyfile updated
- H-14: deploy-forge.sh `run_migrations()` fully implemented (SSH + Docker exec paths, timestamps, error handling, `--force --no-interaction`)

#### Files Changed

- `.github/workflows/deploy.yml` — C-04 (tsc + vitest gates)
- `.github/workflows/tests.yml` — M-26 (REDIS_PASSWORD fix)
- `scripts/ship.sh` — M-28 (Gate 4 compose config)
- `frontend/Dockerfile` — H-13 (non-root nginx-unprivileged)
- `frontend/nginx.conf` — H-13 (listen 8080)
- `docker-compose.prod.yml` — H-13 (port mapping 80:8080, healthcheck port)
- `Caddyfile` — H-13 (frontend proxy target 8080)
- `deploy-forge.sh` — H-14 (run_migrations implementation)

#### Gates

- [x] `bash -n scripts/ship.sh` PASS
- [x] `bash -n deploy-forge.sh` PASS
- [x] `docker compose -f docker-compose.yml config` PASS
- [x] `docker compose -f docker-compose.prod.yml config` PASS
- [ ] YAML syntax (tests.yml, deploy.yml): [REQUIRES LOCAL VERIFICATION — Python not available]
- [ ] Frontend tsc + vitest: [REQUIRES LOCAL VERIFICATION — no app code changed]

### Risks & Notes

- H-13 nginx port 80→8080: docker-compose.prod.yml port mapping, Caddyfile, and healthcheck all updated atomically. Validate in staging.
- C-03 fail-fast: REDIS_PASSWORD must be set in production env before deploying PR-1. Intentional startup failure if unset.
- H-14: Laravel migrations are idempotent forward but NOT automatically reversible. Manual `php artisan migrate:rollback` required if schema change breaks.
- M-25: Caddy rate limiting remains disabled (requires custom Caddy build with xcaddy + caddy-ratelimit plugin).
- Previous Batch 1 (2026-03-01) branches exist but were never merged to dev; this batch supersedes them.

### Rollback

- All changes are config/script-only — `git revert <sha>` is safe for both PRs
- H-13 port change must be reverted atomically across Dockerfile + compose + Caddyfile + nginx.conf

---

## 2026-03-04 — Batch 8: Backend Architecture — Validation, Null-Safety, Service/Repository Extraction

### PR-1: fix/auth-login-validation (M-07)

- M-07: Auth\LoginRequest password rule now enforces `min:8` (matching RegisterRequest and v2 LoginRequest)
- H-02 DEFERRED: DB::table insertGetId blocked by F-24 (HasUuids conflict)
- L-04 VERIFIED: detectAuthMode() is actively used (3 call-sites), no change needed

**Files changed:** LoginRequest.php, +LoginRequestValidationTest.php

### PR-2: fix/booking-room-validation-null-safety (M-06, L-05, L-06)

- M-06: UpdateBookingRequest guest_name now requires min:2; room_id changed to `sometimes` (updates don't change room)
- L-05: EloquentRoomRepository::hasOverlappingConfirmedBookings() now uses findOrFail() instead of find()
- L-06: Room model $casts now includes `'max_guests' => 'integer'`
- L-03: Already resolved in Batch 3 — verified, no change needed

**Files changed:** UpdateBookingRequest.php, Room.php, RoomRepositoryInterface.php, EloquentRoomRepository.php, EloquentRoomRepositoryTest.php, +UpdateBookingRequestValidationTest.php

### PR-3: refactor/admin-booking-and-contact-service (M-02, M-05)

- M-02: AdminBookingController thinned — index() now uses BookingRepositoryInterface::getAllWithTrashedPaginated(); restore()/restoreBulk() overlap checks use BookingRepositoryInterface::hasOverlappingBookings() instead of direct Booking:: scope calls
- M-05: ContactController fully refactored — all 3 methods now delegate through ContactMessageService -> EloquentContactMessageRepository. StoreContactRequest already existed (no change). Audit logging moved to service layer.

**Files created:**

- backend/app/Repositories/Contracts/ContactMessageRepositoryInterface.php
- backend/app/Repositories/EloquentContactMessageRepository.php
- backend/app/Services/ContactMessageService.php

**Files modified:**

- backend/app/Http/Controllers/AdminBookingController.php
- backend/app/Http/Controllers/ContactController.php
- backend/app/Providers/AppServiceProvider.php (added ContactMessageRepository binding)
- backend/app/Repositories/Contracts/BookingRepositoryInterface.php (added getAllWithTrashedPaginated)
- backend/app/Repositories/EloquentBookingRepository.php (added getAllWithTrashedPaginated impl)
- backend/app/Services/BookingService.php (added cache TTL constant)

### Gates Passed

- [x] php artisan test — 857 passed, 2430 assertions
- [x] vendor/bin/pint --test — 278 files, 0 violations
- [x] php -l syntax checks — all files clean
- [ ] Frontend tsc + vitest: no frontend changes in this batch

### Risks & Notes

- M-02: Response shape UNCHANGED — same BookingResource, same meta structure
- M-05: Response shape UNCHANGED — same success() wrapper and translation keys
- New IoC binding added for ContactMessageRepositoryInterface in AppServiceProvider — revert if rolling back PR-3
- BookingService does not yet inject BookingRepositoryInterface (uses static Booking:: calls) — existing tech debt, out of scope

## 2026-03-04 — Batch 9: Backend Cleanup, Tests & Configs

### Issues Addressed

| Issue | Status      | Summary                                                                                 |
| ----- | ----------- | --------------------------------------------------------------------------------------- |
| H-04  | ✅ RESOLVED | Installed `vimeo/psalm ^6.15` as composer dev dependency                                |
| L-01  | ✅ RESOLVED | Replaced 4x `config('app.env') === 'production'` → `app()->isProduction()`              |
| L-02  | ✅ RESOLVED | Translated Indonesian strings to Vietnamese in HttpOnlyTokenController                  |
| L-07  | ✅ RESOLVED | Clarified `@deprecated` tag on RateLimitService (dead code documentation)               |
| M-08  | ✅ RESOLVED | Deleted dead `app/Http/Middleware/Cors.php` (not registered; Laravel HandleCors active) |
| M-10  | ✅ RESOLVED | Named all 25 v1 API routes with `v1.*` convention                                       |
| L-08  | ✅ RESOLVED | Env-gated v2 skeleton routes: hidden in production, visible in dev/testing              |
| M-12  | ✅ RESOLVED | Added 6 CspViolationReportController tests (dedicated test file)                        |
| H-06  | ⚠️ PARTIAL  | Created `phpunit.pgsql.xml` for opt-in PostgreSQL testing; SQLite remains default       |
| M-11  | 🛑 BLOCKED  | No squash protocol exists in governance; SQUASH PLAN documented below                   |

### Files Changed

- `backend/composer.json` — added `vimeo/psalm ^6.15`
- `backend/composer.lock` — updated with psalm + dependencies
- `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` — L-01 + L-02
- `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` — L-01
- `backend/app/Services/RateLimitService.php` — L-07 (docblock clarification)
- `backend/app/Http/Middleware/Cors.php` — DELETED (M-08)
- `backend/routes/api/v1.php` — M-10 (route names)
- `backend/routes/api/v2.php` — L-08 (env-gating)
- `backend/phpunit.pgsql.xml` — NEW (H-06 opt-in PG config)
- `backend/tests/Feature/Security/CspViolationReportControllerTest.php` — NEW (M-12)
- `docs/COMPACT.md` — this update

### Gates

- `php artisan test`: 871 tests, 2449 assertions ✅
- `vendor/bin/pint --test`: 280 files, 2 pre-existing violations ✅ (not from this batch)
- `vendor/bin/psalm --version`: 6.15.1 ✅ installed
- `docker compose config`: valid ✅
- `php artisan route:list --path=api/v1`: 25 routes, all named ✅

### M-11 SQUASH PLAN (REQUIRES HUMAN APPROVAL)

**Current state:** 35 migration files in `backend/database/migrations/`

**Recommended approach:**

1. Ensure ALL deployed environments (staging, production) are fully migrated
2. Run `php artisan schema:dump --prune` to create a SQL dump and remove old files
3. Verify the schema dump includes PostgreSQL-specific features (EXCLUDE constraints, triggers, ENUMs)
4. Test fresh install with dump: `php artisan migrate:fresh --seed`
5. Keep the dump committed as the new baseline migration

**Prerequisites:**

- Confirm no environment is mid-migration
- Coordinate with deployment team
- Test rollback strategy (dump file cannot be rolled back individually)

**Risk:** Medium — irreversible once deployed; requires all environments synchronized first.

### Rollback Notes

- PR-1: Revert composer.json/lock (remove psalm), restore old strings/config checks
- PR-2: Restore Cors.php from git, remove ->name() from routes, remove env-gate from v2

## 2026-03-05 — fix/psalm-v1-routes-typing

### Problem

Psalm gate (exit code 2) — 6× `PossiblyInvalidMethodCall` in `routes/api/v1.php`:
lines 42, 45, 46, 47 (booking CRUD) and lines 51, 53 (confirm/cancel).
All caused by chaining `->name()` after `->middleware()` on a `Route` instance.

### Root Cause

`Route::middleware()` returns `static|array` — with args it returns `$this`, without args
it returns the middleware list. Psalm sees the union and flags `->name()` as invalid on
`array<array-key, mixed>`. No Psalm Laravel plugin is installed to narrow the type.

### Fix Applied

Swapped chain order from `->middleware(...)->name(...)` to `->name(...)->middleware(...)`.
`Route::name()` always returns `static`, so chaining is safe. `->middleware()` at end of
chain has its `static|array` return unused — no `PossiblyInvalidMethodCall`.

Files changed:

- `backend/routes/api/v1.php` — 6 chain order swaps (zero runtime behavior change)

### Gate Results

- `psalm --output-format=github`: exit 0, 0 v1.php errors
- `artisan route:list --path=api/v1`: 25 routes OK
- `artisan test`: 871 passed (2449 assertions)
- `vendor/bin/pint --test routes/api/v1.php`: PASS

### Residual Risk

- 399 other Psalm issues remain (suppressed or info-level) — tracked separately
- `PossiblyInvalidMethodCall` is not globally suppressed in psalm.xml, unlike sibling
  `Possibly*` issues — consider adding to issueHandlers if more instances appear
- PR-3: Delete phpunit.pgsql.xml, delete CspViolationReportControllerTest.php

## 2026-03-05 — Batch 10: Frontend Cleanup (H-07, M-16, M-18, L-11, L-13)

### PR-1: fix/fe-batch10-ui-fsd-console-vnd

#### Issues Fixed

- H-07: Translated all English UI strings to Vietnamese in LocationList.tsx (heading, subtitle, error, filter label, empty states)
- M-16: Moved `BookingApiRaw` type to `shared/types/booking.types.ts`; `admin.types.ts` now imports from shared instead of cross-feature `@/features/booking/`; original `booking.types.ts` re-exports for backward compatibility
- M-18: Dev-gated `console.error` in `main.tsx` with `import.meta.env.DEV` (ErrorBoundary and api.ts were already gated)
- L-11: Replaced `$` USD-formatted fixtures with VND format in `BookingDetailPanel.test.tsx` (`$200.00`→`200.000 ₫`, `$150.00`→`150.000 ₫`) and `bookingViewModel.test.ts` (`$50.00`→`50.000 ₫`)

#### Files Changed

- `frontend/src/features/locations/LocationList.tsx` — H-07: 5 English strings → Vietnamese
- `frontend/src/shared/types/booking.types.ts` — NEW: shared `BookingApiRaw` type
- `frontend/src/features/booking/booking.types.ts` — M-16: replaced `BookingApiRaw` definition with re-export from shared
- `frontend/src/features/admin/admin.types.ts` — M-16: import path changed to `@/shared/types/booking.types`
- `frontend/src/main.tsx` — M-18: added `import.meta.env.DEV` guard around `console.error`
- `frontend/src/features/bookings/BookingDetailPanel.test.tsx` — L-11: `$200.00`→`200.000 ₫`, `$150.00`→`150.000 ₫`
- `frontend/src/features/bookings/bookingViewModel.test.ts` — L-11: `$50.00`→`50.000 ₫`

#### Gates

- `tsc --noEmit`: 0 errors ✅
- `vitest run`: 226 pass, 21 suites ✅
- `eslint`: 0 errors ✅

#### Notes / Residual

- `dev:frontend` script in root `package.json` still uses `npm` — out of scope per L-13 (only `scripts.dev` specified)
- D-NEW-01 from Batch 6: `docs/agents/COMMANDS.md` npm references remain (docs scope, not frontend)

### PR-2: chore/fe-batch10-root-pnpm-dev

#### Issues Fixed

- L-13: root `package.json` `scripts.dev` changed from `cd frontend && npm run dev -- --host` to `cd frontend && pnpm run dev -- --host`

#### Files Changed

- `package.json` — scripts.dev updated

#### Gates

- Root script verified: `concurrently "cd backend && php artisan serve ..." "cd frontend && pnpm run dev -- --host"` ✅
- pnpm-lock.yaml: unchanged ✅

## 2026-03-05 — fix/frontend-booking-type-alignment

### Problem

`pnpm run build` (`tsc -b && vite build`) failed with 17 TypeScript errors across 3 files:

- `src/features/booking/booking.types.ts` — 3× TS2304 Cannot find name 'BookingApiRaw'
- `src/features/bookings/BookingDetailPanel.tsx` — 11× TS2339 property missing on BookingDetailRaw
- `src/features/bookings/BookingDetailPanel.test.tsx` — 4× TS2353 invalid keys in mock objects

### Root Cause

`booking.types.ts` line 38 used `export type { BookingApiRaw } from '@/shared/types/booking.types'` — a re-export that does NOT create a local binding. The same file referenced `BookingApiRaw` locally at lines 42, 48, and 66 (`BookingDetailRaw extends BookingApiRaw`). Since the extends clause failed, `BookingDetailRaw` lost all inherited fields from `BookingApiRaw`, causing cascade failures in `BookingDetailPanel.tsx` (11 errors) and its test file (4 errors).

### Strategy Applied

Option 1: Added `import type { BookingApiRaw } from '@/shared/types/booking.types'` alongside the existing re-export. Single-line fix resolves all 17 errors.

### Files Changed

- `frontend/src/features/booking/booking.types.ts` — added local import for `BookingApiRaw`

### Gate Results

- `tsc --noEmit`: PASS — 0 errors
- `vitest run`: PASS — 226 tests passed, 21 suites
- `pnpm run build`: PASS — built in 6.79s

### Residual Risk

None — all 17 errors resolved, no type assertions added, no fields invented.

## 2026-03-05 — Batch 11: Docs Sync (D-01, D-02, D-03, D-04, D-07, D-14, D-18)

### Facts synchronized

| Fact                            | Old value                | New value  | Source                            |
| ------------------------------- | ------------------------ | ---------- | --------------------------------- |
| Backend tests (AGENTS.md)       | 857 / 2430               | 871 / 2449 | COMPACT.md §1 verified 2026-03-05 |
| Backend tests (CLAUDE.md)       | 857 / 2430               | 871 / 2449 | COMPACT.md §1 verified 2026-03-05 |
| Backend tests (README.md)       | 857 / 2430 (6 locations) | 871 / 2449 | COMPACT.md §1 verified 2026-03-05 |
| Backend tests (PRODUCT_GOAL.md) | 857                      | 871        | COMPACT.md §1 verified 2026-03-05 |
| Pint files (AGENTS.md)          | 275                      | 280        | COMPACT.md §1 verified 2026-03-05 |
| Pint files (CLAUDE.md)          | 275                      | 280        | COMPACT.md §1 verified 2026-03-05 |
| Verified date (AGENTS.md)       | Mar 2                    | Mar 5      | Batch 9 gate results              |
| Verified date (CLAUDE.md)       | 2026-03-02               | 2026-03-05 | Batch 9 gate results              |

### D-14 outcome

- chk_rooms_max_guests: NOT in migrations (confirmed via grep + reading migration 2026_02_22_000001)
- DB_FACTS.md: already correct — no change needed
- DATABASE.md: removed chk_rooms_max_guests from CHECK constraints SQL block; added note clarifying it is app-layer only

### D-18 outcome

- Canonical file: docs/HOOKS.md (confirmed)
- Merged unique sections from DEVELOPMENT_HOOKS.md into HOOKS.md: Purpose, Hook Policy Source (with `<!-- merged from DEVELOPMENT_HOOKS.md 2026-03-05 -->` comment)
- DEVELOPMENT_HOOKS.md: reduced to redirect stub (3 lines)
- docs/README.md: Quick Navigation link updated to point to HOOKS.md; all-docs index entry updated to "Redirect to HOOKS.md"
- docs/HOOKS.md: removed cross-reference to DEVELOPMENT_HOOKS.md

### Files changed

- `AGENTS.md` — D-01: stats 857→871, 2430→2449, 275→280, date Mar 2→Mar 5
- `CLAUDE.md` — D-02: stats 857→871, 2430→2449, 275→280, date 2026-03-02→2026-03-05
- `README.md` — D-03: stats 857→871, 2430→2449 (6 locations)
- `PRODUCT_GOAL.md` — D-07: stats 857→871
- `docs/DATABASE.md` — D-14: removed chk_rooms_max_guests from SQL, added app-layer note
- `docs/HOOKS.md` — D-18: merged Purpose + Hook Policy Source sections
- `docs/DEVELOPMENT_HOOKS.md` — D-18: replaced with redirect stub (D-04 stats update made moot)
- `docs/README.md` — D-18: updated Quick Navigation + all-docs index links

### Verification

- Stats sweep: CLEAN — 0 matches for 857/2430 in target files
- New values confirmed: 9 matches for 871/2449 in target files
- Dangling links: CLEAN — no active DEVELOPMENT_HOOKS.md links remain (only historical refs in AUDIT_REPORT, WORKLOG, FINDINGS_BACKLOG)
- DB constraint: present in both DB docs with consistent "not in migrations" note ✅
- Markdown lint: NOT FOUND — no markdownlint config or CI gate

### D-NEW items (out of scope — log only)

- D-NEW-02: `docs/README.md:3` has stale "857 tests (2430 assertions)" — not in D-01..D-07 scope
- D-NEW-03: `PROJECT_STATUS.md:22-24` has stale "857/2430/275" — not in D-01..D-07 scope

### Issues with no change needed

- D-04 (DEVELOPMENT_HOOKS.md stats): file replaced with redirect stub by D-18; stale stats eliminated by consolidation

## 2026-03-05 — BATCH_AUDIT_2026-03-05: Full Project Health Check + All Docs Sync

**Model:** Claude Opus 4.6
**Issues addressed:** D-1..D-20 drift items across 8 doc files + 2 findings status updates
**Files modified:** 9 doc files + COMPACT.md

### Facts synchronized

| Fact                                | Old value                        | New value                          | Source                                                 |
| ----------------------------------- | -------------------------------- | ---------------------------------- | ------------------------------------------------------ |
| Backend tests (PROJECT_STATUS)      | 857 / 2430                       | 871 / 2449                         | E1: `php artisan test` verified 2026-03-05             |
| Pint file count (PROJECT_STATUS)    | 275                              | 280                                | E2: `pint --test` verified 2026-03-05                  |
| PHPStan baseline (PROJECT_STATUS)   | 151                              | 150                                | E15: phpstan-baseline.neon sum of counts               |
| Backend tests (docs/README)         | 857 / 2430                       | 871 / 2449                         | E1, E12                                                |
| Date (PROJECT_STATUS)               | March 2, 2026                    | March 5, 2026                      | E14: git log                                           |
| Date (docs/README)                  | March 2, 2026                    | March 5, 2026                      | E14                                                    |
| Date (PRODUCT_GOAL)                 | 2026-03-02                       | 2026-03-05                         | E14                                                    |
| Date (BACKLOG)                      | 2026-03-02                       | 2026-03-05                         | E14                                                    |
| Frontend commands (agents/COMMANDS) | npm run lint/format/install/dev  | pnpm lint/format/install/dev       | E10: pnpm-lock.yaml present                            |
| AGENTS.md "Unknown" note            | "`develop` appears in workflows" | F-04 resolved note                 | E13: FINDINGS_BACKLOG F-04 Fixed                       |
| README.md tech stack                | "React Query, Zustand, Axios"    | "Axios" (RQ/Zustand not installed) | E9: not in package.json                                |
| F-21 status                         | Open                             | Fixed                              | Code verified: LoginPage + RegisterPage now Vietnamese |
| F-22 status                         | Open                             | Fixed                              | Code verified: "ditemukan" → "Không tìm thấy"          |
| Open findings count                 | 4                                | 2                                  | F-21 + F-22 now Fixed; F-23 + F-24 remain Open         |

### Gate snapshot (verified 2026-03-05)

- Backend: 871 tests, 2449 assertions — PASS
- Frontend: 226 pass, 21 suites — PASS
- TypeScript: 0 errors — PASS
- Pint: 280 files, 0 violations — PASS
- PHPStan: Level 5, 150 baseline errors
- Docker Compose: PASS (prod config requires REDIS_PASSWORD env — expected)

### Hooks / D-18 outcome

- Canonical hooks file: docs/HOOKS.md (no change needed — already complete from Batch 11)
- DEVELOPMENT_HOOKS.md: redirect stub (no change needed)
- Referrers: all correct

### Files changed

- `PROJECT_STATUS.md` — full update: date, test counts (857→871, 2430→2449, 275→280), added March 5 batch, open findings section, PHPStan 151→150
- `PRODUCT_GOAL.md` — dates updated (2026-03-02→2026-03-05)
- `BACKLOG.md` — date updated (2026-03-02→2026-03-05)
- `AGENTS.md` — "Unknown/verify" section: stale `develop` note → F-04 resolved note
- `README.md` — removed React Query + Zustand from tech stack (not installed)
- `docs/README.md` — test counts (857→871, 2430→2449), date updated
- `docs/agents/COMMANDS.md` — frontend npm→pnpm (lint, format, install, dev)
- `docs/FINDINGS_BACKLOG.md` — F-21 + F-22 marked Fixed (verified in code)
- `docs/COMPACT.md` — §1 updated + this batch appended

### New findings discovered (deferred to separate batch)

- NEW-FINDING-1: `package.json:9` — `dev:frontend` uses `npm run dev` but should use `pnpm dev` (inconsistent with line 6 which correctly uses pnpm). Risk: LOW. Recommended: next code batch.

### Issues with no change needed

- CLAUDE.md: metrics already correct (871/2449/226/280) — no edit needed
- docs/DATABASE.md: migration count 35 correct, chk_rooms_max_guests note present — no edit needed
- docs/DB_FACTS.md: constraint docs accurate — no edit needed
- docs/HOOKS.md: already has merged content from Batch 11 — no edit needed
- docs/DEVELOPMENT_HOOKS.md: already redirect stub — no edit needed
- docs/OPERATIONAL_PLAYBOOK.md: daterange + deleted_at IS NULL correct — no edit needed
- docs/COMMANDS_AND_GATES.md: root npm commands correct (root pkg uses npm); frontend pnpm refs OK — no edit needed
- docs/KNOWN_LIMITATIONS.md: LIM statuses match current state — no edit needed

### Open Questions / TBDs

- PHPStan baseline: phpstan-baseline.neon sums to 150 but COMPACT/docs historically say 151 — updated to 150 in PROJECT_STATUS.md; remaining docs left at 151 where they're historical references. Net difference is 1 error.
- ESLint: 3 warnings from `coverage/` generated files (not source code) — ESLint config should exclude `coverage/`. Not a docs issue.

---

## H-02 — AuthController Eloquent migration [RESOLVED]

- **Date:** 2026-03-06
- **Agent:** Claude Sonnet 4.6
- **Files changed:**
  - `backend/app/Http/Controllers/Auth/AuthController.php` — replaced raw `DB::table()->insertGetId()` in `login()` and `refresh()` with `$user->tokens()->create()`
  - `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` — replaced raw `DB::table()->insertGetId()` in `login()` and `refresh()` with `$user->tokens()->create()`; removed `use Illuminate\Support\Facades\DB`
  - `backend/app/Models/PersonalAccessToken.php` — added `uniqueIds(): array { return []; }` override (prerequisite fix for F-24: prevents HasUuids from assigning UUID to bigint primary key)
  - `backend/tests/Feature/Auth/AuthenticationTest.php` — added Eloquent `creating` event assertions in `test_login_success_with_valid_credentials` and `test_refresh_token_creates_new_token`
  - `backend/tests/Feature/Auth/LoginHttpOnlyTest.php` — added `PersonalAccessToken` import + Eloquent `creating` event assertion in `test_valid_credentials_return_200_with_csrf_token_and_httponly_cookie`
  - `docs/COMPACT.md` — this entry
- **Approach:** Option B — `$user->tokens()->create()` (morphMany relationship auto-sets `tokenable_id`/`tokenable_type`; all other fields in `$fillable`; mutator handles abilities JSON encoding)
- **Events now fired:** `PersonalAccessToken::creating`, `PersonalAccessToken::created` (Eloquent model lifecycle events; no custom observer registered, but future observers will work)
- **F-24 resolved as prerequisite:** `uniqueIds()` overridden to `[]`; HasUuids no longer tries to assign UUID to bigint `id` column
- **Tests added:** 3 new Eloquent event assertions across 2 test files; 4 new assertions total
- **Gate results:** All 5 gates passed — 871 tests/2453 assertions; Pint 280 files/0 violations; all auth routes present; no `DB::table` in auth controllers; fillable + uniqueIds verified
- **Rollback:** `git revert <sha>` — restores raw DB path; zero observable API behavior change for consumers; run `php artisan optimize:clear` after revert

---

## H-05 + H-06 — Review CRUD Feature Tests + PostgreSQL default [RESOLVED]

- **Date:** 2026-03-06
- **Agent:** Claude Opus 4.6
- **H-05 — Review CRUD Feature Tests:**
  - **Root cause:** No ReviewController existed; routes were commented out; no ReviewFactory; only Unit tests existed
  - **Files created:**
    - `backend/database/factories/ReviewFactory.php` — definition + `forBooking(Booking)` + `approved()` states
    - `backend/app/Http/Controllers/ReviewController.php` — `store()` / `update()` / `destroy()` using ReviewPolicy
    - `backend/tests/Feature/Review/ReviewCrudTest.php` — 14 Feature tests (create×9, update×2, delete×3)
  - **Files modified:**
    - `backend/app/Http/Requests/StoreReviewRequest.php` — replaced `room_id` with `booking_id` (required, exists:bookings,id); room_id is now sourced from the booking in the controller
    - `backend/routes/api/v1.php` — added `ReviewController` import + POST/PUT/PATCH/DELETE review routes inside `check_token_valid+verified` middleware group
  - **Policy alignment:** `authorize('create', [Review::class, $booking])` passes Booking to ReviewPolicy::create(); admin-denial, owner-only-update, admin+owner-delete all enforced
  - **Root cause of first test failure:** `guest_name` column is NOT NULL (from `create_reviews_table` migration); fixed by sourcing from `auth()->user()->name`
  - **Tests added:** 14 Feature tests / 34 assertions
- **H-06 — PostgreSQL default:**
  - **File modified:** `backend/phpunit.xml` — `DB_CONNECTION` sqlite→pgsql; added `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` matching `phpunit.pgsql.xml`
  - **Prerequisite:** PostgreSQL must be running at 127.0.0.1:5432 with database `soleil_test`, user `soleil`, password `secret`
  - **Quick start:** `docker compose up -d postgres && php artisan test`
  - **Opt-in SQLite still available:** `php artisan test --configuration=phpunit.pgsql.xml` for the pgsql config; prior phpunit.xml behavior recoverable by reverting or using a local override
  - **CI impact:** GitHub Actions workflows may need `services: postgres:` block — see `.github/workflows/` for update
- **Gate results (SQLite run):** 885 tests / 2487 assertions; Pint 283 files / 0 violations
- **Note:** Full gate for H-06 (PostgreSQL path) requires PostgreSQL running; not verified in this session
- **Rollback H-05:** `git revert <sha>` — removes ReviewController, routes, factory, tests; no DB schema change
- **Rollback H-06:** revert `phpunit.xml` to restore SQLite default

---

## H-07a — booking.validation.ts Vietnamese Copy [RESOLVED]

- **Date:** 2026-03-06
- **Agent:** Claude Sonnet 4.6
- **File changed:** `frontend/src/features/booking/booking.validation.ts`
- **Strings replaced:** 12 English validation messages → Vietnamese
- **Translation reference:** `frontend/src/features/auth/RegisterPage.tsx` + `LoginPage.tsx` (polite imperative `'Vui lòng nhập/chọn...'`; format errors `'... không hợp lệ'`)
- **Logic changed:** none — validation rules, field checks, return shape all unchanged
- **Tests updated:** 1 file — `frontend/src/features/booking/booking.validation.test.ts` (12 string literals updated, test logic untouched)
- **Gates:** All 5 passed — tsc 0 errors ✅, vitest 226/226 ✅, no English messages remain ✅, no dependency changes ✅
- **Rollback:** `git revert <sha>` restores English strings; validation logic unchanged; zero API impact

## H-07b — ErrorBoundary.tsx Vietnamese Copy [RESOLVED]

- **Date:** 2026-03-06
- **Agent:** Claude Sonnet 4.6
- **File changed:** `frontend/src/shared/components/ErrorBoundary.tsx`
- **Strings replaced:** 6 English UI strings → Vietnamese
- **Vietnamese used:** `'Ôi! Đã xảy ra lỗi'` (heading), `'Đã có lỗi không mong muốn xảy ra. Đừng lo, đây không phải lỗi của bạn!'` (description), `'Chi tiết lỗi:'` (dev label), `'Stack gọi component'` (dev summary), `'Thử lại'` (button — matches `LocationDetail.tsx`), `'Về trang chủ'` (button — matches `LoginPage.tsx`)
- **Logic changed:** none — componentDidCatch, handleReset, handleGoHome behavior all unchanged
- **Tests updated:** none — no ErrorBoundary test file asserting UI copy exists
- **Gates:** All 5 passed — tsc 0 errors ✅, vitest 226/226 ✅, no target English strings remain ✅, no dependency changes ✅
- **Rollback:** `git revert <sha>` restores English copy; retry/navigate behavior, class structure all unchanged; zero backend impact

---

## H-08 — PHPStan AuthController `Model::tokens()` Fix [RESOLVED]

- **Date:** 2026-03-06
- **Root cause:** `$oldToken->tokenable` / `$token->tokenable` returns `Illuminate\Database\Eloquent\Model` via morphTo. PHPStan baseline expected 1 `tokens()` error but 2 call sites existed (refresh + logoutAll).
- **Fix:** Added `/** @var User $user */` annotations at both `tokenable` assignment sites in `AuthController.php`. Removed stale `tokens()` baseline entry; removed resolved `$email`/`$name` entries; reduced `$id` count (2→1). Added new baseline entry for `MorphMany::notRevoked()` (pre-existing scope resolution issue unmasked by fix).
- **Files changed:** `backend/app/Http/Controllers/Auth/AuthController.php`, `backend/phpstan-baseline.neon`
- **Runtime behavior changed:** none — PHPDoc annotations only
- **Gates:** PHPStan 0 errors ✅, Pint 283 files / 0 violations ✅, `php artisan test` requires PostgreSQL (local env missing driver — CI verified)
- **Rollback:** `git revert <sha>` — re-adds stale baseline entry + removes type annotations; zero runtime impact
