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

- Date updated: 2026-06-02
- Current branch: `dev` (HEAD=`2f52ade`)
- Working-tree change on 2026-06-02: **Track 4 backlog reconciliation (Change Set A)** — `docs/FINDINGS_BACKLOG.md` now records source-proven commit refs for phantom-Open fixes F-26/F-27/F-28/F-29/F-31/F-33/F-35/F-38 plus adjacent landed closures F-34/F-36/F-37/F-39/F-41/F-42/F-43/F-44/F-49/F-63/F-64/F-65/F-66/F-73/F-74/F-76. Added a landed-ref ledger for SH-01/02/03/04/05/09/10/11/12. Frontend convention cleanup remains the active next batch.
- Working-tree change on 2026-06-01: **AI eval gate integrity** — `ai:eval` now binds an eval-only deterministic `ModelProviderInterface`, creates rollback-scoped users/policy/location/room/booking fixtures instead of reading `User::first()`, assigns unique request IDs, clears proposal cache entries, and blocks every `ERROR` plus every non-explicit `FALLBACK`. Proposal assertions now require expected actions and discard invalid proposals. Hermetic Phase 4+ coverage exposed a durable-proposal FK edge case: proposals for missing rooms now preserve the proposed room ID in review params while persisting a nullable FK. Added forced provider failure tests and reconciled `docs/EVAL_STRATEGY.md`; harness config remains default-off. Gates: `php artisan ai:eval --all-phases` PASS (10 FAQ / 8 room discovery / 12 admin draft / 10 action proposals); AI harness feature suite PASS (168 / 550 assertions); full backend PASS (1701 passed / 9 skipped / 5452 assertions); Pint, PHPStan, Psalm, frontend typecheck, frontend Vitest (499), and `docker compose config` PASS. `bash scripts/verify-control-plane.sh` blocked locally because this Windows host has no `/bin/bash`. Soleil MCP impact/detect tools unavailable in this Codex thread; index status was current and shared proposal-path scope was checked manually with reference grep + diff.
- Working-tree change on 2026-05-26: **DATE-01 hostel-local booking dates** — backend app timezone is env-driven with default `Asia/Ho_Chi_Minh`; `booking.business_timezone` and `App\Support\HostelClock` now own booking civil-date rules. Store/update/availability requests no longer use `after_or_equal:today`; booking started/expired/review/cancellation-window/cache/backfill/stay “today” paths use hostel-local dates, while OTP/email verification cooldown timestamps were pinned back to UTC instants after the full suite exposed a 7h Retry-After regression from the app-timezone change. Frontend added `shared/lib/hostelDate.ts`; booking min-date, guest/admin booking filters, review eligibility, search/location/assistant date inputs, and calendar day formatting no longer derive date-only values from `toISOString()`. Guardrail: `scripts/assert-date-correctness.sh` wired into `scripts/ship.sh` and CI. Tests/gates: new `BookingLocalDateTest` PASS; focused validation/cancellation/admin suites PASS (96); auth OTP regression PASS (35); full backend `php artisan test` PASS (1611 passed / 9 skipped); Pint/PHPStan PASS; focused frontend Vitest PASS (54), full frontend Vitest PASS (485), `pnpm type-check` PASS, `pnpm build` PASS, `pnpm audit --audit-level=high` PASS (4 moderate only), `pnpm run lint` PASS with existing coverage-file warnings. Soleil index refreshed (`npx soleil-engine-cli analyze`, embeddings 0) and status up-to-date; MCP `detect_changes` unavailable and CLI has no equivalent, so scope checked with guard grep + `git diff`.
- Working-tree change on 2026-05-25 (later): **PHPStan baseline drift fix (CONTRACT-02 follow-up)** — the CONTRACT-02 work added `number_of_guests`/`special_requests` reads to `BookingResource::toArray()` (lines 26-27) but its gate list ran Pint + tests, not PHPStan, so two new `property.notFound` errors on `BookingResource::$number_of_guests`/`$special_requests` broke CI (`phpstan analyse --error-format=github`, exit 1). Root cause: in this repo every `JsonResource` dynamic property access is baseline-suppressed (Larastan does not resolve `JsonResource`→model — see the ~20 existing `BookingResource::$*` entries), and the two new reads lacked matching `phpstan-baseline.neon` entries. Fix: added exactly two `property.notFound` baseline entries in alphabetical position within the `BookingResource` block (`+12` insertions, 0 deletions; analysis-config only, no code/runtime change). Did NOT use `--generate-baseline` (local regen dropped ~120 entries for errors only CI's env raises — optional Stripe/Octane deps, Sanctum methods; `reportUnmatchedIgnoredErrors: false` tolerates surplus entries but a *missing* one would resurface in CI). Gates: `vendor/bin/phpstan analyse` PASS (exit 0), `composer audit` clean, `php artisan test --filter=Booking` PASS (508 / 1550 assertions, PostgreSQL). soleil-ai-review-engine impact analysis N/A — no code symbol edited.
- Working-tree change on 2026-05-25: **CONTRACT-02 booking FE/BE contract drift** — create contract now accepts and persists `number_of_guests` plus nullable `special_requests`; `BookingResource` returns both fields; guarded migration adds the columns when absent. Generic booking update explicitly prohibits `room_id` and `number_of_guests`; `special_requests` remains updateable, and room movement is documented as a future dedicated flow. Frontend create payload trims blank notes to `null`; shared booking types and tests match the backend response/request shape. Gates: targeted booking backend tests PASS (83), fillable/N+1 fallout tests PASS (12), Pint PASS (511 files), full backend `php artisan test` PASS (1577 passed / 9 skipped; existing PHPUnit doc-comment warnings), targeted frontend booking/type tests PASS (51), `npx tsc --noEmit` PASS, full `npx vitest run` PASS (479), `docker compose config` PASS. Soleil MCP `detect_changes` unavailable and CLI has no equivalent; local index refreshed with `npx soleil-engine-cli analyze` and scope checked with `git status`/`git diff`.
- Working-tree change on 2026-05-24: **L1/SEC-01 named rate limiter wiring** — applied existing named throttles to sensitive routes: `throttle:login` on legacy/v2/HttpOnly login, `throttle:refresh-token` on legacy/v2/HttpOnly refresh, and `throttle:booking` on legacy + v1 booking creation. `RateLimiterServiceProvider` hashes normalized email, bearer token, HttpOnly cookie token identifier, user/IP actor material before cache keys; refresh uses 5/min actor + 20/min IP and invalid bearer + HttpOnly refresh attempts throttle before token lookup. Deleted proven-unreferenced `api-public`, `global-api`, and `password-reset` limiter definitions; remaining production named limiters are referenced by route or queue middleware. Added `NamedRateLimiterMiddlewareTest`; updated legacy rate-limit tests and the booking concurrency test to bypass throttle only for the concurrency invariant scenario. Gates: `composer validate --strict` PASS, Pint PASS, `php artisan test --filter=NamedRateLimiterMiddlewareTest` PASS (5), `--filter=RateLimit` PASS (16), `--filter=Auth` PASS (220 + 2 skipped), `--filter=Booking` PASS (485 + 13 skipped), full backend `php artisan test --stop-on-failure` PASS (1455 + 113 skipped), frontend `npx tsc --noEmit` PASS, frontend `npx vitest run` PASS (472), `docker compose config -q` PASS, route-list evidence PASS. `bash scripts/ship.sh` could not run because `/bin/bash` is unavailable on this Windows host; its inspected Windows-equivalent gates passed.
- Working-tree change on 2026-05-23 (later): **L3 payments null-user cancellation refund fallback** — `CancellationService::processRefund()` no longer dereferences `booking->user` unconditionally. User-present bookings keep the existing Cashier refund path; null-user bookings call `StripeService::createBookingRefund()` against `booking.payment_intent_id` with amount preservation, safe booking/source metadata, and idempotency key `booking:{booking_id}:refund:{payment_intent_id}` (no PII). Guarded missing PaymentIntent path now becomes `RefundFailedException` / `refund_failed` instead of a fatal. Tests added in `BookingCancellationTest` for Cashier path, orphan fallback partial amount + idempotency, and missing PaymentIntent guard. Gates: targeted cancellation PASS (32/122), `StripeServiceTest` PASS, Pint PASS, PHPStan PASS, Psalm PASS on retry after first timeout, full `php -d max_execution_time=0 artisan test` PASS (1444 passed / 113 skipped / 4360 assertions). `composer test` attempted but Composer's 300s process timeout killed the same suite; direct Artisan run completed successfully. soleil index refreshed with `npx soleil-engine-cli analyze` after stale warning; generated AGENTS/CLAUDE stats churn reverted.
- Working-tree change on 2026-05-23: **CI-audit-transport** — hardened `.github/workflows/tests.yml` `composer-audit` step against Packagist transport timeouts (`curl error 28` → Composer exit 100, observed at ~10s). Added `COMPOSER_IPRESOLVE=4` (force IPv4) + `COMPOSER_NO_INTERACTION=1`, pre-audit network diagnostics, `php -d default_socket_timeout=300` on the audit, and a bounded 3× retry (10/20/30s backoff) that retries ONLY exit 100; exit 0 = pass, any other non-zero = immediate hard fail. No `--no-dev` / `--ignore-severity` / `--ignore-unreachable` / fail-open — gate strength preserved (prod+dev coverage, `--locked`). CI-only: single file, no app code, no `composer.json`/`composer.lock`. Local `composer audit --locked --no-interaction --format=summary` from `backend/` clean (exit 0). Not yet committed.
- Working-tree change on 2026-05-20 (later 1): **SEC-deps** — Symfony component security patch to clear the `composer audit` CI gate (8 advisories across 5 packages: http-kernel CVE-2026-45075; mailer CVE-2026-45068; mime CVE-2026-45067/45070; routing CVE-2026-45065; yaml CVE-2026-45304/45305/45133). Fixed lockfile-only via `composer update symfony/{http-kernel,mailer,mime,routing,yaml} -W` → all five at **v7.4.12** (outside every advisory range). 19 total lock updates, 0 installs / 0 removals: the five named packages plus tightly-coupled siblings (http-foundation/error-handler/event-dispatcher/var-dumper → 7.4.8/7.4.9), contracts 3.6→3.7, polyfills 1.33→1.37; `laravel/framework` did NOT move. `composer.json` untouched (constraints already permitted the patches); no `--ignore` / audit bypass introduced. Gates: `composer audit --locked` clean, `composer validate --strict` valid, Pint (505 files) PASS, PHPStan PASS, full `php artisan test` PASS (1431 passed / 113 skipped / 4303 assertions — identical to A-2 baseline, zero regressions). Supersedes the "pre-existing symfony/yaml advisories (unrelated)" caveat on the A-2 entry below. Not yet committed.
- Working-tree change on 2026-05-20: **A-2** webhook-reaper re-claim gate + auto-surface. `webhook:reconcile-stuck-events` previously bumped `reconcile_attempts` on every claim but never gated re-claim, so a persistently-deferring row (transient Stripe error, network blackhole, misconfigured PI) was re-claimed every 5 min forever with no operator signal. Fix: new `config('booking.reconciliation.webhook_max_attempts', 12)` (env `BOOKING_WEBHOOK_RECONCILE_MAX_ATTEMPTS`; intentionally distinct from the refund job's `max_attempts=5`); `StripeWebhookEvent::scopeStaleProcessing` now also requires `reconcile_attempts < max`; new `scopeReconciliationExhausted` + `markReconciliationExhausted` (status→failed, `failed_at` set, last error preserved inline); command's new `failExhaustedEvents` step runs before the claim and emits `stripe_webhook_reconciler.reconciliation_exhausted` (error log) per row for SIEM/log alerting. 3 new tests (auto-fail w/ preserved error + no Stripe contact, `<`-boundary still-eligible, full deferring→exhausted lifecycle). Backend gates: full `php artisan test` PASS (1431 passed / 113 skipped / 4303 assertions); `composer audit` reports only pre-existing `symfony/yaml` advisories (unrelated; composer files untouched). Doc `docs/backend/STRIPE_WEBHOOK_RECONCILIATION.md` updated (behavior, schema, P2 alert, runbook). Closes finding A-2. Not yet committed.
- Working-tree change on 2026-05-19 (later 4): **A-1** defense-in-depth on Booking mass-assignment. `Booking::$fillable` shrunk from 24 columns to 5 user-input columns (`room_id`, `check_in`, `check_out`, `guest_name`, `guest_email`). State-machine column (`status`), authorship (`user_id`, `deleted_by`), payment/deposit/refund state, and cancellation-audit columns are no longer mass-assignable; trusted services now write them via `forceFill(...)->save()` or direct property assignment. Updated: `Booking::transitionTo`, `CreateBookingService::createBookingWithLocking` and `::voidPendingBookingAfterPaymentIntentFailure`, `CancellationService` (4 sites), `ReconcileRefundsJob` (6 sites), `ExpireStaleBookings`, `StripeWebhookController::handleChargeRefunded`, `DevRolePreviewSeeder`. Tests using `Booking::create([...])` to pre-seed protected columns switched to `Booking::forceCreate(...)`. New `BookingFillableTest` asserts the new contract — including the requested regression that `Booking::create(['status' => 'confirmed', 'refund_id' => 're_x', ...])` from user-shaped input does not persist any protected column. Backend gates: full `php artisan test` PASS (1424 passed / 113 skipped / 4259 assertions), `composer audit` clean. Closes finding A-1 from the audit. Not yet committed.
- Prior working-tree fix on 2026-05-19 (later 3): **NEW-7** Psalm/PHPStan `InvalidReturnType` cleanup in `CreateBookingService::createWithDeadlockRetry` — landed on `main` (`14f2459`).
- Backend gate baseline: 2026-05-11 F-30 run PASS — `php artisan test --filter=Csrf` (10/35), `php artisan test --filter=Auth` (205 passed / 1 skipped / 623), full `php -d max_execution_time=0 artisan test` (1304 passed / 110 skipped / 3763); Pint, PHPStan, Psalm PASS.
- Frontend: 39 Vitest test files; May 3 intermediate run 418 tests PASS; axios bumped `^1.15.0`→`^1.16.0` (`97c684c`); typecheck PASS.
- AI Harness: kill-switch contract finalized — `FeatureFlag::killSwitch()` is the sole gate (the `config('ai_harness.enabled', …)` path was non-functional and silently passing tests for the wrong reason — `2ab45ae`); `FeatureFlag::forget()` no longer re-throws Redis exceptions (`6372d7f`).
- Open findings: F-23, F-40, F-45–F-47, F-50–F-52, F-54, F-56–F-62, F-75. F-30 **Fixed** (authenticated-only `/api/auth/csrf-token`; `/sanctum/csrf-cookie` remains pre-auth bootstrap). F-67 **Mitigated**. T-13 **Mitigated**.
- **F-68 (2026-04-19, Fixed)**: original `rooms.location_id` doctrine-routed `->change()` race closed by `a540d46`; remaining PostgreSQL `migrate:fresh` `->change()` races hardened in `2f52ade` (SH-12).
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
