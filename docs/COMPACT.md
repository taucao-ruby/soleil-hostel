# COMPACT — Soleil Hostel (AI Session Memory)

> **Lifecycle Policy**
> - **Append** §1 snapshot after code tasks, gate runs, or milestone changes
> - **Do not append** for docs-only tasks, read-only exploration, or questions
> - **Archive**: when history exceeds ~80 lines, move resolved items to `docs/WORKLOG.md` and keep only the latest 5 entries here
> - **Stable facts** (invariants, architecture, auth) belong in `docs/agents/ARCHITECTURE_FACTS.md` — never here
> - **Owner**: this file is volatile session state; `ARCHITECTURE_FACTS.md` and `CLAUDE.md` own canonical truth
>
> **Lifetime metadata** (per master contract)
> - generated_from: ARCHITECTURE_FACTS.md, CONTRACT.md, COMMANDS_AND_GATES.md, FINDINGS_BACKLOG.md
> - last_verified_at: 2026-06-14
> - scope: AI session handoff state (current snapshot, active work, known warnings, pointers)
> - expiry_trigger: any code task, gate run, or milestone change

## 1) Current Snapshot (keep under 12 lines)

- Date updated: 2026-06-14 (docs-sync); branch `dev`, HEAD=`edadbf5`
- Docs restructure Batch 1/7 (2026-06-15): removed 2 orphaned files — `docs/DEVELOPMENT_HOOKS.md` (redirect stub) + `.claude/output-styles/audit.md` (superseded by `audit-report.md`); see `docs/DOCS_RESTRUCTURE_PLAN.md`
- Latest landed (2026-06-14, `f5ffa02..edadbf5`): **VND currency default** — `backend/config/cashier.php` → `env('CASHIER_CURRENCY','vnd')`; `BookingResource`/`BookingController` now render whole VND + `₫` (`0307e95`/`f703452`). **Admin trashed-bookings route + page** — static `bookings/trashed` before dynamic `bookings/:id`; moderator+admin view, admin-only restore/force-delete (`7f7fd3b`). **Nights-sign fix** — `Booking::getNightsAttribute` earliest-first for Carbon 3 (`ee2f6a8`). **F-92 Fixed, F-93 Fixed.**
- RBAC reconciliation (2026-06-14): PERMISSION_MATRIX Tables F + B mark the trashed **view** moderator read-only to match backend `role:moderator` (`37e4120`/`952b38a`); embedded `AdminDashboard` trashed tab opened to moderators read-only (`cfa0673`); restore/force-delete stay admin-only.
- Prior wave (2026-06-12, `f5ffa02`): P1-5 index-prune DEFERRED (needs prod `pg_stat_user_indexes`); P1-6 `reconciliation_refund_drift` view + `reconciliation:check-drift` (F-85); P1-7 `decide()` DB-authoritative cold-cache + write ordering (F-86). **F-85/F-86 Fixed.**
- soleil-ai-review-engine index: **7345 symbols / 21657 relationships / 300 flows** (`edadbf5`).
- Archived → git history + WORKLOG: the 2026-05-19→2026-06-04 working-tree entries (NEW-5/6/7, A-1, A-2, SEC-deps, CI-audit-transport, L3 null-user refund, L1/SEC-01 rate-limiters, DATE-01 hostel-local dates, CONTRACT-02, AI-eval-gate integrity, Track-4 A/B/C, T-8 booking group, T-3 overlap matrix) all landed on `dev`.
- Open findings: F-23, F-45–F-47, F-75, F-91 (Open); F-67 + T-13 Mitigated; F-68 Fixed.
- **H-06**: `phpunit.xml` defaults to PostgreSQL — run `docker compose up -d db` before `php artisan test`.
- Test accounts (`soleil_test`): user@ / admin@ / moderator@soleil.test — `P@ssworD123`.

## 2) Invariants

Canonical detail: `docs/agents/ARCHITECTURE_FACTS.md` (auto-loaded via CLAUDE.md).
This section intentionally left as a pointer — do not duplicate invariants here.

## 3) Active work (Now / Next)

### Now

- **Frontend ops/API batch (2026-04-29)**: ✅ COMPLETE — removed react-toastify, corrected TodayOperations room route to shared room API, removed hardcoded `lock_version`, aligned RoomDiscoveryWidget to `{content, proposals, citations}`.
- **F-32 unified Bearer detection (2026-05-01)**: ✅ COMPLETE — `UnifiedAuthController::detectAuthMode()` now uses Sanctum `PersonalAccessToken::findToken()` for Bearer lookup; diagnostic Sanctum-format token test fails before fix and passes after. Auth feature slice, full backend suite, frontend gates, and compose config pass.
- **AI-002 / AI-003 policy hardening (2026-05-01)**: ✅ COMPLETE — `PolicyEnforcementService` now normalizes Unicode for injection scans (NFC, zero-width/bidi stripping, ICU transliteration, lowercase), blocks output PII with safe response, and writes HMAC-only audit evidence. Targeted AI harness tests pass.
- **ARCH-001 schema constraint gate (2026-05-02)**: ✅ COMPLETE — `php artisan db:assert-schema-constraints` runs after PostgreSQL CI migrations and before deploy provider steps; command verifies `btree_gist`, `no_overlapping_bookings` pg_constraint shape, and soft-delete filter.
- **OPS-004 stay cancellation propagation (2026-05-03)**: ✅ COMPLETE — `BookingCancelled` now synchronously cancels non-terminal stays, `StayStatus::CANCELLED` is a terminal FSM state, PG stay-status check accepts `cancelled`, actor context propagates through `CancellationService`; targeted, adjacent cancellation, pint, and full backend gates pass.
- **PAY-006 refund idempotency (2026-04-29)**: ✅ COMPLETE — `charge.refunded` uses DB-backed `stripe_refund_events` unique `stripe_refund_id`, booking fetch locks `FOR UPDATE`, Redis/cache guard removed from refund path; targeted and full backend gates pass.
- **AI Harness Phases 0–4**: ✅ COMPLETE — all 7 endpoints, eval framework, kill switch, canary routing
- **F-67 proposer-binding** (formerly cited as F-06 2026-04-18): ✅ COMPLETE (2026-04-18) — cache envelope carries `proposer_user_id`; `decide()` 404s on mismatch; service-layer cancellation ownership gate; T-13 reclassified Accepted→Mitigated
- **Documentation governance remediation (2026-04-18)**: ✅ COMPLETE — 11 docs aligned with post-F-67 code truth (ARCHITECTURE_FACTS, PERMISSION_MATRIX, THREAT_MODEL_AI, CONTRACT, COMMANDS_AND_GATES, OPERATIONAL_PLAYBOOK, ROLLOUT_AND_KILL_SWITCH, backend/.env.example, backend/.env.production.example, PROJECT_STATUS, COMPACT)
- **F-ID namespace disambiguation (2026-04-19)**: ✅ COMPLETE — 2026-04-18 proposer-binding finding promoted from informal "F-06 (2026-04-18)" → canonical **F-67** in `FINDINGS_BACKLOG.md`. Live docs swept (ARCHITECTURE_FACTS, PERMISSION_MATRIX, THREAT_MODEL_AI, COMPACT, WORKLOG). Historical commit messages and append-only WORKLOG lines preserved as-is.
- **Deploy hardening**: ✅ COMPLETE — F-04 DEPLOY_HOST pre-flight gate + migration-before-health ordering + Spectral OpenAPI contract-lint CI gate
- PAY-001 Phase 2: Stripe checkout session + frontend payment UI
- TD-005 RBAC Follow-ups (FU-1..FU-5) — legacy test migration, coverage gaps, config verification (see `docs/PERMISSION_MATRIX.md`)
- OPS-001: SSH deploy step ✅ (real SSH deploy landed `40bcf6c`); automated health check after migration reorder; automatic rollback on health failure still pending

### Next

- M-11: Migration squash — BLOCKED, needs human-approved `php artisan schema:dump --prune` process
- I18N-002: Frontend i18n
- FE-004: Booking modification history (guest)
- TD-004: Audit log retention policy (`bookings:archive --older-than=2y`, log rotation)

## 4) Verification commands

See `docs/agents/COMMANDS.md` for full command catalog.

Latest OPS-004 verification (2026-05-03): `vendor\bin\pint.bat --test <touched backend files>` PASS; targeted OPS/stay FSM tests PASS (19 tests / 43 assertions); adjacent cancellation/notification tests PASS (59 tests / 139 assertions); full `php artisan test` PASS (1414 passed / 7 skipped / 4110 assertions). `soleil-ai-review-engine impact` reported CRITICAL blast radius for stay FSM guards; MCP `detect_changes` was unavailable and CLI has no `detect_changes` command, so scope was checked with `git status --short`, `git diff --name-only`, and `git ls-files --others --exclude-standard`.

## 5) Known warnings / noise (non-blocking)

- PHPUnit doc-comment metadata deprecation warnings can appear; treat as non-blocking when `php artisan test` is PASS.
- Vitest can emit `act(...)` and non-boolean DOM attribute warnings; treat as non-blocking when `npx vitest run` is PASS.
- Any new warning pattern or warning volume increase should be treated as a change signal and reviewed.
- `bash scripts/verify-control-plane.sh` is currently blocked in this Windows environment (`/bin/bash` unavailable after WSL access-denied fallback); rerun in a WSL/Git Bash-capable shell before release.
- Test accounts (soleil_test DB): user@soleil.test / admin@soleil.test / moderator@soleil.test — `P@ssworD123`
- Pint 8 residual violations (email-verification cluster) are non-blocking for dev but will fail CI gate. Fix before next merge to main.

## 6) Key pointers (docs / important files)

- [Project Status](../PROJECT_STATUS.md)
- [Audit Report (2026-02-21)](./AUDIT_2026_02_21.md)
- [Docs Index](./README.md)
- [Operational Playbook](./OPERATIONAL_PLAYBOOK.md)
- [DB Facts (Invariants)](./DB_FACTS.md)
- [Agent Framework](./agents/README.md)
- [Commands & Gates](./COMMANDS_AND_GATES.md)
- [Findings Backlog](./FINDINGS_BACKLOG.md)
- [WORKLOG](./WORKLOG.md)

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

## History (archived 2026-03-09)

Full history for 2026-02-12 through 2026-03-06 archived to `docs/WORKLOG.md`.
