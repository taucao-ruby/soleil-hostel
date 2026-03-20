# Soleil Hostel - Project Status

**Last Updated:** March 17, 2026
**Current Branch:** `dev`
**Latest Commit:** `81df6c9` — docs: sync documentation after DB hardening pass

## Current Status: Repo Health Green

> Audits v1–v4 complete: 179 total findings, 179 resolved (100%).
> Batches 1–12 + DevSecOps + quality hardening + DB hardening complete.
> See [AUDIT_REPORT.md](./AUDIT_REPORT.md) for detailed audit history.

Gates (verified March 17, 2026):

- Backend tests PASS: **954 tests**, **2596 assertions** (`cd backend && php artisan test`)
- Frontend typecheck PASS: 0 errors (`cd frontend && npx tsc --noEmit`)
- Frontend unit tests PASS: **21 files**, **226 tests** (`cd frontend && npx vitest run`)
- Compose config PASS (`docker compose config`)
- Pint style PASS: **283 files**, 0 violations (`cd backend && vendor/bin/pint --test`)
- PHPStan Level 5 installed with Larastan (baseline: 151 pre-existing errors)
- Psalm: 0 blocking errors (v1 routes)

Open Findings: 1 (F-23 — MD lint, low)
Blocked Items: M-11 (migration squash — needs human approval)

Findings backlog: [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)
Previous audits: [AUDIT_REPORT.md](./AUDIT_REPORT.md) | [docs/AUDIT_2026_02_21.md](./docs/AUDIT_2026_02_21.md)

---

## Overall Progress

```text
Backend (Laravel)  ██████████████████████░  99%
Frontend (React)   ████████████████████░░  96%
Testing            ██████████████████████  99%
Audits (v1–v4)     █████████████████████░ 100% ✅ 179/179
Quality batches    █████████████████████░ 100% ✅ Batches 1–12
DevSecOps          █████████████████████░ 100% ✅ Docker/Redis/Caddy + CI gates
Payment bootstrap  ██████████████░░░░░░░░  65% ✅ Cashier + webhooks (checkout UI pending)
Documentation      ████████████████████░░  95%
Deployment         ███████████████░░░░░░░  60%
─────────────────────────────────────────────
Total Progress     █████████████████████░  92%
```

---

## Test Results Summary

### Backend (PHPUnit/Pest)

```text
954 tests passed
2596 assertions
Duration: ~210s (verified March 17, 2026)
```

### Frontend (Vitest)

```text
226 tests passed (21 test files)
Duration: ~30s (verified March 11, 2026)
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
| [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)         | Code issues backlog (24 items, 23 fixed, 1 open)  |
| [docs/COMMANDS_AND_GATES.md](./docs/COMMANDS_AND_GATES.md)     | Verified commands + CI gates                      |
| [docs/AI_GOVERNANCE.md](./docs/AI_GOVERNANCE.md)               | AI agent workflow                                 |
| [docs/agents/](./docs/agents/)                                 | Agent framework                                   |
| [AUDIT_REPORT.md](./AUDIT_REPORT.md)                           | Audit report (v1–v4)                              |
| [docs/OPERATIONAL_PLAYBOOK.md](./docs/OPERATIONAL_PLAYBOOK.md) | Operational runbooks                              |

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

## Q2 2026 Roadmap

| Feature                         | Priority | Notes                                                                                |
| ------------------------------- | -------- | ------------------------------------------------------------------------------------ |
| **Stripe Payment Integration**  | High     | Cashier bootstrapped, webhooks implemented; checkout session + payment UI pending    |
| **RBAC Hardening**              | ✅ Done  | Defense-in-depth, phases 1-3, moderator activation, mobile guard, password complexity (Mar 10-14) |
| **Booking Detail Panel**        | ✅ Done  | Guest read-only panel with 14 tests (Feb 27)                                         |
| **Admin Pagination**            | ✅ Done  | All 3 tabs paginated with Trước/Sau controls (Feb 27)                                |
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

---

## Status Note

Audits v1 (61/61), v2 (98/98), v3 (14/14), v4 (6/6) complete. Findings F-01 through F-22 and F-24 resolved. F-23 open (low — MD lint).
RBAC hardening (Mar 10): defense-in-depth verified, PERMISSION_MATRIX.md created, 5 follow-ups open (FU-1..FU-5).
RBAC phases 1-3 (Mar 11): enforcement gaps closed, admin audit log, moderator activated.
Admin panel expansion (Mar 12): AdminLayout, sidebar, customer/room/booking management (39556d7); CI hygiene hooks.
RBAC mobile guard + password complexity (Mar 13-14): admin route guard on frontend, registration password rule.
CVE fix (Mar 14): flatted >=3.4.0, undici >=7.24.0 (ef138cc). Logout-401 investigation: no code bug (stale cookie).
DB hardening (Mar 17): FK CASCADE→SET NULL/RESTRICT on 4 FKs (bookings.user_id, bookings.room_id, reviews.user_id, reviews.room_id). CHECK constraints added: chk_rooms_max_guests, chk_bookings_status. All PG-only, runtime-gated. 954 tests, 0 failures.
Findings backlog: [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)
