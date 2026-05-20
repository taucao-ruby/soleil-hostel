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
> - last_verified_at: 2026-05-08
> - scope: AI session handoff state (current snapshot, active work, known warnings, pointers)
> - expiry_trigger: any code task, gate run, or milestone change

## 1) Current Snapshot (keep under 12 lines)

- Date updated: 2026-05-20
- Current branch: `dev` (HEAD=`4d4f823`)
- Working-tree change on 2026-05-20 (later 1): **SEC-deps** — Symfony component security patch to clear the `composer audit` CI gate (8 advisories across 5 packages: http-kernel CVE-2026-45075; mailer CVE-2026-45068; mime CVE-2026-45067/45070; routing CVE-2026-45065; yaml CVE-2026-45304/45305/45133). Fixed lockfile-only via `composer update symfony/{http-kernel,mailer,mime,routing,yaml} -W` → all five at **v7.4.12** (outside every advisory range). 19 total lock updates, 0 installs / 0 removals: the five named packages plus tightly-coupled siblings (http-foundation/error-handler/event-dispatcher/var-dumper → 7.4.8/7.4.9), contracts 3.6→3.7, polyfills 1.33→1.37; `laravel/framework` did NOT move. `composer.json` untouched (constraints already permitted the patches); no `--ignore` / audit bypass introduced. Gates: `composer audit --locked` clean, `composer validate --strict` valid, Pint (505 files) PASS, PHPStan PASS, full `php artisan test` PASS (1431 passed / 113 skipped / 4303 assertions — identical to A-2 baseline, zero regressions). Supersedes the "pre-existing symfony/yaml advisories (unrelated)" caveat on the A-2 entry below. Not yet committed.
- Working-tree change on 2026-05-20: **A-2** webhook-reaper re-claim gate + auto-surface. `webhook:reconcile-stuck-events` previously bumped `reconcile_attempts` on every claim but never gated re-claim, so a persistently-deferring row (transient Stripe error, network blackhole, misconfigured PI) was re-claimed every 5 min forever with no operator signal. Fix: new `config('booking.reconciliation.webhook_max_attempts', 12)` (env `BOOKING_WEBHOOK_RECONCILE_MAX_ATTEMPTS`; intentionally distinct from the refund job's `max_attempts=5`); `StripeWebhookEvent::scopeStaleProcessing` now also requires `reconcile_attempts < max`; new `scopeReconciliationExhausted` + `markReconciliationExhausted` (status→failed, `failed_at` set, last error preserved inline); command's new `failExhaustedEvents` step runs before the claim and emits `stripe_webhook_reconciler.reconciliation_exhausted` (error log) per row for SIEM/log alerting. 3 new tests (auto-fail w/ preserved error + no Stripe contact, `<`-boundary still-eligible, full deferring→exhausted lifecycle). Backend gates: full `php artisan test` PASS (1431 passed / 113 skipped / 4303 assertions); `composer audit` reports only pre-existing `symfony/yaml` advisories (unrelated; composer files untouched). Doc `docs/backend/STRIPE_WEBHOOK_RECONCILIATION.md` updated (behavior, schema, P2 alert, runbook). Closes finding A-2. Not yet committed.
- Working-tree change on 2026-05-19 (later 4): **A-1** defense-in-depth on Booking mass-assignment. `Booking::$fillable` shrunk from 24 columns to 5 user-input columns (`room_id`, `check_in`, `check_out`, `guest_name`, `guest_email`). State-machine column (`status`), authorship (`user_id`, `deleted_by`), payment/deposit/refund state, and cancellation-audit columns are no longer mass-assignable; trusted services now write them via `forceFill(...)->save()` or direct property assignment. Updated: `Booking::transitionTo`, `CreateBookingService::createBookingWithLocking` and `::voidPendingBookingAfterPaymentIntentFailure`, `CancellationService` (4 sites), `ReconcileRefundsJob` (6 sites), `ExpireStaleBookings`, `StripeWebhookController::handleChargeRefunded`, `DevRolePreviewSeeder`. Tests using `Booking::create([...])` to pre-seed protected columns switched to `Booking::forceCreate(...)`. New `BookingFillableTest` asserts the new contract — including the requested regression that `Booking::create(['status' => 'confirmed', 'refund_id' => 're_x', ...])` from user-shaped input does not persist any protected column. Backend gates: full `php artisan test` PASS (1424 passed / 113 skipped / 4259 assertions), `composer audit` clean. Closes finding A-1 from the audit. Not yet committed.
- Prior working-tree fix on 2026-05-19 (later 3): **NEW-7** Psalm/PHPStan `InvalidReturnType` cleanup in `CreateBookingService::createWithDeadlockRetry` — landed on `main` (`14f2459`).
- Backend gate baseline: 2026-05-11 F-30 run PASS — `php artisan test --filter=Csrf` (10/35), `php artisan test --filter=Auth` (205 passed / 1 skipped / 623), full `php -d max_execution_time=0 artisan test` (1304 passed / 110 skipped / 3763); Pint, PHPStan, Psalm PASS.
- Frontend: 39 Vitest test files; May 3 intermediate run 418 tests PASS; axios bumped `^1.15.0`→`^1.16.0` (`97c684c`); typecheck PASS.
- AI Harness: kill-switch contract finalized — `FeatureFlag::killSwitch()` is the sole gate (the `config('ai_harness.enabled', …)` path was non-functional and silently passing tests for the wrong reason — `2ab45ae`); `FeatureFlag::forget()` no longer re-throws Redis exceptions (`6372d7f`).
- Open findings: F-23, F-25, F-26–F-29, F-31–F-47, F-49–F-62, F-63–F-66, F-68. F-30 **Fixed** (authenticated-only `/api/auth/csrf-token`; `/sanctum/csrf-cookie` remains pre-auth bootstrap). F-67 **Mitigated**. T-13 **Mitigated**.
- **F-68 (2026-04-19, Open, Medium)**: `backend/database/migrations/2026_02_09_000005_assign_rooms_to_locations.php:50` — doctrine-routed `->change()` races primary connection for `rooms` locks during `RefreshDatabase`. Test-infra only, no production impact.
- **H-06**: `phpunit.xml` defaults to PostgreSQL; run `docker compose up -d db` before `php artisan test`. Test env vars now declared explicitly (`6372d7f`).

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
