# Soleil Hostel - Project Status

**Last Updated:** May 8, 2026
**Current Branch:** `dev`
**Latest Commit:** `6372d7f` — fix(backend): graceful degradation in FeatureFlag::forget() + Redis-free harness test (HEAD as of 2026-05-08)

## Current Status: Repo Health Green

> Audits v1–v4 complete: 179 total findings, 179 resolved (100%).
> Batches 1–12 + DevSecOps + quality hardening + DB hardening + v3.1 stay domain + AI Harness Phases 0–4 + AI proposal lifecycle hardening (Apr–May).
> See [docs/AUDIT_2026_02_21.md](./docs/AUDIT_2026_02_21.md) for detailed audit history.

Gates (verified at the documentation layer May 8, 2026; runtime gate re-verification required after the Apr 19 → May 8 batches):

- Backend tests: **re-verification required** — Mar 31 baseline 1047/2875; intermediate run May 3 reported 1414/4110 PASS at `b69a7a0` (per `docs/COMPACT.md`). Apr 19 → May 8 added: AI proposal lifecycle (`5a295c0`), HMAC audit + PII hard-block (`e588432`), AI-001 prompt-injection defense (`347649a`), no-overlap constraint hardening (`92f1ad1`), immutable actor snapshots (`048e40b`), RefundIdempotencyTest (`abc3959`), BookingPaymentHoldTest (`ae2d070`), StateMachineInvariantTest (`ac7275b`), Batch-2 Sanctum hardening (`5e258e7`), OPS-004 stay cancellation (`7027adb`), CONC-005/006 deposit FSM (`b69a7a0`), AUTH-004 OTP race (`1079946`), batch-8 kill-switch (`c5a37dc`); May 5–8 added Redis-free `AiHarnessDisabledTest` (`6372d7f`/`2ab45ae`) + Stripe charge type guard (`1441edb`).
- Frontend typecheck PASS: 0 errors (`cd frontend && npx tsc --noEmit`) — TS5103 fixed
- Frontend build PASS: `pnpm run build` exits 0
- Frontend unit tests: **re-verification required** — Mar 31 baseline 261/25 files; current file count 39 (post-Apr feature batches).
- Compose config PASS (`docker compose config`) — host-env shadowing hardened (`093f5ae`); REDIS_PASSWORD placeholder injected (`fd796cf`); Redis auth enforced in non-local envs (`1737970`).
- Pint style: **re-verification required** (AI proposal events + cancellation actor snapshot added)
- PHPStan Level 5: **re-verification required** (AI harness lifecycle expanded; refactor of TransactionExceptions hierarchy `746a5bf`)
- Psalm: **re-verification required** (cancellationActorSnapshot type contracts hardened `e68f40f` / `842e64a` / `98fbe93`)
- AI eval gate: `php artisan ai:eval --all-phases` — nightly CI at 03:00, blocks deploy on failure
- OpenAPI contract lint (Spectral): CI gate `contract-lint.yml` added 2026-04-17 (`4a33755`); blocks on `docs/api/openapi.yaml` or `.spectral.yaml` changes
- E2E smoke gate (new — `c5a37dc`): batch-8 added a frontend smoke gate to CI manifests as part of kill-switch hardening

Open Findings: F-23 (MD lint, low), F-25 (CSRF path, low), F-26–F-62 (2026-03-20 audit — 36 open, F-48 resolved), F-63–F-66 (2026-04-05 audit — 4 findings)
Security Resolved (2026-04-18): T-13 — proposer-binding (F-06) now enforced at `ProposalConfirmationController::decide` via cache-envelope `proposer_user_id`; see `docs/THREAT_MODEL_AI.md`. Supersedes the 2026-04-12 "Accepted" posture.
Security Resolved (2026-04-22 → 2026-05-02): F-32 (Sanctum `findToken()` for Bearer lookup in `detectAuthMode`), AI-001 (policy-document prompt-injection defense), RBAC-001 (contact messages locked to admin via `ContactMessagePolicy`), OBS-001 + OBS-002 (admin-gated detail health probes; topology no longer leaked to anonymous callers), PII redaction across all log channels and Sentry (`cb7911a`), Stripe webhook idempotency moved to durable `stripe_refund_events` UNIQUE — TOCTOU window eliminated (`abc3959`), AUTH-004 OTP resend race-hardened (`1079946`).
Blocked Items: M-11 (migration squash — needs human approval)

Findings backlog: [docs/FINDINGS_BACKLOG.md](./docs/FINDINGS_BACKLOG.md)
Previous audits: [docs/AUDIT_2026_02_21.md](./docs/AUDIT_2026_02_21.md)

---

## Overall Progress

```text
Backend (Laravel)        █████████████████████░  99%
Frontend (React)         █████████████████████░  97%
Testing                  ██████████████████████  99%
Audits (v1–v4)           █████████████████████░ 100% ✅ 179/179
Quality batches          █████████████████████░ 100% ✅ Batches 1–12
DevSecOps                █████████████████████░ 100% ✅ Docker/Redis/Caddy + CI gates + E2E smoke gate
AI Harness (Phases 0–4)  █████████████████████░ 100% ✅ 7 endpoints, durable proposal lifecycle, kill-switch hardening (batch-8), HMAC audit, PII hard-block, prompt-injection defense (AI-001)
Booking integrity        █████████████████████░ 100% ✅ FSM invariants, no-overlap constraint hardening, immutable actor snapshots, deposit FSM (CONC-005/006), stay cancellation propagation (OPS-004)
Auth hardening           █████████████████████░ 100% ✅ Batch-2 Sanctum (atomic refresh, fingerprint binding), F-32 Bearer lookup, AUTH-004 OTP race
Payment bootstrap        ████████████████░░░░░░  75% ✅ Cashier + payment-hold + durable refund-event idempotency (TOCTOU eliminated). Checkout UI still pending.
Documentation            █████████████████████░  99%
Deployment               ███████████████░░░░░░░  60%
─────────────────────────────────────────────
Total Progress           █████████████████████░  96%
```

---

## Test Results Summary

> **Last verified suite run: March 31, 2026.** Apr–May commits added new tests (AI proposal lifecycle, RefundIdempotencyTest, BookingPaymentHoldTest, BookingStateMachineInvariantTest, ConcurrentBookingTest, AiProposalEventActorPreservationTest, deposit FSM tests, OPS-004 cancellation propagation tests, AUTH-004 OTP race tests). Re-run before next merge to `main`.

### Backend (PHPUnit/Pest)

```text
Mar 31 baseline: 1047 tests passed / 2875 assertions / ~237s
Test files on disk: 141 (includes new suites in tests/Feature/AiHarness/, tests/Feature/Stays/, tests/Feature/Payment/, tests/Feature/Database/)
Apr–May delta: re-verification required
```

### Frontend (Vitest)

```text
Mar 31 baseline: 261 tests passed / 25 files / ~42s
Test files on disk: 39 (added admin/, bookings/, locations/, assistant/ suites)
Apr–May delta: re-verification required
```

### E2E (Playwright)

```text
Scaffolded under frontend/tests/e2e/ — 4 flows: guest-booking, payment-webhook, ai-proposal, admin-restore.
E2E smoke gate added to CI manifests via batch-8 (c5a37dc); full suite still gated behind workflow_dispatch.
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
| [docs/HARNESS_ENGINEERING.md](./docs/HARNESS_ENGINEERING.md)   | AI Harness architecture and engineering guide      |
| [docs/THREAT_MODEL_AI.md](./docs/THREAT_MODEL_AI.md)           | AI-specific threat model (Phases 1–4)             |
| [docs/EVAL_STRATEGY.md](./docs/EVAL_STRATEGY.md)               | AI eval framework and regression gates            |
| [docs/ROLLOUT_AND_KILL_SWITCH.md](./docs/ROLLOUT_AND_KILL_SWITCH.md) | AI rollout checklist and kill switch procedure |

---

## Development Commands

```bash
# Backend verification
cd backend && php artisan test

# Frontend verification
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run

# AI harness regression gate
cd backend && php artisan ai:eval --all-phases

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
| **AI Harness Phases 0–4**           | ✅ Done  | 7 endpoints `/v1/ai/*`, 2 tables, 3 middleware, 7-layer safety pipeline, kill switch, canary routing, eval framework (`ai:eval`), proposal confirmation (Apr 9–11) |
| **AI Harness hardening (batch-8)**  | ✅ Done  | Durable AI proposal lifecycle + drift detection + proposer-binding (`5a295c0`); HMAC audit + PII hard-block + injection-bypass prevention (`e588432`); AI-001 policy-document prompt-injection defense (`347649a`); kill-switch hardening + E2E smoke gate + CI manifests (`c5a37dc`); AUTH-004 OTP race (`1079946` + test fix `10b153e`) |
| **Booking integrity hardening**     | ✅ Done  | Booking state-machine invariants + idempotent Stripe webhook (`ac7275b`); durable `stripe_refund_events` UNIQUE replaces in-memory `IdempotencyGuard` (`abc3959`); payment-hold on creation (`ae2d070`); immutable actor snapshots on bookings + admin_audit_logs (`048e40b`); no-overlap constraint hardening + pre-deploy gate (`92f1ad1`); deposit FSM + null-user reconciliation (`b69a7a0`); stay cancellation propagation (`7027adb`) |
| **Email Verification OTP Flow**     | ✅ Done  | Full-stack 6-digit code: EmailVerificationCodeService, EmailVerificationCode model + migration, controller, notification, listener, VerificationResult enum, EmailVerifyPage.tsx SPA (Apr 3); resend race-hardened (AUTH-004, May 2) |
| **Location Room Availability Fix**  | ✅ Done  | `scopeWithRoomCounts` uses booking-based availability; LocationResource + LocationCard use `rooms_count` (Apr 3) |
| **PHPStan gate maintained**         | ✅ Done  | 10 errors introduced by new files Apr 3 — all resolved Apr 4 (0 errors, Level 5, no baseline) |
| **Psalm gate maintained**           | ✅ Done  | 4 errors in auth/service layer resolved Apr 4 (0 blocking, Level 1) |
| **Stripe Payment Integration**  | Medium   | Cashier + webhooks done; payment-hold + durable refund-event idempotency landed (Apr 22). Checkout session + payment UI pending — moved off "High" because backend is no longer a blocker; only frontend work remains. |
| **Observability hardening (OBS-001/OBS-002)** | ✅ Done  | Detailed health endpoints gated behind authenticated admin (no topology leak); PII redaction across all log channels and Sentry; Stripe origin pinning in CSP + Caddyfile (Apr 24) |
| **RBAC-001 contact-message lockdown** | ✅ Done  | `ContactMessagePolicy` enforces admin-only access; legacy moderator path closed (Apr 26) |
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
| Apr 9–11, 2026 | AI Harness Phases 0–4: foundation, provider abstraction, FAQ pipeline, 7-layer safety, proposal confirmation | 50+ backend files, 2 migrations, 7 endpoints, 3 middleware, eval framework |
| Apr 12, 2026 | Documentation governance audit + full docs sync for AI Harness | 10 docs updated (ARCHITECTURE_FACTS, PERMISSION_MATRIX, CONTRACT, DB_FACTS, DATABASE, openapi.yaml, THREAT_MODEL_AI, COMMANDS, COMPACT, WORKLOG) |
| Apr 13–17, 2026 | F-06 proposer-binding + F-01/F-02/F-03 remediations + deploy workflow hardening + OpenAPI contract lint gate | 11 commits (`e6673dd`→`1deaf8e`): proposal decide throttle 10→5, proposer-binding at cache envelope, CancellationService service-layer ownership gate, F-04 pre-flight DEPLOY_HOST gate, migration-before-health reordering, `contract-lint.yml` Spectral workflow |
| Apr 18, 2026 | Documentation governance remediation pass — docs aligned with post-F-06 code truth | 11 docs updated: ARCHITECTURE_FACTS (TTL/proposer-binding/defense-in-depth invariants), PERMISSION_MATRIX (A14 throttle 10→5, BR-1/BR-2 cross-refs), THREAT_MODEL_AI (T-13 Accepted→Mitigated, V-5 residual risk removed), CONTRACT + COMMANDS_AND_GATES (Spectral gate), OPERATIONAL_PLAYBOOK (F-04 runbook, pending-backlog runbook, migration ordering note), ROLLOUT_AND_KILL_SWITCH (TTL=0 implicit kill switch), backend/.env.example + backend/.env.production.example (BOOKING_PENDING_TTL_MINUTES, BOOKING_PENDING_EXPIRY_BATCH_SIZE), PROJECT_STATUS, COMPACT, WORKLOG |
| Apr 19–24, 2026 | Booking integrity wave (state-machine invariants → durable refund idempotency) | `ac7275b` booking state-machine invariants + idempotent Stripe webhook; `ae2d070` Stripe payment-hold on booking creation + pending-limit enforcement; `89e42b8` batch-4 platform hardening (policy dedup, rooms.status phase 3, Redis kill switches, CI gates); `abc3959` ephemeral `IdempotencyGuard` deleted (-515 LOC) — replaced with durable `stripe_refund_events` UNIQUE; eliminates TOCTOU window, survives restart and horizontal scaling; RefundIdempotencyTest (replay, partial refund, concurrent INSERT) |
| Apr 25–28, 2026 | Auth + observability hardening | `5e258e7` batch-2 Sanctum hardening (atomic refresh, fingerprint binding, fence-post unification); `4ab9cfd` F-32 — Sanctum `findToken()` for Bearer lookup in `detectAuthMode`; `04c7d63` RBAC-001 — contact messages admin-only via `ContactMessagePolicy`; `cb7911a` PII redaction across all log channels and Sentry; `58da55e` OBS-001 + OBS-002 (admin-gated detail health probes); `95f9f80` Stripe origin pinning in CSP middleware + Caddyfile |
| Apr 29 – May 1, 2026 | Booking actor immutability + no-overlap constraint hardening + DB safety | `048e40b` immutable actor snapshot on bookings + admin_audit_logs; `92f1ad1` `no_overlapping_bookings` constraint assertion + pre-deploy gate; `746a5bf` decompose monolithic `TransactionExceptions` into SRP hierarchy; `2e120c0` guard null booking after `fresh()` in `ReconcileRefundsJob`; `10b5346` token refresh type guard + pending-count lock semantics; cancellationActorSnapshot type contracts hardened (`e68f40f`/`842e64a`/`98fbe93`/`d5798fe`/`2cbb436`) |
| May 2, 2026 | AI Harness hardening + OPS-004 + CONC-005/006 + AUTH-004 (batch-8) | `347649a` AI-001 policy-document prompt-injection defense; `e588432` AI harness PII hard-block + injection bypass prevention + HMAC audit; `5a295c0` durable AI proposal lifecycle + drift detection + proposer binding; `b69a7a0` deposit FSM lifecycle + null-user reconciliation (CONC-005/006); `7027adb` OPS-004 stay cancellation propagation; `1079946` OTP resend race-hardened (AUTH-004); `c5a37dc` batch-8 kill-switch hardening + E2E smoke gate + CI manifests; `10b153e` AI harness test fix for AUTH-004 kill-switch migration |
| May 5–8, 2026 | Maintenance batch — type-safety, test stability, dep hygiene | `aa205a4` README pass + RoomSeeder rebalance (44 rooms, `updateOrCreate`); `97c684c` axios `^1.15.0`→`^1.16.0` + lockfile reconcile; `77f93b4` AI harness trait order + `DepositEvent` `self::` guard; `d488923` trust Eloquent enum casts (narrow exception handling); `1441edb` `ReconcileRefundsJob` Stripe charge type guard; `176051d` Booking `@template` PHPDoc generic alignment; `2ab45ae` `AiHarnessDisabledTest` aligned with `FeatureFlag::killSwitch()` (config gate was non-functional); `6372d7f` `FeatureFlag::forget()` graceful degradation on Redis outage + Redis-free harness setup + explicit `REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD` in `phpunit.xml` and CI |
| Apr 3–4, 2026 | Email verification OTP flow (full-stack), location availability fix, concurrent booking HTTP 500 fix, mail view assets (infra), PHPStan 10→0 errors, Psalm 4→0 errors, TS5103 tsconfig fix, Pint style fix (3 files) | Merged to main Apr 4 (9756bba, 7 commits, 40 files, 1954+/403−) |
| Mar 31, 2026 | Docs sync v3: 5 confirmed docs updated, 9 findings patched (F-01, F-02, F-03, F-04, F-05, F-07, F-09) | — |

---

## Status Note

Audits v1 (61/61), v2 (98/98), v3 (14/14), v4 (6/6) complete. Findings F-01 through F-22 and F-24 resolved. F-23 open (low — MD lint). F-25 open (low — CSRF path). F-26–F-62 open (2026-03-20 audit, code findings — not fixed in docs pass).
AI Harness Phases 0–4 (Apr 9–11): 7 endpoints, 2 new tables (policy_documents, ai_proposal_events), 3 middleware, 7-layer safety pipeline, eval framework, kill switch + canary routing.
AI Harness hardening (Apr 22 → May 2): durable proposal lifecycle (lifecycle exceptions, drift detection, proposer-binding); HMAC audit; PII hard-block; AI-001 prompt-injection defense; batch-8 kill-switch hardening + E2E smoke gate + CI manifests. AUTH-004 OTP resend hardened against concurrent-request race (`1079946`); AI harness tests aligned with kill-switch migration (`10b153e`).
AI Harness kill-switch contract finalized (May 5 → May 8): `FeatureFlag::killSwitch()` is the sole gate (the `config('ai_harness.enabled', …)` path was non-functional and silently passing tests for the wrong reason — `2ab45ae`); `FeatureFlag::forget()` no longer re-throws Redis exceptions (`6372d7f`) — local in-process cache is always evicted, readers degrade safely on the next miss, and `AiHarnessDisabledTest` seeds the array driver directly so Redis is not on the test path.
Booking integrity hardening (Apr 19 → May 1): booking state-machine invariants (`ac7275b`); payment-hold on booking creation (`ae2d070`); durable Stripe refund idempotency via `stripe_refund_events` UNIQUE — replaced ephemeral `IdempotencyGuard`, eliminating TOCTOU window (`abc3959`); immutable actor snapshots on bookings + admin_audit_logs (`048e40b`); no-overlap constraint assertion + pre-deploy gate (`92f1ad1`); deposit FSM lifecycle + null-user reconciliation (`b69a7a0`); stay cancellation propagation (`7027adb`).
Auth + observability hardening (Apr 25 → 28): batch-2 Sanctum (atomic refresh, fingerprint binding, fence-post unification — `5e258e7`); F-32 Sanctum `findToken()` for Bearer lookup in `detectAuthMode` (`4ab9cfd`); RBAC-001 contact messages locked to admin via `ContactMessagePolicy` (`04c7d63`); PII redaction across all log channels and Sentry (`cb7911a`); OBS-001 + OBS-002 admin-gated detail health probes (`58da55e`); Stripe origin pinning in CSP and Caddyfile (`95f9f80`).
T-13 superseded (Apr 18): proposer-binding (F-06) enforced at `ProposalConfirmationController::decide` via cache-envelope `proposer_user_id`; `decide()` 404s on mismatch. Defense-in-depth at `CancellationService::validateCancellation`. Reclassified as Mitigated in `docs/THREAT_MODEL_AI.md`.
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
