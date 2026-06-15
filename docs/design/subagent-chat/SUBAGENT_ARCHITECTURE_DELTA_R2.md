# SubAgent Architecture — Delta Hardening Round 2

> Contract-hardening pass on SubAgent Architecture v1.
> Addresses 6 identified contract gaps. Delta-only — does not restate unchanged v1 content.
> Source-grounded against codebase at commit `d42211b` (branch: dev, 2026-03-23).

---

## OUTPUT 1: Delta Summary

| Area | V1 Status | Round 2 Action | Remaining Gap |
|---|---|---|---|
| Architecture Decision (§1) | Complete | → UNCHANGED FROM V1 | None |
| Agent Registry (§2) | Complete | → UNCHANGED FROM V1 | None |
| Orchestrator Spec (§3) | Complete | → UNCHANGED FROM V1 | None |
| Handoff Contract (§4) | Complete | → UNCHANGED FROM V1 | None |
| Session State Model (§5) | Complete | → UNCHANGED FROM V1 | None |
| Tool Inventory (§6) | Complete | Tool contracts reclassified (Output 8) | Source mapping refined; no signature changes |
| Prompt Pack (§7) | Complete | → UNCHANGED FROM V1 | None |
| Failure Modes (§8) | Complete | → UNCHANGED FROM V1 | None |
| Engineering Plan (§9) | Complete | Threshold reclassification (Output 7), idempotency contract added | None |
| Rollout Plan (§10) | Complete | Threshold reclassification (Output 7) | None |
| **GAP 1 — Overclaims** | Not addressed | Confidence Reclassification Ledger produced (Output 2) | None — all overclaims corrected |
| **GAP 2 — Invariant/Contract mixing** | Not addressed | Separated into Table A (invariants) + Table B (contracts) (Output 3) | None |
| **GAP 3 — Booking lifecycle** | Underspecified | Full state machine with 11 stages (Output 4) | 3 stages require source verification (payment flow details) |
| **GAP 4 — Authorization model** | Assumed session-only | Multi-scenario authorization contract (Output 5) | Guest-without-account flow needs product decision |
| **GAP 5 — Idempotency** | Noted "no retry" only | Full write-safety contract per operation (Output 6) | None — `IdempotencyGuard` source-confirmed |
| **GAP 6 — Operational thresholds** | Presented as specs | All reclassified as `OPERATIONAL DEFAULT` (Output 7) | Baseline data collection plan defined |

---

## OUTPUT 2: Confidence Reclassification Ledger

### 2.1 Overclaim Corrections

| # | Item from V1 | Original Claim | Corrected Classification | Why Downgraded | Source Evidence Needed to Restore |
|---|---|---|---|---|---|
| OC-1 | "All 13 tool signatures typed and complete" | Implied implementation-ready | `DESIGN-BASELINE` | 6 of 13 tools are `[REQUIRED NEW]` — signatures are proposed, not source-confirmed. No endpoint, service, or test exists for them. | Implement each new service; verify response shapes match proposed signatures. |
| OC-2 | "Handoff JSON schema deployable as-is" | Deployable | `DESIGN-BASELINE` | Schema is well-formed but no runtime code exists. Session state management (Redis key structure, TTL enforcement, lock behavior) is entirely proposed. | Implement `ChatSessionService`; integration test handoff round-trip. |
| OC-3 | "Engineering plan requires no follow-up meetings" | Self-sufficient | `DESIGN-BASELINE pending source verification` | Plan maps to existing endpoints well, but 6 new services need design review. Authorization model (Gap 4) was insufficient. Idempotency strategy (Gap 5) was absent. | Address Gaps 4-5 (done in this pass); design review for new services. |
| OC-4 | Tool status `[EXISTING]` on `check_availability` | Implied direct wrapper | `SOURCE-CONFIRMED (internal service)` | `RoomAvailabilityService::isRoomAvailable()` exists but has no HTTP endpoint. Chat tool must call the service directly, not an API route. | None — already source-confirmed. Refinement: tool dispatcher calls service, not HTTP. |
| OC-5 | "Routing accuracy > 95%" (§10 Phase 3) | Presented as target spec | `OPERATIONAL DEFAULT` | No baseline data. This is a reasonable aspiration, not a verified capability. | Collect routing accuracy metrics over Phase 1-2 period. |
| OC-6 | "Escalation rate < 15%" (§10 Phase 3) | Presented as target spec | `OPERATIONAL DEFAULT` | Same as OC-5. Highly dependent on knowledge base quality and prompt tuning. | Observe over minimum 1000 sessions before committing as SLO. |
| OC-7 | "Response latency < 3s p95" (§10 Phase 1) | Presented as acceptance criterion | `OPERATIONAL DEFAULT` | Depends on Claude API latency, tool dispatch round-trip, Redis performance. Not within our sole control. | Measure during staging; set initial alert at p95 > 5s (generous). |
| OC-8 | Tool TTLs (120s avail, 300s price, etc.) in §5.6 | Presented as system specs | `OPERATIONAL DEFAULT` | These are starting points. Optimal values depend on booking volume and price change frequency at Soleil. | Log cache hit/miss rates in Phase 1; tune after 2 weeks of data. |
| OC-9 | `get_price_quote` backend mapping: "Room.price × nights" | Implied source knowledge | `REQUIRES SOURCE VERIFICATION` | V1 assumed `room.price` is the sole pricing input. No `PriceService` exists. If seasonal pricing, promotions, or group rates exist or are planned, this formula is wrong. | Inspect `Room` model for price fields; verify with product owner whether `price × nights` is the complete formula. |
| OC-10 | `create_escalation_ticket` → "extends contact_messages" | Proposed integration | `PROPOSED CONTRACT` | `contact_messages` table structure not inspected. May lack fields needed for session context (JSON metadata column). | Inspect `ContactMessage` model and migration schema. |

### 2.2 Confidence Tier Definitions

| Tier | Meaning | Implementation Guidance |
|---|---|---|
| `SOURCE-CONFIRMED` | Directly verified from source code, migration, or API route | Can be coded directly. Tool dispatchers can be wired. |
| `DESIGN-BASELINE` | Architecturally sound, carried from v1 without change | Valid architecture; implement framework layer. Tool contracts need service implementation. |
| `PROPOSED CONTRACT` | New design in this delta pass; sound but not source-verified | Requires source verification of specific artifacts before coding write paths. |
| `OPERATIONAL DEFAULT` | Reasonable starting threshold with no production backing | Deploy with logging (not alerting). Tune after minimum observation period. |
| `REQUIRES SOURCE VERIFICATION` | Cannot be finalized without inspecting actual codebase | Blocked — do not code until verification completed. |

---

## OUTPUT 3: Invariant vs Contract Separation

### Table A — Domain Invariants (Business Rules)

These are confirmed business rules enforced by the existing codebase. They constrain *what the system must do*, not *how a tool implements it*.

| ID | Invariant Statement | Classification | Why Confirmed | Implementation Impact |
|---|---|---|---|---|
| I-01 | Booking dates use half-open interval `[check_in, check_out)`. Same-day turnover is valid. | `SOURCE-CONFIRMED` | `Room::scopeAvailableBetween()` implements `existing.check_in < new.check_out AND existing.check_out > new.check_in`. PostgreSQL EXCLUDE USING gist uses `daterange(check_in, check_out, '[)')`. | All date logic in agents and tools must preserve half-open semantics. No tool may validate `check_out > check_in + 1`. |
| I-02 | Room availability is a backend-computed fact, not an agent-stated fact. | `SOURCE-CONFIRMED` | `RoomAvailabilityService::isRoomAvailable()` checks overlapping active bookings against DB. | No agent may state availability without a tool call that reaches the backend. |
| I-03 | Only `pending` and `confirmed` bookings block availability. | `SOURCE-CONFIRMED` | Overlap scope in `Booking.php` filters `whereIn('status', ['pending', 'confirmed'])`. EXCLUDE constraint matches. | Tools that check availability inherit this filter. Cancelled/refund_failed bookings do not block. |
| I-04 | Every booking action requires explicit `location_id` (multi-location). | `SOURCE-CONFIRMED` | `rooms.location_id` is NOT NULL FK. `bookings.location_id` is denormalized via PostgreSQL trigger `trg_booking_set_location`. | Agent must resolve location before any booking tool call. Backend auto-sets `bookings.location_id` from room, but agent must ensure correct room-at-location. |
| I-05 | Booking creation uses pessimistic locking with deadlock retry. | `SOURCE-CONFIRMED` | `CreateBookingService` uses `SELECT ... FOR UPDATE` with 3-attempt exponential backoff for SQLSTATE 40001/40P01. | `create_hold` tool inherits this safety. Agent need not retry — backend retries internally. |
| I-06 | Cancellation is two-phase: DB lock → Stripe refund outside transaction → DB finalize. | `SOURCE-CONFIRMED` | `CancellationService::cancel()` transitions to `refund_pending`, processes refund outside transaction, then finalizes. `IdempotencyGuard` prevents double refunds. | `cancel_booking` tool must not re-invoke if first call returned `refund_pending`. Backend handles idempotency. |
| I-07 | Booking confirmation is admin-only. | `SOURCE-CONFIRMED` | `BookingPolicy::confirm()` returns `$user->isAdmin()`. `BookingController::confirm` calls `$this->authorize('confirm', $booking)`. | Chat AI agent (acting as guest) cannot confirm bookings. Confirmation is a backend/admin action after hold creation. |
| I-08 | Booking ownership is enforced by `BookingPolicy`. Owner or admin can view/update/cancel. Moderator+ can view. | `SOURCE-CONFIRMED` | `BookingPolicy::view()`: `$user->isAtLeast(MODERATOR) \|\| $user->id === $booking->user_id`. `BookingPolicy::update()`: `$user->isAdmin() \|\| $user->id === $booking->user_id`. `BookingPolicy::cancel()`: ownership + status + timing checks. | Agent's tool calls are HTTP requests authenticated as the guest. Backend enforces ownership per-request via policy. Agent cannot bypass. |
| I-09 | Refund amounts are computed by `CancellationService::calculateRefundAmount()`, not by the agent. | `SOURCE-CONFIRMED` | `CancellationService::processRefund()` calls `$this->calculateRefundAmount($booking)` internally. | Agent must never state refund amount except from `cancel_booking` tool response. |
| I-10 | Soft-deleted bookings (`deleted_at IS NOT NULL`) do not block availability. | `SOURCE-CONFIRMED` | EXCLUDE constraint filters `WHERE deleted_at IS NULL`. | Irrelevant to agent tool design but important for architects: soft-delete is safe for availability. |
| I-11 | Booking status is VARCHAR with CHECK constraint. Values: `pending`, `confirmed`, `refund_pending`, `cancelled`, `refund_failed`. | `SOURCE-CONFIRMED` | Migration `2026_03_17_000003`. `App\Enums\BookingStatus` PHP enum. | Agent cannot set arbitrary status values. Backend enforces via CHECK + enum. |
| I-12 | One review per booking. | `SOURCE-CONFIRMED` | `reviews_booking_id_unique` constraint. | Out of scope for chat AI but constrains any future review-via-chat feature. |

### Table B — Tool/API Contracts (Implementation Artifacts)

These are proposed implementations that serve the invariants above. Their signatures, response shapes, and caching behaviors are design proposals until implemented and tested.

| Tool Name | Proposed Signature | Classification | Source Mapping | Gap to Resolve |
|---|---|---|---|---|
| `resolve_location` | `(input_text: string) → {location_id, name, slug, city} \| null` | `PROPOSED CONTRACT` | No existing endpoint. `Location` model has `name`, `slug`, `city`, `address`, `is_active` fields (confirmed). | New `LocationResolverService` needed. Fuzzy matching algorithm TBD. |
| `get_location_list` | `() → Location[]` | `SOURCE-CONFIRMED` | `GET /api/v1/locations` → `LocationController::index` → `Location::active()->withRoomCounts()` | Response shape from `LocationResource` — verify which fields are exposed. |
| `get_available_room_types` | `(location_slug, check_in, check_out, guest_count?) → RoomAvailability[]` | `SOURCE-CONFIRMED` | `GET /api/v1/locations/{slug}/availability` → `LocationController::availability` → `Room::availableBetween()` scope | Response shape from `RoomResource` — **verify whether `price` field is included** (SV-1 from v3). |
| `check_availability` | `(room_id, check_in, check_out) → {available: boolean, room: RoomInfo}` | `SOURCE-CONFIRMED (service-level)` | `RoomAvailabilityService::isRoomAvailable()` — no HTTP endpoint, internal service only | Tool dispatcher calls service directly. No API route needed. |
| `get_price_quote` | `(room_id, check_in, check_out, guest_count) → PriceQuote` | `PROPOSED CONTRACT` | No existing endpoint or service. Current price logic is `room.price × nights` computed in frontend. | New `PriceService` needed. **Verify whether pricing is truly `price × nights` or if seasonal/promo logic exists.** |
| `create_hold` | `(payload: BookingPayload) → {booking_id, status, confirmation_code}` | `SOURCE-CONFIRMED (endpoint)` | `POST /api/v1/bookings` → `BookingController::store` → `CreateBookingService::create()` | **Note**: `StoreBookingRequest` validates `room_id, check_in, check_out, guest_name, guest_email`. Does NOT accept `guest_count` or `source_channel`. V1 proposed these fields — they require backend changes. |
| `get_booking_detail` | `(booking_id: int) → BookingDetail \| null` | `SOURCE-CONFIRMED` | `GET /api/v1/bookings/{id}` → `BookingController::show` → `BookingService::getBookingById()`. Protected by `BookingPolicy::view`. | None — works as-is for authenticated owner or moderator+. |
| `check_modification_eligibility` | `(booking_id, proposed_changes) → EligibilityResult` | `PROPOSED CONTRACT` | No endpoint exists. Logic would combine `BookingPolicy::update` + overlap check from `CreateBookingService::update` pre-check. | New `BookingEligibilityService` needed. |
| `check_cancellation_eligibility` | `(booking_id) → CancellationEligibility` | `PROPOSED CONTRACT` | No endpoint exists. Logic exists in `CancellationService::validateCancellation()` + `BookingPolicy::cancel()` but not as a standalone read. | New `BookingEligibilityService` needed. Must not trigger actual cancellation. |
| `apply_modification` | `(booking_id, changes) → ModifiedBooking` | `SOURCE-CONFIRMED (endpoint)` | `PUT /api/v1/bookings/{id}` → `BookingController::update` → `CreateBookingService::update()`. Protected by `BookingPolicy::update`. | `UpdateBookingRequest` validates `check_in, check_out, guest_name, guest_email`. Works as-is. |
| `cancel_booking` | `(booking_id, reason) → {status, refund_amount_cents?, refund_status?}` | `SOURCE-CONFIRMED (endpoint)` | `POST /api/v1/bookings/{id}/cancel` → `BookingController::cancel` → `CancellationService::cancel()`. Protected by `BookingPolicy::cancel`. | **Note**: Current `cancel()` does not accept `reason` as input. `CancellationService` sets `cancellation_reason` via internal logic. Adding `reason` field requires backend change. |
| `search_knowledge_base` | `(query, location_slug?) → KBResult[]` | `PROPOSED CONTRACT` | No existing service or data store. | Entirely new. Phase 1: JSON file with keyword matching. |
| `create_escalation_ticket` | `(session_id, reason, severity, context_summary, guest_contact?) → TicketResult` | `PROPOSED CONTRACT` | Partial: `contact_messages` table exists (`ContactController::store`). | **Verify `contact_messages` schema** supports `source` field and `metadata` JSON column. |

---

## OUTPUT 4: Booking Lifecycle Contract

### State Machine

The following state machine covers the booking lifecycle as the chat AI agent participates in it. States are classified by source evidence.

---

**Stage 1: `availability_confirmed`**

```
Stage Name:        availability_confirmed
Trigger:           Guest asks about rooms + agent calls get_available_room_types or check_availability
Required Inputs:   location_id (from resolve_location), check_in, check_out, guest_count (optional)
Backend Decision:  Room::availableBetween() scope filters active bookings with half-open interval
Agent Responsibility: Present available rooms to guest. Do NOT state availability without tool call.
Write-Safety:      None — read-only operation
Failure Path:      No rooms available → agent reports honestly, suggests alternative dates/locations
Escalation Path:   Tool call fails 2x → escalate
Hold Expiry:       N/A
Payment Coupling:  N/A
Classification:    SOURCE-CONFIRMED — LocationController::availability, RoomAvailabilityService
```

---

**Stage 2: `price_quoted`**

```
Stage Name:        price_quoted
Trigger:           Guest selects a room type or asks for price
Required Inputs:   room_id, check_in, check_out, guest_count
Backend Decision:  Price calculation (currently room.price × nights — REQUIRES SOURCE VERIFICATION for seasonal/promo logic)
Agent Responsibility: Call get_price_quote. Present price to guest. Record price_snapshot with quoted_at timestamp.
Write-Safety:      None — read-only
Failure Path:      Price service error → agent says "Tôi không thể tính giá lúc này"
Escalation Path:   None (retry once, then report error)
Hold Expiry:       N/A — but price_snapshot becomes stale after 15 minutes (OPERATIONAL DEFAULT)
Payment Coupling:  N/A — price quote is informational
Classification:    PROPOSED CONTRACT — PriceService does not exist yet
                   REQUIRES SOURCE VERIFICATION — verify room.price units (cents? VND? per-night?)
```

---

**Stage 3: `hold_requested`**

```
Stage Name:        hold_requested
Trigger:           Guest confirms all details, agent calls create_hold
Required Inputs:   room_id, check_in, check_out, guest_name, guest_email (+ user_id from auth)
Backend Decision:  CreateBookingService::create() — pessimistic lock, overlap check, INSERT
Agent Responsibility:
  - Verify all mandatory slots collected and confirmed by guest
  - Verify price_snapshot.quoted_at < 15 minutes
  - Call create_hold with complete payload
  - Do NOT retry on failure (backend retries deadlocks internally)
Write-Safety:      Backend handles via SELECT ... FOR UPDATE + deadlock retry (3 attempts)
Failure Path:
  - Overlap error (room booked between availability check and hold) → agent suggests alternatives
  - Validation error (422) → agent reports specific missing/invalid fields
  - Server error (500) → agent apologizes, suggests retry later
Escalation Path:   Overlap error after availability check = race condition → escalate (FM-14)
Hold Expiry:       See Stage 5
Payment Coupling:  None at creation — booking is created as status=pending
Classification:    SOURCE-CONFIRMED — POST /api/v1/bookings, CreateBookingService::create()
```

---

**Stage 4: `hold_active`**

```
Stage Name:        hold_active
Trigger:           create_hold returns success with status=pending
Required Inputs:   N/A (state is the pending booking record)
Backend Decision:  Booking exists with status=pending. Blocks room for overlap checks.
Agent Responsibility:
  - Inform guest: booking is pending confirmation
  - Provide booking_id / confirmation reference
  - Inform guest of next steps (payment if required, or admin confirmation)
Write-Safety:      N/A — no write at this stage
Failure Path:      N/A — booking already created
Escalation Path:   N/A
Hold Expiry:       REQUIRES SOURCE VERIFICATION — no hold expiry mechanism found in codebase.
  The backend has no TTL on pending bookings. There is no cron job or scheduler that
  auto-cancels stale pending bookings. This means a pending booking blocks the room
  indefinitely until an admin confirms or cancels it.
  DECISION NEEDED: Should the chat system assume pending bookings expire?
  If yes, a backend PendingBookingExpiryJob is needed (new).
  If no, pending bookings rely on admin action.
Payment Coupling:  REQUIRES SOURCE VERIFICATION — see Stage 8
Classification:    PROPOSED CONTRACT + REQUIRES SOURCE VERIFICATION
```

---

**Stage 5: `hold_expiry`**

```
Stage Name:        hold_expiry
Trigger:           Timer elapsed without confirmation (if expiry mechanism exists)
Required Inputs:   booking_id, expiry duration
Backend Decision:  DOES NOT EXIST IN CURRENT CODEBASE
Agent Responsibility: N/A — this is a backend-only process if implemented
Write-Safety:      Backend job would cancel the booking (same as CancellationService flow)
Failure Path:      N/A
Escalation Path:   N/A
Hold Expiry:       The expiry duration itself — REQUIRES PRODUCT DECISION
Payment Coupling:  If payment was pre-authorized, release authorization on expiry
Classification:    REQUIRES SOURCE VERIFICATION — no pending booking expiry mechanism found.
  Inspected: no scheduled command in app/Console/Kernel.php or app/Console/Commands/
  that cancels stale pending bookings.
  RECOMMENDATION: Implement PendingBookingExpiryJob (30-minute TTL) before Phase 2.
```

---

**Stage 6: `hold_released`**

```
Stage Name:        hold_released
Trigger:           Expired hold cleaned up (if expiry exists) OR admin cancels pending booking
Required Inputs:   booking_id
Backend Decision:  CancellationService::cancel() or CancellationService::forceCancel()
Agent Responsibility: If guest returns and references expired booking: inform them the hold expired,
  offer to start a new booking flow
Write-Safety:      Backend handles cancellation with locks
Failure Path:      N/A — release is a backend operation
Escalation Path:   N/A
Hold Expiry:       N/A — this IS the post-expiry state
Payment Coupling:  If payment was pre-authorized, authorization is voided. Currently no payment at
  pending stage (see Stage 8), so no payment coupling.
Classification:    PROPOSED CONTRACT (contingent on Stage 5 being implemented)
```

---

**Stage 7: `booking_confirmed`**

```
Stage Name:        booking_confirmed
Trigger:           Admin confirms pending booking
Required Inputs:   booking_id, admin auth
Backend Decision:  BookingService::confirmBooking() — updates status to confirmed, creates Stay record,
  dispatches notification
Agent Responsibility:
  THE CHAT AI AGENT DOES NOT TRIGGER CONFIRMATION.
  BookingPolicy::confirm() requires isAdmin(). The guest-facing chat agent acts as the
  authenticated guest, not as admin. Confirmation is an out-of-band admin action.
  Agent can only inform: "Đặt phòng của bạn đang chờ xác nhận từ nhân viên."
Write-Safety:      Backend transaction handles atomicity (booking status + Stay creation)
Failure Path:      If confirmation fails (booking no longer pending), admin gets error
Escalation Path:   N/A — admin handles directly
Hold Expiry:       N/A
Payment Coupling:  REQUIRES SOURCE VERIFICATION — does confirmation trigger payment capture?
  Inspected BookingService::confirmBooking(): only updates status and creates Stay.
  No payment capture call found. This suggests payment is handled separately
  (Stripe webhook or frontend payment flow).
Classification:    SOURCE-CONFIRMED — BookingController::confirm, BookingPolicy::confirm
```

---

**Stage 8: `payment_authorized`**

```
Stage Name:        payment_authorized
Trigger:           Guest completes payment (frontend Stripe integration)
Required Inputs:   payment_intent_id, booking_id
Backend Decision:  REQUIRES SOURCE VERIFICATION — payment flow is handled by StripeWebhookController.
  payment_intent_id column exists on bookings table. Stripe integration confirmed (Cashier).
Agent Responsibility:
  THE CHAT AI AGENT DOES NOT HANDLE PAYMENT.
  Payment is a frontend + Stripe flow. The agent should not collect card details,
  process payments, or display payment forms. Agent can only:
  - Direct guest to payment page/link
  - Inform that payment is required for confirmation
Write-Safety:      Stripe webhook handles atomically
Failure Path:      Payment failure → Stripe retries or guest retries via frontend
Escalation Path:   Guest reports payment issues → escalate
Hold Expiry:       If payment not completed within hold window, hold expires (Stage 5)
Payment Coupling:  This IS the payment stage
Classification:    REQUIRES SOURCE VERIFICATION — inspect StripeWebhookController for
  payment flow: does payment trigger confirmation? Or is payment + confirmation separate?
```

---

**Stage 9: `payment_captured`**

```
Stage Name:        payment_captured
Trigger:           Stripe webhook confirms successful payment
Required Inputs:   payment_intent_id (from Stripe)
Backend Decision:  StripeWebhookController processes webhook, updates booking.payment_intent_id
Agent Responsibility: None — this is a webhook-driven backend process
Write-Safety:      Stripe idempotency keys + webhook signature verification
Failure Path:      Webhook failure → Stripe retries
Escalation Path:   N/A
Hold Expiry:       N/A — payment is complete
Payment Coupling:  Payment is captured
Classification:    REQUIRES SOURCE VERIFICATION — inspect StripeWebhookController::handlePaymentIntentSucceeded
```

---

**Stage 10: `stale_quote_detected`**

```
Stage Name:        stale_quote_detected
Trigger:           Agent detects price_snapshot.quoted_at > 15 minutes when about to create_hold
Required Inputs:   price_snapshot from session state
Backend Decision:  None — this is an agent-layer check
Agent Responsibility:
  - Do NOT proceed with create_hold
  - Re-call get_price_quote with same parameters
  - If new price differs from stale quote: inform guest of price change
  - If guest accepts new price: update price_snapshot, proceed
  - If guest rejects: abandon flow
Write-Safety:      Prevents booking at outdated price. Backend has no price validation on
  booking creation (StoreBookingRequest does not check price), so this is an
  agent-layer-only protection.
Failure Path:      Re-quote fails → escalate
Escalation Path:   Guest disputes price change → escalate
Hold Expiry:       N/A
Payment Coupling:  Stale price → stale payment amount. Must re-quote before any payment.
Classification:    PROPOSED CONTRACT — 15-minute threshold is OPERATIONAL DEFAULT
  IMPORTANT: Backend does not store or validate price at booking creation.
  StoreBookingRequest has no 'amount' field. The price_snapshot is agent-state only.
  This means price consistency between quote and booking is NOT enforced by the backend.
  RECOMMENDATION: Add amount field to StoreBookingRequest and validate against room.price × nights
  as a server-side safeguard.
```

---

**Stage 11: `reconciliation`**

```
Stage Name:        reconciliation
Trigger:           Hold exists but payment status is unclear (network failure, webhook delay,
  guest abandoned mid-payment)
Required Inputs:   booking_id, payment_intent_id (if any)
Backend Decision:  ReconcileRefundsJob exists for refund reconciliation.
  REQUIRES SOURCE VERIFICATION — is there a PendingBookingReconciliationJob for stale holds?
Agent Responsibility:
  - If guest asks about a booking with unclear status: call get_booking_detail
  - Report actual status from backend (pending, confirmed, refund_pending, etc.)
  - Do NOT guess or infer payment status
Write-Safety:      N/A — agent reads, does not write
Failure Path:      get_booking_detail returns unexpected status → report honestly
Escalation Path:   Guest disputes status → escalate
Hold Expiry:       Connected to Stage 5 — if hold expired, booking may be auto-cancelled
Payment Coupling:  If payment_intent_id exists but status is still pending → unclear state.
  Agent should escalate rather than guess.
Classification:    PROPOSED CONTRACT + REQUIRES SOURCE VERIFICATION
  Inspect: ReconcileRefundsJob.php scope. Is there a stale-pending reconciler?
```

### Lifecycle Summary Diagram

```
Guest selects room
    │
    ▼
[availability_confirmed] ──── SOURCE-CONFIRMED (read-only)
    │
    ▼
[price_quoted] ──────────── PROPOSED (PriceService needed)
    │
    ▼
[hold_requested] ─────────── SOURCE-CONFIRMED (POST /api/v1/bookings)
    │
    ▼
[hold_active] ───────────── status=pending, blocks room
    │
    ├── Timer elapsed ──► [hold_expiry] ──► [hold_released]
    │                       NOT IMPLEMENTED — needs PendingBookingExpiryJob
    │
    ├── Admin confirms ──► [booking_confirmed] ──── SOURCE-CONFIRMED (admin-only)
    │
    ├── Guest pays ──────► [payment_authorized] ──► [payment_captured]
    │                       REQUIRES SOURCE VERIFICATION (Stripe flow)
    │
    └── Stale quote ─────► [stale_quote_detected] ──► re-quote ──► [hold_requested]
                            PROPOSED CONTRACT (agent-layer only)

Unclear state ──────────► [reconciliation] ──► escalate or report
```

### Source Verification Items from Lifecycle Analysis

| ID | What to Inspect | Why It Matters |
|---|---|---|
| SV-L1 | `StripeWebhookController.php` — full payment flow | Determines whether payment triggers confirmation or is separate |
| SV-L2 | Any scheduled command that auto-cancels stale pending bookings | Determines whether hold expiry is needed (Stage 5) |
| SV-L3 | `Room` model — `price` field type and units | PriceService formula depends on this |
| SV-L4 | `StoreBookingRequest` — whether `amount` is accepted | Determines if price can be validated server-side at booking |
| SV-L5 | `ReconcileRefundsJob.php` — scope | Determines reconciliation coverage |

---

## OUTPUT 5: Authorization Hardening

### 5.1 Authorization Scenarios

**Scenario A: Authenticated account holder modifying their own booking**

```
How guest arrives:    Logged in via Sanctum (Bearer token or HttpOnly cookie)
Identity proof:       auth()->id() resolves to the user who created the booking
System must verify:   BookingPolicy checks $user->id === $booking->user_id
Chat agent context:   Session has is_authenticated=true, user_id set in guest_summary
```

**Scenario B: Guest using booking reference + email (no account)**

```
How guest arrives:    Provides booking ID or confirmation code + email address in chat
Identity proof:       booking_id + guest_email match in bookings table
System must verify:   booking.guest_email === provided email (case-insensitive)
Chat agent context:   Session has is_authenticated=false, no user_id
IMPORTANT:            Current codebase does NOT support this flow.
                      All booking endpoints require Sanctum authentication.
                      BookingPolicy requires a User model instance.
                      REQUIRES PRODUCT DECISION: Support unauthenticated booking lookup?
                      If yes: new endpoint needed (POST /api/v1/bookings/lookup with email+booking_id)
                      If no: agent must direct guest to log in first.
```

**Scenario C: Guest arriving from a signed confirmation email link**

```
How guest arrives:    Clicks link in booking confirmation email containing signed token
Identity proof:       Signed URL with booking_id, expiry, signature
System must verify:   URL signature valid + not expired
Chat agent context:   Session pre-populated with booking_id from signed URL
IMPORTANT:            REQUIRES SOURCE VERIFICATION — do confirmation emails contain
                      signed booking links? Inspect BookingConfirmed notification template.
                      If not, this scenario is N/A for current system.
```

**Scenario D: Guest in a new session without prior authentication**

```
How guest arrives:    Opens chat widget, no auth state
Identity proof:       None
System must verify:   N/A — no booking operations possible without identity
Chat agent context:   is_authenticated=false, no user_id, no booking_id
Agent behavior:       Can use Support_Agent (FAQ, policy questions).
                      For booking actions: "Vui lòng đăng nhập để tôi có thể hỗ trợ đặt phòng."
                      For new bookings: Can browse availability (read-only).
                      create_hold requires auth (BookingController::store uses auth()->id()).
```

**Scenario E: Staff-assisted modification (human on behalf of guest)**

```
How guest arrives:    Staff member (moderator/admin) uses admin panel, not chat
Identity proof:       Staff auth token with role >= moderator
System must verify:   BookingPolicy role checks (moderator for view, admin for modify)
Chat agent context:   NOT APPLICABLE — staff uses admin dashboard, not guest chat.
                      Chat AI serves guests only. Admin tools are separate.
```

### 5.2 Recommended Authorization Rules

**Path: Booking creation (create_hold)**

```
Path:                      POST /api/v1/bookings (create_hold)
Identity Proof Required:   Sanctum authentication (Bearer or HttpOnly cookie)
Server-Side Verification:  StoreBookingRequest::authorize() returns true (any auth user).
                           auth()->id() auto-set as user_id on booking.
Agent-Side Verification:   Verify session has is_authenticated=true before calling create_hold.
                           If not authenticated, do NOT attempt create_hold — direct to login.
Failure Behavior:          401 Unauthorized → agent: "Vui lòng đăng nhập để đặt phòng."
Classification:            SOURCE-CONFIRMED — StoreBookingRequest, BookingController::store
```

**Path: Booking view (get_booking_detail)**

```
Path:                      GET /api/v1/bookings/{id}
Identity Proof Required:   Sanctum authentication
Server-Side Verification:  BookingPolicy::view() — owner OR moderator+
Agent-Side Verification:   Verify session has booking_id that belongs to current user.
                           Agent MUST NOT enumerate booking IDs — only use IDs provided
                           by the guest or retrieved from the guest's booking list.
Failure Behavior:          403 Forbidden → agent: "Bạn không có quyền xem đặt phòng này."
                           404 Not Found → agent: "Không tìm thấy đặt phòng với mã này."
Classification:            SOURCE-CONFIRMED — BookingPolicy::view
```

**Path: Booking modification (apply_modification)**

```
Path:                      PUT /api/v1/bookings/{id}
Identity Proof Required:   Sanctum authentication
Server-Side Verification:  BookingPolicy::update() — owner OR admin
Agent-Side Verification:   Verify booking_id belongs to authenticated user (from get_booking_detail).
                           Call check_modification_eligibility before apply_modification.
Failure Behavior:          403 Forbidden → agent: "Bạn không có quyền thay đổi đặt phòng này."
                           422 Unprocessable → agent reports specific validation error
Classification:            SOURCE-CONFIRMED (endpoint) + PROPOSED CONTRACT (eligibility check)
```

**Path: Booking cancellation (cancel_booking)**

```
Path:                      POST /api/v1/bookings/{id}/cancel
Identity Proof Required:   Sanctum authentication
Server-Side Verification:  BookingPolicy::cancel() — ownership + status + timing checks
                           CancellationService::validateCancellation() — additional validation
Agent-Side Verification:   Call check_cancellation_eligibility before cancel_booking.
                           Require explicit guest confirmation ("Bạn có chắc muốn hủy?").
Failure Behavior:          403 → not owner/not eligible
                           BookingCancellationException → report reason
                           RefundFailedException → inform + escalate
Classification:            SOURCE-CONFIRMED — BookingPolicy::cancel, CancellationService
```

### 5.3 IDOR Prevention Contract

**Server-side enforcement (existing, SOURCE-CONFIRMED):**

1. **Route model binding**: Laravel resolves `{booking}` to `Booking` model. If ID doesn't exist, 404.
2. **Policy authorization**: Every BookingController method calls `$this->authorize($action, $booking)` which invokes BookingPolicy. Policy checks `$user->id === $booking->user_id` for owner operations.
3. **Scoped queries**: `BookingService::getUserBookings(auth()->id())` filters by user. Guest cannot see other users' bookings via index endpoint.

**Agent-side guardrails (PROPOSED CONTRACT):**

1. **No booking ID enumeration**: Agent MUST NOT iterate booking IDs. Only use IDs from:
   - Guest's own booking list (`GET /api/v1/bookings` filtered by auth)
   - Guest explicitly providing their booking ID
   - Handoff payload containing booking_id from previous agent
2. **Session binding**: `guest_summary.user_id` in session state must match `auth()->id()`. Tool dispatcher verifies this before every booking write call.
3. **No cross-session data**: Tool results containing booking details MUST NOT leak into other sessions. Redis key isolation (session_id namespace) prevents this.

**Where enforcement lives:**

| Layer | What It Validates | Bypass Risk |
|---|---|---|
| Agent prompt | "Do not access bookings you haven't been given" | Prompt injection — mitigated by tool scope |
| Tool dispatcher | `session.guest_summary.user_id === auth()->id()` | Bug in dispatcher — write unit test |
| Backend policy | `$user->id === $booking->user_id` | None — hard gate. Even if agent/dispatcher fails, backend rejects. |
| Database | FK constraints, no cross-user queries in scoped methods | None — structural |

**Replacement for v1 assumption:**

V1 stated: *"booking_id belongs to the current session's guest"*

Corrected contract: **The agent trusts no booking ownership claim from session state alone. Every write operation on a booking is authorized by the backend via `BookingPolicy`, which validates `$user->id === $booking->user_id` using the authenticated user from the Sanctum token. The agent's session state (`guest_summary.user_id`) is informational context for UX decisions, not an authorization gate. The backend is the sole authority for booking ownership.**

---

## OUTPUT 6: Idempotency and Write-Safety Contract

### `create_hold` (Booking Creation)

```
Operation:           create_hold (POST /api/v1/bookings)

Idempotency Key:
  Shape:             {user_id}:{room_id}:{check_in}:{check_out}
  Scope:             User-scoped (same user, same room, same dates = duplicate)
  TTL:               5 minutes (prevents rapid re-submission, allows legitimate rebooking after change of mind)

Duplicate Request Behavior:
  Key exists + success:     Return existing booking (200 with existing booking_id)
  Key exists + failure:     Allow retry (clear failed key)
  Key exists + in-progress: Block — return 429 "Booking in progress, please wait"

Retry Semantics:
  Agent layer:       Do NOT retry. If create_hold fails, report error to guest.
                     Backend retries deadlocks internally (3 attempts, exponential backoff).
  API layer:         PROPOSED — add idempotency middleware to POST /api/v1/bookings
                     Accept X-Idempotency-Key header. If key exists in cache, return cached response.
  DB layer:          PostgreSQL EXCLUDE USING gist rejects overlapping bookings.
                     Pessimistic lock (SELECT ... FOR UPDATE) serializes concurrent attempts.

Replay Safety:
  Safe to replay:    Conditional
  Condition:         Same idempotency key → returns cached result. Different key → new booking attempt.
                     Without idempotency middleware: replay creates duplicate booking (UNSAFE).

Enforcement Points:
  Agent prompt:      "KHÔNG retry create_hold. Nếu thất bại, báo lỗi cho khách."
  Server middleware: PROPOSED — IdempotencyMiddleware (check X-Idempotency-Key before controller)
  Service layer:     CreateBookingService uses pessimistic lock + overlap check (SOURCE-CONFIRMED)
  Database:          EXCLUDE USING gist constraint (SOURCE-CONFIRMED)

Classification:      PROPOSED CONTRACT (idempotency middleware) + SOURCE-CONFIRMED (DB safety)

NOTE: Without the proposed idempotency middleware, the backend has NO duplicate request protection
for booking creation (unlike cancellation which uses IdempotencyGuard). The EXCLUDE constraint
prevents double-booking of the SAME room/dates but does NOT prevent duplicate booking records
if a network retry hits a different room or slightly different date range.
RECOMMENDATION: Implement IdempotencyMiddleware before Phase 2 (booking creation via chat).
```

### `confirm_booking`

```
Operation:           confirm_booking (admin-only)

Agent Callability:   NOT AGENT-CALLABLE.
  BookingPolicy::confirm() requires isAdmin(). Chat agent acts as guest.
  Confirmation is triggered by admin via dashboard, not by chat AI.

Idempotency:         Handled by BookingService::confirmBooking() — checks status === pending
                     before updating. Re-confirming a confirmed booking throws RuntimeException.

Classification:      SOURCE-CONFIRMED — out of scope for chat agent tool inventory.
```

### `apply_modification` (Booking Update)

```
Operation:           apply_modification (PUT /api/v1/bookings/{id})

Idempotency Key:
  Shape:             modify:{booking_id}:{check_in}:{check_out}:{timestamp_bucket_5min}
  Scope:             Booking-scoped with time bucket (prevents rapid identical modifications)
  TTL:               5 minutes

Duplicate Request Behavior:
  Key exists + success:     Return modified booking (200)
  Key exists + failure:     Allow retry (clear failed key)
  Key exists + in-progress: Block — return 429

Retry Semantics:
  Agent layer:       Do NOT retry. Report error to guest.
  API layer:         PROPOSED — same IdempotencyMiddleware as create_hold
  DB layer:          CreateBookingService::update() uses pessimistic lock for overlap check (SOURCE-CONFIRMED)

Replay Safety:
  Safe to replay:    Conditional
  Condition:         Same idempotency key → cached result. Idempotent at API layer if middleware exists.
                     Without middleware: replay applies same changes again (no-op if data unchanged,
                     but wastes resources and may trigger events).

Enforcement Points:
  Agent prompt:      "KHÔNG retry apply_modification. Kiểm tra eligibility trước."
  Server middleware: PROPOSED — IdempotencyMiddleware
  Service layer:     CreateBookingService::update() overlap check with FOR UPDATE (SOURCE-CONFIRMED)
  Database:          EXCLUDE USING gist prevents overlapping dates (SOURCE-CONFIRMED)

Classification:      PROPOSED CONTRACT (idempotency middleware) + SOURCE-CONFIRMED (overlap safety)
```

### `cancel_booking`

```
Operation:           cancel_booking (POST /api/v1/bookings/{id}/cancel)

Idempotency Key:
  Shape:             cancel:{booking_id}
  Scope:             Booking-scoped (one cancellation per booking, ever)
  TTL:               24 hours (matches IdempotencyGuard::DEFAULT_RESULT_TTL)

Duplicate Request Behavior:
  Key exists + success:     Return already-cancelled booking (SOURCE-CONFIRMED)
  Key exists + failure:     CancellationService checks status; if already cancelled, returns booking
  Key exists + in-progress: IdempotencyGuard::waitForResult() polls with backoff (SOURCE-CONFIRMED)

Retry Semantics:
  Agent layer:       Do NOT retry. If cancel_booking returns error, report to guest and escalate.
  API layer:         CancellationService::cancel() checks if already cancelled FIRST (line 59-66).
                     Returns fresh booking if status === CANCELLED. This IS idempotent at service level.
  DB layer:          Pessimistic lock (FOR UPDATE) serializes concurrent cancel attempts.
                     IdempotencyGuard key: "refund:{booking_id}:{payment_intent_id}" for refund specifically.

Replay Safety:
  Safe to replay:    Yes
  Condition:         Already-cancelled booking returns immediately. Refund protected by IdempotencyGuard.
                     This is the ONLY write operation with SOURCE-CONFIRMED full idempotency.

Enforcement Points:
  Agent prompt:      "KHÔNG retry cancel_booking."
  Server middleware: Not needed — service-level idempotency exists
  Service layer:     CancellationService::cancel() early return for CANCELLED (SOURCE-CONFIRMED)
                     IdempotencyGuard for refund processing (SOURCE-CONFIRMED)
  Database:          Status CHECK constraint prevents invalid transitions (SOURCE-CONFIRMED)

Classification:      SOURCE-CONFIRMED — full idempotency stack exists
```

### `create_escalation_ticket`

```
Operation:           create_escalation_ticket (proposed: POST /api/v1/chat/escalate or extends contact_messages)

Idempotency Key:
  Shape:             escalation:{session_id}:{reason_hash}
  Scope:             Session-scoped (one escalation per session per reason)
  TTL:               30 minutes (matches session TTL)

Duplicate Request Behavior:
  Key exists + success:     Return existing ticket_id
  Key exists + failure:     Allow retry
  Key exists + in-progress: Block

Retry Semantics:
  Agent layer:       May retry ONCE if first attempt fails (read operation + write, low risk)
  API layer:         PROPOSED — check for existing escalation in same session before creating
  DB layer:          No unique constraint (multiple escalations per session may be valid if reasons differ)

Replay Safety:
  Safe to replay:    Conditional
  Condition:         Same session + same reason → returns existing ticket. Different reason → new ticket.

Enforcement Points:
  Agent prompt:      "Retry once if ticket creation fails. If still fails, provide fallback contact info."
  Server middleware: PROPOSED — session_id + reason deduplication
  Service layer:     PROPOSED — check existing escalation for session
  Database:          No existing constraint

Classification:      PROPOSED CONTRACT — entirely new
```

### Write-Safety Summary

| Operation | Backend Idempotency | Agent Retry | Needs New Middleware | Risk Level |
|---|---|---|---|---|
| `create_hold` | **NO** (overlap constraint only) | No retry | **YES — CRITICAL** | HIGH without middleware |
| `confirm_booking` | Partial (status check) | N/A (not agent-callable) | No | LOW |
| `apply_modification` | **NO** (overlap constraint only) | No retry | **YES** | MEDIUM |
| `cancel_booking` | **YES** (full stack) | No retry | No | LOW |
| `create_escalation_ticket` | **NO** (doesn't exist yet) | Retry once | YES (new service) | LOW |

**Critical finding**: `create_hold` and `apply_modification` lack idempotency at the API layer. The EXCLUDE constraint prevents double-booking of the same room, but a network retry could theoretically create a duplicate booking record if the retry hits a different execution path (e.g., deadlock on first attempt, success on retry before the first attempt's retry completes). The `CreateBookingService` internal retry handles deadlocks but does not prevent external duplicate requests.

**Recommendation**: Implement `IdempotencyMiddleware` before enabling `create_hold` via chat (Phase 2). The middleware should:
1. Accept `X-Idempotency-Key` header
2. Check cache for existing result with that key
3. If found: return cached response
4. If not: acquire lock, execute request, cache result
5. Pattern: identical to `IdempotencyGuard` but at HTTP layer

---

## OUTPUT 7: Operational Threshold Reclassification

### Threshold Reclassification Table

| Parameter | V1 Value | New Classification | Rationale | Recommended Validation Step | Initial Monitoring |
|---|---|---|---|---|---|
| Availability tool TTL | 5 min (§5.6) | `OPERATIONAL DEFAULT` | Matches `RoomAvailabilityService` cache but optimal chat TTL may differ | Log cache hit/miss rate per session; compare stale-hit rate vs booking conflict rate | Log only for 2 weeks |
| Price quote TTL | 15 min (§5.6) | `OPERATIONAL DEFAULT` | No source basis. Depends on how often prices change at Soleil (likely rarely). | Track price delta between consecutive quotes for same room | Log only for 2 weeks |
| Booking snapshot TTL | 10 min (§5.6) | `OPERATIONAL DEFAULT` | Reasonable for modification flows but untested | Track booking state change frequency during active chat sessions | Log only |
| Location list TTL | 1 hour (§5.6) | `OPERATIONAL DEFAULT` | Locations change extremely rarely. 1 hour is conservative. | Could be 24 hours. Log location change events. | Log only |
| KB result TTL | 1 hour (§5.6) | `OPERATIONAL DEFAULT` | Depends on KB update frequency (manual, so infrequent) | Log KB content updates; align TTL with update frequency | Log only |
| Routing accuracy target | >95% (§10 Phase 3) | `OPERATIONAL DEFAULT` | No baseline. Depends on prompt quality, intent distribution, language complexity (Vietnamese) | Measure on 500+ real conversations before setting SLO | Log routing decisions with labels; human-review sample of 100 |
| Escalation rate target | <15% (§10 Phase 3) | `OPERATIONAL DEFAULT` | No baseline. Highly dependent on KB completeness and agent quality | Observe over 1000+ sessions | Log escalation rate per day; alert only if >40% (clear problem) |
| Session completion target | <8 turns for simple booking (§10 Phase 3) | `OPERATIONAL DEFAULT` | No baseline. 8 turns = ~greeting + location + dates + guests + room selection + price + confirm details + hold. Could be tight. | Measure turn distribution per completed booking flow | Log turn counts; histogram analysis after 200 bookings |
| p95 latency target | <3s tool-backed, <1.5s direct (§10 Phase 1) | `OPERATIONAL DEFAULT` | Depends on Claude API latency (not under our control) + tool dispatch time | Measure in staging with realistic tool latencies | Log per-turn latency; alert at p95 > 8s (double of target, catches real problems) |
| Tool failure rate alert | >5% in 5-min window (§9.5) | `OPERATIONAL DEFAULT` | No baseline. 5% in 5 min is sensitive — could alert on normal variance during low traffic | Observe failure rate distribution over 1 week | Log tool failures; alert at >15% in 5 min (catches real outages, not noise) |
| Max turn count per agent | 15 (§3.3) | `OPERATIONAL DEFAULT` | Reasonable but arbitrary. Some modification flows may legitimately need 10+ turns. | Log turn count distribution per agent | Log only; force-escalation at 15 is reasonable safety net |
| Max total turn count | 30 (§8, FM-08) | `OPERATIONAL DEFAULT` | Reasonable session limit. | Log session length distribution | Log only; 30 is a safety net, not an SLO |
| Session TTL active | 30 min (§5.3) | `OPERATIONAL DEFAULT` | Standard for chat sessions. | Track session timeout rate vs completion rate | Log only |
| Session TTL idle | 15 min (§5.3) | `OPERATIONAL DEFAULT` | Conservative. Some guests may step away for 20 min. | Track idle-timeout rate | Log only; consider extending to 20 min if data shows frequent timeouts |
| Stale handoff threshold | 30 min (§4.3) | `OPERATIONAL DEFAULT` | Matches session TTL. Logically consistent. | N/A — follows from session TTL | N/A |

### Operational Default Declaration

*The following statement should be inserted at the top of Section 9 (Engineering Integration Plan) in the final spec:*

> **OPERATIONAL DEFAULT NOTICE**
>
> All performance targets, cache TTLs, turn limits, and alert thresholds in this specification are **operational defaults** — reasonable starting points based on engineering judgment, NOT source-backed guarantees or committed SLOs.
>
> **No threshold in this document is a Service Level Objective (SLO) until it has been validated through the following process:**
>
> 1. **Baseline** (Phase 1-2): Deploy with logging (not alerting) for all thresholds. Collect distribution data.
> 2. **Observe** (minimum 2 weeks in production OR 1000 sessions, whichever comes first): Analyze distributions. Identify natural breakpoints.
> 3. **Tune**: Adjust thresholds based on observed data. Some will tighten (routing accuracy may reach 98%), others will loosen (session TTL may need 45 min).
> 4. **Commit**: Convert tuned thresholds to SLOs with defined alerting and incident response.
>
> **Minimum observation period before converting any default to an alert: 2 weeks of production traffic.** Before that period, all thresholds are logged metrics only. The sole exception is safety-critical alerts (e.g., FM-14 double-booking, which should alert immediately on any occurrence since the cost of a miss is a displaced guest).
>
> **Defaults may be overridden** by the team during implementation based on domain knowledge. If a default is overridden, document the replacement value and reasoning in the deployment runbook.

---

## OUTPUT 8: Updated Tool Contract Matrix

| Tool Name | Owner Agent | Classification (Updated) | Source Mapping | Gaps Remaining |
|---|---|---|---|---|
| `resolve_location` | Booking, Support, Modify | `PROPOSED CONTRACT` — unchanged | → Tool contract UNCHANGED FROM V1 | New service needed |
| `get_location_list` | Booking, Support | `SOURCE-CONFIRMED` — unchanged | → Tool contract UNCHANGED FROM V1 | None |
| `get_available_room_types` | Booking | `SOURCE-CONFIRMED` — **refined** | `LocationController::availability`. Response includes `RoomResource` fields. | **Verify whether `RoomResource` includes `price` field.** If yes, get_price_quote may be redundant for initial display (but still needed for total calculation). |
| `check_availability` | Booking | `SOURCE-CONFIRMED (service-level)` — **clarified** | `RoomAvailabilityService::isRoomAvailable()` — internal service, no HTTP endpoint. Tool dispatcher calls service directly. | None — dispatcher implementation detail |
| `get_price_quote` | Booking | `PROPOSED CONTRACT` — unchanged | → Tool contract UNCHANGED FROM V1 | New PriceService needed. Verify pricing formula. |
| `create_hold` | Booking | `SOURCE-CONFIRMED (endpoint)` — **refined** | `POST /api/v1/bookings`. **StoreBookingRequest does NOT accept `guest_count` or `source_channel`.** These require backend changes to add. | Add `guest_count` and `source_channel` to `StoreBookingRequest`. Add IdempotencyMiddleware. |
| `get_booking_detail` | Modify, Escalation | `SOURCE-CONFIRMED` — unchanged | → Tool contract UNCHANGED FROM V1 | None |
| `check_modification_eligibility` | Modify | `PROPOSED CONTRACT` — unchanged | → Tool contract UNCHANGED FROM V1 | New service needed |
| `check_cancellation_eligibility` | Modify | `PROPOSED CONTRACT` — unchanged | → Tool contract UNCHANGED FROM V1 | New service needed |
| `apply_modification` | Modify | `SOURCE-CONFIRMED (endpoint)` — **refined** | `PUT /api/v1/bookings/{id}`. Protected by `BookingPolicy::update` (owner or admin). | Add IdempotencyMiddleware. |
| `cancel_booking` | Modify | `SOURCE-CONFIRMED (endpoint)` — **refined** | `POST /api/v1/bookings/{id}/cancel`. **Full idempotency stack confirmed** (CancellationService early return + IdempotencyGuard for refund). | **`reason` parameter not accepted by current endpoint.** Backend change needed to pass cancellation reason from request. |
| `search_knowledge_base` | Support | `PROPOSED CONTRACT` — unchanged | → Tool contract UNCHANGED FROM V1 | Entirely new |
| `create_escalation_ticket` | Escalation | `PROPOSED CONTRACT` — unchanged | → Tool contract UNCHANGED FROM V1 | Verify contact_messages schema |

---

## OUTPUT 9: Minimal Codebase Mapping Plan

### 9.1 Per-Agent Backend Touchpoints

**Booking_Agent:**

| Tool | Route | Controller | Service | Repository |
|---|---|---|---|---|
| resolve_location | **NEW** (internal) | — | **NEW**: LocationResolverService | Location model (existing) |
| get_location_list | `GET /api/v1/locations` | LocationController::index | — (direct query) | — |
| get_available_room_types | `GET /api/v1/locations/{slug}/availability` | LocationController::availability | Room::availableBetween scope | — |
| check_availability | **internal service** | — | RoomAvailabilityService::isRoomAvailable | EloquentRoomRepository (existing) |
| get_price_quote | **NEW** | **NEW** ChatApiController or internal | **NEW**: PriceService::quote | Room model (existing) |
| create_hold | `POST /api/v1/bookings` | BookingController::store | CreateBookingService::create | — |

**Modify_Agent:**

| Tool | Route | Controller | Service | Repository |
|---|---|---|---|---|
| get_booking_detail | `GET /api/v1/bookings/{id}` | BookingController::show | BookingService::getBookingById | — |
| check_modification_eligibility | **NEW** | **NEW** or internal | **NEW**: BookingEligibilityService | — |
| check_cancellation_eligibility | **NEW** | **NEW** or internal | **NEW**: BookingEligibilityService | — |
| apply_modification | `PUT /api/v1/bookings/{id}` | BookingController::update | CreateBookingService::update | — |
| cancel_booking | `POST /api/v1/bookings/{id}/cancel` | BookingController::cancel | CancellationService::cancel | — |

**Support_Agent:**

| Tool | Route | Controller | Service |
|---|---|---|---|
| search_knowledge_base | **NEW** | **NEW** or internal | **NEW**: KnowledgeBaseService |
| get_location_list | existing (shared) | LocationController::index | — |
| resolve_location | **NEW** (shared) | — | LocationResolverService |

**Escalation_Agent:**

| Tool | Route | Controller | Service |
|---|---|---|---|
| create_escalation_ticket | **NEW** or extend `POST /api/v1/contact` | ContactController (extend) or **NEW** | **NEW** or extend ContactMessageService |
| get_booking_detail | existing (shared) | BookingController::show | BookingService |

### 9.2 Existing Backend Capabilities (Can Implement Now)

These tools can be wired to existing endpoints/services with minimal backend changes:

1. **get_location_list** → Direct HTTP call to `GET /api/v1/locations` — no changes needed
2. **get_available_room_types** → Direct HTTP call to `GET /api/v1/locations/{slug}/availability` — no changes needed
3. **check_availability** → Service call to `RoomAvailabilityService::isRoomAvailable()` — needs tool dispatcher wiring only
4. **get_booking_detail** → Direct HTTP call to `GET /api/v1/bookings/{id}` — needs auth token forwarding
5. **apply_modification** → Direct HTTP call to `PUT /api/v1/bookings/{id}` — needs auth token forwarding
6. **cancel_booking** → Direct HTTP call to `POST /api/v1/bookings/{id}/cancel` — needs auth token forwarding

### 9.3 Requiring New Backend Endpoints/Services

| New Service | Methods | Dependencies | Estimated Effort |
|---|---|---|---|
| `LocationResolverService` | `resolve(text): ?Location` | Location model, ILIKE query | Small — single query with fuzzy matching |
| `PriceService` | `quote(room_id, check_in, check_out, guest_count): PriceQuote` | Room model | Small if `price × nights` formula. Medium if seasonal/promo logic needed. |
| `BookingEligibilityService` | `checkModification(booking_id, changes): EligibilityResult`, `checkCancellation(booking_id): CancEligibility` | BookingPolicy, CancellationService::validateCancellation (extract logic) | Medium — extract existing validation into standalone read operations |
| `KnowledgeBaseService` | `search(query, location_slug?): KBResult[]` | New JSON/DB knowledge base | Medium — data creation + search implementation |
| `ChatToolDispatcher` | `dispatch(toolName, input): array` | All above services | Medium — routing + response normalization |
| `ChatSessionService` | `getState(sessionId): SessionState`, `saveState(state): void` | Redis | Small — CRUD on Redis key |
| `IdempotencyMiddleware` | HTTP middleware for POST routes | Cache (Redis) | Small — pattern exists in IdempotencyGuard |

### 9.4 Requiring Backend Changes to Existing Code

| Change | File | What | Why |
|---|---|---|---|
| Add `guest_count` to `StoreBookingRequest` | `app/Http/Requests/StoreBookingRequest.php` | Add `'guest_count' => 'sometimes|integer|min:1'` | V1 tool contract requires it; current validation omits it |
| Add `source_channel` to `StoreBookingRequest` | `app/Http/Requests/StoreBookingRequest.php` | Add `'source_channel' => 'sometimes|string|in:web,chat_ai'` | Distinguish chat bookings from web bookings for analytics |
| Add `reason` to cancel endpoint | `BookingController::cancel` | Accept `cancellation_reason` from request body | Current cancel uses CancellationService which sets reason internally |
| Add `guest_count` column to bookings | New migration | `$table->unsignedInteger('guest_count')->nullable()` | Track guest count per booking |

### 9.5 Requiring Source Inspection Before Specification

| ID | What to Inspect | File/Layer | Why |
|---|---|---|---|
| SI-1 | `RoomResource` fields — does it include `price`? | `app/Http/Resources/RoomResource.php` | Determines if `get_available_room_types` returns price or if separate `get_price_quote` is always needed |
| SI-2 | `contact_messages` migration — columns available | `database/migrations/*contact_messages*` | Determines if `create_escalation_ticket` can extend existing table |
| SI-3 | `StripeWebhookController` — payment-confirmation coupling | `app/Http/Controllers/Payment/StripeWebhookController.php` | Determines lifecycle Stages 8-9 |
| SI-4 | Room.price field type and units | `Room` model + migration | PriceService formula |
| SI-5 | Scheduled commands — any pending booking cleanup | `app/Console/Kernel.php` or `routes/console.php` | Determines if Stage 5 (hold_expiry) exists |
| SI-6 | Booking confirmation email template | `app/Notifications/BookingConfirmed.php` or similar | Determines if signed links exist (Scenario C in auth model) |

---

## OUTPUT 10: Final Hardening Verdict

### Safe to treat as design baseline (implement architecture, not tool layer yet):

- Orchestrator-Worker pattern with 4 SubAgents (§1-3)
- Agent registry with tool scope isolation (§2)
- Handoff contract JSON schema (§4)
- Session state Redis model (§5)
- Prompt pack for all 5 agents (§7)
- Failure mode registry (§8) — all 15 modes remain valid
- Routing decision tree (§3.2)
- Active agent continuation rules (§3.3-3.4)

### Safe to implement now (confirmed or low-risk proposed):

- `get_location_list` tool — wraps existing endpoint, read-only
- `get_available_room_types` tool — wraps existing endpoint, read-only
- `check_availability` tool — wraps existing service, read-only
- `get_booking_detail` tool — wraps existing endpoint, read-only (auth-gated)
- `ChatSessionService` — Redis CRUD, no external dependencies
- `ChatToolDispatcher` for read-only tools — routing + response normalization
- Intent_Orchestrator — routing only, no tools, low risk
- Support_Agent shell — with stub KnowledgeBaseService returning "I don't have that info"
- Escalation_Agent shell — with fallback to contact info if ticket creation not ready
- Per-turn structured logging (§9.5)

### Must NOT be implemented until source is verified:

- **`create_hold` via chat** — until:
  - `IdempotencyMiddleware` is implemented (Gap 5 critical finding)
  - `StoreBookingRequest` is updated with `guest_count` and `source_channel`
  - `RoomResource` price exposure is verified (SI-1)
  - `PriceService` is implemented and price formula confirmed
- **`cancel_booking` via chat** — until:
  - `cancellation_reason` parameter is added to the cancel endpoint
  - `BookingEligibilityService::checkCancellation` is implemented as standalone read
- **`apply_modification` via chat** — until:
  - `IdempotencyMiddleware` is implemented
  - `BookingEligibilityService::checkModification` is implemented as standalone read
- **`create_escalation_ticket`** — until:
  - `contact_messages` schema is inspected (SI-2)
- **Payment-coupled flows** (Stages 8-9) — until:
  - `StripeWebhookController` is inspected for payment-confirmation coupling (SI-3)
- **Hold expiry mechanism** (Stage 5) — until:
  - Confirmed whether pending bookings expire (SI-5)
  - If yes: `PendingBookingExpiryJob` is implemented

### Top 5 Unresolved Risks After This Hardening Pass

| Rank | Risk | Impact | Likelihood | Mitigation Path |
|---|---|---|---|---|
| 1 | **No idempotency on `create_hold`** — network retry or agent resend creates duplicate pending booking | Duplicate bookings in DB; guest charged twice if payment implemented | Medium (network retries are common in production) | Implement `IdempotencyMiddleware` before Phase 2. Block `create_hold` in Phase 1. |
| 2 | **No hold expiry mechanism** — pending bookings block rooms indefinitely | Room inventory locked by abandoned chat sessions | High (guests will abandon mid-flow regularly) | Implement `PendingBookingExpiryJob` with 30-min TTL. Product decision needed on expiry window. |
| 3 | **Price not validated server-side at booking** — `StoreBookingRequest` has no `amount` field | Agent quotes price X, booking records no price. If price changes between quote and checkout, no audit trail. | Medium (prices change infrequently at hostels) | Add `amount` field to `StoreBookingRequest`; validate `amount === room.price × nights` server-side. |
| 4 | **Unauthenticated guest cannot manage bookings via chat** — all booking endpoints require Sanctum auth | Walk-in or email-reference guests cannot use Modify_Agent | Medium (common hostel scenario) | Product decision: implement lookup-by-email+booking_id endpoint OR require login for all booking actions. |
| 5 | **Payment flow coupling is unverified** — unclear whether payment triggers confirmation or is separate | Chat agent cannot give accurate guidance on "what happens after I book" | Low (Phase 1 is read-only + hold) but HIGH for Phase 2+ | Inspect `StripeWebhookController` before Phase 2 launch. Document payment lifecycle. |

---

*Delta hardening pass completed 2026-03-23. Grounded against codebase at commit `d42211b` (branch: dev).*
*This document is a delta — it supplements, does not replace, SubAgent Architecture v1.*
