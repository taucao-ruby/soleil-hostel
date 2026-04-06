# Soleil Hostel - Project Status

**Last Updated:** April 6, 2026
**Current Branch:** `dev`
**Latest Commit:** `30ad47e` — chore(backend): fix binary_operator_spaces in RoomSeeder

## Current Status: Repo Health Green

> Audits v1–v4 complete: 179 total findings, 179 resolved (100%).
> Batches 1–12 + DevSecOps + quality hardening + DB hardening + v3.1 stay domain complete.
> See [docs/AUDIT_2026_02_21.md](./docs/AUDIT_2026_02_21.md) for detailed audit history.

Gates (verified April 4, 2026):

- Backend tests: **re-verification required** (email verification tests heavily revised Apr 3; previous baseline 1047/2875)
- Frontend typecheck PASS: 0 errors (`cd frontend && npx tsc --noEmit`) — TS5103 fixed
- Frontend build PASS: `pnpm run build` exits 0 — verified Apr 4
- Frontend unit tests: **re-verification required** (previous baseline 261/25 — Mar 31)
- Compose config PASS (`docker compose config`)
- Pint style: **8 violations** in email-verification cluster (line_ending CRLF, braces_position, unary_operator_spaces, class_definition) — NOT at 0 (`cd backend && vendor/bin/pint --test`)
- PHPStan Level 5 PASS: **0 errors** (10 errors from Apr 3 new files resolved Apr 4)
- Psalm: Level 1, 0 blocking errors (4 errors from Apr 3 new files resolved Apr 4)

Open Findings: F-23 (MD lint, low), F-25 (CSRF path, low), F-26–F-62 (2026-03-20 audit — 36 open, F-48 resolved), **F-63–F-66** (2026-04-05 full-stack audit — 4 new: 1 High, 1 Medium, 2 Low)
Blocked Items: M-11 (migration squash — needs human approval)

Findings backlog: [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)
Previous audits: [docs/AUDIT_2026_02_21.md](./docs/AUDIT_2026_02_21.md)

---

## Overall Progress

```text
Backend (Laravel)  ██████████████████████░  99%
Frontend (React)   █████████████████████░░  97%
Testing            ██████████████████████  99%
Audits (v1–v4)     █████████████████████░ 100% ✅ 179/179
Quality batches    █████████████████████░ 100% ✅ Batches 1–12
DevSecOps          █████████████████████░ 100% ✅ Docker/Redis/Caddy + CI gates
Payment bootstrap  ██████████████░░░░░░░░  65% ✅ Cashier + webhooks (checkout UI pending)
Documentation      █████████████████████░  97%
Deployment         ███████████████░░░░░░░  60%
─────────────────────────────────────────────
Total Progress     █████████████████████░  93%
```

---

## Test Results Summary

### Backend (PHPUnit/Pest)

```text
1047 tests passed
2875 assertions
Duration: ~237s (verified March 31, 2026)
```

### Frontend (Vitest)

```text
261 tests passed (25 test files)
Duration: ~42s (verified March 31, 2026)
```

### E2E (Playwright)

```text
data-testid coverage added for targeted flows
Playwright remains scaffolded; app runtime required for execution
```

---

## Non-Blocking Warnings

- PHPUnit doc-comment metadata deprecation warnings; suite result remains PASS.
- Vitest `act(...)` and non-boolean DOM attribute warnings; suite result remains PASS.

---

## Key Documentation

| Document                                                       | Description                                       |
| -------------------------------------------------------------- | ------------------------------------------------- |
| [docs/README.md](./docs/README.md)                             | Documentation entry point                         |
| [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)         | Code issues backlog                               |
| [docs/COMMANDS_AND_GATES.md](./docs/COMMANDS_AND_GATES.md)     | Verified commands + CI gates                      |
| [docs/AI_GOVERNANCE.md](./docs/AI_GOVERNANCE.md)               | AI agent workflow                                 |
| [docs/agents/](./docs/agents/)                                 | Agent framework                                   |
| [AUDIT_REPORT.md](./AUDIT_REPORT.md)                           | Audit report (v1–v4)                              |
| [docs/OPERATIONAL_PLAYBOOK.md](./docs/OPERATIONAL_PLAYBOOK.md) | Operational runbooks                              |
| [docs/DOMAIN_LAYERS.md](./docs/DOMAIN_LAYERS.md)               | Four-layer operational domain model               |

---

## Development Commands

```bash
# Backend verification
cd backend && php artisan test

# Frontend verification
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run

# Compose validation
docker compose config
```

## Known Product Limitations (March 2026)

The following confirmed limitations affect operator-facing or guest-facing functionality. Each is a code-level defect requiring a code change to resolve; they are documented here so that contributors and operators are aware of current behavior.

| ID | Limitation | Impact | Remediation |
|----|-----------|--------|-------------|
| TL-01 | Admin booking screens (arrivals/departures, calendar) parse `response.data.data` but backend returns `data.bookings` + `data.meta` — bookings may not render correctly | High | Batch 1 |
| ~~TL-02~~ | ~~Admin booking list filters ignored server-side~~ | ~~High~~ | ✅ Fixed Mar 29 — `getAdminPaginated()` now applies all 7 filter params (check_in, check_out, status, location_id, search) |
| TL-03 | Booking form submits `number_of_guests` and `special_requests` but backend does not validate or persist these fields — guest data is silently discarded | Medium | Batch 2 |
| TL-04 | Admin sidebar links to `/admin/reviews` and `/admin/messages` are non-functional — routes not defined in frontend router | Low | Batch 2 |
| ~~TL-05~~ | ~~Moderator capability inaccessible via SPA (`/admin/*` routes redirected all non-admin users)~~ | ~~Medium~~ | ✅ Fixed Mar 29 — `AdminRoute.tsx` now accepts `minRole` prop (default `'moderator'`); room edit/new routes remain admin-only |

See also: `docs/PERMISSION_MATRIX.md` Table E for current moderator access surface.

---

## Q2 2026 Roadmap

| Feature                         | Priority | Notes                                                                                |
| ------------------------------- | -------- | ------------------------------------------------------------------------------------ |
| **Email Verification OTP Flow**     | ✅ Done  | Full-stack 6-digit code: EmailVerificationCodeService, EmailVerificationCode model + migration, controller, notification, listener, VerificationResult enum, EmailVerifyPage.tsx SPA (Apr 3) |
| **Location Room Availability Fix**  | ✅ Done  | `scopeWithRoomCounts` uses booking-based availability; LocationResource + LocationCard use `rooms_count` (Apr 3) |
| **PHPStan gate maintained**         | ✅ Done  | 10 errors introduced by new files Apr 3 — all resolved Apr 4 (0 errors, Level 5, no baseline) |
| **Psalm gate maintained**           | ✅ Done  | 4 errors in auth/service layer resolved Apr 4 (0 blocking, Level 1) |
| **Stripe Payment Integration**  | High     | Cashier bootstrapped, webhooks implemented; checkout session + payment UI pending    |
| **RBAC Hardening**              | ✅ Done  | Defense-in-depth, phases 1-3, moderator activation, mobile guard, password complexity (Mar 10-14) |
| **Booking Detail Panel**        | ✅ Done  | Guest read-only panel with 14 tests (Feb 27)                                         |
| **Admin Pagination**            | ✅ Done  | All 3 tabs paginated with Trước/Sau controls (Feb 27)                                |
| **Four-Layer Operational Domain** | ✅ Done | stays, room_assignments, service_recovery_cases, readiness, classification, deposit, settlement, escalation engine (Mar 20–23) |
| **Admin Booking Filters**       | ✅ Done  | 7 server-side filter params (check_in, check_out, status, location_id, search) with ILIKE (Mar 29) |
| **Moderator SPA Access**        | ✅ Done  | `AdminRoute.tsx` `minRole` prop; room edit/new remain admin-only (Mar 29)            |
| **Restore Path Integrity**      | ✅ Done  | `BookingService::restore()` in transaction with `FOR UPDATE`; TOCTOU race eliminated (Mar 29) |
| **Review Submission (Guest)**   | ✅ Done  | `ReviewForm.tsx` with star rating, Vietnamese UI, 403/422 handling (Mar 29)          |
| **PHPStan Level 5 Clean**       | ✅ Done  | 0 errors, no baseline, no ignores (was 151 pre-existing — Mar 21)                   |
| **RBAC Follow-ups (FU-1..5)**   | Medium   | Legacy test migration, coverage gaps, config verification — see PERMISSION_MATRIX.md |
| **E2E Test Suite (Playwright)** | Medium   | Scaffolded; blocked on stable staging environment                                    |
| **2FA (TOTP)**                  | Low      | Force-logout-all on 2FA enable already wired in `logoutAll()`; TOTP issuance pending |
| **Deployment Pipeline**         | Low      | Docker Compose validated; cloud target TBD                                           |

---

## Completed Work (Summary)

All audit and batch details are preserved in [AUDIT_REPORT.md](./AUDIT_REPORT.md) and [docs/WORKLOG.md](./docs/WORKLOG.md).

| Period | Work | Key Metrics |
|--------|------|-------------|
| Feb 9–11, 2026 | Audit v1 + v2 | 159 issues found, 159 resolved |
| Feb 21–23, 2026 | Audit v3 (docs) + v4 (code) | 20 findings, 20 resolved |
| Feb 27, 2026 | FE-001/002/003 + TD-001 | +34 frontend tests, +19 backend tests |
| Feb 28, 2026 | Phase 5 clean-up | TD-002, ship script, rollup CVE fix |
| Mar 1, 2026 | DevSecOps + Cashier + i18n | +21 backend tests |
| Mar 2, 2026 | Batch 3 (backend) + Batch 4 (frontend) | +67 backend, +8 frontend tests |
| Mar 5, 2026 | Stabilization | Composer/Pint/Psalm CI fixes, +14 backend tests |
| Mar 6, 2026 | Batch 9–12 + H-02/H-05/H-06/H-07 | +14 backend tests, PG test default |
| Mar 9, 2026 | Docs sync v5 + RBAC UX audit | COMPACT archived, frontend RBAC.md + RBAC_UX_AUDIT.md |
| Mar 10, 2026 | RBAC hardening (defense-in-depth) | +16 backend tests, PERMISSION_MATRIX.md, Gate::authorize in AdminBookingController |
| Mar 11, 2026 | RBAC phases 1-3: enforcement gaps, admin audit log, moderator activation | — |
| Mar 12, 2026 | Admin panel expansion (AdminLayout, room/booking/customer mgmt) + CI hygiene checks | — |
| Mar 13-14, 2026 | RBAC mobile guard, password complexity, EmailVerificationTest, CVE fixes (flatted/undici) | — |
| Mar 17, 2026 | DB hardening: FK delete policy hardening + CHECK constraints + DB tests | +53 backend tests (901→954), 3 migrations, 2 test files |
| Mar 20, 2026 | v3.1 stay domain: stays/room_assignments/service_recovery_cases + BackfillOperationalStays command | +35 backend tests (954→989), 3 migrations, 4 test files, 3 models, 9 enums, 3 factories |
| Mar 21, 2026 | v3.2 operations: room readiness, blockage resolver, financial ops | 1009 tests |
| Mar 21, 2026 | v3.3 static analysis: Psalm 35→0, PHPStan 151→0 (no baseline, no ignores) | 1037 tests |
| Mar 23, 2026 | v3.4 operational completion: readiness, classification, deposit lifecycle, settlement, escalation engine, OperationalDashboardService (16 metrics) | 1014 tests at that point |
| Mar 29, 2026 | Restore path integrity (Wave 1), admin filters (Wave 2), CSRF clarity (Wave 3), ReviewForm (Wave 4), governance docs (Wave 5) | +40 backend tests, +35 frontend tests; TL-02/TL-05 resolved |
| Mar 30, 2026 | picomatch ReDoS CVE fix (GHSA-c2c7-rcm5-vvqj), Pint cleanup (8 files), null-safe RoomResource fix, AGENT_LEARNINGS scaffold | — |
| Apr 3–4, 2026 | Email verification OTP flow (full-stack), location availability fix, concurrent booking HTTP 500 fix, mail view assets (infra), PHPStan 10→0 errors, Psalm 4→0 errors, TS5103 tsconfig fix, Pint style fix (3 files) | Merged to main Apr 4 (9756bba, 7 commits, 40 files, 1954+/403−) |
| Mar 31, 2026 | Docs sync v3: 5 confirmed docs updated, 9 findings patched (F-01, F-02, F-03, F-04, F-05, F-07, F-09) | — |

---

## Status Note

Audits v1 (61/61), v2 (98/98), v3 (14/14), v4 (6/6) complete. Findings F-01 through F-22 and F-24 resolved. F-23 open (low — MD lint). F-25 open (low — CSRF path). F-26–F-62 open (2026-03-20 audit, code findings — not fixed in docs pass).
RBAC hardening (Mar 10): defense-in-depth verified, PERMISSION_MATRIX.md created, 5 follow-ups open (FU-1..FU-5).
RBAC phases 1-3 (Mar 11): enforcement gaps closed, admin audit log, moderator activated.
Admin panel expansion (Mar 12): AdminLayout, sidebar, customer/room/booking management (39556d7); CI hygiene hooks.
RBAC mobile guard + password complexity (Mar 13-14): admin route guard on frontend, registration password rule.
CVE fix (Mar 14): flatted >=3.4.0, undici >=7.24.0 (ef138cc). Logout-401 investigation: no code bug (stale cookie).
DB hardening (Mar 17): FK CASCADE→SET NULL/RESTRICT on 4 FKs (bookings.user_id, bookings.room_id, reviews.user_id, reviews.room_id). CHECK constraints added: chk_rooms_max_guests, chk_bookings_status. All PG-only, runtime-gated. 954 tests, 0 failures.
v3.1 stay domain (Mar 20): four-layer operational model (stays, room_assignments, service_recovery_cases). BackfillOperationalStays command. Booking.stay() hasOne relationship added. 989 tests, 0 failures.
v3.2 operations (Mar 21): room readiness, blockage resolver, financial ops. 1009 tests.
v3.3 static analysis (Mar 21): Psalm 35→0, PHPStan 151→0 (no baseline, no ignores). 1037 tests.
v3.4 operational completion (Mar 23): readiness, classification, deposit, settlement, escalation engine, OperationalDashboardService. 1014 tests at that point.
Restore path integrity + product completeness (Mar 29): 5-wave execution — restore TOCTOU fix, admin booking filters, CSRF clarity, ReviewForm.tsx, governance docs. TL-02 and TL-05 resolved.
picomatch ReDoS CVE fix (Mar 30): GHSA-c2c7-rcm5-vvqj via pnpm overrides.
Docs sync v3 (Mar 31): 9 findings patched across 5 canonical docs. Backend 1047 tests, frontend 261 tests confirmed.
Findings backlog: [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)
