# WORKLOG — Soleil Hostel (Append-only)

## 2026-06-12

- Change: **P1-5 index prune — data-gate executed, DEFERRED by design.** No code/migration change; ledger updates only (this entry + F-84 status). The task is gated on a prod `pg_stat_user_indexes` snapshot (≥14 days, spanning month-end); that input does not exist on this machine, so no DROP was recommended or staged.
- Catalog rebuild (live local chain — `soleil_test` after full migration run): 25 indexes on `bookings`. All six F-84 candidates present (`bookings_room_id_index`, `bookings_status_index`, `bookings_user_id_index`, `idx_bookings_deleted_at`, `idx_bookings_location_id`, `idx_bookings_active_overlap`). Constraint-backing confirmed via `pg_constraint`: `bookings_pkey` (contype `p`) and `no_overlapping_bookings` (contype `x`, GiST) — never prunable. Every candidate is a strict prefix of (or functionally overlapped by) a surviving composite; the status family alone has 5 indexes (`bookings_status_index` ⊂ `status_check_in_check_out` / `status_check_out` / `idx_bookings_status_period` / `idx_bookings_cancellation`).
- Correction to the C-3 analysis input: `bookings_room_id_check_in_check_out_index` is NOT gone on the current migration chain — `2025_12_18_optimize_booking_indexes` dropped it as a duplicate, but `2026_02_11_000000_reconcile_legacy_index_ordering.php:32` deliberately re-creates it (documented in DB_FACTS §"Legacy index reconciliation (intentional, idempotent)"). The other two C-3 indexes (`idx_room_dates_overlap`, `idx_room_active_bookings`) are confirmed absent. Whether prod has it depends on whether prod ran 2026_02_11 — the prod snapshot will settle it.
- Why deferred: prod DB is reachable only via `secrets.PROD_DB_HOST` (`.github/workflows/deploy.yml:400`); the local container holds only `soleil_test` + 8 ParaTest shards (no prod/dev DB). Local `pg_stat_user_indexes` is test-runner traffic (16–32 kB indexes) and may not be cited for usage decisions per the task's abort condition.
- Next: run on prod and re-invoke P1-5 with output pasted — `SELECT now(), stats_reset FROM pg_stat_database WHERE datname = current_database();` (window start), `pg_stat_user_indexes WHERE relname = 'bookings'`, `pg_indexes WHERE tablename = 'bookings'`.
- Scope: docs-only (WORKLOG + FINDINGS_BACKLOG F-84 status cell). No production symbol touched — soleil-ai-review-engine impact/detect N/A.

## 2026-06-04

- Change: **T-3 — v1 overlap matrix parametrization** — new test-only file `backend/tests/Feature/Booking/BookingOverlapMatrixTest.php`. Parametrises the overlap / double-book / half-open / cancel-frees-room / concurrent matrix over a `{endpoint, version}` data provider so the SAME scenarios run against legacy `/api/bookings` and versioned `/api/v1/bookings` from one implementation. Tagged `#[Group('booking')]` to inherit T-8 group #3.
- Reconciliation (why test-only): inspected routes/services/migrations first. `/api/v1/bookings` already exists (`routes/api/v1.php:52`) and routes to the SAME `App\Http\Controllers\BookingController::store` + `CreateBookingService` + `Booking::overlappingBookings()` scope as legacy (`routes/api/legacy.php:49`); the half-open PG exclusion constraint `no_overlapping_bookings` already exists with the correct predicate (`daterange(check_in,check_out,'[)')`, `status IN ('pending','confirmed') AND deleted_at IS NULL`); `throttle:booking` already rate-limits both endpoints. The ticket's generic spec steps (new `BookingConflictEngine`, new v1 controller, new `tsrange … WHERE status != 'CANCELLED'` migration, idempotency table, `enable_v1_bookings` flag, 404→200 enumeration, 409+`conflictingBookingIds`) were therefore either already-implemented or in direct conflict with the existing constraint/contract/active-frontend and CLAUDE.md booking-overlap/migration invariants. User confirmed the reconciled test-only scope (chose "T-3 thật: test-only").
- Contract asserted (repo-real, not the generic spec): create → 201; exact-same-dates & partial overlap → 422 (`success:false`, application-layer `FOR UPDATE` pre-insert check); adjacent (checkout==checkin) → 201 (half-open `[check_in, check_out)`); cancel then rebook same interval → 201; sequential concurrent simulation → exactly one 201 + rest conflict (422 — the 409 PG-exclusion-constraint race remains proven by `ConcurrentBookingTest`).
- Verification (from `backend/`): `php artisan test tests/Feature/Booking/BookingOverlapMatrixTest.php` → 12 passed / 44 assertions (PostgreSQL `soleil_test`, both data sets); `vendor/bin/phpunit --list-groups` → `booking (546 tests)` (was 534 after T-8), confirming group #3 inheritance; `vendor/bin/pint --test tests/Feature/Booking/BookingOverlapMatrixTest.php` PASS (1 file).
- Scope: backend test-only — 1 new file, zero production/runtime/migration/route change. No new dependency. soleil-ai-review-engine impact/detect N/A (no production symbol touched).
- Follow-up: optional — `ConcurrentBookingTest` still holds legacy-only copies of a few matrix scenarios; single-source de-dup could relocate them under this parametrised class. The net-new prompt ideas (booking-creation `Idempotency-Key`) remain unbuilt by design (user chose test-only, not spin-off).

- Change: **T-8 — booking test group** — added class-level `#[\PHPUnit\Framework\Attributes\Group('booking')]` to 45 booking-critical test classes and a `test:booking` script (`@php artisan config:clear --ansi` + `@php artisan test --group=booking`) to `backend/composer.json`. 46 files changed, 49 insertions (composer +4; 45 test files +1 each). User-confirmed scope "Core + invariants": all 20 `tests/Feature/Booking/*`; root `BookingCancellationTest`/`CreateBookingConcurrencyTest`/`RoomOptimisticLockingTest`; booking notifications (`Feature/Notifications/BookingNotificationTest`, `SendBookingConfirmationEmailTest`, `Feature/Listeners/BookingNotificationListenerTest`, `Unit/Notifications/BookingNotificationTest`); booking unit (`Unit/CreateBookingServiceTest`, `Unit/BookingStatusTest`, `Unit/BookingFactoryMethodsTest`, `Unit/Models/BookingFillableTest`, `Unit/Repositories/EloquentBookingRepositoryTest`, `Unit/Requests/UpdateBookingRequestValidationTest`); concurrency/isolation (`Feature/Database/TransactionIsolationIntegrationTest`, `Unit/Database/TransactionIsolationTest`, `Feature/Room/RoomConcurrencyTest`); `Feature/Database/CheckConstraintTest`; `Feature/Operational/BookingCancellationStayPropagationTest`; booking cache (`Feature/Cache/CacheInvalidationOnBookingTest`, `RoomAvailabilityCacheTest`); `Feature/RateLimiting/BookingRateLimitTest`; refund/deposit (`Feature/Payment/RefundIdempotencyTest`, `BookingRefundIdempotencyKeyTest`, `PaymentCancellationOutboxTest`, `DepositLifecycleTest`).
- Why: T-8 backlog item — give a booking developer one focused command (`composer test:booking` / `php artisan test --group=booking`) that runs the whole booking domain and its critical invariants (double-booking prevention, the locking write contract, cancellation/refund integrity) instead of enumerating folders/filters.
- Why attribute over `@group` (user-confirmed): the repo already has 22 test files using PHPUnit attributes vs only 2 using `@group`; BE-05 flags doc-comment `@group`/`@test` as deprecated for PHPUnit 12 (migration deferred). New `@group` annotations would grow that debt, so the non-deprecated `#[\PHPUnit\Framework\Attributes\Group('booking')]` was used — fully-qualified inline, matching `CheckConstraintTest`'s existing `#[\PHPUnit\Framework\Attributes\Test]` style, so no new `use` import. `--group=booking` filters identically for attribute and annotation.
- Verification (from `backend/`): `composer validate` → "./composer.json is valid"; `vendor/bin/phpunit --list-groups` → `booking (534 tests)` (group recognized + whole suite parses); `composer test:booking` → 534 passed / 1677 assertions (PostgreSQL `soleil_test`, 194.37s) — proves the new script, `--group=booking` selection, and zero breakage from the metadata edits; `vendor/bin/pint --test tests` PASS (181 files). `composer audit` surfaced 1 advisory (laravel/framework CVE-2026-48019, CRLF in default email rule, fixed `>=12.60.0`) — PRE-EXISTING and unrelated; this task added a composer *script* only, no `require`/lock change.
- Scope: backend test metadata + one composer script. No application/runtime code, no migration, no booking/auth/RBAC/Stripe logic, no API contract, no frontend. soleil-ai-review-engine `impact`/`detect_changes` N/A — class-level test attributes and a composer script alter no production symbol (and per repo note `detect_changes` over-reports annotation-only diffs). Full git diff reviewed manually (46 files, 49 insertions).
- Follow-up: (1) PRE-EXISTING `composer audit` advisory CVE-2026-48019 needs a `laravel/framework` bump to `>=12.60.0` — out of scope here; own dependency task. (2) `docs/backend/guides/TESTING.md` is stale (still says `DB_CONNECTION=sqlite`/`:memory:`, lists 4 `Feature/Booking` files vs the current 20) and should document the new `booking` group + `composer test:booking`; docs-sync task. (3) BE-05 annotation→attribute migration remains deferred.

## 2026-05-25

- Change: **PHPStan baseline drift fix** (CONTRACT-02 follow-up) — added two `property.notFound` ignore entries to `backend/phpstan-baseline.neon` for `App\Http\Resources\BookingResource::$number_of_guests` and `::$special_requests`, placed in alphabetical position inside the existing `BookingResource` block (between `$nights`/`$refund_amount` and between `$room_id`/`$status`). `+12` insertions, 0 deletions. No application code touched.
- Why: the CONTRACT-02 booking-contract work added `'number_of_guests' => $this->number_of_guests` and `'special_requests' => $this->special_requests` to `BookingResource::toArray()` (BookingResource.php:26-27) but its gate run covered Pint + PHPUnit + tsc + vitest, NOT PHPStan. CI's `phpstan analyse --error-format=github` then failed (exit 1) with two `Access to an undefined property` errors. In this repo, Larastan does not resolve `JsonResource` dynamic property access against the wrapped model, so EVERY `BookingResource::$*` / `RoomResource::$*` / `LocationResource::$*` read is already baseline-suppressed (~20 entries for BookingResource alone). The two new reads simply lacked their matching baseline entries.
- Why not `--generate-baseline`: regenerating locally produced a clean `[OK] Baseline generated with 119 errors` but a `git diff` showed it DROPPED ~120 existing entries (e.g. `ReconcileRefundsJob::refund()/stripe()`, Sanctum middleware methods, Octane, several `User` enum comparisons) because this Windows dev environment resolves errors that CI's environment raises (optional/absent deps). `phpstan.neon` sets `reportUnmatchedIgnoredErrors: false`, so surplus baseline entries are harmless, but a *missing* entry would resurface as a hard CI error. Reverted the regen (`git restore`) and hand-added only the two needed entries to keep the diff minimal and CI-safe.
- Verification (from `backend/`): reproduced the failure first — `vendor/bin/phpstan analyse` → 2 errors at BookingResource.php:26-27 (`property.notFound`), matching the CI log exactly. After the fix: `vendor/bin/phpstan analyse` PASS (exit 0, no output under `--error-format=github`); `git diff --stat backend/phpstan-baseline.neon` = `1 file changed, 12 insertions(+)` (0 deletions); `composer audit` → "No security vulnerability advisories found."; `php artisan test --filter=Booking` PASS (508 passed / 1550 assertions, PostgreSQL via the running `db` container). Full suite not re-run: the change is analysis-config only and provably inert to runtime (PHPUnit/Laravel never read `phpstan-baseline.neon`).
- Scope: backend static-analysis config only — single file `backend/phpstan-baseline.neon`. No code symbol modified, so soleil-ai-review-engine `impact`/`detect_changes` are N/A (the engine indexes code, not PHPStan neon config). No migration, no booking/auth/RBAC/Stripe logic, no API contract, no frontend.
- Follow-up: optional — the recurring class of failure is "code task ran Pint + tests but skipped PHPStan, so a new `JsonResource` property read isn't baselined." Consider adding PHPStan to the canonical pre-commit/ship gate sequence (or a Larastan `@mixin`/`@property` typing for the resources so these reads resolve instead of being baselined wholesale). Flag as its own task; out of scope here.

## 2026-05-23

- Change: **CI-audit-transport** — hardened the `composer-audit` job's "Audit Composer dependencies" step (`.github/workflows/tests.yml`, was bare `composer audit` at line 546) against Packagist transport timeouts. The step previously failed at ~10s with `curl error 28 ... Operation timed out after 10005 milliseconds with 0 bytes received` (Composer surfaces this as exit 100) while fetching `https://packagist.org/api/security-advisories/`. New step: adds env `COMPOSER_NO_INTERACTION=1` + `COMPOSER_IPRESOLVE=4`; runs pre-audit diagnostics (`composer --version`, a PHP `default_socket_timeout` echo, `composer diagnose -vvv || true`, and an IPv4 `curl -4 -I --connect-timeout 20 --max-time 60` probe of the advisories endpoint, all non-fatal); then runs `php -d default_socket_timeout=300 "$(command -v composer)" audit --locked --no-interaction --format=summary` inside a bounded 3-attempt retry with 10/20/30s backoff. Exit 0 = pass; exit 100 = transport/runtime → retry; any other non-zero = real advisory/abandoned/policy finding → immediate hard fail; exhausted retries → exit 100.
- Why: distinct failure MODE from the 2026-05-20 SEC-deps entry below (which patched real Symfony advisories on this same step). Here the advisory-DB HTTP fetch stalls on the GitHub-hosted runner. PHP's default `default_socket_timeout` is 60s yet the cut-off was ~10s with "0 bytes received", consistent with a connect-phase stall — most commonly an IPv6 route to Packagist that black-holes on CI. `COMPOSER_IPRESOLVE=4` forces IPv4 (highest-leverage root-cause control); `-d default_socket_timeout=300` lifts Composer's transfer-timeout ceiling for a slow-but-working endpoint; bounded retry absorbs transient blips.
- Why exit 100 = transport/runtime: empirically the code Composer returned for this curl-28 failure (per the CI log), and — decisively — the retry branch never converts a non-zero into success, so a misclassified code can never make the gate pass. Real advisory/abandoned/filtered findings surface as a different small non-zero status and take the immediate hard-fail branch, so detection is unaffected.
- Why not `process-timeout`: `config.process-timeout` / `COMPOSER_PROCESS_TIMEOUT` bounds spawned CHILD processes (git clone, scripts), not in-process HTTP downloads. The advisory fetch is an HTTP request, so process-timeout has zero effect on it; the correct levers are the socket/transfer timeout and the network path (IP family).
- Why not `--ignore-unreachable` / `--ignore-severity` in the gate: `--ignore-unreachable` treats an unreachable advisory DB as success (fail-open) and `--ignore-severity` suppresses findings — both forbidden in a blocking merge gate. Deliberately did NOT add `--no-dev` either: kept prod+dev advisory coverage (matches the pre-existing gate scope; "without weakening the gate"). `--locked` is retained for determinism and matches the SEC-deps verification command.
- Verification: local `composer --version` → 2.8.8; `php -r "echo ini_get('default_socket_timeout')"` → 60 (overridden to 300 in the CI command); `composer audit --locked --no-interaction --format=summary` from `backend/` → "No security vulnerability advisories found." (exit 0), confirming the happy path and current clean advisory state. Workflow YAML structure verified by inspection (no actionlint/python-yaml available on the Windows dev box); the `run: |` block scalar terminates cleanly into the `npm-audit` job.
- Scope: CI only — single file `.github/workflows/tests.yml`, one step rewritten. No application code, no `composer.json`/`composer.lock`, no migration, no booking/auth/RBAC/Stripe logic, no frontend. soleil-ai-review-engine impact analysis N/A (no code symbol touched; the engine indexes code, not workflow YAML).
- Follow-up: optional — a SEPARATE non-blocking scheduled audit job could use `--ignore-unreachable` purely for observability, but it must never be added to the PR/merge hard gate. Left unimplemented; flag if the team wants it.

## 2026-05-20 (later 1)

- Change: **SEC-deps** — Symfony component security patch to resolve the `composer audit` CI gate failure (`.github/workflows/tests.yml:546`, "Audit Composer dependencies" step). `composer audit` reported 8 advisories across 5 packages: `symfony/http-kernel` (CVE-2026-45075 — HEAD bypasses `methods: ['GET']` in `#[IsGranted]`/`#[IsCsrfTokenValid]`), `symfony/mailer` (CVE-2026-45068 — sendmail argument injection), `symfony/mime` (CVE-2026-45070 mime-parameter header injection + CVE-2026-45067 CRLF in `Address`), `symfony/routing` (CVE-2026-45065 — UrlGenerator off-site `//host` injection), and `symfony/yaml` (CVE-2026-45304 billion-laughs + CVE-2026-45305 cleanup-regex ReDoS + CVE-2026-45133 unbounded-recursion stack exhaustion). All five were on the Symfony 7.4 branch (http-kernel v7.4.2; mailer/mime/routing v7.4.0; yaml v7.4.6), each below the advisory fix line `>=7.4.12`. Fixed lockfile-only via `composer update symfony/http-kernel symfony/mailer symfony/mime symfony/routing symfony/yaml -W` (`--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` on the local Windows dev box — `laravel/horizon` requires those POSIX extensions, present in Linux/CI/Docker, absent on Windows; the flags affect local resolution only, not version selection or the committed lockfile). Result: **0 installs, 19 updates, 0 removals** — the five named packages → **v7.4.12**, tightly-coupled siblings `symfony/http-foundation` v7.4.1→v7.4.8, `symfony/error-handler` v7.4.0→v7.4.8, `symfony/var-dumper` v7.4.4→v7.4.8, `symfony/event-dispatcher` v7.4.4→v7.4.9, `*-contracts` v3.6→v3.7, `symfony/polyfill-*` v1.33→v1.37. `laravel/framework` (v12.42.0) did NOT move; no major-version jumps; all Symfony core components stay on 7.4.x.
- Why: CI's `composer audit` gate (blocking, `continue-on-error: false`) failed correctly because known-vulnerable Symfony versions were pinned in `backend/composer.lock`. The fix moves them to the patched releases without weakening the gate — no `composer audit --ignore`, no `--no-audit`, no `COMPOSER_NO_AUDIT`, no advisory-blocking change, no `frontend/.audit-exceptions.json`-style suppression on the composer side (none exists, and none was added). Lockfile-only was sufficient because none of the five packages are declared in the root `require`/`require-dev` of `backend/composer.json` (they are transitive deps of `laravel/framework` + dev tooling), and the existing constraints already permitted the 7.4.12 patches — so `composer.json` did not need to be touched.
- Verification (all from `backend/`): `composer audit --locked` → "No security vulnerability advisories found." (exit 0); `composer validate --strict` → "./composer.json is valid" (exit 0); `vendor/bin/pint --test` PASS (505 files); `vendor/bin/phpstan analyse` PASS (No errors); full `php artisan test` PASS (1431 passed / 113 skipped / 4303 assertions, 378.05s, PostgreSQL via the already-running `soleil-hostel-db-1` postgres:16 container) — identical pass/skip/assertion counts to the 2026-05-20 A-2 baseline, confirming the patch bump introduced zero behavioral regressions.
- Scope: backend dependency metadata only — single file `backend/composer.lock` changed (`git status --short` = ` M backend/composer.lock`; 121 insertions / 113 deletions = 19 package version+ref+dist updates; `content-hash` unchanged because `composer.json` is unchanged). No application code, no migration, no booking/auth/RBAC/Stripe logic, no API contract, no frontend, no CI/workflow edits. Patch-level Symfony movement within the 7.4.x line plus minor contract/polyfill bumps.
- Follow-up: none required — `composer audit --locked` is clean. Note: this supersedes the recurring "pre-existing transitive `symfony/yaml` advisories (CVE-2026-45305, CVE-2026-45133), composer files untouched, out of scope" caveat carried by the 2026-05-20 A-2 and earlier entries; those advisories are now resolved. The local `ext-pcntl`/`ext-posix` ignore is a Windows-dev-only ergonomic (CI/Docker run Linux with both extensions) and is NOT persisted to any committed file.

## 2026-05-20

- Change: **A-2** — gate re-claim and auto-surface exhausted rows in the Stripe webhook reaper (`webhook:reconcile-stuck-events`). Before this change, `claimStaleEvents` bumped `reconcile_attempts` on every claim but nothing ever read it back to gate re-claim, so a row that kept deferring (persistent transient Stripe error, network blackhole, misconfigured PaymentIntent) was re-claimed every 5-minute scheduler tick forever, with no operator signal. Fix spans 4 files: (1) `backend/config/booking.php` adds `reconciliation.webhook_max_attempts` (default 12, env `BOOKING_WEBHOOK_RECONCILE_MAX_ATTEMPTS`), documented as intentionally distinct from the pre-existing `reconciliation.max_attempts` (default 5) which governs the unrelated `ReconcileRefundsJob`. (2) `backend/app/Models/StripeWebhookEvent.php`: `scopeStaleProcessing($cutoff, $maxAttempts)` gains a `reconcile_attempts < $maxAttempts` predicate; new `scopeReconciliationExhausted($maxAttempts)` selects `status='processing' AND reconcile_attempts >= $maxAttempts AND type IN RECONCILABLE_TYPES`; new `markReconciliationExhausted($maxAttempts)` transitions the row to `failed` (sets `failed_at`, preserves the last transient `error` inline via the existing `sanitizeError` clamp/redaction). (3) `backend/app/Console/Commands/ReconcileStuckStripeWebhookEvents.php`: `handle()` resolves `$maxAttempts` from config and runs the new `failExhaustedEvents($maxAttempts, $limit)` step (same `DB::transaction` + `lockForUpdate` discipline as `claimStaleEvents`) BEFORE claiming, emitting a `stripe_webhook_reconciler.reconciliation_exhausted` error-level log per retired row for SIEM/log-based alerting; `exhausted=` is threaded through the run summary + `run_complete` structured log (now also emitted on the empty-claim path). (4) test file: `makeStuckEvent` helper extended with optional `reconcileAttempts` / `error` seeds; 3 new tests.
- Why: audit finding **A-2** (LOW / observability). No data-integrity bug — the booking is never wrongly mutated — but the missing gate meant a permanently-failing event silently consumed reaper budget every tick and never surfaced to an operator. The fix converts "re-claim forever, silently" into "retry up to N times, then fail loudly with forensic context." Threshold lives in config so ops can tune it without a code change; kept separate from the refund job's knob so tuning one cannot perturb the other (decision confirmed with the requester).
- Verification: targeted `php artisan test tests/Feature/Payment/ReconcileStuckStripeWebhookEventsTest.php` PASS (11 tests / 56 assertions — 8 pre-existing + 3 new: exhausted-row auto-fail with preserved error and zero Stripe contact, `<`-boundary one-below-max still reconciled, full deferring→exhausted two-run lifecycle). Full `php artisan test --stop-on-failure` PASS (1431 passed / 113 skipped / 4303 assertions, 365.48s, PostgreSQL via `docker compose up -d db`). `composer audit` reports only pre-existing transitive `symfony/yaml` advisories (CVE-2026-45305, CVE-2026-45133) — `composer.json`/`composer.lock` untouched this session, so out of scope (flag as its own dependency-bump task). soleil-ai-review-engine `impact` on `scopeStaleProcessing` = LOW (sole code caller is the command); `detect_changes` confirms scope = the 4 intended files only.
- Scope: backend only — 1 config, 1 model, 1 command, 1 test file. No migration (the `reconcile_attempts` column + `idx_stripe_webhook_events_status_created_at` index already exist from `2026_05_18_000001_add_reconciliation_fields_to_stripe_webhook_events_table.php`). No frontend, no API contract, no RBAC surface. Existing reaper behavior (claim/verify/apply/processed/failed/deferred) is byte-identical below the threshold; the only new observable behavior is the auto-fail transition once `reconcile_attempts` reaches `webhook_max_attempts`.
- Follow-up: none required for A-2. Note for ops: recovering an auto-failed exhausted row requires resetting both `status='processing'` and `reconcile_attempts=0` before re-running the command (documented in `docs/backend/STRIPE_WEBHOOK_RECONCILIATION.md` runbook step 5), else step 1 immediately re-fails it. Pre-existing `symfony/yaml` advisories remain open as a separate dependency concern.

## 2026-05-19 (later 4)

- Change: **A-1** defense-in-depth on `Booking` mass-assignment surface. `Booking::$fillable` (`backend/app/Models/Booking.php:28-46`) was shrunk from 24 columns to 5 user-supplied booking inputs (`room_id`, `check_in`, `check_out`, `guest_name`, `guest_email`). The state-machine column (`status`), authorship (`user_id`, `deleted_by`), all payment/deposit/refund columns (`payment_intent_id`, `amount`, `deposit_*`, `refund_*`), and the cancellation-audit columns (`cancelled_at`, `cancelled_by`, `cancelled_by_email`, `cancelled_by_role`, `cancelled_by_display`, `cancellation_reason`) are no longer mass-assignable via `Booking::create`/`->update`/`->fill`. Trusted code paths now write these columns through `forceFill(...)->save()` or direct property assignment. Sites updated: `Booking::transitionTo` (Models/Booking.php), `CreateBookingService::createBookingWithLocking` + `::voidPendingBookingAfterPaymentIntentFailure`, `CancellationService::transitionToRefundPending` + `::finalizeCancellation` + `::handleRefundFailure` + `::forceCancel`, `ReconcileRefundsJob::verifyExistingRefund` + `::checkPaymentIntentRefunds` + `::retryRefund` (6 sites), `ExpireStaleBookings::cancelOne`, `StripeWebhookController::handleChargeRefunded`, `DevRolePreviewSeeder::upsertTrashedBooking`. Tests that pre-seeded protected columns via `Booking::create([...])` (`CreateBookingConcurrencyTest`, `CreateBookingServiceTest`, `TransactionIsolationIntegrationTest`) switched to `Booking::forceCreate([...])`; `ReconcileRefundsJobTest` switched its `amount` precondition write to `forceFill`. `BookingFillableTest` rewritten with five assertions: $fillable shape, protected-column negative list, A-1 regression (`Booking::create(['status' => 'confirmed', 'refund_id' => 're_evil', 'cancelled_by' => 1, ...])` from user-shaped input must not persist any protected column), $booking->update() defense-in-depth, and the H-01 forward path — cancellation audit still persists via `forceFill`.
- Why: defense-in-depth from audit finding **A-1**. No live exploit exists today — no controller mass-assigns `$request->all()` into a `Booking`, and `StoreBookingRequest`/`UpdateBookingRequest` strip protected keys before reaching the service. The risk surface is that a future regression (a new controller, a refactor that switches `$validated[...]` to `$request->all()`, or a developer who trusts `Booking::create` to be safe because past code happened to populate only `$validated` fields) could promote a pending booking, spoof a cancellation actor, or rewrite refund state purely through mass assignment. Removing the columns from `$fillable` makes that mass-assign path silently drop instead of silently succeed. Pairs with the existing controller-side FormRequest validation and policy authorization — those remain the primary defenses.
- Verification: full `php artisan test --stop-on-failure` PASS (1424 passed / 113 skipped / 4259 assertions, 384.10s, PostgreSQL via `docker compose up -d db`); targeted `php artisan test --filter=BookingFillableTest` PASS (5 tests / 50 assertions); targeted booking+payment suite (`CreateBookingConcurrency`, `CreateBookingService`, `TransactionIsolation`, `ReconcileRefunds`, `BookingCancellation`, `StripeWebhook*`, `RefundStateOverlap`, `PendingBookingExpiry`, `BookingStateMachine`, `DepositLifecycle`, `RefundIdempotency`, `ActorSnapshot`, `BookingPaymentHold`, `ConcurrentBooking`) PASS (177 passed / 602 assertions); `composer audit` PASS (no advisories).
- Scope: backend only — 8 source files + 5 test files. No migration, no frontend, no contract surface. `Booking::$fillable` is the only schema-adjacent change and is purely an Eloquent guard list (no DB DDL). Observable booking behavior — overlap detection, status transitions, refund processing, cancellation audit columns, stripe webhook idempotency — is byte-identical pre/post fix; the only observable difference is that `Booking::create(['status' => 'confirmed', ...])` from user input now leaves `status` at the DB default ('pending') instead of honoring the input.
- Follow-up: optional — consider enabling `Model::preventSilentlyDiscardingAttributes()` (or the stricter `Model::shouldBeStrict()`) in a future change so that the silent-drop behavior becomes a thrown `MissingAttributeException` in non-production environments. Would surface any latent mass-assign attempt during local + CI runs but requires sweeping any factory/state code that relies on silent drop. Out of scope for A-1 — flag as separate finding if pursued.

## 2026-05-19 (later 3)

- Change: `backend/app/Services/CreateBookingService.php::createWithDeadlockRetry` — reshaped the retry loop from `do { … } while ($attempt < self::MAX_RETRY_ATTEMPTS)` to `while (true) { … }` and dropped the trailing `continue;` in the `catch` arm. Same semantics — every iteration still either `return`s a `Booking` or throws (non-retryable rethrow, max-retries `RuntimeException`, or PaymentIntent failure) — but Psalm 6.15.1 and PHPStan no longer have a predicate-can-be-false path to reason about, so the method now provably satisfies its `: Booking` return type.
- Why this surfaced now: NEW-5 (commit `2cb419d`) removed the post-loop `throw new RuntimeException('Không thể tạo booking …', 0, $lastException)` and its `$lastException` tracker as provably dead code. That proof is correct at runtime, but Psalm/PHPStan don't simulate the cross-arm "`continue` is unreachable when `$attempt >= MAX`" inference — they only see `do { … } while ($attempt < MAX_RETRY_ATTEMPTS)` without a post-loop terminator and report `InvalidReturnType: Not all code paths … end in a return statement, return type App\Models\Booking expected` (`app/Services/CreateBookingService.php:115`) plus PHPStan's matching `should return App\Models\Booking but return statement is missing`. NEW-5's worklog explicitly flagged the predicate as redundant and deferred reshaping; this entry closes that follow-up.
- Verification: `./vendor/bin/psalm --no-cache app/Services/CreateBookingService.php` PASS (no errors); `./vendor/bin/phpstan analyse app/Services/CreateBookingService.php` PASS (0 errors); `php artisan test tests/Unit/CreateBookingServiceTest.php` PASS (13/13, 23 assertions, 8.05s); `php artisan test tests/Unit/CreateBookingServiceTest.php tests/Feature/Booking/ tests/Feature/Database/TransactionIsolationIntegrationTest.php` PASS (263 passed, 872 assertions, 80.08s); `composer audit` PASS (no advisories).
- Scope: backend only — single file, single method, control-flow-equivalent rewrite. No migration, no contract, no test changes. Hot booking-creation path; the retry guarantee (3 attempts, exponential backoff/jitter by error type, `RuntimeException` after exhaustion) is byte-identical pre/post fix.
- Follow-up: none. NEW-5's deferred predicate-reshape is now resolved.

## 2026-05-19 (later 2)

- Change: NEW-5 dead-code removal in `backend/app/Services/CreateBookingService.php::createWithDeadlockRetry`. Deleted the unreachable post-loop `throw new RuntimeException('Không thể tạo booking', 0, $lastException)` (line 196 pre-edit) together with its orphan tracking variable (`$lastException = null` initializer and the `$lastException = $e` assignment inside the catch). Control-flow proof: each `do { try { … } catch { … } } while (…)` iteration is exhaustive — the `try` block ends with `return $booking;`, and the `catch (PDOException)` block either rethrows on non-retryable error, throws the max-retries `RuntimeException` when `$attempt >= MAX_RETRY_ATTEMPTS`, or `continue`s; the `while ($attempt < self::MAX_RETRY_ATTEMPTS)` predicate can therefore never observe an attempt count above MAX, so the post-loop `throw` was unreachable. Removing it eliminates the only reader of `$lastException`, which is why the assignment + initializer were stripped in the same pass to avoid leaving dead writes.
- Verification: `php artisan test --filter=CreateBookingService --stop-on-failure` PASS (13/13, 23 assertions, 7.16s); `php artisan test --filter=Booking --stop-on-failure` PASS (475 passed, 13 skipped, 1347 assertions, 141.53s); `./vendor/bin/pint app/Services/CreateBookingService.php --test` PASS; `composer audit` PASS (no advisories).
- Scope: backend only — single file, behavior preserved (dead-code prune); no migration, no contract, no test changes. soleil-ai-review-engine impact graph flagged CRITICAL upstream blast (BookingController, CreateBookingJob, v1/legacy routes) which is expected since this is on the booking-creation hot path; the removed code was provably unreachable, so the blast radius is informational only.
- Follow-up: none. The `do {} while` predicate is now redundant in PHP semantics (every iteration returns or throws), but reshaping the loop is out of scope for this dead-code prune — left as-is to minimize diff per `.agent/rules/soleil-ai-review-engine-impact-and-change-scope.md`.
- Companion change (NEW-6, same session): `frontend/src/features/booking/booking.types.ts` — corrected misleading inline comments on `BookingFormData.check_in` and `BookingFormData.check_out`. Both fields are typed `string` and the previous trailing comment `// ISO date string` implied a full ISO 8601 datetime (e.g. `2026-06-15T00:00:00Z`), but the actual wire format sent to `POST /api/v1/bookings` and validated by `StoreBookingRequest::rules()` is the date-only `YYYY-MM-DD` form — confirmed by every test fixture under `frontend/src/features/bookings/*.test.tsx` (e.g. `check_in: '2026-06-15'`) and the BookingForm assertion `room_id=1&check_in=2026-06-15&check_out=2026-06-18&guests=3`. Comments now read `// YYYY-MM-DD format (date-only)`.
- Frontend verification: `npx tsc --noEmit` PASS (0 errors); `npx vitest run --reporter=dot` PASS (45 files, 472 tests, 79.30s). No test fixtures or runtime code changed — comment-only, so zero behavioral surface.

## 2026-05-19 (later)

- Change: Static-analysis cleanup on the Stripe webhook reaper to clear CI gates (PHPStan + Psalm). Two files touched, no behavior change. `backend/app/Console/Commands/ReconcileStuckStripeWebhookEvents.php`: (a) `claimStaleEvents` return type corrected from `\Illuminate\Support\Collection` to `\Illuminate\Database\Eloquent\Collection` — `Eloquent\Collection::fresh()` (the call inside `claimStaleEvents`) is not defined on `Support\Collection`, so PHPStan flagged the call as undefined; (b) added literal-union PHPDoc `@return 'processed'|'failed'|'deferred'` on `reconcileOne()` and `@return 'processed'|'failed'` on `applyOutcome()` so the `match ($outcome)` arm in `handle()` (lines 85-89) is provably exhaustive without a default arm that would mask a future regression; (c) removed the unnecessary `/** @var StripeClient $client */` annotation in `resolveStripeClient()` — `app(StripeClient::class)` already infers the correct type via larastan, and Psalm flagged the annotation as `UnnecessaryVarAnnotation`. `backend/app/Models/StripeWebhookEvent.php`: removed redundant `is_string($message)` short-circuit in `sanitizeError()` — `$message` is already typed `string` after the `Throwable|string` narrowing (the null branch returns earlier), so both PHPStan (`AlwaysTrue`) and Psalm (`TypeDoesNotContainType`) flagged the dead guard.
- Verification: `./vendor/bin/phpstan analyse` PASS (228 files, 0 errors); `./vendor/bin/psalm` PASS (262 files, no errors — 566 info-level issues unrelated to this change); `php artisan test tests/Feature/Payment/ReconcileStuckStripeWebhookEventsTest.php tests/Feature/Payment/StripeWebhookIdempotencyTest.php` PASS (17 tests, 82 assertions, 12.06s); `composer audit` PASS (no advisories).
- Scope: backend only — two source files. No migration, no contract, no test changes. The reaper's observable behavior (claim → verify → apply → mark) is byte-identical pre/post fix; only type metadata and a dead guard changed.
- Follow-up: none; the static-analysis errors that surfaced on CI for `b051cda` are now resolved.

## 2026-05-19

- Change: `backend/app/Console/Commands/ReconcileStuckStripeWebhookEvents.php` — removed trailing blank line immediately before the class closing brace to satisfy Laravel Pint `class_attributes_separation`. Pure style fix; no behavior change. The reaper command was introduced in `ec51d6a` (2026-05-18) and the trailing blank slipped past the local pre-commit because `vendor/bin/pint --test` was not run on the final state of that file.
- Verification: `vendor/bin/pint --test` PASS (504 files, 0 style issues); `php artisan test --stop-on-failure` PASS (1422 passed, 113 skipped, 4217 assertions, 340.82s, PostgreSQL via `docker compose up -d db`); `composer audit` PASS (no advisories).
- Scope: backend only — single file, single-line removal. No migration, no contract, no test changes.
- Follow-up: none; the fix is the entire change.

## 2026-05-08

- Change: Governance docs sync — `PROJECT_STATUS.md` (HEAD `10b153e`→`6372d7f`, May 5–8 maintenance row added, kill-switch contract paragraph appended), `PRODUCT_GOAL.md` (date refresh), `BACKLOG.md` (8 new Done rows for `aa205a4`/`97c684c`/`77f93b4`/`d488923`/`1441edb`/`176051d`/`2ab45ae`/`6372d7f`), `AUDIT_REPORT.md` (rewritten as rolling current-state index — v1–v9 cycle table, gates with owners, open findings post-F-48-close = 35 from v6 + 4 from v7 + F-23/F-25/F-68; old Feb-23-v4 detail rolled up and preserved at commit `61f430a` per repo history), `docs/COMPACT.md` §1 snapshot refresh.
- Scope: docs-only — no `backend/`, `frontend/`, `.github/`, `docker-compose*` touched.
- Verification: docs-only pass; runtime gates not re-run. PROJECT_STATUS records re-verification-required state for backend/frontend tests since `b69a7a0`.
- Cross-reference: this entry is the WORKLOG counterpart to PROJECT_STATUS line "May 5–8, 2026 | Maintenance batch …" and BACKLOG Done rows for `aa205a4`→`6372d7f`.

## 2026-05-05 — 2026-05-08

- Change: Maintenance batch — type-safety, test stability, dep hygiene. 8 commits on `dev`: `aa205a4` README pass + RoomSeeder rebalance (44 rooms; `firstOrCreate`→`updateOrCreate` for idempotent re-seed; Location 2 12→10 rooms, Location 5 6→7 rooms); `97c684c` axios `^1.15.0`→`^1.16.0` + pnpm lockfile reconcile; `77f93b4` AI harness trait order + `DepositEvent` `self::` guard; `d488923` trust Eloquent enum casts (narrow exception handling — caught exceptions previously assumed mismatch which the cast already prevents); `1441edb` `ReconcileRefundsJob` Stripe charge type guard against `null`/non-charge payloads; `176051d` Booking `@template` PHPDoc generic aligned to project convention; `2ab45ae` `AiHarnessDisabledTest` aligned with `FeatureFlag::killSwitch()` Redis path — replaced `config()->set('ai_harness.enabled', false)` (which the middleware never read) with `FeatureFlag::forget('ai_harness.enabled')`; `6372d7f` `FeatureFlag::forget()` graceful degradation on Redis outage (logs + swallows + always evicts local in-process cache so this process cannot serve stale value), Redis-free `AiHarnessDisabledTest` setUp seeds the array driver directly, explicit `REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD` declared in `phpunit.xml` and `.github/workflows/tests.yml`.
- Defect class addressed: silent test pass-throughs caused by middleware-vs-config drift (`2ab45ae`); cascading failure on Redis outage in feature-flag eviction (`6372d7f`); type contract drift in money/charge handlers (`1441edb`).
- Verification: targeted PHPUnit on `tests/Feature/AiHarness/AiHarnessDisabledTest.php` PASS (post-`6372d7f`); broader gate re-run pending. `npx tsc --noEmit` PASS. `pnpm-lock.yaml` reconciliation PASS.
- Scope: backend src + tests + CI workflow + frontend lockfile. README pass touched only docs (per `aa205a4` review).

## 2026-04-19

- Change: F-ID namespace disambiguation — the 2026-04-18 AI-harness proposer-binding finding, informally cited as "F-06 (2026-04-18)" throughout the prior day's remediation pass, was promoted to canonical **F-67** in `docs/FINDINGS_BACKLOG.md` to eliminate collision with the existing 2026-02-21 F-06 (CHECK `check_out > check_in` constraint, Fixed PR-2).
- Backlog (FINDINGS_BACKLOG.md): added top-of-file namespace note (explains F-06 collision and the F-06→F-67 promotion) and a new §2026-04-18 Audit Findings — AI Harness Security section containing the F-67 row (status: Fixed — Mitigated; commits `17a4880`, `39cba7a`).
- Source of truth sweep: ARCHITECTURE_FACTS.md (3 refs), PERMISSION_MATRIX.md (2 refs), THREAT_MODEL_AI.md (4 refs), COMPACT.md (live-status block + §3 Now entries + `last_verified_at`). All "F-06 (2026-04-18)" callouts in live docs now read F-67.
- Append-only discipline: the 2026-04-18 WORKLOG entry below still references "F-06" (2026-04-18 proposer-binding / namespace collision / T-13 citation); those references are historical record and MUST NOT be rewritten. The namespace note in FINDINGS_BACKLOG and ARCHITECTURE_FACTS explicitly call this out.
- Historical commit messages (`17a4880`, `39cba7a`, plus the 7 docs-governance commits from 2026-04-18) are also preserved as-is; the commit-message corpus cites F-06 (2026-04-18) which now canonically means F-67.
- Verification: docs-only pass — no runtime gates re-run. Working tree at start of this entry was clean at `16618a9`; end-of-entry HEAD will advance when the F-67 promotion is committed.
- Scope: no `backend/`, `frontend/`, `.github/`, or `docker-compose*` touched. Pure docs.
- Follow-up (push cycle): 10 commits above `aef28a1` pushed to `origin/dev` at HEAD `9261798`.
- Hook fix (`chore(infra)`, `9261798`): `tools/hooks/pre-push.mjs` added `isNonCodePath()` helper that exempts `*.md` and `.env.example` / `.env.*.example` paths from triggering `backend_tests` / `frontend_unit_tests` / `frontend_typecheck`. Previously the naive `startsWith("backend/")` check caused commits touching `backend/.env.example` to run the full PHPUnit suite, approaching or exceeding the 900000 ms hook timeout on slow dev machines. Source files (`.xml`, `.json`, `.php`, `.ts`, lockfiles, config) still trigger tests as before.
- Re-index: `npx soleil-engine-cli analyze` advanced `.soleil-ai-review-engine/meta.json.lastCommit` to `9261798`. The indexer also rewrote `AGENTS.md` / `CLAUDE.md` stats-string from `5528` to `5525` symbols (indexer counts digit-tokens in its own output as countable symbols — a known ping-pong loop). Restored both auto-managed files to their committed state so the loop does not propagate into git history.
- New finding (F-68, Open, Medium): `backend/database/migrations/2026_02_09_000005_assign_rooms_to_locations.php:50` — `->change()` invokes doctrine/dbal which opens a secondary PostgreSQL connection to inspect column type, racing the primary connection's `UPDATE bookings … FROM rooms` at `:27` for locks on `rooms`, producing intermittent `SQLSTATE[40P01]: Deadlock detected` during `RefreshDatabase`. Reproduced 2026-04-19 (`php artisan test --filter=GateTest`: 2 failed / 12 passed). Suggested fix: raw `ALTER TABLE rooms ALTER COLUMN location_id SET NOT NULL` (no doctrine introspection), or split the migration into UPDATE-only and NOT-NULL-only steps. Not a production-code defect — test-infra reliability only.

## 2026-04-18

- Change: Documentation governance remediation pass — 11 docs aligned with post-F-06 code truth spanning commits `e6673dd`→`1deaf8e` (BASE=`3ea3e8b`, HEAD=`aef28a1`; 11 commits, 27 files in audit window). DIFF-FIRST, EVIDENCE-GATED.
- Evidence anchor: `git diff 3ea3e8b..aef28a1` surfaced 8 drift findings (DF-1..DF-8); this commit lands remediation for all eight.
- Invariants (ARCHITECTURE_FACTS.md): added §Pending TTL (Auto-Expiry Invariant), §Terminal-State Immutability, §Proposer-Binding Invariant (AI Proposals), §Cancellation Ownership: Defense-in-Depth. Fixed A14 middleware list (`throttle:10,1`→`throttle:5,1`) and added proposer-binding note to §Booking Interaction. Disambiguated F-06 namespace collision (2026-02-21 CHECK constraint vs 2026-04-18 proposer-binding).
- RBAC source of truth (PERMISSION_MATRIX.md): A14 row corrected (ALLOWED→ALLOWED-OWN-PROPOSAL-ONLY, `throttle:10,1`→`throttle:5,1`, enforcement type +OWNERSHIP-BOUND, defense-in-depth NO→YES, evidence +F-06). BR-1/BR-2 cross-refs updated with terminal-state immutability and service-layer defense-in-depth note. "Resources not investigated" booking-update line replaced with terminal-state immutability pointer.
- Threat model (THREAT_MODEL_AI.md): T-13 reclassified Accepted→Mitigated with F-06 citation; T-14 mitigation expanded with service-layer ownership gate citation; V-5 residual risk removed ("None after F-06"). Added two monitors: proposer-binding blocks and service-layer ownership blocks on `ai` channel.
- Gates (CONTRACT.md, COMMANDS_AND_GATES.md): Spectral OpenAPI contract-lint gate registered (CI workflow `contract-lint.yml` added 2026-04-17 via commit `4a33755`). Verification date updated 2026-03-23→2026-04-18.
- Runbooks (OPERATIONAL_PLAYBOOK.md): new §Pending Booking Backlog runbook, new §F-04 Deploy Gate Triggered runbook, §Failed Deployment Rollback expanded with migration-before-health-check ordering explanation (commits `75bb790`, `ec025ca`).
- Kill switches (ROLLOUT_AND_KILL_SWITCH.md): new §Pending-Booking TTL Implicit Kill Switch (BOOKING_PENDING_TTL_MINUTES=0); added ABORT condition for proposer-binding mismatch spike.
- Env examples: added `BOOKING_PENDING_TTL_MINUTES=30` + `BOOKING_PENDING_EXPIRY_BATCH_SIZE=100` to both `backend/.env.example` and `backend/.env.production.example` with rationale + kill-switch note. Production file adds MUST-NOT-be-zero warning.
- Summary docs: PROJECT_STATUS.md date Apr 12→Apr 18, HEAD `a67cfcc`→`aef28a1`, T-13 Accepted→Resolved-Mitigated, two new completed-work rows. COMPACT.md snapshot fully refreshed (date/HEAD/T-13/F-06 status). WORKLOG.md this entry.
- Verification: docs-only pass — no runtime gates re-run. Backend + frontend gate re-verification remains open (noted in PROJECT_STATUS.md).
- Scope note: touched `backend/.env.example` and `backend/.env.production.example` (under `backend/`) because they are configuration documentation keyed to this docs batch; flagged per CLAUDE.md escalation rule. No application code changed.

## 2026-04-12

- Change: Documentation governance audit + full docs sync for AI Harness Phases 0–4.
- Updated: ARCHITECTURE_FACTS.md (AI domain section), PERMISSION_MATRIX.md (rows A13-A15, Tables E/F), CONTRACT.md (AI DoD), DB_FACTS.md (AI tables + indexes), DATABASE.md (ER diagram, table defs, migrations, seeders, model relationships), openapi.yaml (3 AI endpoint groups + schemas), THREAT_MODEL_AI.md (Phase 4: T-13, T-14, V-5, V-6), COMMANDS.md (ai:eval), COMPACT.md (snapshot update).
- Security finding: T-13 ACCEPTED — ProposalConfirmationController has no user-to-proposal ownership check; relies on 256-bit hash entropy + rate limiting.
- Superseded 2026-04-18: T-13 reclassified Mitigated after F-06 proposer-binding remediation landed (`17a4880`, `39cba7a`).

## 2026-04-09 — 2026-04-11

- Change: AI Harness Phases 0–4 implementation complete.
- Phase 0: Foundation — `config/ai_harness.php` (kill switch, providers, timeouts, circuit breaker, canary), 3 middleware (`ai_harness_enabled`, `ai_request_normalizer`, `ai_canary_router`), `HarnessRequest` DTO, `TaskType`/`RiskTier`/`ResponseClass` enums.
- Phase 1: Provider abstraction — `ProviderGateway`, `OpenAiProvider`, `AnthropicProvider`, circuit breaker pattern, cost estimation, token budgets.
- Phase 2: FAQ pipeline — `FaqPipeline`, `PolicyContentService`, `PromptRegistry`, `GroundedContextAssembler`, `CitationBuilder`. `policy_documents` table + `PolicyDocumentSeeder`.
- Phase 3: Safety layers — 7-layer pipeline (L1 normalize → L2 intent → L3 context → L4 safety screen → L5 tool orchestration → L6 format → L7 audit), `PolicyScreen` with 7 injection patterns, `ToolRegistry` with static classification, `AuditLogger`.
- Phase 4: Proposal confirmation — `ProposalConfirmationController`, `BookingActionProposal` DTO, `ProposalDecisionRequest`, downstream delegation to BookingService. `ai_proposal_events` audit table.
- New routes: 7 endpoints under `/api/v1/ai/*` in `routes/api/v1_ai.php`.
- New migrations: `2026_04_09_000001_create_policy_documents_table`, `2026_04_11_000001_create_ai_proposal_events_table`.
- Eval framework: `AiEvalCommand` (`php artisan ai:eval --all-phases`), nightly CI gate at 03:00.
- Frontend: LoginPage/RegisterPage/RoomList redesign, AI assistant widgets, axios ^1.15.0 (GHSA-3p68-rc4w-qgx5 fix), vite 6.4.2 (GHSA-p9ff-h696-f583 fix).

## 2026-04-04

- Change: 5 commits across email-verification hardening + static analysis clean + style normalization. Merged dev → main (9756bba), 7 commits, 40 files, 1954 insertions, 403 deletions.
- fix(frontend): TS5103 — removed `ignoreDeprecations: "6.0"` from `tsconfig.app.json`. `"6.0"` is not a valid TS 5.7.3 deprecation wave token. No deprecated options in tsconfig chain (Branch B). `pnpm run build` exits 0.
- fix(backend): PHPStan Level 5 — 10 errors introduced by new email-verification files (Apr 3) resolved. 0 errors, no baseline, no ignores. Larastan.
- fix(backend): Psalm Level 1 — 4 errors in auth and service layer resolved. 0 blocking.
- chore(backend): Pint style — 3 files fixed: `_seed_test_accounts.php` (ordered_imports, concat_space, binary_operator_spaces, line_ending), `AppServiceProvider.php` (binary_operator_spaces in http_build_query array), `EmailVerificationCodeService.php` (class_attributes_separation between const declarations). 21 lines, whitespace/ordering only. Security surface untouched.
- Residual: 8 Pint violations in email-verification file cluster — line_ending (CRLF authored on Windows), unary_operator_spaces, braces_position, class_definition. Files: VerificationResult.php, EmailVerificationCodeController.php, VerifyCodeRequest.php, SendEmailVerificationCode.php, EmailVerificationCode.php, EmailVerificationCodeNotification.php, migration, EmailVerificationTest.php. Tracked as next `Now` item.
- merge: dev → main (9756bba). `--no-ff`. Branch history preserved.

## 2026-04-03

- Change: Email verification code (OTP) full-stack feature + concurrent booking fix + mail asset fix (commits 74320b7, 6b9ecd4, bd91e90).
- Backend — Email OTP: `email_verification_codes` table (SHA-256 `code_hash`, `attempts`, `max_attempts`, `expires_at`, `consumed_at`, `last_sent_at`). `EmailVerificationCodeService`: `issue()`, `verify()`, `cooldownRemaining()` — timing-safe `hash_equals`, `FOR UPDATE` pessimistic lock, COOLDOWN_SECONDS=60, MAX_ATTEMPTS=5, EXPIRY_MINUTES=15. `VerificationResult` enum (7 states). `EmailVerificationCodeController` (POST `/email/verification-code`, POST `/email/verify-code`). `VerifyCodeRequest`. `SendEmailVerificationCode` listener. `EmailVerificationCodeNotification` (styled Markdown mail). `EventServiceProvider` updated. `AppServiceProvider`: `VerifyEmail::createUrlUsing()` rewrites link to SPA `/email/verify` path (avoids 401 from raw API URL in mail client). 4 new routes under auth middleware.
- Backend — Location availability: `scopeWithRoomCounts` rewritten to use booking-based overlap count (active `pending`/`confirmed` bookings) instead of stale `room.status` column. `LocationResource` uses `rooms_count` (not `total_rooms`). `LocationCard` updated to match. Fixes "0 còn trống" display bug.
- Backend — Infra: mail view assets (`soleil.css`, `email.blade.php`) committed — previously excluded by `.gitignore` (6b9ecd4). Concurrent booking HTTP 500 + IP-rate-limit collapse fix (bd91e90).
- Frontend: `EmailVerifyPage.tsx` (312 lines) — 6-digit OTP input, resend cooldown countdown, error states, Vietnamese UI. `router.tsx`: `/email/verify` route. `LoginPage.tsx` + `RegisterPage.tsx`: redirect to verify page for unverified users. `GuestDashboard.tsx` refactored. `LocationCard.tsx`: `rooms_count`.
- Seed: `_seed_test_accounts.php` — user/moderator/admin test accounts with `Test1234!` password.
- Tests: `EmailVerificationTest.php` (672-line heavy revision). `RegisterTest.php` (+23 lines). `LocationApiTest.php` (+28 lines). `LocationTest.php` (+100 lines).
- Gates post-feature: PHPStan 10 errors (new files), Psalm 4 errors — resolved same day (Apr 4). Pint 3 declared + 8 residual — declared 3 fixed Apr 4, residual 8 tracked.

## 2026-03-31

- Change: Docs sync v3 (evidence-gated pass across 5 canonical docs) + PROJECT_STATUS / README / PRODUCT_GOAL / BACKLOG refresh.
- Docs sync: 9 findings patched (F-01 through F-09). Key corrections: `Booking.php` lockForUpdate line ref `:340`→`:376 (scopeWithLock)`; composer-audit documented as blocking gate (was advisory); `frontend-typecheck`, `docker-compose-validate`, `hygiene.yml` CI jobs added to COMMANDS_AND_GATES.md; customer endpoint list pruned (no `update`/`destroy` in source); F-02 (test count discrepancy D01 vs D03) resolved via live `php artisan test` run.
- Project docs: PROJECT_STATUS.md, PRODUCT_GOAL.md, BACKLOG.md, docs/README.md all updated: test counts (backend 989→1047, frontend 226→261), PHPStan "151 baseline"→"Level 5 0 errors", TL-02/TL-05 marked resolved, completed work rows added through Mar 31.
- Verification: `php artisan test` 1047/2875 PASS (2026-03-31). `npx vitest run` 261/25 PASS (2026-03-31).

## 2026-03-30

- Change: picomatch ReDoS CVE fix + Pint style cleanup + null-safe guard + AGENT_LEARNINGS scaffold + CI fallback.
- CVE: GHSA-c2c7-rcm5-vvqj — picomatch `<2.3.1` ReDoS; fixed via `pnpm overrides` (commit `0fb8c54`).
- Fix: Removed redundant null check in `EloquentBookingRepository` (`5bbb768`). Fixed 8 Pint style violations (`00ca18f`).
- Docs: Added AGENT_LEARNINGS scaffold Phase 1 (`9fc8b41`). Updated soleil-ai-review-engine index stats (`ac7cc3b`). Added composer install fallback for CI cache misses (`34dc7d3`). Updated license link to GitHub (`a2da01b`).

## 2026-03-29

- Change: 5-wave execution — restore path integrity, moderator access, hardening, product completeness (commit `263f929`).
- Wave 1 — Restore path: `BookingService::restore()` wrapped in `DB::transaction()` with `hasOverlappingBookingsWithLock()` (FOR UPDATE). Eliminates TOCTOU race on concurrent restore. Post-restore: `roomAvailabilityService::invalidateAvailability()` + `BookingRestored` event → `InvalidateCacheOnBookingChange` listener. `BookingRestoreConflictException` → 422; PG `23P01` → 409. New: `BookingRestored` event, `BookingRestoreConflictException`, `EloquentBookingRepository::hasOverlappingBookingsWithLock()`. Tests: `RestoreIntegrityTest` (16 tests).
- Wave 2 — Admin operational paths: `AdminBookingController::index()` now extracts 7 filter params (`check_in_start/end`, `check_out_start/end`, `status`, `location_id`, `search`). `EloquentBookingRepository::getAdminPaginated()` applies all filters with ILIKE search and inclusive date bounds (fixes TL-02). `AdminRoute.tsx`: `minRole` prop (default `'moderator'`); `router.tsx` gates rooms/new and rooms/:id/edit with `minRole="admin"` (fixes TL-05). Tests: `AdminBookingFilterTest` (11 tests), `AdminBookingCoverageTest` (13 tests).
- Wave 3 — Hardening: `api.ts` CSRF architecture comments corrected (SameSite=Strict is active defence; X-XSRF-TOKEN is defence-in-depth). `CreateBookingService`: explicit `location_id` from `room->location_id`. `UpdateBookingRequest::validated()` override purifies `guest_name` via HtmlPurifierService.
- Wave 4 — Product completeness: `ReviewForm.tsx` — full star-rating review form with 403/422 error handling, Vietnamese UI; integrated into `BookingDetailPanel` for confirmed bookings past checkout. `booking.api.ts::submitReview()`, new `ReviewSubmitData`/`ReviewResponse` types. `BookingDetailPanel`: `refund_failed` escalation alert. Tests: `ReviewForm.test.tsx` (10 tests).
- Wave 5 — Governance: `docs/api/BOOKING_SEMANTICS.md` created (409/422 contract, PUT/PATCH equivalence, bulk restore response shape). `docs/api/LEGACY_AUTH_SUNSET.md` created (sunset date 2026-07-01). `docs/decisions/wave-0-decision-lock.md` (moderator scope, TodayOperations semantics, password reset launch mode).
- Verification: `php artisan test` and `npx vitest run` both PASS post-merge.

## 2026-03-23

- Change: v3.4 operational completion — five workstreams completing the four-layer operational model (commit `3263e43`).
- Workstreams: (1) Room readiness tracking (`rooms.readiness_status`, 6 canonical states, CHECK constraint, audit fields, indexes). (2) Room classification (`room_type_code` + `room_tier` for equivalence/upgrade routing). (3) Deposit lifecycle on bookings (`deposit_amount`, `deposit_collected_at`, `deposit_status`). (4) Settlement lifecycle on `service_recovery_cases` (`settlement_status`, `settled_amount`, `settled_at`, `settlement_notes`). (5) Blocked-arrival escalation engine (`ArrivalResolutionService` — 5-step resolver, recommendation-only; operator-gated writes via `applyAcceptedRecommendation()`).
- Also: `OperationalDashboardService` with 16 PM/BM operational metrics. `reviews.room_id` FK corrected SET NULL→RESTRICT (migration `_000005`). `StayStatus` state machine with `canTransitionTo()` guard. Type safety tightened in `RoomResource` and `ArrivalResolutionService` (null-safe access).
- Tests: schema, financial lifecycle, dashboard, arrival resolution. 1014 tests at this point.
- Docs: DATABASE.md, DOMAIN_LAYERS.md, ARCHITECTURE_FACTS.md updated.

## 2026-03-21

- Change: v3.2 operations + v3.3 static analysis.
- v3.2: Room readiness infrastructure, blockage resolver, financial operations domain. 1009 tests, 4 skipped.
- v3.3: Full static analysis clean pass. Psalm 35→0 errors. PHPStan 151→0 errors (Level 5, no baseline, no ignores). 1037 tests, 0 failures.

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
