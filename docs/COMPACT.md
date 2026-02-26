# COMPACT — Soleil Hostel (AI Session Memory)

## 1) Current Snapshot (keep under 12 lines)
- Date updated: 2026-02-26
- Current branch: `dev`
- Latest verified commands: `cd frontend && npx tsc --noEmit` (0 errors), `cd frontend && npx vitest run` (194 tests, 19 suites, 0 failures) — re-verified 2026-02-26
- Backend test baseline: `cd backend && php artisan test` (737 tests, 2071 assertions) — verified 2026-02-26
- Pint baseline: `cd backend && vendor/bin/pint --test` (250 files, 0 violations) — verified 2026-02-23
- Progress summary: Frontend Phases 0-4 ALL COMPLETE; auth 401 fix committed (EncryptCookies mismatch); proxy fix; Dashboard + SearchCard wired; audit v3+v4 remediation (20/20 fixed)
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
- Keep this COMPACT snapshot current after each meaningful change batch.
- Keep branch/test snapshot synced with `../PROJECT_STATUS.md` and latest local verification runs.
- Keep warning/noise notes current so failures are not masked by known benign output.

### Completed this session (2026-02-21)

- Homepage Phase 1: implemented 8-section mobile-first public homepage per spec
  - New files: `home.tokens.ts`, `home.types.ts`, `home.mock.ts`, `RoomsSection.tsx`, `SiteFooter.tsx`
  - Rewritten: `Hero.tsx`, `SearchCard.tsx`, `FilterChips.tsx`, `RoomCard.tsx`, `BottomNav.tsx`, `PromoBanner.tsx`, `ReviewsCarousel.tsx`, `HomePage.tsx`
  - Fixed 7 defects: C-01 (watermark), C-02 (flat colour), C-03 (no role=search), H-01 (Cuộn xuống), H-02 (duplicate CTA), H-03 (no pill), M-01/M-03 (layout)
  - Regression tests added in `HomePage.test.tsx` + `FilterChips.test.tsx` (14 + 4 tests)
  - Router: `PublicLayout` (HeaderMobile + BottomNav) added around `/`

### Next

- Dashboard Phase 5+: booking detail panel, admin actions (restore/force-delete trashed bookings)
- Admin pagination (currently returns page 1 only per tab)
- Re-run verification command set after backend/frontend behavior changes.
- Re-check booking overlap and soft-delete semantics when booking migrations change.
- Refresh branch and CI snapshot lines after each PR merge or commit batch.

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
- Docs sync: COMPACT, WORKLOG, README, frontend/* all updated to reflect actual code state
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
- Dashboard Phase 5+: Booking detail panel, admin actions (restore/force-delete trashed bookings)
- Pagination for admin tabs (currently V1 returns page 1 only)
- PWA / offline support
- Full i18n (currently Vietnamese strings are inline, not i18n-managed)

## 2026-02-25 — Fix GET /api/v1/locations 500

- Root cause: Two Vite config files existed (`vite.config.js` + `vite.config.ts`). Vite loads `.js` first — so `vite.config.js` was the active config. That file had the proxy target as `http://backend:8000` (Docker-only hostname, ENOTFOUND locally) → Vite returned 500. Backend was healthy throughout (200 OK confirmed directly). The `.ts` fix alone was insufficient.
- Fix: Changed proxy fallback target in **both** `vite.config.js` and `vite.config.ts` from `http://backend:8000` to `http://127.0.0.1:8000`. Changed `api.ts` BASE_URL fallback from `http://localhost:8000/api` to `/api` (proxy path, eliminates CORS on local dev). Cleared `VITE_API_URL` in `.env.example`.
- Files: `frontend/vite.config.js`, `frontend/vite.config.ts`, `frontend/src/shared/lib/api.ts`, `frontend/.env.example`, `docs/COMPACT.md`
- Gates: `tsc --noEmit` 0 errors ✅, `vitest run` 194/194 ✅ (backend not touched)
- Commits: `f53a3cb fix(frontend)`, `df8c706 docs(frontend)` + this commit
