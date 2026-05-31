# Architecture Facts — Soleil Hostel

Domain invariants last verified 2026-04-18 (documentation governance audit; ref commits `17a4880`, `39cba7a`, `a86f597`). See [AUDIT_2026_02_21.md](../AUDIT_2026_02_21.md) for the original 2026-02-21 audit evidence.

## Booking Domain

### Overlap Prevention (Two-Layer Defense)

**Layer 1 — Application (PHP):**
- Half-open interval: `[check_in, check_out)` — same-day turnover is valid
- Overlap query: `existing.check_in < new.check_out AND existing.check_out > new.check_in`
- Active statuses for overlap: `pending`, `confirmed` only
- `lockForUpdate()` in transaction for booking creation/cancellation
- Source: `CancellationService.php`, `Booking.php` model scope

**Layer 2 — Database (PostgreSQL):**

```sql
ALTER TABLE bookings
ADD CONSTRAINT no_overlapping_bookings
EXCLUDE USING gist (
    room_id WITH =,
    daterange(check_in, check_out, '[)') WITH &&
)
WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL);
```

Source: `2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php`

Requires: `CREATE EXTENSION IF NOT EXISTS btree_gist;`

### Booking Business Dates

Booking business dates are evaluated in hostel-local civil time (`Asia/Ho_Chi_Minh` by default). UTC remains correct for stored instants, logs, audit timestamps, Stripe/webhook timestamps, and DB timestamp columns, but booking min-date, check-in/check-out validation, started-state, cancellation-window, review eligibility, and admin booking date filters must use hostel-local date semantics.

### Soft Delete & Cancellation Audit

- `deleted_at` + `deleted_by` (FK to users, ON DELETE SET NULL)
- `cancelled_at` + `cancelled_by` + `cancellation_reason`
- Soft-deleted bookings do NOT block availability (constraint filters `deleted_at IS NULL`)

### Payment / Refund Columns

On `bookings` table: `amount`, `payment_intent_id`, `refund_id`, `refund_status`, `refund_amount`, `refund_error`.

Payment-state columns: `payment_policy` (`PaymentPolicy`: `prepaid` | `authorize_then_capture` | `pay_at_property` | `not_required`) and `payment_status` (`PaymentStatus`, 13 states), plus capture tracking `amount_capturable`, `amount_received`, `authorized_at`, `paid_at`, `capture_due_at`. Confirmation is gated by `Booking::paymentAllowsConfirmation()`. `refund_status` is the **closed** `RefundStatus` projection `{pending, succeeded, failed}` — a plain string (intentionally not enum-cast); raw Stripe statuses are normalized via `RefundStatus::tryFromStripe()` (fails closed). FSM detail: `docs/backend/features/BOOKING.md` §Payment State Machine.

Deposit lifecycle also lives on `bookings`: `deposit_amount`, `deposit_collected_at`, `deposit_status`.
`deposit_amount` is operational liability tracking only and is **not** recognized revenue at collection time.

### bookings.refund_id semantics

`bookings.refund_id` stores the most-recent Stripe refund ID observed for the booking.

It is a **latest-pointer** field, not an authoritative refund ledger. Under partial refunds (one charge → multiple Stripe `refund` objects), this value is overwritten by each subsequent refund. Code that needs refund history, total refunded amount, full-refund detection, partial-refund classification, or reconciliation MUST read from `stripe_refund_events`.

Invariants:
- `bookings.refund_id` = latest Stripe refund pointer for operational lookup.
- `stripe_refund_events` = authoritative refund history ledger (UNIQUE on `stripe_refund_id` is the durable replay guard).

Sources: `app/Http/Controllers/Payment/StripeWebhookController.php` (`handleChargeRefunded`), `app/Models/StripeRefundEvent.php`, migration `2026_04_29_000003_create_stripe_refund_events_table.php`.

### Booking Status

**VARCHAR column, NOT a PostgreSQL ENUM.** Values enforced at application level (`App\Enums\BookingStatus`) AND DB CHECK constraint `chk_bookings_status` on PostgreSQL (migration `2026_03_17_000003`):
- `pending`, `confirmed`, `refund_pending`, `cancelled`, `refund_failed`

### Pending TTL (Auto-Expiry Invariant)

Pending bookings block availability via `Booking::ACTIVE_STATUSES`. Without a TTL they would hold inventory indefinitely. The system enforces expiry:

- Config: `config('booking.pending_ttl_minutes')` — default **30 minutes** from `created_at` (not `updated_at`: the TTL is a commitment window, not a heartbeat).
- Config: `config('booking.pending_expiry_batch_size')` — default **100**; protects a cold-start backlog from DoS'ing the DB.
- Job: `App\Jobs\ExpireStaleBookings` — scheduled every 5 minutes via `routes/console.php` (`withoutOverlapping()`, `onOneServer()`).
- Semantics: only `status = PENDING` is considered. Each row is re-read under `lockForUpdate()` inside its own transaction; a concurrent `confirm()` that promoted the booking to `confirmed` wins and the expire is skipped. Expired rows transition PENDING → CANCELLED with `cancellation_reason = 'expired'` and `cancelled_by = null`; `BookingCancelled` event fires so cache invalidation runs.
- Source: `backend/app/Jobs/ExpireStaleBookings.php`, `backend/config/booking.php`, `backend/routes/console.php`.

**Implicit kill switch**: setting `BOOKING_PENDING_TTL_MINUTES=0` disables the job (it logs a warning and returns). Use this to pause expiry during incident response without removing the schedule.

### Terminal-State Immutability

Terminal booking statuses — `cancelled`, `refund_failed` in a terminal posture, and any soft-deleted booking — must not be mutated back into an active posture except by the explicit restore path (`BookingService::restore()`, which itself is admin-only and wrapped in `DB::transaction()` with `hasOverlappingBookingsWithLock()`).

Applies to:
- Cancellation service, job, and proposal confirmation paths all transition via the state machine — never `UPDATE bookings SET status = 'pending'` directly.
- `ExpireStaleBookings` only runs on `status = PENDING` and re-checks under lock — it cannot resurrect a cancelled booking.

**Cancellation idempotency (BL-6)**: an owner / admin retrying cancellation on an already-cancelled booking is intentionally authorized by `BookingPolicy::cancel`, and `CancellationService::cancel` short-circuits the terminal state to a no-op. The no-op must not dispatch `BookingCancelled`, queue `ProcessDepositRefund`, call Stripe, mutate deposit / availability state, or overwrite cancellation audit columns. Non-owners remain blocked at the policy ownership gate (the idempotency branch sits after it). Regression: `tests/Feature/BookingCancellationTest.php::test_cancelling_already_cancelled_booking_is_idempotent_noop` and companion policy tests.

### Proposer-Binding Invariant (AI Proposals)

`ProposalConfirmationController::decide` MUST reject any request where the authenticated user is not the original proposer. This is enforced at the cache envelope:

- Producer (`AiOrchestrationService`) writes `proposer_user_id` into the cache payload alongside `action_type` and `proposed_params`.
- Consumer (`ProposalConfirmationController::decide`) reads `proposer_user_id` from the envelope. If absent or not equal to `(int) $request->user()->id`, the controller **404s** (not 403 — the contract is "this hash is not yours to decide, and we will not acknowledge its existence").
- Legacy cache entries written before F-67 lack the field; those are also treated as unbound → 404.
- Log: `ai` channel, event `Proposal decide blocked by proposer-binding check`.
- Source: `backend/app/Http/Controllers/ProposalConfirmationController.php:65-74`, `backend/app/AiHarness/Services/AiOrchestrationService.php`, test `backend/tests/Feature/AiHarness/ActionProposalTest.php`.

### Cancellation Ownership: Defense-in-Depth

Cancellation ownership is enforced at **two independent layers**:

1. **Policy layer** — `BookingPolicy::cancel` (route gate via `authorize()`): non-admin actors may only cancel their own booking.
2. **Service layer** — `CancellationService::validateCancellation()` (`backend/app/Services/CancellationService.php:174-188`, ownership check at `:176-178`): rechecks `! $actor->isAdmin() && (int) $booking->user_id !== (int) $actor->id` and throws `BookingCancellationException::unauthorized()`.

The service-layer check is the **last line of defense** for alternate entry points (proposal confirmation, queue jobs, artisan commands) that may bypass the HTTP policy gate. It must not be removed without a matching guarantee at every caller.

### Booking Model Relationships

`Booking` has the following Eloquent relationships (verified `Booking.php`):
- `room()` — BelongsTo `Room`
- `location()` — BelongsTo `Location` (denormalized)
- `user()` — BelongsTo `User`
- `cancelledBy()` — BelongsTo `User` (via `cancelled_by`)
- `stay()` — HasOne `Stay` (nullable; exists once stay tracking begins — lazy-created at confirmation)
- `review()` — HasOne `Review` (via `booking_id`)

## Operational Domain (Four-Layer Model)

Added 2026-03-20. Full specification: `docs/DOMAIN_LAYERS.md`.

| Table | Domain | Purpose |
|-------|--------|---------|
| `bookings` | Commercial | Reservation state machine |
| `stays` | Operational | Occupancy lifecycle per booking (`stay_status`) |
| `room_assignments` | Allocation | Physical room assignment history per stay |
| `service_recovery_cases` | Incident | Service failure incidents, compensation, and settlement tracking audit trail |

### Key Invariants

- `bookings.status` = commercial reservation state only; `stays.stay_status` = operational occupancy lifecycle
- `bookings.deposit_*` = operational deposit lifecycle only; not authoritative accounting
- In-house guest detection: `stays.stay_status IN ('in_house', 'late_checkout')` — do NOT use a static flag on `users`
- One stay per booking: UNIQUE `booking_id` on `stays`
- Active room assignment: partial unique index `UNIQUE (stay_id) WHERE assigned_until IS NULL` (PG only)
- `rooms.readiness_status` = canonical physical room state (`ready`, `occupied`, `dirty`, `cleaning`, `inspected`, `out_of_service`)
- `rooms.room_type_code` = equivalence key; `rooms.room_tier` = upgrade comparability, both nullable until operators populate them
- All monetary fields in `service_recovery_cases` (`refund_amount`, `voucher_amount`, `cost_delta_absorbed`) stored in **cents** (BIGINT)
- `service_recovery_cases.settlement_*` = operational settlement tracking only; not authoritative accounting / GL
- Booking overlap logic (`no_overlapping_bookings` constraint, `Booking.php`, `CancellationService.php`) is **orthogonal** to and untouched by the four-layer model

Blocked-arrival escalation path:
1. equivalent room, same location
2. complimentary upgrade, same location
3. equivalent room, another location in chain
4. upgrade room, another location in chain
5. no internal candidate → external/manual review

Steps 3-5 require operator approval before any assignment or recovery write is performed.

### Stay Creation: Two-Path Strategy

**Forward path** (new bookings): `BookingService::confirmBooking()` calls `Stay::firstOrCreate()` inside the confirmation transaction. If stay creation fails, the confirmation rolls back.

**Backfill path** (historical bookings): `php artisan stays:backfill-operational` creates `expected` stays for `confirmed` bookings with `check_out >= today` and no existing stay row. Idempotent; safe to re-run. `--dry-run` flag available.

Source: `app/Console/Commands/BackfillOperationalStays.php`, `app/Services/BookingService.php`
Canonical operational note and source-of-truth boundaries: `docs/DOMAIN_LAYERS.md`.

### Stay Domain Enums

`App\Enums\StayStatus`: `expected`, `in_house`, `late_checkout`, `checked_out`, `no_show`, `relocated_internal`, `relocated_external`

Stay lifecycle guard: `App\Enums\StayStatus::canTransitionTo()` + `App\Models\Stay::transitionTo()`

`App\Enums\AssignmentType`: `original`, `equivalent_swap`, `complimentary_upgrade`, `maintenance_move`, `overflow_relocation`

`App\Enums\AssignmentStatus`: (see source)

`App\Enums\IncidentType`, `App\Enums\IncidentSeverity`, `App\Enums\CaseStatus`, `App\Enums\CompensationType`: see `app/Enums/` for values
`App\Enums\RoomReadinessStatus`, `App\Enums\DepositStatus`, `App\Enums\SettlementStatus`, `App\Enums\BlockerType`, `App\Enums\ResolutionStep`: operational PM/BM support enums

### Migrations

- `2026_03_20_000001` — creates `stays` table
- `2026_03_20_000002` — creates `room_assignments` table
- `2026_03_20_000003` — creates `service_recovery_cases` table
- `2026_03_23_000001` — adds room readiness fields to `rooms`
- `2026_03_23_000002` — adds room classification fields to `rooms`
- `2026_03_23_000003` — adds deposit lifecycle fields to `bookings`
- `2026_03_23_000004` — adds settlement lifecycle fields to `service_recovery_cases`
- `2026_03_23_000005` — corrects `reviews.room_id` FK delete policy to RESTRICT
- `2026_04_03_084257` — creates `email_verification_codes` table (SHA-256 `code_hash`, `attempts`/`max_attempts`, `expires_at`, `consumed_at`, `last_sent_at`; FK → `users` CASCADE)

## Concurrency Control

### Optimistic Locking
- `lock_version` column on `rooms` (NOT NULL, default 1) — source: migration `2025_12_18_200000`
- `lock_version` column on `locations` (default 1) — source: migration `2026_02_09_000001`
- Compare-and-swap semantics in `EloquentRoomRepository`, `RoomService`

### Pessimistic Locking
- `SELECT ... FOR UPDATE` via `lockForUpdate()` in booking/cancellation flows
<!-- SYNC-EDIT: DRIFT-01 F-01 -->
<!-- SOURCE: backend/app/Models/Booking.php:467, backend/app/Services/CancellationService.php:202,336 -->
- Source: `CancellationService.php:202,336` (`lockForUpdate` in `transitionToRefundPending` / `finalizeCancellation`), `Booking.php:467` (`scopeWithLock`)

## Authentication

### Dual Mode (Both Active)
- **Bearer Token**: Standard Sanctum `Authorization: Bearer <token>` header
- **HttpOnly Cookie**: Custom cookie-based auth with `token_identifier` → `token_hash` DB lookup

### Custom Token Columns (personal_access_tokens)

Added across two migrations (`2025_11_20_000100` + `2025_11_21_150000`):

| Column | Type | Purpose |
|--------|------|---------|
| `token_identifier` | UUID, unique | Cookie-based token lookup |
| `token_hash` | string, indexed | Hash of identifier for fast lookup |
| `remember_token_id` | UUID, nullable | "Remember me" flow link |
| `type` | string, default `'short_lived'` | Token type (`short_lived` / `long_lived`) |
| `device_id` | UUID, nullable, indexed | Per-device token binding |
| `refresh_count` | integer, default 0 | Rotation tracking |
| `device_fingerprint` | string, nullable | Anti-theft device binding |
| `expires_at` | timestamp, nullable | Token expiration |
| `revoked_at` | timestamp, nullable | Token revocation |
| `last_rotated_at` | timestamp, nullable | Last rotation timestamp |

### Auth Enforcement
- Middleware checks: expiry, revocation, refresh abuse
- Controllers: `HttpOnlyTokenController`, `UnifiedAuthController`, `AuthController`
- Public CSRF bootstrap is Laravel Sanctum's `GET /sanctum/csrf-cookie`.
- Supplementary `GET /api/auth/csrf-token` is authenticated-only via `check_token_valid`; `throttle:csrf-token` is retained as secondary abuse control.

## Multi-Location

- `rooms.location_id` — NOT NULL, FK to locations (RESTRICT on delete) — location deletion blocked if rooms exist
- `bookings.location_id` — nullable, FK to locations (SET NULL on delete) — denormalized for analytics
- PostgreSQL trigger `trg_booking_set_location`: auto-sets `bookings.location_id` from `rooms.location_id` on insert/update
- `locations.is_active` gates room/booking visibility

## Enums

### user_role_enum (PostgreSQL ENUM)

```sql
CREATE TYPE user_role_enum AS ENUM ('user', 'moderator', 'admin');
```

PHP: `App\Enums\UserRole` (backed string enum). Default: `user`.

### room_status (NOT a PostgreSQL ENUM)

**The rooms `status` column is VARCHAR** (`$table->string('status')` in migration `2025_05_09_000000`). No `CREATE TYPE room_status` exists in migrations despite some docs claiming otherwise.

Application-level values remain inconsistent across the codebase (`available`, `occupied`, `maintenance`, `booked`, `active`). `rooms.status` is not the canonical physical readiness field.

## Reviews

- One review per booking: `reviews_booking_id_unique` constraint
- `booking_id` is NOT NULL (migration `2026_02_10_000002`)
- `room_id` is NOT NULL in schema and its FK is `ON DELETE RESTRICT` after the 2026-03-23 correction
- `approved` column: **DB default is `true`** (migration `2025_11_24_000000`); Eloquent `$attributes` sets `false`. Raw inserts (seeders, `DB::table`, queue jobs) bypass the model and silently auto-approve reviews. Divergence logged as **F-44** — pending migration fix.
- FK `reviews.booking_id → bookings.id` added via migration (F-09 fix, PR-3 2026-02-21)

## Key Indexes

See [DB_FACTS.md](../DB_FACTS.md) Section 4 for complete index listing.

Critical indexes:
- `idx_bookings_availability` on `(room_id, status, check_in, check_out)`
- `idx_bookings_active_overlap` partial on `(room_id, check_in, check_out)` WHERE active statuses (PG only)
- `idx_bookings_deleted_at`, `idx_bookings_soft_delete_audit`
- Token indexes: `token_hash`, `device_id`, `expires_at`, `revoked_at` (Laravel-generated names)

## Admin Audit Log

- `admin_audit_logs` table (append-only): `actor_id`, `action`, `resource_type`, `resource_id`, `ip_address`, `metadata` (JSON)
- Written by `AdminAuditService`; integrated into `AdminBookingController`, `RoomController`, `ReviewController` (IMPLEMENTED)
- Force-delete records pre-deletion snapshot in `metadata` for forensic recovery
- Source: RBAC Phase 2, migration `2026_03_12_000001`

## Admin Resources

<!-- SYNC-EDIT: DRIFT-01 F-09 -->
<!-- SOURCE: backend/routes/api/v1.php:80-85 -->
- Customer management: `GET /api/v1/admin/customers/*` (stats, index, show, bookings) — gated by `role:moderator` middleware — `App\Http\Controllers\Admin\CustomerController`

## RBAC Permission Baseline

Canonical permission matrix: [docs/PERMISSION_MATRIX.md](../PERMISSION_MATRIX.md)

Current enforcement status: PASS WITH FOLLOW-UPS. Room CUD and admin booking endpoints use defense-in-depth (route middleware + controller-level gate/policy). Moderator role is ACTIVE: gates admin booking READ routes (`role:moderator` middleware, v1.php) and customer management endpoints (`/api/v1/admin/customers/*`). Open follow-ups: 5 — see PERMISSION_MATRIX.md.

**Role Hierarchy Stability:** `isAtLeast()` at `User.php:139-151` uses level comparison. Adding or reordering roles silently shifts all HIERARCHY-DEPENDENT permissions. See [PERMISSION_MATRIX.md § Role Hierarchy Stability Warning](../PERMISSION_MATRIX.md) for the required change procedure.

## AI Harness Domain (Added 2026-04-09)

### Overview

The AI Harness is a safety-first LLM orchestration pipeline that mediates all interactions between the application and model providers. All AI endpoints are gated by a master kill switch (`AI_HARNESS_ENABLED`, default `false`).

Full architecture: `docs/HARNESS_ENGINEERING.md`. ADR: `docs/ADR-AI-BOUNDARY.md`. Threat model: `docs/THREAT_MODEL_AI.md`. Rollout/kill switch: `docs/ROLLOUT_AND_KILL_SWITCH.md`. Incident runbook: `docs/RUNBOOK_AI_INCIDENT.md`. Eval strategy: `docs/EVAL_STRATEGY.md`.

### Route Surface

All AI routes live in `backend/routes/api/v1_ai.php`, mounted under `/api/v1/ai` via `v1.php`.

| Endpoint | Auth | Middleware Stack | Controller |
|----------|------|-----------------|------------|
| `POST /api/v1/ai/{task_type}` | `check_token_valid` + `verified` | `throttle:10,1`, `ai_harness_enabled`, `ai_canary_router`, `ai_request_normalizer` | `AiController::handle` |
| `POST /api/v1/ai/proposals/{hash}/decide` | `check_token_valid` + `verified` | `throttle:5,1`, `ai_harness_enabled` | `ProposalConfirmationController::decide` |
| `POST /api/v1/ai/proposals/{hash}/shown` | `check_token_valid` + `verified` | `throttle:5,1`, `ai_harness_enabled` | `ProposalConfirmationController::shown` |
| `GET /api/v1/ai/health` | None | `ai_harness_enabled` | Inline closure |

> **Proposal decide throttle**: `throttle:5,1` is deliberately tighter than the `throttle:10,1` on the main task handler because `decide` is a confirmed-action surface — each POST can trigger a real booking create or cancellation via the service layer. Per-hash replay is already neutralised by `Cache::forget()` after the first decide. See `backend/routes/api/v1_ai.php:47-58` for the full rationale.

**Task types**: `faq_lookup`, `room_discovery`, `booking_status`, `admin_draft`

### 7-Layer Pipeline

```
L1  AiRequestNormalizer     → builds HarnessRequest DTO, maps TaskType → RiskTier
L2  ContextAssemblyService  → allowlisted sources, token budget, RBAC filtering
L3  ModelExecutionService   → provider selection, timeout ladder, circuit breaker
L4  PolicyEnforcementService → PII scan, injection heuristics, tool classification
L5  ToolOrchestrationService → READ_ONLY auto-exec, APPROVAL_REQUIRED → ToolDraft, BLOCKED → throw
L6  AiObservabilityService  → RequestTrace (17 fields), masked PII, cost estimation
L7  AiOrchestrationService  → top-level orchestrator composing L1–L6
```

### AI Safety Boundary

- **No autonomous writes**: AI models cannot create, update, or delete any record
- **APPROVAL_REQUIRED tools** return `ToolDraft` structs — never written to DB until human confirms
- **BLOCKED tools** throw `BlockedToolException` — fail-safe, unknown tools default to BLOCKED
- **Policy enforcement (L4) is authoritative** — prompt instructions are behavioral guidance only
- **Context is allowlisted** per `TaskType` — model cannot request sources outside its allowlist

### Booking Interaction (Phase 4+)

`ProposalConfirmationController` introduces a **third booking mutation entry point** (alongside `BookingController` and `AdminBookingController`):

- `executeBooking()` → delegates to `CreateBookingService` (same service layer, same invariants)
- `executeCancellation()` → delegates to `BookingService::cancelBooking()` (same service layer)
- Proposals are cached with a 64-char SHA-256 hash; user confirms/declines via `POST /api/v1/ai/proposals/{hash}/decide`
- All proposal events recorded to `ai_proposal_events` table + `ai` log channel

**Invariant preservation**: The controller delegates to existing service layer — it does NOT bypass `lockForUpdate()`, exclusion constraints, or status validation. All booking invariants from §Booking Domain above remain enforced.

**Proposer-binding** (F-67, 2026-04-18): every cache envelope carries `proposer_user_id`. `decide()` 404s on absence or mismatch. Service-layer ownership check in `CancellationService::validateCancellation` is the last line of defense for alternate callers — see §Proposer-Binding Invariant and §Cancellation Ownership: Defense-in-Depth above.

### AI Models & Tables

| Model | Table | Purpose |
|-------|-------|---------|
| `PolicyDocument` | `policy_documents` | Hostel policy content for FAQ grounding (UUID PK) |
| `AiProposalEvent` | `ai_proposal_events` | Audit trail for proposal confirm/decline decisions |

### AI Enums

`App\AiHarness\Enums\TaskType`: `faq_lookup`, `room_discovery`, `booking_status`, `admin_draft`
`App\AiHarness\Enums\RiskTier`: `low`, `medium`, `high`
`App\AiHarness\Enums\ResponseClass`: `answered`, `refused`, `fallback`, `error`
`App\AiHarness\Enums\ToolClassification`: `read_only`, `approval_required`, `blocked`
`App\AiHarness\Enums\ProposalActionType`: `suggest_booking`, `suggest_cancellation`

### AI Middleware (registered in `bootstrap/app.php`)

| Alias | Class | Purpose |
|-------|-------|---------|
| `ai_harness_enabled` | `AiHarnessEnabled` | Kill switch — returns 404 when `config('ai_harness.enabled')` is false |
| `ai_request_normalizer` | `AiRequestNormalizer` | Builds `HarnessRequest` DTO, validates task type |
| `ai_canary_router` | `AiCanaryRouter` | Percentage-based traffic routing per task type |

### AI Migrations

- `2026_04_09_000001` — creates `policy_documents` table (UUID PK, slug, title, content, category, language, is_active, version)
- `2026_04_11_000001` — creates `ai_proposal_events` table (user_id FK→users CASCADE, proposal_hash, action_type, user_decision, downstream_result)

### AI Observability

- Dedicated logging channel: `ai` (daily rotation, JSON format, 90-day retention)
- `SensitiveDataProcessor` masks PII before logging
- `RequestTrace` DTO captures 17 fields per AI request
- Nightly regression gate: `php artisan ai:eval --all-phases` (03:00, blocks deploy on failure)
- Config: `backend/config/ai_harness.php`

## DB Constraints Added (formerly backlog)

All four previously absent constraints added via migrations (audit v2, PR-2 + PR-3, 2026-02-21):
- `CHECK (check_out > check_in)` on bookings — **added** (2026-02-21 F-06 fixed)
- `CHECK (rating BETWEEN 1 AND 5)` on reviews — **added** (2026-02-21 F-07 fixed)
- `CHECK (price >= 0)` on rooms — **added** (2026-02-21 F-08 fixed)
- FK `reviews.booking_id -> bookings.id` — **added** (2026-02-21 F-09 fixed)

> **F-ID namespace note (updated 2026-04-19)**: the `F-06` above refers to the 2026-02-21 audit (CHECK `check_out > check_in`, Fixed). The AI-harness proposer-binding fix from 2026-04-18 was initially cited as "F-06 (2026-04-18)" during the documentation governance pass; it has since been promoted to **F-67** in `docs/FINDINGS_BACKLOG.md` to eliminate the ID collision. Historical WORKLOG entries and commit messages that still say "F-06 (2026-04-18)" refer to what is now F-67.

## DB Hardening (2026-03-17 / 2026-03-23)

FK delete policy hardening (migration `2026_03_17_000001`, PG-only, runtime-gated via `DB::getDriverName()`):
- `bookings.user_id → users.id`: CASCADE → **SET NULL** (booking history survives user deletion)
- `bookings.room_id → rooms.id`: CASCADE → **RESTRICT** (room deletion blocked if bookings exist)
- `reviews.user_id → users.id`: CASCADE → **SET NULL** (review survives user deletion)
- `reviews.room_id → rooms.id`: CASCADE → **RESTRICT** after correction in `2026_03_23_000005`

Correction on record: prior operator baseline incorrectly claimed `reviews.user_id` was already SET NULL. Source `2026_02_09_000000_add_foreign_key_constraints.php` confirms original was `onDelete('cascade')`.

Additional source correction:
- `reviews.room_id` is `NOT NULL` in the original table definition, so the interim `SET NULL` FK from `2026_03_17_000001` was internally inconsistent and has been corrected back to `RESTRICT`.

Additional CHECK constraints (PG-only, runtime-gated):
- `chk_rooms_max_guests CHECK (max_guests > 0)` — migration `2026_03_17_000002`
- `chk_bookings_status CHECK (status IN ('pending','confirmed','refund_pending','cancelled','refund_failed'))` — migration `2026_03_17_000003`
- `chk_rooms_readiness_status CHECK (readiness_status IN (...))` — migration `2026_03_23_000001`
- `chk_rooms_room_tier_positive CHECK (room_tier IS NULL OR room_tier > 0)` — migration `2026_03_23_000002`
- `chk_bookings_deposit_status CHECK (deposit_status IN ('none','collected','applied','refunded'))` — migration `2026_03_23_000003`
- `chk_src_settlement_status CHECK (settlement_status IN ('unsettled','partially_settled','settled','written_off'))` — migration `2026_03_23_000004`

Deferred:
- `rooms.status` DB CHECK — legacy room status values are still inconsistent across codebase; physical readiness is now enforced separately via `rooms.readiness_status`
- Legacy migration `2026_02_09_000000` uses `config('database.default')` gating (weaker than `DB::getDriverName()`); cleanup deferred

Test coverage: `FkDeletePolicyTest.php` (5 tests), `CheckConstraintTest.php` (3 tests — covers `chk_rooms_max_guests` only), plus PM/BM operational tests for room readiness, arrival resolution, financial lifecycle, and dashboard queries. <!-- SYNC-EDIT: DRIFT-06 F-02 -->
<!-- SOURCE: php artisan test output — 1047 passed, 2875 assertions -->
Backend suite: 1047 tests, 0 failures <!-- AS OF: 2026-03-31 -->.
