# WORKLOG — Soleil Hostel (Append-only)

## 2026-04-19

- Change: F-ID namespace disambiguation — the 2026-04-18 AI-harness proposer-binding finding, informally cited as "F-06 (2026-04-18)" throughout the prior day's remediation pass, was promoted to canonical **F-67** in `docs/FINDINGS_BACKLOG.md` to eliminate collision with the existing 2026-02-21 F-06 (CHECK `check_out > check_in` constraint, Fixed PR-2).
- Backlog (FINDINGS_BACKLOG.md): added top-of-file namespace note (explains F-06 collision and the F-06→F-67 promotion) and a new §2026-04-18 Audit Findings — AI Harness Security section containing the F-67 row (status: Fixed — Mitigated; commits `17a4880`, `39cba7a`).
- Source of truth sweep: ARCHITECTURE_FACTS.md (3 refs), PERMISSION_MATRIX.md (2 refs), THREAT_MODEL_AI.md (4 refs), COMPACT.md (live-status block + §3 Now entries + `last_verified_at`). All "F-06 (2026-04-18)" callouts in live docs now read F-67.
- Append-only discipline: the 2026-04-18 WORKLOG entry below still references "F-06" (2026-04-18 proposer-binding / namespace collision / T-13 citation); those references are historical record and MUST NOT be rewritten. The namespace note in FINDINGS_BACKLOG and ARCHITECTURE_FACTS explicitly call this out.
- Historical commit messages (`17a4880`, `39cba7a`, plus the 7 docs-governance commits from 2026-04-18) are also preserved as-is; the commit-message corpus cites F-06 (2026-04-18) which now canonically means F-67.
- Verification: docs-only pass — no runtime gates re-run. Working tree at start of this entry was clean at `16618a9`; end-of-entry HEAD will advance when the F-67 promotion is committed.
- Scope: no `backend/`, `frontend/`, `.github/`, or `docker-compose*` touched. Pure docs.

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
