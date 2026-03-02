# WORKLOG — Soleil Hostel (Append-only)

## 2026-03-02

- Change: Batch 3 backend quality + Batch 4 frontend hardening + full docs sync.
- Why: Systematic fix of 78-issue audit list (batches 3–4) + documentation alignment.
- Batch 3: Extracted HealthService from HealthController (464→~80 lines), extracted 4 FormRequests, installed PHPStan/Larastan (Level 5, baseline 151), added Contact endpoint tests (10) + Review model tests (9), removed debug /test route, removed custom CORS middleware.
- Batch 4: AbortController cleanup in RoomList/LocationList/BookingForm, vi.hoisted() auth mocks in LoginPage/RegisterPage tests, no-console ESLint rule with 8 files cleaned, RoomList.test.tsx (8 tests).
- Docs sync: Updated 10+ docs with current baselines (857/2430 backend, 226/21 frontend, 275 Pint).
- Verification: `php artisan test` 857/2430 ✅, `tsc --noEmit` 0 errors ✅, `vitest run` 226/21 ✅, `pint --test` 275 files ✅.
- Files updated: PROJECT_STATUS.md, BACKLOG.md, AGENTS.md, CLAUDE.md, docs/README.md, docs/KNOWN_LIMITATIONS.md, docs/COMMANDS_AND_GATES.md, docs/DEVELOPMENT_HOOKS.md, docs/COMPACT.md, docs/WORKLOG.md, docs/MIGRATION_GUIDE.md.

## 2026-03-01

- Change: DevSecOps Batch 1 (Docker/Redis/Caddy hardening, CI gates) + Batch 2 backend fixes (review purification, booking fillable, Stripe webhooks) + i18n + Cashier bootstrap.
- Why: Fix critical/high issues from comprehensive audit (C-01–C-04, H-01, H-03, H-10–H-14).
- Batch 1: Redis protected-mode, Caddy security headers (HSTS, CSP), non-root Docker, CI typecheck gate, pinned GitHub Actions, fixed hardcoded URLs.
- Batch 2: Fixed review FormRequest purification crash (C-01/C-02: validated→purify→validated infinite loop), added cancellation_reason to Booking $fillable (H-01), implemented Stripe webhook handlers (H-03).
- Other: minimatch CVE fix, Psalm return type fix, i18n test assertion updates.
- Verification: `php artisan test` 790/2245 ✅, `tsc --noEmit` 0 errors ✅, `vitest run` 218/20 ✅.
- Files: 30+ files across backend, frontend, infra, CI.

## 2026-02-28

- Change: Phase 5 audit clean-up (TD-002 comments, ship script) + 4-PR batch (OPS-001, PAY-001, I18N-001, TD-003).
- Why: Address tech debt, prepare infrastructure, bootstrap payment integration.
- TD-002: Translated Vietnamese comments to English across 13 PHP files. F-22 logged (Indonesian string).
- OPS-001: Created docker-compose.prod.yml, .env.production.example, frontend prod Dockerfile (nginx), Caddyfile with auto-HTTPS.
- PAY-001: Installed Laravel Cashier ^16.3, Billable trait, Stripe webhook handlers (3 events), 14 tests.
- I18N-001: 47 translation keys (en+vi), \_\_() in 5 controllers.
- TD-003: BookingFactory::expired(), cancelledByAdmin(), multiDay() methods.
- Verification: `php artisan test` 769/2192 ✅.

## 2026-02-27

- Change: FE-001 Booking Detail Panel, FE-002 Admin Actions, FE-003 Pagination, TD-001 Error Format, EncryptCookies regression tests.
- Why: Complete guest/admin dashboard features + standardize API error format.
- FE-001: BookingDetailPanel.tsx (click booking → modal, 14 tests).
- FE-002: Admin trashed restore + force-delete with ConfirmDialog (10 tests).
- FE-003: Paginated admin tabs with PaginationControls.
- TD-001: ApiResponse trace_id, unified exception handler (10 tests, 57 assertions).
- EncryptCookies: 9 regression tests for soleil_token cookie encryption exclusion.
- Verification: `php artisan test` 756/2171 ✅, `vitest run` 218/20 ✅.

## 2026-02-25

- Change: Frontend Phases 0-4 complete + full docs sync.
- Why: Implement guest/admin dashboard, wire SearchCard to locations API, polish BookingForm, fix deprecated endpoints, sync all docs.
- Phase 0: Lazy-loaded `DashboardPage` with role-based routing (admin → AdminDashboard, user → GuestDashboard).
- Phase 1: `GuestDashboard` — booking list with filter tabs (All/Upcoming/Past), cancel with `ConfirmDialog`, skeleton/empty/error states, toast on success/error. New files: `bookings/GuestDashboard.tsx`, `useMyBookings.ts`, `bookingViewModel.ts`, `booking.constants.ts`, `ConfirmDialog.tsx`.
- Phase 2: `SearchCard` wired to `GET /v1/locations`; navigates to `/locations/:slug?check_in=&check_out=&guests=`.
- Phase 3: `AdminDashboard` — 3 tabs (Đặt phòng/Đã xóa/Liên hệ), `useAdminFetch<T>` hook, `AdminBookingCard`, `ContactCard`. New files: `admin/AdminDashboard.tsx`, `admin.api.ts`, `admin.types.ts`.
- Phase 4: `BookingForm` — URL params pre-fill, Vietnamese UI; `booking.api.ts` `/v1/bookings`; `room.api.ts` `/v1/rooms`; removed `AvailabilityResponse` dead type; `vi.hoisted` fix in `BookingForm.test.tsx`.
- Verification: `npx vitest run` → 194 tests, 19 suites, 0 failures. `tsc --noEmit` → 0 errors.
- Docs updated: `docs/README.md`, `docs/COMPACT.md`, `docs/WORKLOG.md`, `docs/DEVELOPMENT_HOOKS.md`, `docs/frontend/README.md`, `docs/frontend/ARCHITECTURE.md`, `docs/frontend/APP_LAYER.md`, `docs/frontend/FEATURES_LAYER.md`, `docs/frontend/SERVICES_LAYER.md`, `docs/frontend/TESTING.md`.
- Git: committed on dev → pushed → merged --no-ff to main → pushed. All pre-push hooks passed.

## 2026-02-12

- Change: Added COMPACT memory snapshot and append-only WORKLOG; linked memory docs from docs index.
- Why: Preserve high-signal context across long AI sessions with low maintenance cost.
- Files: `docs/COMPACT.md`, `docs/WORKLOG.md`, `docs/README.md`.
- Verification: Confirmed target paths/invariants from repository docs and code references.
- Notes/Risks: Snapshot uses latest verified command results recorded on 2026-02-11; refresh after next verification run.
