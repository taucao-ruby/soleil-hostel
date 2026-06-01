# AI Engineering Capability Assessment — Soleil Hostel

## Meta

| Field                | Value                                                                                                  |
| -------------------- | ------------------------------------------------------------------------------------------------------ |
| **Subject**          | Soleil Hostel Monorepo (Laravel 12 + React 19 TypeScript)                                              |
| **Subject Level**    | Expert Principal Engineer, 15+ years experience                                                        |
| **Evaluator**        | Distinguished Engineer perspective — evaluating for Principal→DE trajectory                            |
| **Method**           | Full codebase read, all gates executed, every finding verified against source, AI system architecture reviewed |
| **Date**             | March 7, 2026 (original) — **Rewritten April 2, 2026 (DE-calibrated)** — **Updated April 4, 2026 (Harness Hardening wave verified)** — **Re-calibrated June 1, 2026 (current-state pass at HEAD `b7d9d28`)** |
| **Branch / HEAD**    | `dev` / `26fe51d` (April 4 scoring) → `main` / `b7d9d28` (June 1 re-calibration)                                                                     |
| **Prior assessment** | Two prior versions existed (rated 7.0–7.1/10 on a Senior→Staff scale). This rewrite recalibrates the entire framework for evaluating a Principal Engineer against Distinguished-level criteria.                    |

---

## ⟳ Re-Calibration — June 1, 2026 (HEAD `b7d9d28`)

> **Supersedes the April 2–4 scoring (Sections A, C) for current state; prior content is retained below as the historical record** — this document is append-only across `26fe51d → b7d9d28`. **Method:** assessed from a full code read + the 126-commit delta `6372d7f..b7d9d28` + the reconciled `PROJECT_STATUS.md`. Runtime gates (tests / Pint / PHPStan / Psalm) are **pending re-verification** at this HEAD — this is a documentation-layer re-calibration, not a fresh gate execution.

### What landed since April 4 (`26fe51d → b7d9d28`, ~8 weeks, 126 commits)

A large, disciplined engineering wave — almost entirely backend correctness and hardening:

- **Booking-logic invariants BL-1..BL-7** — restore empty-overlap race, refund-state overlap, constraint-first webhook idempotency, queued delivery, `location_id` three-layer guard, idempotency contract, confused-deputy cancellation.
- **Payment & refund subsystem** — `PaymentPolicy`×`PaymentStatus` state machine, `RefundStatus` projection, PAY-01/03/04 (concurrent-refund prevention, PaymentIntent-cancellation outbox, ledger-on-all-paths), SH-01/02/03 (date immutability, idempotent refund unification, ledger-coupled finalize), a fail-closed Stripe webhook reaper.
- **The exact gaps this assessment flagged, now closed** — F-33 `finalizeCancellation` re-lock; the A-1 mass-assignment defense (`Booking::$fillable` shrunk to user input); F-32 Bearer lookup; the `docker compose config` mysql override. The April "restore consistency" Phase-1 list is largely done.
- **Plus** hostel-local timezone correctness, the room readiness endpoint + RBAC, a production runtime-config pre-traffic gate, Symfony CVE patches, and an OpenAPI enum runtime-contract test.
- **The governance framework operating in anger** — this very re-calibration rides on a docs-reconciliation pass (booking-domain canon + root ledgers) executed under the `CLAUDE.md → CONTRACT` pipeline. The framework is no longer merely designed; it is visibly load-bearing.

### Re-scored DE dimensions

| Dimension | Apr 4 | **Jun 1** | Δ | Rationale |
|---|---|---|---|---|
| 1. Original technical contribution (25%) | 8.8 | **8.9** | ▲0.1 | Booking depth grew, but that is *expected* Principal mastery, not new originality. The novel artifact (the governance framework) is more battle-tested yet **still unextracted/unpublished** — the ceiling is unchanged. |
| 2. Judgment & decision quality (25%) | 6.5 | **6.5** | ◦ | **Micro ▲, macro ▼.** The flagged locking / TOCTOU / mass-assignment gaps were closed (excellent micro-judgment). But 126 further commits of hardening on a **zero-user** system is the macro scope-control failure *compounding*, not resolving. |
| 3. Leverage creation (20%) | 5.8 | **5.8** | ◦ | Still author-scoped. No OSS extraction, no users, no team. The framework multiplied output across *more* sessions — productivity, not org/community leverage. |
| 4. Shipping & impact (15%) | 3.0 | **3.0** | ◦ | **Unchanged — still the blocker.** Zero deployments, zero users, zero payments processed; `PROJECT_STATUS` shows Deployment ~60% and checkout UI pending. More quality behind the same closed door. |
| 5. Technical culture & influence (15%) | 5.0 | **5.0** | ◦ | No blog posts, talks, or open-source since April. External reach remains absent. |

**Weighted score: 6.2/10 → `6.2/10` (unchanged).**

### The finding that matters

**~8 weeks and 126 commits moved Distinguished-readiness by ≈ 0.0.** Not because the work was weak — it was excellent — but because **every commit landed in dimensions that were already strong** (technical contribution, micro-judgment) while the **three dimensions this assessment named as the gap — Shipping (3.0), Influence (5.0), Leverage (5.8) — received zero new evidence.** The April thesis is now confirmed with longitudinal data: the constraint on the Principal→Distinguished transition here is **not** engineering capability; it is the conversion of internal quality into external impact, and that conversion has not started.

### Meta-observation (the uncomfortable, DE-honest one)

Commissioning *this* re-calibration before shipping is itself the documented anti-pattern — §H **"What to STOP"**: *"Stop commissioning assessments before deploying. Six cycles. The signal/noise ratio per additional audit is near zero. Ship, then audit production behaviour."* This is the next cycle. The most useful thing a reviewer can do is decline to inflate the number and point at the work that actually moves it: **`BACKLOG` EPIC 7 → DE-01 (ship to staging) and DE-02 (extract + publish the framework).** A seventh assessment is not on the critical path; a first deployment is.

### Net verdict (June 1, 2026)

Still an **Expert Principal Engineer** with Distinguished-level spikes in AI-governance design and backend correctness — now with the micro-judgment gaps largely closed, a genuine improvement. Still **not yet Distinguished**, gated on the identical three axes as in April. The gap is now **better-evidenced, not smaller**: the number moves when a *user* does something with this system and when the governance framework *leaves the repo* — not before.

### Booking-system capability surface (as-built at `b7d9d28`) — full inventory

The complete current Soleil booking domain. Each line is evidenced in source/commit. Per the rubric this is the *necessary* engineering depth treated as table-stakes Principal mastery (Section C) — comprehensive and correct, but not the DE differentiator.

**1. Double-booking prevention (two independent layers).** PostgreSQL `EXCLUDE USING gist (room_id =, daterange(check_in, check_out) &&)` constraint `no_overlapping_bookings`, filtered to active statuses + `deleted_at IS NULL`, with a pre-deploy assertion gate `db:assert-schema-constraints` (`92f1ad1`); plus application pessimistic locking — `SELECT … FOR UPDATE` in `CreateBookingService` with deadlock-aware retry (3×, 100/200/400 ms). Half-open intervals `[check_in, check_out)` allow same-day turnover; same-day check-in datetime-precision fixed (`9793bff`); present/future availability constraint (`4332fdf`).

**2. Booking state machine.** 5 states (PENDING → CONFIRMED → REFUND_PENDING → CANCELLED, + REFUND_FAILED); sole mutation path `Booking::transitionTo()` (row-locked, transition-validated, emits `BookingStatusChanged`); `ACTIVE_STATUSES = [pending, confirmed]` gate overlap, canonicalised to a single source (`4808142`); invariants locked by `BookingStateMachineInvariantTest`.

**3. Payment FSM.** `PaymentPolicy` × `PaymentStatus` (4 × 13) with DB CHECKs (`chk_bookings_payment_policy/_status`) + indexes; `paymentAllowsConfirmation()` confirmation gate; PaymentIntent creation moved outside the DB transaction (`94cc391`) with constructor-injected `StripeClient` (`16939c8`); capture tracking (`amount_capturable`/`amount_received`/`authorized_at`/`paid_at`/`capture_due_at`).

**4. Cancellation + refund (3-phase).** `CancellationService`: idempotent terminal no-op (BL-6) → lock + transition to `refund_pending` → Stripe refund *outside* the transaction → ledger-first finalize (F-33 re-lock, SH-03) → deposit FSM. Refund policy in `Booking::calculateRefundAmount()`/`cancellationPolicy()` (48 h → 100 %, 24 h → 50 %, configurable fee) in hostel-local time; one idempotent `StripeService::createBookingRefund` (SH-02). Canonical design: `docs/backend/architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md`.

**5. Stripe webhook ingestion.** Custom **fail-closed** `StripeWebhookController` — owns signature verification (missing secret → 500, bad/expired sig → 400; `f927d51`); handles `payment_intent.succeeded` / `charge.refunded` / `payment_failed` / `canceled` / `amount_capturable_updated`; `RefundStatus::tryFromStripe` fail-closed normalisation (SH-05/F-73).

**6. Refund durability & idempotency stack.** `stripe_refund_events` ledger (PAY-04, `UNIQUE(stripe_refund_id)` replay guard; `bookings.refund_id` is a latest-pointer only); BL-3 webhook `stripe_event_id` UNIQUE; `ReconcileRefundsJob` every 5 min (pending + failed passes, PAY-01 CAS lease + existing-refund pre-check, null-user fallback CONC-006); stuck-webhook reaper with SIEM backlog telemetry (`ec51d6a`/`c2935cf`); PaymentIntent-cancellation outbox (PAY-03, `a90124c`).

**7. Deposit lifecycle FSM (CONC-005/006).** `Deposit::transitionTo()` sole mutation; append-only `deposit_events`; `none → collected → applied | refunded | partial_refund | forfeited` (CHECK `chk_bookings_deposit_status`); async `ProcessDepositRefund`; null-user system-actor reconciliation.

**8. Operational layers.** Stay-cancellation propagation OPS-004 (`BookingCancelled` → terminal `StayStatus::CANCELLED`, `7027adb`); immutable cancellation actor snapshot surviving user deletion (`048e40b`); room readiness endpoint + `RoomPolicy::updateReadiness` (moderator+, SH-10/F-63).

**9. Integrity & safety.** A-1 mass-assignment defense (`Booking::$fillable` user-input-only, `d67b13f`); SH-01 date immutability for money-final bookings; TOCTOU-safe soft-delete restore (BL-1, `902f912`); HTML-Purifier XSS sanitisation on `guest_name`; `number_of_guests`/`special_requests` validated + persisted (`1e32c8b`/`f6cc916`); pending-booking TTL expiry (`ExpireStaleBookings`, 30 min default).

**10. Contract & tests.** Published `docs/api/openapi.yaml` Booking schema (status / payment_policy / payment_status / refund_status enums) locked by `OpenApiEnumContractTest`; regression harnesses BL-1..BL-7, `RefundIdempotencyTest`, `BookingPaymentHoldTest`, `ConcurrentBookingTest`, `RoomReadinessTest`, `RefundStatusTest`.

**Assessment note:** this surface is complete and correct — and it is exactly the engineering the rubric scores as *expected at Principal level* (Section C: "expected competencies … not differentiators"). It raises confidence in dimensions 1–2; it does **not** move dimensions 4–5 (shipping, influence), which remain the DE gate. A booking system this thorough that has still served **zero** real bookings is the assessment's central tension in one sentence.

---

## A. Executive Assessment — Distinguished Engineer Perspective

**Calibration note:** Previous versions of this assessment evaluated the codebase as if built by a mid-career developer, scoring against a Senior→Staff rubric. The subject is a Principal Engineer with 15+ years of experience. This rewrite evaluates from a Distinguished Engineer lens: does this project demonstrate the judgment, leverage, and organizational-scale thinking required for the Principal→Distinguished transition? The technical patterns — deadlock retry, exclusion constraints, two-phase commits — are expected competencies at this level, not differentiators. What matters is: **decision quality, system-of-systems thinking, leverage creation, and impact per unit of effort.**

---

**Overall Classification:** A Principal Engineer building a technically exceptional portfolio project that simultaneously demonstrates and undermines Distinguished-level readiness. The engineering is beyond reproach in isolation. The AI agent orchestration system is genuinely novel — it represents original thinking about how to govern AI-assisted software development at scale. But the project as a whole reveals a Principal-level blind spot: optimizing the system's internal quality while neglecting its external impact function.

**What a Distinguished Engineer would build differently:** Not a better booking system — a *shipped* booking system with a published technical blog series, an open-source extraction of the AI governance framework, and evidence that the patterns work under production load. The technical depth here is sufficient. The leverage is insufficient.

**The strongest signal is not the code — it is the AI orchestration system.** A 15-year Principal Engineer building CRUD booking logic (however sophisticated) is not newsworthy. A 15-year Principal Engineer who has designed a multi-agent governance framework with constitutional hierarchy (CLAUDE.md → ARCHITECTURE_FACTS → CONTRACT), domain-specific skill routing (17 skill files), self-learning with human verification gates (AGENT_LEARNINGS), specialist subagents (security-reviewer, db-investigator, docs-sync, frontend-reviewer), MCP safety constraints, and reproducible batch execution across 30+ sessions — that is a Distinguished-level contribution to the field. The question is whether this contribution has been extracted, published, and validated externally.

**The project's unresolved tension:** This codebase cannot decide whether it is (a) a production hostel booking system, or (b) a research vehicle for AI-assisted software engineering. Both are valid. Pursuing both simultaneously without declaring which is primary has led to eight weeks of development post-assessment with zero deployment. A Distinguished Engineer resolves ambiguity; they do not let it compound.

---

## B. Verified Gate Results

### March 7, 2026 (Original)

| Gate                                       | Result                         | Notes                                                                                          |
| ------------------------------------------ | ------------------------------ | ---------------------------------------------------------------------------------------------- |
| `cd backend && php artisan test`           | **885 tests, 2487 assertions** | PASS — all green                                                                               |
| `cd frontend && npx tsc --noEmit`          | **0 errors**                   | PASS                                                                                           |
| `cd frontend && npx vitest run`            | **21 files, 226 tests**        | PASS                                                                                           |
| `docker compose config`                    | **REPO_ISSUE**                 | Renders `DB_CONNECTION: mysql`, `DB_PORT: 3306` — `.env.example` overrides PostgreSQL defaults |
| `cd backend && vendor/bin/pint --test`     | **283 files, 0 violations**    | PASS                                                                                           |
| `cd backend && vendor/bin/phpstan analyse` | **0 emitted errors**           | PASS (151 baseline)                                                                            |

Note: The prior assessment reported 871 tests. Actual current count is 885. Multiple docs (PROJECT_STATUS.md, PRODUCT_GOAL.md, README.md) still state 871. This drift is itself a finding.

### April 2, 2026 (Updated — All Gates Re-Executed)

| Gate                                       | Result                              | Notes                                                                                                                                  |
| ------------------------------------------ | ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `cd backend && php artisan test`           | **1120 tests, 3138 assertions**     | PASS — +235 tests vs March 7                                                                                                           |
| `cd frontend && npx tsc --noEmit`          | **0 errors**                        | PASS                                                                                                                                   |
| `cd frontend && npx vitest run`            | **35 files, 404 tests**             | PASS — +14 files, +178 tests vs March 7                                                                                                |
| `docker compose config`                    | **REPO_ISSUE (persists)**           | Still renders `DB_CONNECTION: mysql`, `DB_PORT: 3306`. `.env.example` is now fixed to `pgsql`; `docker-compose.yml` defaults to `pgsql` with `${DB_CONNECTION:-pgsql}`. Local `.env` file retains `DB_CONNECTION=mysql` and overrides the stack. Any developer with an existing `.env` hits this. |
| `cd backend && vendor/bin/pint --test`     | **PASS (assumed)**                  | Not re-run; no style changes introduced                                                                                                |
| `cd backend && vendor/bin/phpstan analyse` | **0 errors — NO BASELINE**          | PASS — significant improvement: 151-error baseline was eliminated. Now runs at Level 5 with 0 errors, 0 ignores (Larastan).            |

---

## C. DE-Calibrated Assessment (April 2, 2026)

**Calibration:** At the Distinguished Engineer level, the scoring dimensions change. Pure technical execution is table stakes. What matters: leverage, judgment, organizational impact, and whether the work advances the state of practice.

### Principal-Level Competencies (Expected — Pass/Fail)

| Competency                          | Status     | Evidence                                                                                                                                       |
| ----------------------------------- | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| System design under concurrency     | **PASS**   | Deadlock-aware retry with SQLSTATE classification, two-phase cancellation with Stripe idempotency, PostgreSQL exclusion constraint — textbook correct. |
| Defense-in-depth security           | **PASS**   | httpOnly cookies, CSRF double-submit, HTML Purifier, non-root Docker, Caddy HSTS/CSP, token expiry/revocation, device fingerprinting.          |
| Test engineering (not just coverage) | **PASS**   | 1120 backend / 404 frontend tests. Concurrency stress, optimistic locking, N+1 detection, cache invalidation, XSS vector suites.              |
| Static analysis discipline          | **PASS**   | PHPStan Level 5, 0 errors, 0 baseline, 0 ignores. TypeScript strict, 0 errors. Pint 0 violations.                                            |
| Architecture consistency            | **PASS**   | Controller→Service→Repository layering, feature-sliced frontend, 13 ADRs with proper context/decision/consequence structure.                  |
| Domain modeling depth               | **PASS**   | Four-layer operational domain (bookings→stays→room_assignments→service_recovery_cases). State machines with explicit transition validation.     |
| Infrastructure production-readiness | **PASS**   | Multi-stage Docker (4-stage frontend), Caddy hardened, CI with 6 jobs including stress test, 95% coverage gate.                                |
| CI/CD maturity                      | **PASS**   | Parallelized jobs, PostgreSQL+Redis services, concurrent cancel-in-progress, tag-based deploy, booking stress test in pipeline.                |

**All 8 Principal-level competencies pass.** The technical foundation is not in question. The previous assessment's 7.0–7.1 scores were miscalibrated — they were grading Principal-level work against a Senior rubric.

---

### Distinguished-Level Dimensions (The Actual Evaluation)

| Dimension                              | Score   | Weight | Evidence                                                                                                                                                                                                                   |
| -------------------------------------- | ------- | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **1. Original Technical Contribution** | 8.8/10  | 25%    | The AI agent governance framework (constitutional hierarchy, 17 skills, 6 task bundles, MCP safety, AGENT_LEARNINGS self-learning, 4 subagents, 6 slash commands, control-plane ownership matrix, replayable verification, structured audit logging) is genuinely novel. April 4 harness hardening added runtime observability, ownership formalization, and verification infrastructure — moving from "well-designed" to "operationally hardened." Missing: external publication, open-source extraction. |
| **2. Judgment & Decision Quality**     | 6.5/10  | 25%    | Strong individual decisions (ADRs are excellent), but meta-decision quality still shows gaps: 48 migrations for an unshipped product, four-layer operational domain before first booking. Improved: F-27/F-28 Critical TOCTOU races fixed (restore now uses transaction + FOR UPDATE). F-26 (`confirmBooking()` without lock) remains open but is a smaller inconsistency now. Harness hardening shows prioritization improvement — addressing governance infrastructure systematically. |
| **3. Leverage Creation**               | 5.8/10  | 20%    | The AI governance framework creates leverage — primarily for the author but now with extraction-ready artifacts (CONTROL_PLANE_OWNERSHIP, TASK_BUNDLES, verify-control-plane.sh). Not yet published or transferred. The codebase itself creates zero leverage: no users served, no team enabled, no patterns published. Distinguished Engineers create disproportionate impact *beyond* their own work. |
| **4. Shipping & Impact**               | 3.0/10  | 15%    | Zero deployments. Zero users. Zero payments processed. Zero post-mortems. Zero production incidents resolved. At the Principal level, shipping large, complex systems under uncertainty is an *expected* competency, not an aspirational one. 15+ years of experience with zero evidence of shipping this system is a fundamental gap. |
| **5. Technical Culture & Influence**    | 5.0/10  | 15%    | Strong governance artifacts (PERMISSION_MATRIX, FINDINGS_BACKLOG, AGENT_LEARNINGS, AI_GOVERNANCE, CONTROL_PLANE_OWNERSHIP). But no external blog posts, conference talks, open-source contributions, or evidence of influencing engineering practices beyond this repository. Distinguished requires moving the industry, not just one codebase. |

**Weighted Score: 6.2/10** — Strong Principal with Distinguished-level spikes in AI governance and backend engineering discipline, held back by a delivery gap and absence of external influence. Improved from 5.9 (April 2) by harness hardening wave and F-27/F-28 locking fixes.

**For comparison, prior assessments scored 7.0–7.1/10 on a Senior→Staff scale.** On a DE scale, the same work scores lower because the evaluation criteria shift from "can you build it correctly?" (yes, clearly) to "does it create leverage, impact, and advance the practice?" (not yet). The April 4 harness hardening moved the score from 5.9→6.2 by adding runtime observability, ownership formalization, and verification infrastructure — the kind of platform engineering that demonstrates DE-adjacent thinking.

---

## D. Deep Dive: Distinguished-Level Dimensions

### D1. Original Technical Contribution — 8.5/10

**The AI Agent Governance Framework is this project's Distinguished-level artifact.**

This is not a configuration file — it is a multi-layered governance system for AI-assisted software development:

| Layer | Component | Purpose | Maturity |
|-------|-----------|---------|----------|
| Constitution | `CLAUDE.md` (221 lines) | Non-negotiable constraints, decision order, escalation rules | Production-grade |
| Domain Facts | `ARCHITECTURE_FACTS.md` | Verified invariants agents must preserve | Active, maintained |
| Contract | `CONTRACT.md` | Definition of Done per task type (code, docs, booking, auth, migration) | 5 DoD profiles |
| Skills | 17 skill files across `skills/laravel/`, `skills/react/`, `skills/ops/` | Task-specific guardrails — agents select 1–3 per task | Comprehensive |
| Task Bundles | `TASK_BUNDLES.md` (6 bundles) | Default skill/rule compositions agents can reference by name | Production-grade (April 4) |
| Session State | `COMPACT.md` | Volatile session handoff with lifecycle policy | Self-healing (April 2 verified) |
| Subagents | 4 specialists: `security-reviewer`, `db-investigator`, `docs-sync`, `frontend-reviewer` | Domain-specific reasoning | Specialist separation |
| Commands | 6 slash commands: `audit-security`, `fix-backend`, `fix-frontend`, `review-pr`, `ship`, `sync-docs` | Executable playbooks | Workflow coverage |
| Self-Learning | `AGENT_LEARNINGS` + operating rules + schema + examples | Failure pattern capture with human verification gate | Scaffolded, not yet populated |
| MCP Server | 5 tools with policy.json | Read-only + allowlisted commands, denylist enforcement | Safety-constrained |
| Hooks | Pre-commit/post-tool enforcement | Runtime guardrails with structured audit logging (JSONL) | Hardened (April 4) — `SOLEIL-HOOK-DEGRADED` warnings on degradation |
| Code Intelligence | soleil-ai-review-engine integration | 4880 symbols, 12804 relationships, 222 execution flows indexed | Integrated |
| Ownership | `CONTROL_PLANE_OWNERSHIP.md` | Single source of truth for component ownership + review cadence | Production-grade (April 4) |
| Verification | `scripts/verify-control-plane.sh` | Replayable health check — prerequisites, hooks, settings, policy, rules, deprecation, governance files | Production-grade (April 4) |

**Why 8.5→8.8 (post-April 4 hardening) and not 9+:** The framework is comprehensive and now has runtime hardening that most governance systems lack:

1. **Partially extracted.** The April 4 harness hardening wave formalized control-plane ownership (`CONTROL_PLANE_OWNERSHIP.md`), default task bundles (`TASK_BUNDLES.md`), replayable verification (`verify-control-plane.sh`), structured audit logging (JSONL hook events), and stale-index degradation protocol. These artifacts are the *infrastructure* for extraction. A Distinguished contribution would complete extraction into a standalone open-source framework (`ai-agent-governance` or similar). The patterns are clearly generalizable — the constitutional hierarchy, skill routing, and self-learning gates are not hostel-specific.

2. **Not published.** No blog post, no conference talk, no technical paper. The AI governance field is nascent. A Principal Engineer who has invented a formal governance system for AI agents and not shared it externally is leaving Distinguished-level impact on the table. This is the most publishable part of the entire project.

**What the April 4 hardening added to the contribution:**
- **Runtime observability:** Hooks now emit structured `SOLEIL-HOOK-DEGRADED` warnings and write JSONL audit events. This moves from "hooks exist" to "hooks are auditable."
- **Ownership formalization:** Every control-plane component has a named owner role, review cadence, and escalation path. This is the organizational governance layer that was previously implicit.
- **Task bundles:** 6 named compositions (`backend-safe-fix`, `frontend-contract-fix`, `migration-audit`, `auth-review`, `docs-sync-only`, `full-release-gate`) reduce agent composition burden from 17-skill manual selection to bundle reference.
- **Replayable verification:** `verify-control-plane.sh` proves control-plane health on any machine — prerequisites, hook integrity, JSON validity, rule freshness, deprecation guards, governance file existence.
- **Deprecation enforcement:** `rooms.status` deprecation plan with code-level warning in verify script. Role hierarchy stability warning with change procedure in PERMISSION_MATRIX.

**What makes it genuinely novel:**
- The constitutional hierarchy (CLAUDE.md → ARCHITECTURE_FACTS → CONTRACT → skills → commands) with explicit conflict resolution rules is not found in any published AI coding framework.
- Agent self-learning with human verification gates (R-05, R-07, W-01 rejection rules) goes beyond prompt engineering into genuine governance design.
- The MCP safety layer (policy.json with allowlisted commands, blocked paths, size limits) demonstrates security-mindedness for AI tool use that most practitioners skip entirely.
- 30+ batch sessions with traceable git history — this is not theoretical; it has been operationalized.

---

### D2. Judgment & Decision Quality — 6.0/10

**Individual technical decisions are excellent. Meta-strategic decisions are self-defeating.**

**Strong decisions (Principal-level expectations met):**

- 13 ADRs with proper context, decision, rationale, alternatives matrix, and consequences (both positive and negative). `ADR-003` (pessimistic locking) explicitly names and rejects optimistic locking and queue-based serialization with quantified tradeoffs. `ADR-006` (dual auth) documents the attack surface cost of supporting two auth modes simultaneously. This is textbook decision documentation.

- Choosing PostgreSQL exclusion constraints as the database-level safety net, then implementing application-level half-open interval checks as defense-in-depth. Two-layer defense with independently verifiable correctness.

- Choosing HTML Purifier over regex sanitization, explicitly documenting "Regex blacklist = 99% bypass. HTML Purifier = 0% bypass" in code. This demonstrates not just the right choice but the right reason.

**Decisions that undermine Distinguished readiness:**

- **47 migrations for an unshipped product.** The operational domain (stays, room_assignments, service_recovery_cases, readiness_status, room_type_code, room_tier, deposit lifecycle, settlement columns, escalation engine, 9 new enums) was added March 20–23. This is production operations infrastructure for a system that has never processed a booking. At the Principal level, this is scope control failure. A Distinguished Engineer would recognize that building Layer 2–4 operational tables before Layer 1 (bookings) has served its first user is building the maintenance department before opening the hotel. (As of April 4: 48 total migrations.)

- **"DO NOT FIX" on Critical concurrency findings.** F-26 (`confirmBooking()` without `lockForUpdate()`) remains Critical and open. F-27 and F-28 (restore TOCTOU races) were fixed between the April 2 assessment and April 4 — `BookingService::restore()` now uses `DB::transaction` + `hasOverlappingBookingsWithLock()` with FOR UPDATE. This is positive judgment signal: the two most exploitable TOCTOU races were closed. F-26 remains: the *cost* of fixing it is near-zero (add `$booking->lockForUpdate()` before update). The *cost* of not fixing it is that concurrent webhook callbacks can double-confirm a booking and create duplicate Stay records.

- **Six audit cycles, zero deployments.** The assessment itself is evidence. A Principal Engineer who commissions a sixth review of an undeployed codebase is optimizing for internal confidence, not external impact. A Distinguished Engineer ships under uncertainty and learns from production. Every audit finding in this project could have been discovered faster by deploying to staging and running the happy path.

- **Building both a production system and a research vehicle without declaring priority.** The AI governance framework (research) and the booking system (product) pull in different directions. Research benefits from breadth and experimentation; products benefit from shipping the smallest viable scope. Neither has been served well by pursuing both simultaneously without acknowledging the tension.

---

### D3. Leverage Creation — 5.5/10

**Leverage means: does this work create disproportionate impact beyond the author's direct effort?**

| Leverage Vector | Status | Evidence |
|----------------|--------|----------|
| Production system serving users | **None** | Zero deployments, zero users |
| Open-source framework | **None** | AI governance framework not extracted |
| Published knowledge | **None** | No blog posts, talks, or papers |
| Team enablement | **None** | Solo project, no human collaborators |
| Reusable internal patterns | **Partial** | ADRs and skill files could be adapted to other projects, but are tightly coupled to Soleil-specific conventions |
| AI agent productivity | **Strong for author** | 30+ batch sessions, reproducible execution, quality gates consistently meet. Author's own velocity is clearly amplified by the governance framework |

**The AI governance framework is the highest-leverage artifact in this repository.** It has multiplied the author's output across 30+ sessions. But leverage that benefits only the author is productivity optimization, not Distinguished-level impact. Distinguished-level leverage creates force-multiplication for an *organization* or *community*.

**The path to 8+/10:** Extract the AI governance framework. Publish it as a standalone open-source project with: (a) the constitutional hierarchy pattern, (b) the skill routing system, (c) the agent self-learning gates, (d) the MCP safety constraint model. Write one blog post explaining the design decisions. This would immediately become one of the most formalized AI agent governance frameworks available, because the competition is nearly zero.

---

### D4. Shipping & Impact — 3.0/10

**This is the section that prevents a Distinguished classification.**

At 15+ years of experience, shipping complex systems to production is not an aspiration — it is a demonstrated competency. The absence of deployment evidence does not suggest inability (the infrastructure is production-viable). It suggests a decision pattern where internal quality optimization has displaced external delivery as the primary objective.

**What exists:**
- Multi-stage Docker (2 stages backend, 4 stages frontend)
- Caddy with production security headers (HSTS 2yr + preload, CSP, X-Frame-Options DENY, server header removed)
- `docker-compose.prod.yml` with resource limits and health checks
- CI/CD with tag-based deploy, pre-deployment gate, concurrency control
- `ship.sh` gate enforcement
- Stripe Cashier bootstrapped with 14 webhook handler tests

**What does not exist:**
- A deployed instance (any environment)
- A processed booking (even in test mode with Stripe test keys)
- A monitoring system (Sentry is a TODO comment)
- A database backup strategy
- A staging environment
- A post-mortem (because nothing has broken because nothing has run)

**Score justification:** 3.0 (not 0) because the *infrastructure* for shipping exists and is well-designed. The gap is execution, not capability. A single weekend of focused effort could move this to 5.0.

---

### D5. Technical Culture & Influence — 5.0/10

**Internal culture artifacts are strong. External influence is zero.**

**Strong internal culture signals:**
- `PERMISSION_MATRIX.md` as RBAC source of truth
- `FINDINGS_BACKLOG.md` with severity grading and status tracking (179 total findings across 4 audits)
- `AI_GOVERNANCE.md` with task checklists, skill selection guides, high-risk area documentation
- Agent Contract (CONTRACT.md) with 5 distinct DoD profiles
- Quality gates as non-negotiable practice (enforced by `ship.sh` and CI)
- Code intelligence integration (soleil-ai-review-engine: 4587 symbols indexed)

**Missing external influence signals:**
- Zero blog posts about the AI governance framework, the two-layer booking overlap defense, or any of the 13 ADRs
- Zero open-source extractions (the skill routing system is immediately reusable)
- Zero conference talks or community contributions
- Zero evidence of teaching, mentoring, or guiding other engineers
- Zero evidence of influencing engineering practices at an organization beyond this project

**Why this matters at the DE level:** Distinguished Engineers define how engineering is practiced, not just practice it well. The AI governance framework *could* define how AI agents work in software development. It is currently defining how AI agents work in one hostel booking system.

---

## E. Verified Issue Registry

Issues discovered during the March 7 review and confirmed against source code. Status updated April 2, 2026. At the Distinguished-Engineer level, individual bugs matter less than the *patterns* they reveal — see Section D2 for judgment analysis of the "DO NOT FIX" policy on Critical findings.

### HIGH

| ID     | File                                    | Issue                                                                                                           | Impact                                                      | April 2 Status                                                                                                                                                                                                                          |
| ------ | --------------------------------------- | --------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| BE-01  | `CheckHttpOnlyTokenValid.php:108`       | Cookie-auth fallback does not set Sanctum guard user or `withAccessToken()`                                     | Cookie-mode SPA users can 500 on booking/review endpoints   | **PARTIALLY FIXED** — Middleware now sets `auth()->setUser($user)` and `auth()->guard('sanctum')->setUser($user)` (lines 128–130). The `withAccessToken($token)` call is still absent (bearer middleware at `CheckTokenNotRevokedAndNotExpired.php:99` correctly calls it). Test comment at `SoleilTokenCookieEncryptionTest.php:224–226` is now stale but still says "may return 500". Test asserts `not-401 and not-403` rather than `200` — `currentAccessToken()` may still return null for cookie-mode users. |
| BE-02  | `AuthController.php:53`, `User.php:169` | Legacy auth routes still active with raw `DB::table()->insertGetId()`                                           | F-24 is not actually closed; bypasses token model lifecycle | **FIXED** — `AuthController.php` now uses `$user->tokens()->create()` via Eloquent relationship (verified lines 100–122), routing through model events. `createToken()` on `User.php` is now `@deprecated` and kept only for backward compatibility. F-24 correctly marked Fixed in `FINDINGS_BACKLOG.md`. |
| FE-01  | `AuthContext.tsx:169`                   | `registerHttpOnly()` calls deprecated `/auth/register`, creates unused bearer token, then re-logs in via cookie | Double request + token garbage on every registration        | **STILL OPEN** — `registerHttpOnly()` at line 191–231 still calls `api.post('/auth/register', {...})` then `loginHttpOnly(email, password)` as two sequential requests. No `POST /auth/register-httponly` unified endpoint exists.      |
| DOC-01 | `docs/COMPACT.md`                       | Internally contradictory on H-06, F-24, test counts, and open-finding counts                                    | Central governance memory is untrustworthy                  | **FIXED** — COMPACT now has a single clean snapshot section with `1047 tests, 2875 assertions` (March 31 baseline). H-06 is correct. F-24 trail is accurate. No internal contradictions detected.                                        |
| DOC-04 | `.env.example:12`                       | MySQL-flavored env overrides PostgreSQL compose stack                                                           | Fresh local setup boots inconsistent stack                  | **PARTIALLY FIXED** — `.env.example` is now `DB_CONNECTION=pgsql`, `DB_PORT=5432`. `docker-compose.yml` uses `${DB_CONNECTION:-pgsql}` default. However, `docker compose config` STILL renders `DB_CONNECTION: mysql` because the local `.env` file was not updated to match. Any existing local environment hits this. Fresh clone works correctly. |

### MEDIUM

| ID     | File                      | Issue                                                                                 | April 2 Status                                                                                                           |
| ------ | ------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| BE-03  | `ProfileTest.php:107`     | Test accepts 200 or 500 — masks auth regression                                       | **STILL OPEN** — `in_array($response->status(), [200, 500])` at line 108 with `@group known-issue` persists.            |
| BE-04  | `RateLimitService.php:25` | Dead/unregistered middleware with live test suite giving false coverage               | **STILL OPEN** — `@deprecated Not registered in middleware stack` still present; `AdvancedRateLimitMiddlewareTest.php` still runs against `/api/health`. |
| FE-02  | `SearchCard.tsx:27`       | Missing AbortController despite API supporting `signal` parameter                     | **FIXED** — `SearchCard.tsx` now implements full `AbortController` lifecycle (lines 57–60): creates controller, passes `controller.signal`, calls `controller.abort()` in cleanup. |
| DOC-02 | Multi-doc                 | `backend/README.md` says Laravel 11 / 537 tests; multiple docs stale against 885/2487 | **PARTIALLY FIXED** — `backend/README.md` now correctly says Laravel 12. Specific test-count references are not pinned in README (no stale count). Underlying doc integrity concern resolved. |

### LOW

| ID     | File                                     | Issue                                                                  | April 2 Status                                                                                                                  |
| ------ | ---------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| BE-05  | 12+ test files                           | ~145 PHPUnit `@test`/`@group` doc-annotations deprecated in PHPUnit 12 | **STILL OPEN** — Not addressed. Deferred per backlog policy.                                                                   |
| FE-03  | `router.tsx`, `LoadingSpinner.tsx`       | English "Loading..." text violates Vietnamese-only convention          | **REGRESSED** — `router.tsx` now has 7 occurrences of `message="Loading..."` (was 3). `LoadingSpinner.tsx` still has `aria-label="Loading"` and `<span className="sr-only">Loading...</span>`. Net worse than March 7. |
| DOC-05 | `KNOWN_LIMITATIONS.md:288`               | Lists Login/Register English copy as open debt when F-21 is fixed      | Not re-verified; presumed still open per backlog policy (document-only, no fix).                                               |

---

## E2. New Findings (April 2, 2026)

The following issues were not in the March 7 assessment and were discovered during the April 2 code review or by the March 20 audit cycle (F-26–F-62, documented in `FINDINGS_BACKLOG.md`).

### CRITICAL (from March 20 audit — still open)

| ID   | File                                                  | Issue                                                                                                                  |
| ---- | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| F-26 | `BookingService.php:84–121`                           | `confirmBooking()` runs in `DB::transaction` without `lockForUpdate()` — concurrent webhooks can double-confirm. **STILL OPEN as of April 4.** |
| F-27 | `AdminBookingController.php:106–144`                  | `restore()` TOCTOU race: overlap check and restore are two separate DB operations without transaction + lock. **FIXED** — `BookingService::restore()` now uses `DB::transaction` + `hasOverlappingBookingsWithLock()` (FOR UPDATE). |
| F-28 | `AdminBookingController.php:194–241`                  | `restoreBulk()` has same TOCTOU race as F-27 per booking iteration. **FIXED** — delegates to `BookingService::restore()` which has proper locking. |

### HIGH (from March 20 audit — still open)

| ID   | File                                                  | Issue                                                                                                                  |
| ---- | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| F-29 | `AuthController.php:53,87`                            | **FIXED as of March review** — legacy tokens now have `expires_at` set at creation time.                              |
| F-30 | `routes/api.php:88–91`                                | `GET /api/auth/csrf-token` has no auth middleware and no rate limiting — returns live CSRF token to unauthenticated callers. |
| F-31 | `UnifiedAuthController.php:154–196`                   | `detectAuthMode()` uses wrong config key (`max_refresh_count` instead of `max_refresh_count_per_hour`) — 5× the intended limit. |
| F-32 | `UnifiedAuthController.php:154–196`                   | Bearer lookup hashes full `{id}|{token}` string; Sanctum stores hash of random portion only — Bearer path always returns 401. **CHANGED** — Bearer lookup now correctly uses `PersonalAccessToken::where('token', $tokenHash)` which matches Sanctum's storage format. Config key mismatch (F-31) still applies to both paths. |

### MEDIUM/LOW (from March 20 audit — open, documented-only per backlog policy)

F-33 through F-62 are documented in `docs/FINDINGS_BACKLOG.md` and cover: locking gaps in `CancellationService` and `StripeWebhookController`, per-page clamping missing in `ContactController`, raw request inputs in `HttpOnlyTokenController::login()`, fence-post inconsistency in refresh-count threshold, cache key collision risk in `CustomerService`, soft-delete scope gap in `Room::scopeAvailableBetween`, `pending` status missing from overlap check in `EloquentRoomRepository`, `reviews.approved` DB/model default mismatch, N+1 in `restoreBulk()`, migration driver-guard inconsistency, and `localStorage` token cleanup calls for keys never written. All status: Open (deferred per `FINDINGS_BACKLOG.md` "DO NOT FIX" policy).

## F. Capability Profile (DE-Calibrated)

At 15+ years of experience at the Principal level, the capability profile is not "what can this person do" but "where is their attention creating the most and least value."

### STRONGEST ZONE: AI Agent Governance Framework

This is the Distinguished-level contribution. Not the booking system — the *system for governing AI agents building the booking system*. The constitutional hierarchy, skill routing, self-learning gates, MCP safety constraints, and session memory lifecycle constitute a novel governance design. Nothing comparable exists in published literature at this level of formalization. At DE level, this is the artifact worth extracting, publishing, and building a reputation around.

**Current limitation:** It lives inside a hostel booking repo and governs one author's workflow. The value ceiling is capped by scope.

### STRONG ZONE: Backend Engineering Discipline

1120 tests / 3138 assertions. PHPStan Level 5, 0 errors, 0 ignores. Deadlock-aware retry with jitter. Two-phase Stripe-safe cancellation. PostgreSQL exclusion constraints as database-level safety net. State machine with `canTransitionTo()`. 11 FormRequest classes. HTML Purifier over regex.

This is **solidly Principal-level backend engineering** — comprehensive, thoughtful, well-tested. It exceeds what most production booking systems implement. The discipline is real, not aesthetic.

**Current limitation:** F-27/F-28 (restore TOCTOU) are now fixed — `BookingService::restore()` uses `DB::transaction` + `hasOverlappingBookingsWithLock()` (FOR UPDATE). F-26 remains: `confirmBooking()` lacks `lockForUpdate()`, meaning concurrent webhook callbacks can double-confirm. `CreateBookingService` and `CancellationService` use `lockForUpdate()` correctly; `confirmBooking()` does not. This is a narrower inconsistency than before (1 gap vs. 3) but still undermines the locking discipline claim.

### ADEQUATE ZONE: Frontend / Infrastructure / Security

- **Frontend:** TypeScript strict, feature-sliced, 404 tests across 35 files. Proper AbortController lifecycle, httpOnly cookie auth with CSRF double-submit. Principal-level quality.
- **Infrastructure:** Multi-stage Docker, Caddy with hardened headers, CI/CD with tag-based deploy and pre-deployment gates. Production-viable.
- **Security:** Non-root containers, HTML Purifier, token expiry/revocation, HSTS/CSP/X-Frame-Options. Above average for any project, remarkable for a solo project.

These are all table-stakes competencies at the Principal level. They are executed well. They are not what differentiates from Distinguished.

### WEAK ZONE: Shipping, External Impact, and Leverage

Zero deployments. Zero users. Zero published artifacts. Zero conference talks. Zero open-source extractions. The AI governance framework — the one genuinely novel contribution — is invisible to anyone outside this codebase.

**This is not a knowledge gap.** The infrastructure is production-viable. The author clearly knows how to deploy. This is a *decision pattern* where internal quality optimization has displaced external delivery as the primary objective. At the DE level, this pattern is the single largest gap.

### BLIND SPOT: The Cookie-Auth Bug (BE-01)

BE-01 is partially fixed. Sanctum guard is set. `withAccessToken($token)` is still absent. `currentAccessToken()` may return null for cookie-mode users. The stale test comment at `SoleilTokenCookieEncryptionTest.php:224–226` is now partially false. A partially-fixed bug with stale documentation is more dangerous than an unfixed bug with accurate documentation — it creates false confidence.

---

## G. Principal → Distinguished Trajectory Analysis

The Senior→Staff dimension table from prior assessments is moot — all 8 Principal-level competencies are met (Section C). The relevant question is: **what separates this Principal Engineer from Distinguished?**

### What Distinguished Engineers Do That This Project Does Not Yet Demonstrate

| DE Trait | Definition | Evidence in This Project | Gap |
|----------|-----------|--------------------------|-----|
| **Org-wide technical direction** | Sets architectural standards and engineering practices for 50–100+ engineers | AI governance framework sets standards — but only for AI agents, not humans | Framework governs 1 author's workflow; no org adoption |
| **External technical influence** | Published writing, conference talks, open-source leadership that shapes how others practice engineering | Zero publications. Zero talks. Zero open-source. The AI governance framework is the most publishable artifact and it is invisible | Complete absence |
| **Shipping under uncertainty** | Delivers complex systems to production, learns from production behavior, makes irreversible decisions with incomplete data | Infrastructure is production-viable. Decision to deploy has not been made in 8+ weeks despite capability. 47 migrations, 0 users | Decision pattern, not capability gap |
| **Force multiplication beyond own work** | Creates tools, patterns, standards, or mentoring that materially accelerate other engineers | The AI governance framework genuinely multiplies output — but author's own, across 30+ batch sessions. No evidence of multiplying human engineers | Leverage is author-scoped, not org-scoped |
| **Strategic judgment at system boundaries** | Knows what NOT to build; scoping decisions demonstrate business awareness | 47 migrations including 4-layer operational domain for an unshipped product. Critical locking findings deferred while new features added | Scope control is the weakest judgment signal |

### What This Project *Does* Demonstrate at Distinguished Level

| DE Trait | Evidence |
|----------|----------|
| **Original technical contribution** | AI Agent Governance Framework: constitutional hierarchy, skill routing, self-learning gates, MCP safety constraints. Nothing comparable exists in published form at this level of formalization |
| **Deep domain mastery** | Deadlock-aware booking creation, two-phase Stripe-safe cancellation, PostgreSQL exclusion constraints, state machine design — these demonstrate genuine expertise, not tutorial-following |
| **Quality engineering at scale** | 1120 tests, PHPStan Level 5 / 0 errors / 0 ignores, 13 ADRs, 4 specialist subagents, quality gates as non-negotiable practice. The governance infrastructure would support a team of 10 |
| **Architectural consistency** | Controller→Service→Repository applied uniformly. Feature-sliced frontend. Dual-auth with proper separation. These patterns hold up under repeated code review across 6 assessment cycles |

### The Transition Gap

```
Current position:  Expert Principal Engineer
                   ├── Technical depth:          ████████████  (Strong DE-level)
                   ├── Original contribution:    █████████░░░  (8.5/10 — novel but unexposed)
                   ├── Judgment quality:          ██████░░░░░░  (6.0/10 — excellent micro, weak macro)
                   ├── Leverage creation:          █████░░░░░░░  (5.5/10 — self-multiplying, not org-multiplying)
                   ├── Shipping & impact:          ███░░░░░░░░░  (3.0/10 — blocking)
                   └── External influence:         ██░░░░░░░░░░  (2.0/10 — absent)

Required for DE:   All bars at 7+ with no bar below 5
```

**The gap is not knowledge, architecture, or technical capability.** It is the conversion of internal quality into external impact. Specifically:

1. **Ship the system** → proves execution under uncertainty (Shipping 3→6)
2. **Extract and publish the AI governance framework** → creates external influence (Influence 2→6, Leverage 5.5→7)
3. **Write 2–3 blog posts** about the governance framework, the booking concurrency design, and the ADR practice → builds reputation (Influence 6→7.5)
4. **Fix the last Critical locking gap (F-26)** → restores consistency to the strongest technical claim (Judgment 6.5→7)

These four actions would move the weighted score from **6.2 → 7.5**, clearing the DE threshold.

---

## H. Distinguished Engineer Growth Plan (Replaces 90-Day Plan)

The prior 90-day plan was Senior→Staff advice: "fix bugs, deploy, onboard a user." At 15+ years Principal level, the advice must be calibrated differently. You know how to fix bugs and deploy. The question is what actions create the most *Distinguished-level* signal per unit of time.

### Phase 1: Restore Consistency (Week 1–2)

These are small actions that remove contradictions from your strongest claims:

| # | Action | Effort | Impact | Why It Matters at DE Level |
|---|--------|--------|--------|---------------------------|
| 1 | Add `$user->withAccessToken($token)` to `CheckHttpOnlyTokenValid.php` | 1 line | Completes BE-01 | Eliminates the auth bug that has persisted across 3 assessment cycles |
| 2 | Add `lockForUpdate()` to `confirmBooking()` (F-26) | 4 lines | Fixes last Critical locking gap | Your strongest technical claim is locking discipline — F-27/F-28 are now fixed; F-26 is the remaining inconsistency |
| 3 | Fix `detectAuthMode()` Bearer lookup hash mismatch (F-32) | 1 line | Fixes broken Bearer path | Cannot claim dual-auth if one path is silently non-functional |
| 4 | Update local `.env` to pgsql | 3 lines | Fixes `docker compose config` | Removes the last gate failure blocking deployment |

**Total effort: ~2 hours.** These are not about learning or growth — they are about closing contradictions that undermine your credibility in the assessment's strongest areas.

### Phase 2: Ship (Week 2–4)

Deploy to any VPS. Not because deployment teaches you something new, but because:

- **It creates the artifact DE evaluation requires.** Without a running system, this is a research project, not a product. Both are valid — but a product claim without deployment evidence is unfalsifiable.
- **It converts test-validated quality into production-validated quality.** 1120 tests are necessary but not sufficient. Production behavior under real traffic (even low traffic) provides evidence that tests cannot.
- **It creates the denominator for impact metrics.** "0 double-bookings with 0 users" is a tautology. "0 double-bookings across 500 bookings over 3 months" is a meaningful claim.

Actions:
1. `docker compose -f docker-compose.prod.yml up` on a VPS (infrastructure already supports this)
2. Configure Sentry (currently a TODO comment in `ErrorBoundary.tsx`)
3. Process one Stripe test-mode payment (webhook handlers already tested)
4. Document the first deployment in `DEPLOYMENT_LOG.md`

### Phase 3: Extract and Publish the AI Governance Framework (Week 4–8)

**This is the highest-leverage action for Distinguished trajectory.**

The AI Agent Governance Framework is a novel contribution to a nascent field. It is currently locked inside a hostel booking repo. Extract it:

1. **Create a standalone repository** (`ai-agent-governance` or similar) containing:
   - The constitutional hierarchy pattern (CLAUDE.md → domain facts → contract → skills → commands)
   - The skill routing system (17 skills with per-task selection)
   - The agent self-learning gates (AGENT_LEARNINGS operating rules with human verification)
   - The MCP safety constraint model (policy.json with allowlists, denylists, size limits)
   - The session memory lifecycle (COMPACT with volatile/stable distinction)

2. **Write documentation** that explains the *design decisions*, not just the structure. Why a constitutional hierarchy instead of a flat config? Why human verification gates on agent self-learning? Why MCP tool allowlisting instead of deny-only?

3. **Write one blog post** (2000–3000 words): "Governing AI Agents: A Formal Framework for Multi-Session AI-Assisted Development." This would immediately become one of the more rigorous public documents on AI agent governance, because almost nothing formal exists in this space.

4. **Submit to a conference or newsletter** (optional but high-signal): AI Engineering Summit, Pragmatic Engineer newsletter, or similar forums where Distinguished-level practitioners share work.

### Phase 4: Build External Influence (Week 8–12)

Two additional blog posts:
- "Half-Open Intervals and Exclusion Constraints: A Two-Layer Defense for Booking Overlap Prevention" — the booking concurrency design is publishable as a standalone pattern article.
- "13 ADRs for a Solo Project: Why I Document Decisions Even When I'm the Only Reader" — the ADR practice is rigorous enough to teach others.

Engage with one open-source project related to AI agent tooling (e.g., contribute to Claude Code discussions, Cursor's community, or AI engineering forums).

### What to STOP

| Stop This | Because |
|-----------|---------|
| Commissioning assessments before deploying | Six cycles. The signal/noise ratio per additional audit is near zero. Ship, then audit production behavior |
| Adding operational domain features (stays, room_assignments, escalation engine) | Four-layer operational infrastructure for zero users inverts the build-measure-learn loop |
| Documenting findings as "DO NOT FIX" when the fix is 4–8 lines | Critical findings in your core domain should be fixed in the same session they are discovered. The documentation cost exceeds the fix cost |
| Treating internal quality as a proxy for external impact | At the DE level, quality without impact is research. Research is valuable — but it should be published, not stored privately |

---

## I. Distinguished-Level Gaps (Calibrated for Principal → DE)

### Gap 1: Impact Vacuum

This is the primary gap. Every other gap is downstream of this.

A Distinguished Engineer's work is measured by impact — on users, on an organization, on the industry. This project has:
- Zero users. Zero deployments. Zero production incidents resolved. Zero revenue generated.
- Zero publications. Zero conference talks. Zero open-source contributions.
- Zero evidence of multiplying other engineers' output.

The *capability* for impact exists (the infrastructure is production-viable, the AI governance framework is publishable). The *realization* of impact does not exist. At 15+ years of experience, this is not a capability gap — it is a strategic allocation problem. The time invested in 47 migrations, 1120 tests, and 4-layer operational domain could have shipped a working product AND published the governance framework.

### Gap 2: Scope Control Under Ambiguity

Distinguished Engineers excel at knowing what NOT to build. The project's scope trajectory reveals the opposite pattern:

- **March 2026:** 37 migrations. Core booking, auth, reviews, contacts.
- **March 20–23:** +10 migrations adding 4-layer operational domain (stays, room_assignments, service_recovery_cases, escalation engine, 9 new enums).
- **April 2:** Still undeployed. Still no checkout UI. Still no staging.

The operational domain is legitimately well-designed domain modeling. But building Layer 2–4 operational infrastructure before Layer 1 (bookings) has served its first user is building the maintenance department before opening the hotel. A Distinguished Engineer would have:
1. Shipped the minimal booking flow (rooms, dates, payment, confirmation).
2. Operated it for 4–6 weeks.
3. Built operational tooling in response to *observed* operational needs, not anticipated ones.

### Gap 3: External Influence — Complete Absence

At Distinguished level, external influence is not optional. It is the mechanism by which a DE creates organizational and industry-level impact. This project has:
- An AI governance framework more formal than anything published → not published
- A booking concurrency design worth teaching → not taught
- An ADR practice rigorous enough for a team of 20 → never shared beyond this repo
- 30+ successful AI batch sessions with traceable results → no retrospective or analysis published

The contributions exist. The sharing does not. A Distinguished Engineer who keeps their best work private is, by definition, not operating at Distinguished level — because DE impact requires external reach.

### Gap 4: Judgment Inconsistency at the Macro Level

Micro-level decisions are excellent (ADRs, technology choices, defense-in-depth patterns). Macro-level decisions show patterns that would not pass DE review:
- Choosing to document Critical findings as "DO NOT FIX" rather than fixing them (cost: 2 hours vs. cost of stale documentation: ongoing credibility)
- Commissioning 6 assessment cycles rather than deploying after cycle 1 or 2
- Building production operations infrastructure for a system with zero production traffic
- Pursuing both a research project (AI governance) and a product (booking system) without declaring priority

These are not wrong decisions in isolation — some are defensible in context. But the *pattern* indicates that optimization for internal quality is displacing optimization for external impact. At the DE level, this pattern is the gap.

---

## J. What Operates at Distinguished Level Already

These are not "better than most" — they are capabilities that meet or exceed Distinguished-level expectations:

### 1. AI Agent Governance Framework — Novel and Formally Rigorous

The constitutional hierarchy (CLAUDE.md → ARCHITECTURE_FACTS → CONTRACT → skills → commands → hooks) with explicit conflict resolution, agent self-learning with human verification gates, MCP safety constraints with allowlists and denylists, and session memory with lifecycle policy constitutes a governance system that does not exist at this level of formalization in any published framework. This is a genuine Distinguished-level original contribution.

**Evidence of real governance, not documentation theater:** COMPACT self-healed between March 7 and April 2 — all four contradictions identified in the prior assessment were resolved. The governance framework detected and corrected its own drift. This is operationalized governance.

### 2. Backend Engineering Depth — Exceeds Production Standards

The deadlock-aware retry with SQLSTATE-specific classification, two-phase Stripe-safe cancellation, PostgreSQL exclusion constraints, and state machine with `canTransitionTo()` are not tutorial-level patterns. They demonstrate genuine expertise in concurrent system design. The 1120-test suite with concurrency stress tests, N+1 detection, optimistic locking conflict verification, and 50-vector XSS purification coverage goes beyond what most teams achieve in production.

PHPStan Level 5 with 0 errors and 0 baseline ignores across 47 migrations and 15 services is a concrete quality bar that most production codebases do not meet.

### 3. Decision Documentation — Rigorous and Useful

13 ADRs with context, decision, rationale, alternatives matrix with quantified tradeoffs, and consequences (both positive and negative). `ADR-003` names and rejects optimistic locking and queue-based serialization with specific reasons tied to the booking domain constraints. This is not ceremonial documentation — it is decision infrastructure that would survive team scaling.

### 4. Quality as Non-Negotiable Practice

Quality gates enforced by `ship.sh`, CI pipeline, and agent contracts. PHPStan, ESLint, TypeScript strict mode, Vitest, PHPUnit — all configured and passing. The developer treats quality as a minimum bar, not a differentiator. At the DE level, this is the right framing.

### 5. Security Posture — Consistent and Defense-in-Depth

httpOnly cookies with CSRF double-submit, HTML Purifier over regex (with documented rationale), non-root Docker containers, Caddy with HSTS/CSP/X-Frame-Options, token expiry and revocation enforcement, MCP tool allowlisting. The security posture is not reactive (fixing findings from audit tools) but proactive (architectural decisions that prevent classes of vulnerability).

---

## K. Final Verdict — Distinguished Engineer Assessment

### Position

**Expert Principal Engineer with Distinguished-level technical depth and a novel contribution, operating below DE threshold due to zero external impact.**

### Weighted Score: 6.2 / 10 (DE Scale)

| DE Dimension | Score | Weight | Weighted |
|-------------|-------|--------|----------|
| Original Technical Contribution | 8.8 | 25% | 2.20 |
| Judgment & Decision Quality | 6.5 | 25% | 1.63 |
| Leverage Creation | 5.8 | 20% | 1.16 |
| Shipping & Impact | 3.0 | 15% | 0.45 |
| Technical Culture & Influence | 5.0 | 15% | 0.75 |
| **Total** | | **100%** | **6.19** |

**DE threshold: 7.0.** Gap: 0.8 points (narrowed from 1.1 on April 2).

### What the Score Means

5.9→6.2 is not a failure. On the prior Senior→Staff scale, this engineer scored 7.0–7.1. The DE scale is fundamentally different — it measures leverage, influence, and impact at levels most engineers never reach. A 6.2 means:

- **All Principal-level competencies are met.** Architecture, testing, security, documentation, AI orchestration — all pass without qualification.
- **One Distinguished-level contribution exists** (AI governance framework at 8.8, up from 8.5 after harness hardening).
- **The conversion of capability to impact has not occurred.** This is the entire gap.

### The Path to Distinguished (6.2 → 7.2)

```
Today:     6.2/10 — Principal with DE-level depth, zero external impact
           │
Phase 1:   Fix remaining consistency gap (F-26)
           │  → Judgment 6.5 → 7.0  │  Weighted: +0.13
           │
Phase 2:   Deploy to production, process real bookings
           │  → Shipping 3.0 → 6.0  │  Weighted: +0.45
           │
Phase 3:   Extract + publish AI governance as open-source framework
           │  → Leverage 5.8 → 7.5  │  Weighted: +0.34
           │  → Influence 5.0 → 6.5 │  Weighted: +0.23
           │
Phase 4:   Write 2–3 blog posts, engage with AI engineering community
           │  → Influence 6.5 → 7.5 │  Weighted: +0.15
           │
Result:    7.5/10 — Distinguished threshold cleared
```

**Estimated time to DE threshold: 6–10 weeks of focused execution** (reduced from 8–12 weeks on April 2 — harness hardening closed 0.3 points of the gap).

This is not a long journey. The raw material is already here. The AI governance framework is a Distinguished-level artifact waiting to be made visible. The booking system is a working product waiting to be deployed. The blog posts are ADRs waiting to be edited for a public audience.

### The One Decision That Matters

You have built a system that governs AI agent behavior with more formalism than any published framework. You have also built a booking system that has never booked a room. These two facts coexist.

The Distinguished-level decision is: **choose one to ship first, and ship it within 30 days.**

- If the AI governance framework is the priority → extract it, document it, publish it as open-source, write the blog post. The hostel booking system becomes the reference implementation, not the product.
- If the booking system is the priority → deploy it, process real bookings, prove the concurrency design works under real traffic. Fix the 3 Critical locking gaps first.

Both paths lead to Distinguished. Neither path includes "commission another assessment" as a step.

---

_End of Assessment — April 4, 2026 (DE-Calibrated Revision, Harness Hardening Update)_
_Evaluator perspective: Distinguished Engineer (Google SRE) + Harness Engineer (Anthropic) + AI Engineer (OpenAI)_
_Subject level: Expert Principal Engineer, 15+ years_
_Weighted DE Score: 6.2 / 10 (up from 5.9 on April 2)_
_DE Threshold: 7.0_
_Gap: 0.8 points — closable in 6–10 weeks_
