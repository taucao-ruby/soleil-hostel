# WORKLOG — Soleil Hostel (Append-only)

## 2026-03-21

### v3.2 — Operational services (room readiness + blockage resolver)

- Change: RoomReadinessService, CheckInBlockageResolver, StayObserver, RoomAssignmentObserver, RoomObserver — front-desk 4-step blockage escalation (equivalent swap → complimentary upgrade → internal relocation → external escalation).
- Tests: 20 targeted tests passed, 4 source-grounded skips. Backend: 1009/2721 PASS (+20 from 989).
- Merged dev → main (`bb3332e`), synced main → dev (`3f59d86`).

### v3.3 — Static analysis clean pass (Psalm + PHPStan)

- Change: Resolved all static analysis errors with zero behavior changes, zero `@phpstan-ignore` annotations, no baseline generation.
- Psalm (Level 1): 35 blocking errors → 0. Files: `BackfillOperationalStays.php`, `RoomAssignmentObserver.php`, `RoomObserver.php`, `StayObserver.php`, `CheckInBlockageResolver.php`.
- PHPStan (Level 5, Larastan): 151 pre-existing errors → 0. Files: same 5 + `RoomReadinessService.php`, `RoomAssignment.php` (docblock), `Stay.php` (docblock).
- Key fixes:
  - `firstOrCreate()` array destructuring → `$model->wasRecentlyCreated` property
  - `instanceof SomeEnum always true` guards removed (cast already guarantees type)
  - `RoomObserver::creating()` always-true guard → `array_key_exists('readiness_status', $room->getAttributes())` (behavior-safe: attribute not set at create time)
  - Psalm/PHPStan dual-tool conflict on `assert($x !== null)` after `isNotEmpty()` → replaced with `/** @var Room $x */` annotation (satisfies both tools)
  - `@property-read Room|null $room` added to `RoomAssignment` docblock
  - `@property-read RoomAssignment|null $currentRoomAssignment` added to `Stay` docblock
  - Missing `use App\Models\RoomAssignment` import added to `RoomReadinessService`
  - `currentStatus()` simplified: removed unreachable instanceof branch
- Tests: 1037/2803 PASS (no regressions; 48 net new tests vs March 20 baseline).
- Commits: `440496d` (Psalm+PHPStan fixes), `b52501a` (GitNexus reindex), `bb3332e` (merge to main), `3f59d86` (sync to dev).

## 2026-03-20

- Change: v3.1 remediation — four-layer operational domain model + docs sync.
- Code: 3 new migrations (`2026_03_20_000001` stays, `2026_03_20_000002` room_assignments, `2026_03_20_000003` service_recovery_cases). 3 new models (`Stay`, `RoomAssignment`, `ServiceRecoveryCase`). 9 new enums (`StayStatus`, `AssignmentType`, `AssignmentStatus`, `IncidentType`, `IncidentSeverity`, `CaseStatus`, `CompensationType`). 3 new factories. `BackfillOperationalStays` Artisan command. `Booking.stay()` hasOne relationship added to `Booking.php`. `BookingService::confirmBooking()` lazy stay creation hook.
- Tests: `StayInvariantTest.php` (8 tests), `StayBackfillTest.php` (7 tests), `RoomAssignmentTest.php` (9 tests), `ServiceRecoveryCaseTest.php` (11 tests). Backend: 989/2677 PASS (+35 from 954).
- Docs: DOMAIN_LAYERS.md created (two-path strategy section). DB_FACTS.md updated (operational domain tables). ARCHITECTURE_FACTS.md updated (stay domain section, Booking model relationships, test count 954→989). PROJECT_STATUS.md updated (test counts, stay domain row). COMPACT.md updated (test baseline). COMMANDS.md + COMMANDS_AND_GATES.md updated (stays:backfill-operational command). WORKLOG.md updated.

## 2026-03-17

- Change: DB hardening pass — FK delete policy hardening + CHECK constraints.
- Migrations: `2026_03_17_000001` (4 FKs: bookings.user_id CASCADE→SET NULL, bookings.room_id CASCADE→RESTRICT, reviews.user_id CASCADE→SET NULL, reviews.room_id CASCADE→SET NULL), `2026_03_17_000002` (chk_rooms_max_guests), `2026_03_17_000003` (chk_bookings_status). All PG-only, runtime-gated.
- Tests: `FkDeletePolicyTest.php` (5 tests), `CheckConstraintTest.php` (3 tests). Backend: 954/2596 PASS.
- Closeout: reviews.user_id original was CASCADE (not SET NULL). Gating standardized to `DB::getDriverName()`.
- Deferred: rooms.status DB CHECK (no stable enum), legacy migration 2026_02_09_000000 gating cleanup.
- Docs sync: DATABASE.md, DB_FACTS.md, ARCHITECTURE_FACTS.md, PROJECT_STATUS.md, BACKLOG.md, COMPACT.md, WORKLOG.md, booking-integrity.md, migrations-postgres-skill.md updated.

## 2026-03-14

- Change: Docs sync v7 — 5-batch truth-alignment pass in worktree `claude/magical-bardeen`.
- Batch 1 (RBAC surface): Fixed moderator access rows A7/A8/A9 (DENIED→ALLOWED), Table B booking:view-all, Contradiction C1 (LATENT-SHADOWED→LATENT), C2/C6; updated RBAC.md moderator capability labels; POLICIES.md: added ReviewPolicy overview entry, fixed viewAny note. Source: v1.php line 57 (`role:moderator`), AdminBookingController (`Gate::authorize('view-all-bookings')`).
- Batch 2 (Reviews domain): Added `booking_id` to REVIEWS.md schema/fillable/relations/form request; removed 4 phantom endpoints (`GET /rooms/{room}/reviews`, `GET /reviews/{id}`, `POST /reviews/import`, `GET /reviews/audit`); fixed `is_approved` → `approved` (9 occurrences). Source: 5 review migrations, Review model, ReviewPolicy.
- Batch 3 (Path prefix): Normalized `/api/` → `/api/v1/` in BOOKING.md (11 paths), ROOMS.md (7 paths), AUTHENTICATION.md (2 paths); added `guest_email` to create-booking example. Auth/email paths marked UNVERIFIED (not in v1.php).
- Batch 4 (Frontend inventory): TESTING.md: 19→21 files, 194→226 tests, removed phantom `booking.constants.test.ts`. FEATURES_LAYER.md: removed `booking.constants.ts` (absent), added `AdminRoute.tsx`/`AdminSidebar.tsx`/`BookingDetailPanel.tsx`/`BookingDetailPage.tsx`; cleaned cross-feature deps and "What Does NOT Exist".
- Batch 5 (Metadata hygiene): ARCHITECTURE_FACTS.md: `target_type`/`target_id` → `resource_type`/`resource_id` (migration-verified), ReviewController IMPLEMENTED label added. COMPACT.md: commit hash `ef138cc` → `d6fc4db`. AUDIT_2026_03_12_STRUCTURE.md: snapshot note added. FINDINGS_BACKLOG.md: F-24 / F-25 ordering corrected.
- Files changed (13): PERMISSION_MATRIX.md, RBAC.md, POLICIES.md, REVIEWS.md, BOOKING.md, ROOMS.md, AUTHENTICATION.md, TESTING.md, FEATURES_LAYER.md, ARCHITECTURE_FACTS.md, COMPACT.md, AUDIT_2026_03_12_STRUCTURE.md, FINDINGS_BACKLOG.md.
- Verification: docs-only task — no gate runs required.

## 2026-03-11

- Change: Docs sync v6 — truth-alignment pass after RBAC hardening (commit `012ce40`, Mar 10).
- Why: Backend tests grew from 885→901 (+16 RBAC tests, 2487→2510 assertions). PERMISSION_MATRIX.md created with 5 open follow-ups (FU-1..FU-5). PROJECT_STATUS, PRODUCT_GOAL, BACKLOG, COMPACT, WORKLOG, README all needed refresh.
- Files: PROJECT_STATUS.md, PRODUCT_GOAL.md, BACKLOG.md, docs/COMPACT.md, docs/WORKLOG.md, docs/README.md.
- Verification: `php artisan test` 901/2510 PASS, `tsc --noEmit` 0 errors, `vitest run` 226/21 PASS, `pint --test` 283 files PASS — all verified 2026-03-11.

## 2026-03-10

- Change: RBAC hardening — defense-in-depth for admin booking + room CUD routes.
- Why: Add Gate::authorize('admin') to AdminBookingController methods, add `role:admin` middleware to v1 room CUD routes. Create PERMISSION_MATRIX.md as canonical RBAC source of truth.
- Files: AdminBookingController.php, v1.php, BookingSoftDeleteTest.php (+new), BookingCancellationTest.php (+new), RoomAuthorizationTest.php (+new), docs/PERMISSION_MATRIX.md (+new), ARCHITECTURE_FACTS.md, POLICIES.md, backend RBAC.md, frontend RBAC.md, CLAUDE.md, .gitignore.
- Verification: 901 backend tests PASS (pre-push hook).

## 2026-03-09

- Change: Audit v5 — repo-wide truth-alignment pass. Refresh PROJECT_STATUS, PRODUCT_GOAL, BACKLOG, COMPACT; archive COMPACT history; fix stale test counts across 5 files; mark F-24 resolved.
- Why: Test counts drifted (871→885 backend, 2449→2487 assertions, 280→283 Pint). F-24 resolved but still marked Open. COMPACT at 1234 lines, violating archive policy.
- Files: PROJECT_STATUS.md, PRODUCT_GOAL.md, BACKLOG.md, docs/FINDINGS_BACKLOG.md, docs/COMPACT.md, docs/WORKLOG.md, docs/README.md, docs/COMMANDS_AND_GATES.md.

## 2026-03-06

- Change: Batches 9–12 + H-02 (Eloquent token creation) + H-05 (ReviewController + 14 tests) + H-06 (phpunit.xml → PostgreSQL default) + H-07a/b (Vietnamese copy).
- Why: Resolve high/medium findings from audit backlog.
- Verification: `php artisan test` 885/2487 ✅, `tsc --noEmit` 0 errors ✅, `vitest run` 226/21 ✅, `pint --test` 283 files ✅.

## 2026-03-05

- Change: Fix composer.lock PHP version mismatch (Symfony 8.x→7.4.x), fix Pint new_with_parentheses, fix Psalm JIT fatal in CI.
- Why: Stabilize CI — Symfony 8.x required PHP 8.4 but runtime targets PHP 8.3.
- Verification: `php artisan test` 871/2449 ✅, `pint --test` 280 files ✅, `docker compose config` ✅.

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

## 2026-02-26

- Change: Auth redirect loop fix (AuthContext response shape), EncryptCookies soleil_token exclusion fix, rollup CVE override.
- Why: Fix 401 on all cookie-auth requests (encrypted cookie → hash mismatch), fix auth context extraction path.
- Verification: `php artisan test` 737/737 ✅, `vitest run` 194/194 ✅.

## 2026-02-23

- Change: Audit v4 remediation (4 batches: CI hardening, env cleanup, frontend cleanup, docs sync) + Dashboard Phase 0-1 (lazy DashboardPage, GuestDashboard with booking list/filter/cancel).
- Why: Resolve 6 audit v4 findings + deliver guest dashboard MVP.
- Verification: `tsc --noEmit` 0 errors ✅, `vitest run` 157/157 ✅.

## 2026-02-21

- Change: Repo-wide docs audit (v3) — created agent framework (CONTRACT, ARCHITECTURE_FACTS, COMMANDS), governance docs (AI_GOVERNANCE, MCP, HOOKS, COMMANDS_AND_GATES), logged 14 findings to FINDINGS_BACKLOG.
- Why: Establish structured governance for AI agents.
- Files: 10+ new/updated docs in `docs/agents/`, `docs/`.

## 2026-02-12

- Change: Added COMPACT memory snapshot and append-only WORKLOG; linked memory docs from docs index.
- Why: Preserve high-signal context across long AI sessions with low maintenance cost.
- Files: `docs/COMPACT.md`, `docs/WORKLOG.md`, `docs/README.md`.
- Verification: Confirmed target paths/invariants from repository docs and code references.

## 2026-03-14

- Investigation: logout-httponly 401 resolved — root cause was stale `soleil_token` cookie from old test users. No code bug. Curl + browser both confirm login→me→logout all 200.
- Cleanup: removed debug files (`setup_roles.php`, `Temp*.txt`) from worktree.
- Test accounts created in `soleil_test` DB: user@soleil.test / admin@soleil.test / moderator@soleil.test — all `P@ssworD123`.
- Docs: F-25 logged (api.ts refresh CSRF path wrong — non-critical).
- Merge: `claude/strange-raman` → `dev` (--no-ff). COMPACT.md updated. All docs synced.
- Verification: gates not re-run (docs-only session); last known state 901 BE + 226 FE tests passing.

## 2026-03-13

- feat(frontend): RBAC mobile remediation — `AdminRouteGuard` protecting admin routes; non-admin redirect to dashboard.
- feat(backend): password complexity enforcement on registration — `StrongPassword` rule, uppercase + lowercase + digit + special char required.
- test(backend): `EmailVerificationTest` updated to use complex passwords matching new rule.
- Commit: `c5bd49a` (mobile guard) + `9fcb657` (password complexity) + `b97dfe1` (test update).

## 2026-03-12

- chore(infra): remove tracked build artifacts + normalize frontend toolchain (`de333f5`).
- ci(infra): hygiene CI checks (pre-commit hook, artifact guard) — `b8b36fd`.
- docs: 2026-03-12 repository structure audit report (`AUDIT_2026_03_12_STRUCTURE.md`).
- feat(frontend): add AdminLayout, sidebar navigation, room/booking/customer management panels, BookingCancelDialog, user-facing booking list + detail pages (`39556d7`).
- Backend: `CustomerController` + `CustomerService` for admin guest view.
- test(frontend): rewrite `AdminDashboard.test.tsx` to match updated component (`e0fc819`).
- fix(frontend): correct toast import paths (`7da79a0`).
- refactor(backend): code style fixes via Pint (`371b822`).
- fix(backend): suppress Psalm `PossiblyInvalidMethodCall` for Laravel routes (`38c0427`).
- fix(backend): force in-memory fallback in `RateLimitService` unit tests (`479c31e`).

## 2026-03-11

- feat(backend): RBAC phases 1-3 — enforcement gaps, admin audit log, moderator activation (`205ecf0`).
  - Phase 1: `role:admin` middleware on legacy room CUD routes; Gate::authorize on ContactController.
  - Phase 2: `admin_audit_logs` table (append-only), `AdminAuditService`, integrated into 3 controllers.
  - Phase 3: Moderator role activation — split booking routes read (moderator+) vs write (admin-only).
- docs: project docs update after RBAC hardening phases 1-3 (`1b36149`).
- Verification: `php artisan test` 901 / 2510 ✅, `vitest run` 226 ✅ (baseline from this session).
