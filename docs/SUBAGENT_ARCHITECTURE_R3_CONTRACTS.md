# SubAgent Architecture — Round 3 Final Implementation Contracts

> Scope: `Booking_Agent`, `Modify_Agent`, `Intent_Orchestrator` only.
> Source baseline inspected in this session on branch `dev` at commit `d42211b` on `2026-03-23`.
> Label system used throughout: `SOURCE-CONFIRMED`, `DESIGN-BASELINE`, `PROPOSED CONTRACT`, `REQUIRES SOURCE INSPECTION`, `OPERATIONAL DEFAULT`.

---

## Gap Resolutions

### GAP A — Output Artifact Quality

`DESIGN-BASELINE`: Artifact 1 is the fix for Gap A.

`DESIGN-BASELINE`: Every tool row below is fully populated.

`DESIGN-BASELINE`: No row uses "unchanged from v1", "same as Round 2", or an empty placeholder.

---

### GAP B — Hold Expiry: Backend Gap + Agent Contract

#### B1 — Backend Gap Declaration

`SOURCE-CONFIRMED`: No stale-pending-booking expiry job was found in inspected scheduler source. `backend/routes/console.php` schedules `ReconcileRefundsJob`, `horizon:snapshot`, and `horizon:clear` only.

`SOURCE-CONFIRMED`: Pending bookings block availability today because active-overlap logic uses `pending` and `confirmed` in both booking overlap checks and room availability checks.

`PROPOSED CONTRACT`: Backend must add an `ExpireStalePendingBookingsJob`.

```text
Job Name:         ExpireStalePendingBookingsJob [PROPOSED CONTRACT]
Schedule:         every 5 minutes [OPERATIONAL DEFAULT]
Selection Rule:   bookings.status = 'pending' AND created_at < now() - ttl [PROPOSED CONTRACT]
Expiry Action:    call CancellationService::forceCancel($booking, $systemUser, 'Auto-expired: pending booking TTL exceeded') [PROPOSED CONTRACT]
Scheduler File:   backend/routes/console.php [SOURCE-CONFIRMED existing scheduler file]
```

`OPERATIONAL DEFAULT`: Initial TTL should be `30 minutes`.

`SOURCE-CONFIRMED`: If the job is never built, every abandoned pending booking blocks one room for its date interval until a human or another backend process changes status.

`SOURCE-CONFIRMED` + `OPERATIONAL DEFAULT`: At `10` abandoned pending bookings, up to `10` room-date intervals remain unavailable. That blocking effect is deterministic under the current overlap rules.

`PROPOSED CONTRACT`: Agent fallback when the backend job does not exist:

- Do not promise an automatic expiry window.
- Do not say "the room is held for 30 minutes."
- Treat `pending` as active until `get_booking_detail` returns another status.
- If the guest returns later, re-check the booking instead of assuming expiry.

#### B2 — Agent Contract for Pending Booking Lifecycle Communication

All rows in this table are `PROPOSED CONTRACT`.

| Situation | Agent Contract |
|---|---|
| After `create_pending_booking` succeeds | "Dat phong cua ban da duoc tao voi ma **{booking_id}**. Trang thai hien tai: **cho xac nhan**. Ban se nhan email khi dat phong duoc xac nhan." |
| Guest asks "Is my booking confirmed?" while status is `pending` | "Dat phong cua ban hien dang o trang thai **cho xac nhan**. Toi se kiem tra lai trang thai hien tai cho ban ngay bay gio." Then call `get_booking_detail`. |
| Guest returns hours later and last known status is still `pending` | "Toi khong the gia dinh dat phong da het han. Toi se kiem tra trang thai thuc te cho ban." Then call `get_booking_detail`. |
| Guest asks what "reserved" means | "Trong he thong Soleil, dat phong vua tao qua chat la **pending booking**. Trang thai nay khong dong nghia voi **confirmed**." |

---

### GAP C — Terminology Standardization

`DESIGN-BASELINE`: Round 3 uses **Option A**. The agent tool name is `create_pending_booking`.

`SOURCE-CONFIRMED`: The backend has no separate "hold" resource, no `holds` table, and no hold-only status. `POST /api/v1/bookings` creates a booking row with `status = 'pending'`.

`DESIGN-BASELINE`: The term `hold` is retired from internal tool naming, session schema, and write-path rules. Guest-facing copy may still describe a room as being kept for them, but internal contracts do not.

| Agent Vocabulary | Backend Reality | Status |
|---|---|---|
| `create_pending_booking` | `POST /api/v1/bookings` creates a booking row with `status = 'pending'` | `SOURCE-CONFIRMED` |
| `pending booking` | `bookings.status = 'pending'` | `SOURCE-CONFIRMED` |
| `confirmed booking` | `bookings.status = 'confirmed'` | `SOURCE-CONFIRMED` |
| `cancelled booking` | `bookings.status = 'cancelled'` | `SOURCE-CONFIRMED` |

---

### GAP D — Pricing Contract Resolution

`DESIGN-BASELINE`: Chosen path is **P1**.

```text
Chosen Path:        P1 — compute quote in the tool adapter from room.price × nights [PROPOSED CONTRACT]
Justification:      room.price exists and is serialized today [SOURCE-CONFIRMED]
                    no seasonal, promotional, surcharge, or quote-service artifacts were found in inspected backend/app or backend/database [SOURCE-CONFIRMED]
Agent Tool Name:    get_price_quote [DESIGN-BASELINE]
Tool Signature:     get_price_quote(room_id: int, check_in: string, check_out: string)
                    -> { room_id: int, price_per_night: number, nights: int, total_amount: number, quoted_at: string } [PROPOSED CONTRACT]
Source Mapping:     GET /api/v1/rooms/{room} -> RoomController::show() -> RoomService::getRoomById() [SOURCE-CONFIRMED]
                    Tool adapter computes nights and total_amount [PROPOSED CONTRACT]
Classification:     PROPOSED CONTRACT
What must be verified: NONE in the currently inspected codebase [SOURCE-CONFIRMED]
What must be built: tool-adapter arithmetic and staleness handling [PROPOSED CONTRACT]
Risk if wrong:      if future dynamic pricing is introduced, agent-computed totals become stale or incorrect [PROPOSED CONTRACT risk]
```

`DESIGN-BASELINE`: Path P3 is rejected. Using booking creation as a dry-run quote source mixes read and write concerns and creates side-effect risk.

---

### GAP E — Agent-to-API Authentication Pattern

#### Pattern 1 — Proxy Pattern

```text
Pattern:                     Proxy / service account
How it works:                Chat layer authenticates with its own privileged credential and calls booking APIs on behalf of the guest.
SOLEIL backend compatibility: SOURCE-CONFIRMED incompatible in the currently inspected auth stack.
                             No service-account middleware, API-key guard, or on-behalf-of header flow was found in:
                             - backend/app/Http/Middleware/
                             - backend/bootstrap/app.php
                             - backend/routes/api.php
                             - backend/routes/api/v1.php
Security properties:         Central privileged credential; expands blast radius on compromise. [DESIGN-BASELINE]
Implementation complexity:   High [DESIGN-BASELINE]
Failure mode:                Policy checks resolve to the service account unless new impersonation middleware is added. [SOURCE-CONFIRMED from BookingPolicy ownership checks]
Recommended:                 NO [DESIGN-BASELINE]
Reason:                      Conflicts with current authorization model and would require new auth middleware plus new audit semantics. [SOURCE-CONFIRMED + PROPOSED CONTRACT]
```

#### Pattern 2 — Passthrough Pattern

```text
Pattern:                     Guest auth-context passthrough
How it works:                The chat gateway preserves the guest's authenticated request context end-to-end.
                             If the inbound chat request uses Bearer auth, the same Bearer credential is forwarded unchanged.
                             If the inbound chat request uses cookie auth on the same Laravel origin, the gateway keeps the same guest auth context and MUST NOT mint a service credential.
SOLEIL backend compatibility: SOURCE-CONFIRMED
                             - booking routes use check_token_valid + verified middleware
                             - BookingPolicy authorizes against the actual authenticated user
                             - check_token_valid accepts Bearer tokens and also falls back to the HttpOnly cookie path
Security properties:         Least privilege per guest [DESIGN-BASELINE]
                             Expiry, revocation, and refresh-abuse checks are enforced today [SOURCE-CONFIRMED]
                             Device fingerprint validation is enforced on the HttpOnly cookie path only, not on the Bearer-token path [SOURCE-CONFIRMED]
Implementation complexity:   Low if chat runs on the same Laravel boundary; medium if an external gateway must securely forward the existing guest credential. [DESIGN-BASELINE]
Failure mode:                Expired or missing guest auth returns 401/403 and the agent must stop write operations. [SOURCE-CONFIRMED]
Recommended:                 YES [DESIGN-BASELINE]
Reason:                      Fits the inspected auth and policy model with no privileged bypass. [SOURCE-CONFIRMED]
```

`DESIGN-BASELINE`: Round 3 standard is **guest auth-context passthrough**.

---

## ARTIFACT 1 — Tool Contract Matrix vFinal

Scope: `Booking_Agent`, `Modify_Agent`, `Intent_Orchestrator`.

---

### Tool 1: `resolve_location`

```text
Tool Name:             resolve_location [DESIGN-BASELINE]
Agent(s):              Booking_Agent, Modify_Agent [DESIGN-BASELINE]
Backend Alias:         resolve_location -> GET /api/v1/locations + adapter-side match on name|slug|city [PROPOSED CONTRACT on top of SOURCE-CONFIRMED endpoint]
Final Signature:       resolve_location(input_text: string)
                       -> { status: "resolved", location: { id: int, name: string, slug: string, city: string } }
                       |  { status: "ambiguous", candidates: Array<{ id: int, name: string, slug: string, city: string }> }
                       |  { status: "not_found" } [PROPOSED CONTRACT]
Classification:        PROPOSED CONTRACT
Source Endpoint:       GET /api/v1/locations [SOURCE-CONFIRMED]
Source Controller:     LocationController::index() [SOURCE-CONFIRMED]
Source Service:        Location::query()->active()->withRoomCounts()->orderBy('name') [SOURCE-CONFIRMED]
                       Matching logic in the tool adapter only [PROPOSED CONTRACT]
Auth Requirement:      None; locations are public in v1 routes [SOURCE-CONFIRMED]
Idempotency:           Read-only [SOURCE-CONFIRMED]
Failure Behavior:      If ambiguous, show candidates and ask the guest to choose [PROPOSED CONTRACT]
                       If not found, ask for city/property clarification [PROPOSED CONTRACT]
Caching:               60 minutes in tool cache [OPERATIONAL DEFAULT]
Gaps Remaining:        NONE for source fields [SOURCE-CONFIRMED]; adapter matching still must be implemented [PROPOSED CONTRACT]
```

---

### Tool 2: `get_location_list`

```text
Tool Name:             get_location_list [DESIGN-BASELINE]
Agent(s):              Booking_Agent [DESIGN-BASELINE]
Backend Alias:         get_location_list -> GET /api/v1/locations [SOURCE-CONFIRMED]
Final Signature:       get_location_list()
                       -> Array<{ id: int, name: string, slug: string, city: string, address_full: string, amenities: string[] }> [PROPOSED CONTRACT adapter subset over SOURCE-CONFIRMED response]
Classification:        PROPOSED CONTRACT
Source Endpoint:       GET /api/v1/locations [SOURCE-CONFIRMED]
Source Controller:     LocationController::index() [SOURCE-CONFIRMED]
Source Service:        Location::query()->active()->withRoomCounts()->orderBy('name') [SOURCE-CONFIRMED]
Auth Requirement:      None [SOURCE-CONFIRMED]
Idempotency:           Read-only [SOURCE-CONFIRMED]
Failure Behavior:      Return a brief apology and ask the guest to name the city/property manually [PROPOSED CONTRACT]
Caching:               60 minutes in tool cache [OPERATIONAL DEFAULT]
Gaps Remaining:        NONE [SOURCE-CONFIRMED]
```

---

### Tool 3: `get_available_rooms`

```text
Tool Name:             get_available_rooms [DESIGN-BASELINE]
Agent(s):              Booking_Agent [DESIGN-BASELINE]
Backend Alias:         get_available_rooms -> GET /api/v1/locations/{slug}/availability [SOURCE-CONFIRMED]
Final Signature:       get_available_rooms(location_slug: string, check_in: string, check_out: string, guest_count?: int)
                       -> { location: { id: int, name: string, slug: string }, available_rooms: Array<{ id: int, name: string, price: number, max_guests: int, status: string, location_id: int }>, total_available: int } [PROPOSED CONTRACT agent-facing alias over SOURCE-CONFIRMED payload]
Classification:        PROPOSED CONTRACT
Source Endpoint:       GET /api/v1/locations/{slug}/availability?check_in=&check_out=&guests= [SOURCE-CONFIRMED]
Source Controller:     LocationController::availability(string $slug, LocationAvailabilityRequest $request) [SOURCE-CONFIRMED]
Source Service:        Location::where('slug', $slug)->active()->firstOrFail()
                       then $location->rooms()->availableBetween(...)->when(guests)->orderBy('price')->get() [SOURCE-CONFIRMED]
Auth Requirement:      None [SOURCE-CONFIRMED]
Idempotency:           Read-only [SOURCE-CONFIRMED]
Failure Behavior:      404 -> ask the guest to choose a valid location [PROPOSED CONTRACT]
                       422 -> ask the guest to correct dates or guest count [PROPOSED CONTRACT]
                       0 rooms -> invite the guest to change dates or location [PROPOSED CONTRACT]
Caching:               No backend cache was found in the inspected controller path [SOURCE-CONFIRMED]
                       Tool cache may retain results for 5 minutes [OPERATIONAL DEFAULT]
Gaps Remaining:        NONE [SOURCE-CONFIRMED]
```

---

### Tool 4: `get_price_quote`

```text
Tool Name:             get_price_quote [DESIGN-BASELINE]
Agent(s):              Booking_Agent [DESIGN-BASELINE]
Backend Alias:         get_price_quote -> GET /api/v1/rooms/{room} + adapter calculation total_amount = price_per_night × nights [PROPOSED CONTRACT on SOURCE-CONFIRMED endpoint]
Final Signature:       get_price_quote(room_id: int, check_in: string, check_out: string)
                       -> { room_id: int, price_per_night: number, nights: int, total_amount: number, quoted_at: string } [PROPOSED CONTRACT]
Classification:        PROPOSED CONTRACT
Source Endpoint:       GET /api/v1/rooms/{room} [SOURCE-CONFIRMED]
Source Controller:     RoomController::show(Room $room) [SOURCE-CONFIRMED]
Source Service:        RoomService::getRoomById() -> RoomAvailabilityService::getRoomAvailability() [SOURCE-CONFIRMED]
Source Price Field:    RoomResource::price [SOURCE-CONFIRMED]
Auth Requirement:      None [SOURCE-CONFIRMED]
Idempotency:           Read-only [SOURCE-CONFIRMED]
Failure Behavior:      404 -> ask the guest to reselect a room from the latest availability list [PROPOSED CONTRACT]
                       stale quote -> re-run the tool before presenting a write summary [PROPOSED CONTRACT]
Caching:               Backend room-availability cache TTL is 300 seconds [SOURCE-CONFIRMED]
                       Quote should be treated as stale after 15 minutes [OPERATIONAL DEFAULT]
Gaps Remaining:        NONE in inspected pricing source [SOURCE-CONFIRMED]
```

---

### Tool 5: `create_pending_booking`

```text
Tool Name:             create_pending_booking [DESIGN-BASELINE]
Agent(s):              Booking_Agent [DESIGN-BASELINE]
Backend Alias:         create_pending_booking -> POST /api/v1/bookings -> BookingController::store() [SOURCE-CONFIRMED]
Final Signature:       create_pending_booking(payload: { room_id: int, check_in: string, check_out: string, guest_name: string, guest_email: string })
                       -> { id: int, status: "pending", check_in: string, check_out: string, guest_name: string, guest_email: string, room: { id: int, name: string, price: number, max_guests: int }, created_at: string } [SOURCE-CONFIRMED]
Classification:        SOURCE-CONFIRMED
Source Endpoint:       POST /api/v1/bookings [SOURCE-CONFIRMED]
Source Controller:     BookingController::store(StoreBookingRequest $request) [SOURCE-CONFIRMED]
Source Service:        CreateBookingService::create(...) with DB transaction, overlap query lock, and deadlock retry [SOURCE-CONFIRMED]
Auth Requirement:      Guest passthrough auth context behind `check_token_valid` + `verified`; no service credential [SOURCE-CONFIRMED backend, PROPOSED CONTRACT gateway pattern]
Idempotency:           No request-idempotency header or middleware exists in the inspected create path [SOURCE-CONFIRMED]
                       DB overlap protection and pessimistic locking exist [SOURCE-CONFIRMED]
Failure Behavior:      401/403 -> stop the write flow and tell the guest to log in or verify email [PROPOSED CONTRACT]
                       422 -> report the validation/conflict error without retrying [PROPOSED CONTRACT]
                       500 -> do not retry automatically; offer human help [PROPOSED CONTRACT]
Caching:               No; write operation [SOURCE-CONFIRMED]
Gaps Remaining:        Duplicate-request suppression above the DB layer is still missing if the gateway can retry writes.
                       If exact-once semantics are required, inspect adding new middleware under backend/app/Http/Middleware and alias registration in backend/bootstrap/app.php [PROPOSED CONTRACT]
```

---

### Tool 6: `get_booking_detail`

```text
Tool Name:             get_booking_detail [DESIGN-BASELINE]
Agent(s):              Modify_Agent, Booking_Agent [DESIGN-BASELINE]
Backend Alias:         get_booking_detail -> GET /api/v1/bookings/{booking} -> BookingController::show() [SOURCE-CONFIRMED]
Final Signature:       get_booking_detail(booking_id: int)
                       -> { id: int, room_id: int, user_id: int | null, check_in: string, check_out: string, guest_name: string, guest_email: string, status: string, status_label: string | null, nights: int, amount?: int, refund_amount?: int, refund_status?: string, refund_percentage?: int, cancelled_at?: string, room?: { id: int, name: string, price: number, max_guests: int }, created_at: string, updated_at: string } [SOURCE-CONFIRMED]
Classification:        SOURCE-CONFIRMED
Source Endpoint:       GET /api/v1/bookings/{booking} [SOURCE-CONFIRMED]
Source Controller:     BookingController::show(Booking $booking) [SOURCE-CONFIRMED]
Source Service:        BookingService::getBookingById($booking->id) with 600-second cache [SOURCE-CONFIRMED]
Auth Requirement:      Guest passthrough auth context behind `check_token_valid` + `verified`; BookingPolicy::view enforces owner-or-moderator access [SOURCE-CONFIRMED]
Idempotency:           Read-only [SOURCE-CONFIRMED]
Failure Behavior:      401/403 -> stop the modify flow and surface the auth/ownership problem [PROPOSED CONTRACT]
                       404 -> ask the guest to confirm the booking ID [PROPOSED CONTRACT]
Caching:               600 seconds in backend booking cache [SOURCE-CONFIRMED]
Gaps Remaining:        If Modify_Agent must display location name directly from this tool, current response is insufficient.
                       Inspect backend/app/Services/BookingService.php and backend/app/Http/Resources/BookingResource.php [REQUIRES SOURCE INSPECTION]
```

---

### Tool 7: `get_booking_history`

```text
Tool Name:             get_booking_history [DESIGN-BASELINE]
Agent(s):              Modify_Agent [DESIGN-BASELINE]
Backend Alias:         get_booking_history -> GET /api/v1/bookings -> BookingController::index() [SOURCE-CONFIRMED]
Final Signature:       get_booking_history()
                       -> Array<{ id: int, room_id: int, check_in: string, check_out: string, status: string, status_label: string | null, nights: int, amount?: int, refund_status?: string, created_at: string, room?: { id: int, name: string, price: number, max_guests: int } }> [SOURCE-CONFIRMED]
Classification:        SOURCE-CONFIRMED
Source Endpoint:       GET /api/v1/bookings [SOURCE-CONFIRMED]
Source Controller:     BookingController::index() [SOURCE-CONFIRMED]
Source Service:        BookingService::getUserBookings(auth()->id()) with 300-second cache [SOURCE-CONFIRMED]
Auth Requirement:      Guest passthrough auth context behind `check_token_valid` + `verified`; query is scoped to auth()->id() [SOURCE-CONFIRMED]
Idempotency:           Read-only [SOURCE-CONFIRMED]
Failure Behavior:      Empty list -> tell the guest no bookings were found for the logged-in account [PROPOSED CONTRACT]
                       401/403 -> stop the modify flow [PROPOSED CONTRACT]
Caching:               300 seconds in backend user-bookings cache [SOURCE-CONFIRMED]
Gaps Remaining:        NONE [SOURCE-CONFIRMED]
```

---

### Tool 8: `check_modification_eligibility`

```text
Tool Name:             check_modification_eligibility [DESIGN-BASELINE]
Agent(s):              Modify_Agent [DESIGN-BASELINE]
Backend Alias:         check_modification_eligibility -> POST /api/v1/bookings/{booking}/modification-eligibility [PROPOSED CONTRACT]
Final Signature:       check_modification_eligibility(booking_id: int, proposed_changes: { check_in: string, check_out: string, guest_name?: string, guest_email?: string })
                       -> { eligible: boolean, reason: string | null, overlap_conflict: boolean, current_dates: { check_in: string, check_out: string }, proposed_dates: { check_in: string, check_out: string } } [PROPOSED CONTRACT]
Classification:        PROPOSED CONTRACT
Source Endpoint:       NONE — new endpoint required [PROPOSED CONTRACT]
Source Controller:     NONE — new BookingEligibilityController::checkModification() required [PROPOSED CONTRACT]
Source Service:        NONE — new BookingEligibilityService::checkModification() required [PROPOSED CONTRACT]
                       Existing source logic available in:
                       - BookingPolicy::update() [SOURCE-CONFIRMED]
                       - UpdateBookingRequest::rules() [SOURCE-CONFIRMED]
                       - CreateBookingService::update() overlap logic [SOURCE-CONFIRMED]
Auth Requirement:      Guest passthrough auth context behind `check_token_valid` + `verified` [PROPOSED CONTRACT endpoint boundary using SOURCE-CONFIRMED auth stack]
Idempotency:           Read-only [DESIGN-BASELINE]
Failure Behavior:      Ineligible -> explain the blocking reason and ask for new dates [PROPOSED CONTRACT]
                       Missing endpoint -> treat as launch blocker for Modify_Agent date changes [PROPOSED CONTRACT]
Caching:               No; eligibility is date- and state-sensitive [DESIGN-BASELINE]
Gaps Remaining:        New route, controller, and service are required.
                       The reusable source logic already exists in the files named above [SOURCE-CONFIRMED]
```

---

### Tool 9: `apply_modification`

```text
Tool Name:             apply_modification [DESIGN-BASELINE]
Agent(s):              Modify_Agent [DESIGN-BASELINE]
Backend Alias:         apply_modification -> PUT /api/v1/bookings/{booking} -> BookingController::update() [SOURCE-CONFIRMED]
Final Signature:       apply_modification(booking_id: int, changes: { check_in: string, check_out: string, guest_name: string, guest_email: string, room_id?: int })
                       -> { id: int, check_in: string, check_out: string, guest_name: string, guest_email: string, status: string, room?: { id: int, name: string, price: number, max_guests: int }, updated_at: string } [SOURCE-CONFIRMED]
Classification:        SOURCE-CONFIRMED
Source Endpoint:       PUT /api/v1/bookings/{booking} [SOURCE-CONFIRMED]
Source Controller:     BookingController::update(UpdateBookingRequest $request, Booking $booking) [SOURCE-CONFIRMED]
Source Service:        CreateBookingService::update(...) [SOURCE-CONFIRMED]
Auth Requirement:      Guest passthrough auth context behind `check_token_valid` + `verified`; BookingPolicy::update enforces owner-or-admin access [SOURCE-CONFIRMED]
Idempotency:           No dedicated request-idempotency layer exists in the inspected update path [SOURCE-CONFIRMED]
                       Overlap checks and DB locking still protect room correctness [SOURCE-CONFIRMED]
Failure Behavior:      401/403 -> stop the write flow and explain the auth/ownership issue [PROPOSED CONTRACT]
                       422 -> surface overlap or validation failure without retry [PROPOSED CONTRACT]
Caching:               No; write operation [SOURCE-CONFIRMED]
Gaps Remaining:        Partial update UX must be adapter-driven because UpdateBookingRequest requires check_in, check_out, guest_name, and guest_email.
                       The tool adapter should backfill unchanged guest fields from get_booking_detail before calling PUT [SOURCE-CONFIRMED + PROPOSED CONTRACT]
```

---

### Tool 10: `check_cancellation_eligibility`

```text
Tool Name:             check_cancellation_eligibility [DESIGN-BASELINE]
Agent(s):              Modify_Agent [DESIGN-BASELINE]
Backend Alias:         check_cancellation_eligibility -> GET /api/v1/bookings/{booking}/cancellation-eligibility [PROPOSED CONTRACT]
Final Signature:       check_cancellation_eligibility(booking_id: int)
                       -> { eligible: boolean, reason: string | null, refund_percentage: int | null, refund_amount_estimate: int | null, booking_status: string } [PROPOSED CONTRACT]
Classification:        PROPOSED CONTRACT
Source Endpoint:       NONE — new endpoint required [PROPOSED CONTRACT]
Source Controller:     NONE — new BookingEligibilityController::checkCancellation() required [PROPOSED CONTRACT]
Source Service:        NONE — new BookingEligibilityService::checkCancellation() required [PROPOSED CONTRACT]
                       Existing source logic available in:
                       - BookingPolicy::cancel() [SOURCE-CONFIRMED]
                       - CancellationService::validateCancellation() [SOURCE-CONFIRMED]
                       - Booking::calculateRefundAmount() / getRefundPercentage() [SOURCE-CONFIRMED]
Auth Requirement:      Guest passthrough auth context behind `check_token_valid` + `verified` [PROPOSED CONTRACT endpoint boundary using SOURCE-CONFIRMED auth stack]
Idempotency:           Read-only [DESIGN-BASELINE]
Failure Behavior:      Ineligible -> explain the reason before any destructive action [PROPOSED CONTRACT]
                       Missing endpoint -> treat as launch blocker for chat cancellation preview [PROPOSED CONTRACT]
Caching:               No; eligibility depends on current booking status and current time [DESIGN-BASELINE]
Gaps Remaining:        New route, controller, and service are required.
                       Note: refund_amount_estimate can only be non-zero when booking.amount is populated, and create_pending_booking does not currently set amount [SOURCE-CONFIRMED]
```

---

### Tool 11: `cancel_booking`

```text
Tool Name:             cancel_booking [DESIGN-BASELINE]
Agent(s):              Modify_Agent [DESIGN-BASELINE]
Backend Alias:         cancel_booking -> POST /api/v1/bookings/{booking}/cancel -> BookingController::cancel() [SOURCE-CONFIRMED]
Final Signature:       cancel_booking(booking_id: int)
                       -> { id: int, status: string, refund_status?: string, refund_amount?: int, cancelled_at?: string, updated_at: string } [SOURCE-CONFIRMED]
Classification:        SOURCE-CONFIRMED
Source Endpoint:       POST /api/v1/bookings/{booking}/cancel [SOURCE-CONFIRMED]
Source Controller:     BookingController::cancel(Booking $booking) [SOURCE-CONFIRMED]
Source Service:        CancellationService::cancel(Booking $booking, User $actor) [SOURCE-CONFIRMED]
Auth Requirement:      Guest passthrough auth context behind `check_token_valid` + `verified`; BookingPolicy::cancel enforces ownership/status/timing [SOURCE-CONFIRMED]
Idempotency:           Already-cancelled bookings short-circuit safely in CancellationService [SOURCE-CONFIRMED]
                       Refund processing uses IdempotencyGuard [SOURCE-CONFIRMED]
Failure Behavior:      BookingCancellationException -> show the backend reason and stop [PROPOSED CONTRACT]
                       RefundFailedException -> apologize and escalate to staff immediately [PROPOSED CONTRACT]
Caching:               No; write operation [SOURCE-CONFIRMED]
Gaps Remaining:        Guest cancellation reason is not accepted by the current cancel endpoint.
                       Inspect backend/app/Http/Controllers/BookingController.php and backend/app/Services/CancellationService.php if product needs that field persisted [REQUIRES SOURCE INSPECTION]
```

---

### Intent_Orchestrator Tools

```text
Tool Count:            0 [DESIGN-BASELINE]
Classification:        DESIGN-BASELINE
Contract:              Intent_Orchestrator classifies and routes only. It does not call availability, pricing, booking, modification, or cancellation tools. [DESIGN-BASELINE]
```

---

## ARTIFACT 2 — Booking State Machine vFinal

`DESIGN-BASELINE`: State names below use Round 3 terminology. `pending_booking_*` replaces internal "hold" terminology.

---

### State 1: `searching`

```text
State:                    searching [DESIGN-BASELINE]
Entry Trigger:            Orchestrator routes booking intent to Booking_Agent [DESIGN-BASELINE]
Backend Status:           No booking row exists [SOURCE-CONFIRMED]
Agent Display to Guest:   Ask for missing search slots such as location or dates [PROPOSED CONTRACT]
Valid Next States:        availability_shown, searching [DESIGN-BASELINE]
Transitions:
  availability_shown:     guest provides enough search inputs and get_available_rooms returns results [DESIGN-BASELINE]
  searching:              guest input is still incomplete or ambiguous [DESIGN-BASELINE]
Backend Action at Entry:  None [SOURCE-CONFIRMED]
Agent Action at Entry:    Resolve location if possible; otherwise ask for it. Ask for dates and guest_count as needed. [DESIGN-BASELINE]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         N/A [SOURCE-CONFIRMED]
Classification:           DESIGN-BASELINE
```

---

### State 2: `availability_shown`

```text
State:                    availability_shown [DESIGN-BASELINE]
Entry Trigger:            get_available_rooms returns one or more rooms [DESIGN-BASELINE]
Backend Status:           No booking row exists [SOURCE-CONFIRMED]
Agent Display to Guest:   Show room name, nightly price, and max guests; ask which room the guest wants [PROPOSED CONTRACT]
Valid Next States:        price_quoted, searching [DESIGN-BASELINE]
Transitions:
  price_quoted:           guest selects a room [DESIGN-BASELINE]
  searching:              guest changes location, dates, or guest_count [DESIGN-BASELINE]
Backend Action at Entry:  None [SOURCE-CONFIRMED]
Agent Action at Entry:    Present the shortlist and capture room selection [DESIGN-BASELINE]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         N/A [SOURCE-CONFIRMED]
Classification:           DESIGN-BASELINE
```

---

### State 3: `price_quoted`

```text
State:                    price_quoted [DESIGN-BASELINE]
Entry Trigger:            Guest selected a room and Booking_Agent calls get_price_quote [PROPOSED CONTRACT]
Backend Status:           No booking row exists [SOURCE-CONFIRMED]
Agent Display to Guest:   Present price_per_night, nights, and total_amount [PROPOSED CONTRACT]
Valid Next States:        details_collecting, availability_shown, searching [DESIGN-BASELINE]
Transitions:
  details_collecting:     guest wants to proceed with the quoted room [DESIGN-BASELINE]
  availability_shown:     guest wants to compare other rooms [DESIGN-BASELINE]
  searching:              guest changes search inputs [DESIGN-BASELINE]
Backend Action at Entry:  GET /api/v1/rooms/{room} returns current room price [SOURCE-CONFIRMED]
Agent Action at Entry:    Compute total_amount = price_per_night × nights in the tool adapter [PROPOSED CONTRACT]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         Quote only; no payment artifact is created here [SOURCE-CONFIRMED]
Classification:           DESIGN-BASELINE for the state shell; PROPOSED CONTRACT for the quote adapter
```

---

### State 4: `details_collecting`

```text
State:                    details_collecting [DESIGN-BASELINE]
Entry Trigger:            Guest accepts the quote and booking creation inputs are still incomplete [DESIGN-BASELINE]
Backend Status:           No booking row exists [SOURCE-CONFIRMED]
Agent Display to Guest:   Ask for the remaining booking fields one at a time [PROPOSED CONTRACT]
Valid Next States:        details_confirmed, price_quoted, searching [DESIGN-BASELINE]
Transitions:
  details_confirmed:      room_id, check_in, check_out, guest_name, and guest_email are all present [SOURCE-CONFIRMED required fields]
  price_quoted:           quote is stale and must be refreshed before confirmation [PROPOSED CONTRACT]
  searching:              guest abandons or changes the search [DESIGN-BASELINE]
Backend Action at Entry:  None [SOURCE-CONFIRMED]
Agent Action at Entry:    Populate collected_slots and missing_slots [DESIGN-BASELINE]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         N/A [SOURCE-CONFIRMED]
Classification:           DESIGN-BASELINE
```

---

### State 5: `details_confirmed`

```text
State:                    details_confirmed [DESIGN-BASELINE]
Entry Trigger:            All required create fields are collected and summarized back to the guest [DESIGN-BASELINE]
Backend Status:           No booking row exists [SOURCE-CONFIRMED]
Agent Display to Guest:   Show a final summary and require explicit confirmation before calling create_pending_booking [PROPOSED CONTRACT]
Valid Next States:        pending_booking_created, details_collecting, searching [DESIGN-BASELINE]
Transitions:
  pending_booking_created: guest explicitly confirms the summary [DESIGN-BASELINE]
  details_collecting:      guest corrects one or more fields [DESIGN-BASELINE]
  searching:               guest abandons or restarts [DESIGN-BASELINE]
Backend Action at Entry:  None [SOURCE-CONFIRMED]
Agent Action at Entry:    Re-check quote freshness before the write [PROPOSED CONTRACT]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         N/A [SOURCE-CONFIRMED]
Classification:           DESIGN-BASELINE
```

---

### State 6: `pending_booking_created`

```text
State:                    pending_booking_created [DESIGN-BASELINE]
Entry Trigger:            create_pending_booking returns 201 with status = pending [SOURCE-CONFIRMED]
Backend Status:           bookings.status = 'pending' [SOURCE-CONFIRMED]
Agent Display to Guest:   Explain that the booking exists but is still awaiting confirmation [PROPOSED CONTRACT]
Valid Next States:        pending_booking_active_awaiting_payment, cancellation_requested [DESIGN-BASELINE]
Transitions:
  pending_booking_active_awaiting_payment: immediate transition after the success message [DESIGN-BASELINE]
  cancellation_requested:                  guest immediately asks to cancel [DESIGN-BASELINE]
Backend Action at Entry:  CreateBookingService inserts the booking and BookingController dispatches BookingCreated [SOURCE-CONFIRMED]
Agent Action at Entry:    Save booking_snapshot and end the create flow cleanly [PROPOSED CONTRACT]
Hold Expiry Behavior:     Pending-booking TTL is not implemented yet in source [SOURCE-CONFIRMED]
Payment Coupling:         No payment initiation source was found in the inspected create path [SOURCE-CONFIRMED]
Classification:           SOURCE-CONFIRMED for backend state; PROPOSED CONTRACT for guest messaging
```

---

### State 7: `pending_booking_active_awaiting_payment`

```text
State:                    pending_booking_active_awaiting_payment [DESIGN-BASELINE]
Entry Trigger:            A pending booking exists after creation and has not yet been confirmed or cancelled [DESIGN-BASELINE]
Backend Status:           bookings.status = 'pending' [SOURCE-CONFIRMED]
Agent Display to Guest:   If asked, explain that the booking is pending and must be checked again before calling it confirmed [PROPOSED CONTRACT]
Valid Next States:        payment_processing, booking_confirmed, pending_booking_expired, cancellation_requested [DESIGN-BASELINE]
Transitions:
  payment_processing:     guest enters a separate payment flow [REQUIRES SOURCE INSPECTION]
  booking_confirmed:      admin confirmation or Stripe webhook success changes status to confirmed [SOURCE-CONFIRMED]
  pending_booking_expired: backend expiry job cancels stale pending bookings [PROPOSED CONTRACT]
  cancellation_requested: guest asks to cancel [DESIGN-BASELINE]
Backend Action at Entry:  None; this is a waiting state [SOURCE-CONFIRMED]
Agent Action at Entry:    Re-check status with get_booking_detail instead of assuming expiry or confirmation [PROPOSED CONTRACT]
Hold Expiry Behavior:     Do not promise TTL until ExpireStalePendingBookingsJob exists [PROPOSED CONTRACT]
Payment Coupling:         Payment confirmation webhook exists; payment initiation source was not found in inspected routes/controllers [SOURCE-CONFIRMED + REQUIRES SOURCE INSPECTION]
Classification:           SOURCE-CONFIRMED for backend waiting state; PROPOSED CONTRACT for expiry communication
```

---

### State 8: `pending_booking_expired`

```text
State:                    pending_booking_expired [DESIGN-BASELINE terminology; PROPOSED CONTRACT backend behavior]
Entry Trigger:            ExpireStalePendingBookingsJob cancels a stale pending booking [PROPOSED CONTRACT]
Backend Status:           bookings.status = 'cancelled' after forced cancellation [PROPOSED CONTRACT]
Agent Display to Guest:   Explain that the pending booking is no longer active and offer a fresh search [PROPOSED CONTRACT]
Valid Next States:        searching [DESIGN-BASELINE]
Transitions:
  searching:              guest wants a new booking [DESIGN-BASELINE]
Backend Action at Entry:  CancellationService::forceCancel(...) invoked by the expiry job [PROPOSED CONTRACT]
Agent Action at Entry:    Clear booking_snapshot and route back to a new booking flow [PROPOSED CONTRACT]
Hold Expiry Behavior:     This is the expiry terminal state [PROPOSED CONTRACT]
Payment Coupling:         No inspected pending-booking expiry payment handling exists; inspect future Stripe checkout implementation if payments can exist before confirmation [REQUIRES SOURCE INSPECTION]
Classification:           PROPOSED CONTRACT
```

---

### State 9: `payment_processing`

```text
State:                    payment_processing [DESIGN-BASELINE name only]
Entry Trigger:            Guest enters Soleil's payment flow after a pending booking exists [REQUIRES SOURCE INSPECTION]
Backend Status:           booking remains pending until confirmation occurs [SOURCE-CONFIRMED]
Agent Display to Guest:   Tell the guest payment is handled outside chat and that status must be checked after completion [PROPOSED CONTRACT]
Valid Next States:        booking_confirmed, pending_booking_active_awaiting_payment [DESIGN-BASELINE]
Transitions:
  booking_confirmed:      Stripe webhook confirms the booking after successful payment [SOURCE-CONFIRMED]
  pending_booking_active_awaiting_payment: payment is abandoned or not yet reflected [PROPOSED CONTRACT]
Backend Action at Entry:  Exact payment initiation source not found in inspected routes/controllers; only `/api/webhooks/stripe` post-payment handling is present [SOURCE-CONFIRMED + REQUIRES SOURCE INSPECTION]
Agent Action at Entry:    Do not improvise payment instructions beyond directing the guest to the existing payment UI/path provided by product [PROPOSED CONTRACT]
Hold Expiry Behavior:     Same as pending booking state; no expiry promise without the backend job [PROPOSED CONTRACT]
Payment Coupling:         Stripe webhook confirmation is real; payment initiation artifact still needs source inspection [SOURCE-CONFIRMED + REQUIRES SOURCE INSPECTION]
Classification:           REQUIRES SOURCE INSPECTION
```

---

### State 10: `booking_confirmed`

```text
State:                    booking_confirmed [DESIGN-BASELINE]
Entry Trigger:            Booking status becomes confirmed via admin confirm endpoint or Stripe webhook [SOURCE-CONFIRMED]
Backend Status:           bookings.status = 'confirmed' [SOURCE-CONFIRMED]
Agent Display to Guest:   Confirm success and point the guest to email confirmation if needed [PROPOSED CONTRACT]
Valid Next States:        modification_requested, cancellation_requested [DESIGN-BASELINE]
Transitions:
  modification_requested: guest asks to change details [DESIGN-BASELINE]
  cancellation_requested: guest asks to cancel [DESIGN-BASELINE]
Backend Action at Entry:  BookingService::confirmBooking() updates status and creates/ensures the operational stay record [SOURCE-CONFIRMED]
Agent Action at Entry:    Refresh booking_snapshot when the guest asks about status [PROPOSED CONTRACT]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         If confirmation came from Stripe, the webhook path handled it [SOURCE-CONFIRMED]
Classification:           SOURCE-CONFIRMED
```

---

### State 11: `modification_requested`

```text
State:                    modification_requested [DESIGN-BASELINE]
Entry Trigger:            Guest asks to change dates or booking details [DESIGN-BASELINE]
Backend Status:           Existing booking remains in its current status during review [SOURCE-CONFIRMED]
Agent Display to Guest:   Ask what should change and then run the eligibility check before any write [PROPOSED CONTRACT]
Valid Next States:        modification_applied, booking_confirmed, pending_booking_active_awaiting_payment, cancellation_requested [DESIGN-BASELINE]
Transitions:
  modification_applied:                 eligibility passes and apply_modification succeeds [PROPOSED CONTRACT]
  booking_confirmed:                    guest abandons the change on a confirmed booking [DESIGN-BASELINE]
  pending_booking_active_awaiting_payment: guest abandons the change on a pending booking [DESIGN-BASELINE]
  cancellation_requested:               guest pivots to cancel instead [DESIGN-BASELINE]
Backend Action at Entry:  None [SOURCE-CONFIRMED]
Agent Action at Entry:    Load get_booking_detail and collect proposed changes into modification_snapshot [DESIGN-BASELINE]
Hold Expiry Behavior:     Pending bookings remain subject to Gap B behavior [PROPOSED CONTRACT]
Payment Coupling:         Price-difference handling for modifications was not found in the inspected update path [REQUIRES SOURCE INSPECTION]
Classification:           DESIGN-BASELINE for flow shell; PROPOSED CONTRACT for eligibility pre-check
```

---

### State 12: `modification_applied`

```text
State:                    modification_applied [DESIGN-BASELINE]
Entry Trigger:            apply_modification succeeds [SOURCE-CONFIRMED]
Backend Status:           Booking row is updated; status is unchanged by CreateBookingService::update() [SOURCE-CONFIRMED]
Agent Display to Guest:   Show before/after values and close the write operation cleanly [PROPOSED CONTRACT]
Valid Next States:        booking_confirmed, pending_booking_active_awaiting_payment, modification_requested [DESIGN-BASELINE]
Transitions:
  booking_confirmed:                    prior status was confirmed [DESIGN-BASELINE]
  pending_booking_active_awaiting_payment: prior status was pending [DESIGN-BASELINE]
  modification_requested:               guest wants another change [DESIGN-BASELINE]
Backend Action at Entry:  BookingController::update() dispatches BookingUpdated after service success [SOURCE-CONFIRMED]
Agent Action at Entry:    Refresh booking_snapshot and clear modification_snapshot [PROPOSED CONTRACT]
Hold Expiry Behavior:     If the booking remains pending, Gap B rules still apply [PROPOSED CONTRACT]
Payment Coupling:         Price-adjustment workflow after modification is not present in inspected source [REQUIRES SOURCE INSPECTION]
Classification:           SOURCE-CONFIRMED for backend update; PROPOSED CONTRACT for guest UX
```

---

### State 13: `cancellation_requested`

```text
State:                    cancellation_requested [DESIGN-BASELINE]
Entry Trigger:            Guest asks to cancel a pending or confirmed booking [DESIGN-BASELINE]
Backend Status:           Existing booking status is unchanged until cancel_booking runs [SOURCE-CONFIRMED]
Agent Display to Guest:   Tell the guest you will check cancellation eligibility before performing the cancellation [PROPOSED CONTRACT]
Valid Next States:        cancellation_processing, booking_confirmed, pending_booking_active_awaiting_payment [DESIGN-BASELINE]
Transitions:
  cancellation_processing:             eligibility passes and guest explicitly confirms cancellation [PROPOSED CONTRACT]
  booking_confirmed:                   guest changes mind on a confirmed booking [DESIGN-BASELINE]
  pending_booking_active_awaiting_payment: guest changes mind on a pending booking [DESIGN-BASELINE]
Backend Action at Entry:  None [SOURCE-CONFIRMED]
Agent Action at Entry:    Run get_booking_detail then check_cancellation_eligibility before any destructive call [PROPOSED CONTRACT]
Hold Expiry Behavior:     Pending bookings still cannot be assumed expired without re-checking the source of truth [PROPOSED CONTRACT]
Payment Coupling:         Refund estimate is a pre-write preview, not final settlement [PROPOSED CONTRACT]
Classification:           DESIGN-BASELINE for flow shell; PROPOSED CONTRACT for eligibility tooling
```

---

### State 14: `cancellation_processing`

```text
State:                    cancellation_processing [DESIGN-BASELINE]
Entry Trigger:            cancel_booking is called after explicit confirmation [DESIGN-BASELINE]
Backend Status:           CancellationService transitions to cancelled immediately or through refund_pending first [SOURCE-CONFIRMED]
Agent Display to Guest:   Acknowledge that cancellation is being processed [PROPOSED CONTRACT]
Valid Next States:        cancelled, refund_processing, refunded [DESIGN-BASELINE]
Transitions:
  cancelled:              no refundable payment exists and cancellation finalizes immediately [SOURCE-CONFIRMED]
  refund_processing:      refundable payment exists and backend enters refund_pending [SOURCE-CONFIRMED]
  refunded:               refund succeeds during the same synchronous cancellation path and the final response is already cancelled+refunded [SOURCE-CONFIRMED]
Backend Action at Entry:  CancellationService validates, locks, updates status, and processes refund work [SOURCE-CONFIRMED]
Agent Action at Entry:    Wait for the single cancel_booking result; do not issue duplicate cancellations [PROPOSED CONTRACT]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         Refund path is coupled to Stripe via CancellationService + IdempotencyGuard [SOURCE-CONFIRMED]
Classification:           SOURCE-CONFIRMED
```

---

### State 15: `cancelled`

```text
State:                    cancelled [DESIGN-BASELINE]
Entry Trigger:            Booking is cancelled with no refund or with refund amount = 0 [SOURCE-CONFIRMED]
Backend Status:           bookings.status = 'cancelled' [SOURCE-CONFIRMED]
Agent Display to Guest:   Confirm cancellation and explain that no refund is due when that is the case [PROPOSED CONTRACT]
Valid Next States:        searching [DESIGN-BASELINE]
Transitions:
  searching:              guest wants to book again [DESIGN-BASELINE]
Backend Action at Entry:  BookingCancelled event may already have been dispatched [SOURCE-CONFIRMED]
Agent Action at Entry:    Clear booking_snapshot and close the flow [PROPOSED CONTRACT]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         None or zero-refund outcome [SOURCE-CONFIRMED]
Classification:           SOURCE-CONFIRMED
```

---

### State 16: `refund_processing`

```text
State:                    refund_processing [DESIGN-BASELINE]
Entry Trigger:            CancellationService sets status = refund_pending before final refund settlement [SOURCE-CONFIRMED]
Backend Status:           bookings.status = 'refund_pending' [SOURCE-CONFIRMED]
Agent Display to Guest:   Explain that refund settlement is in progress and may require staff follow-up if it fails [PROPOSED CONTRACT]
Valid Next States:        refunded, cancelled [DESIGN-BASELINE]
Transitions:
  refunded:               refund succeeds and final status becomes cancelled with refund_status = succeeded [SOURCE-CONFIRMED]
  cancelled:              refund amount is zero and finalization completes without a payable refund [SOURCE-CONFIRMED]
Backend Action at Entry:  Stripe refund call runs through IdempotencyGuard; ReconcileRefundsJob exists for stale refund states [SOURCE-CONFIRMED]
Agent Action at Entry:    If a refund failure is surfaced, escalate to staff instead of retrying inside chat [PROPOSED CONTRACT]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         This is the refund settlement stage, not guest payment initiation [SOURCE-CONFIRMED]
Classification:           SOURCE-CONFIRMED
```

---

### State 17: `refunded`

```text
State:                    refunded [DESIGN-BASELINE]
Entry Trigger:            Refund settles successfully after cancellation [SOURCE-CONFIRMED]
Backend Status:           bookings.status = 'cancelled' AND refund_status = 'succeeded' [SOURCE-CONFIRMED]
Agent Display to Guest:   Confirm cancellation and the successful refund outcome [PROPOSED CONTRACT]
Valid Next States:        searching [DESIGN-BASELINE]
Transitions:
  searching:              guest wants a new booking after refund completion [DESIGN-BASELINE]
Backend Action at Entry:  CancellationService finalizes cancellation with refund metadata or Stripe webhook later records the successful refund [SOURCE-CONFIRMED]
Agent Action at Entry:    Show refund amount when present in the source response [PROPOSED CONTRACT]
Hold Expiry Behavior:     N/A [SOURCE-CONFIRMED]
Payment Coupling:         Refund completed [SOURCE-CONFIRMED]
Classification:           SOURCE-CONFIRMED
```

---

## ARTIFACT 3 — Redis Session Schema vFinal

### Key Structure

```text
Primary state:    soleil:session:{session_id}:state [DESIGN-BASELINE]
Tool cache:       soleil:session:{session_id}:tool:{tool_name}:{params_hash} [DESIGN-BASELINE]
Idempotency log:  soleil:session:{session_id}:idem:{operation} [PROPOSED CONTRACT]
Escalation log:   soleil:session:{session_id}:escalation [DESIGN-BASELINE]
```

### TTL Rules

| Session Condition | TTL | Classification |
|---|---|---|
| Active session | `last_activity_at + 30 minutes` | `OPERATIONAL DEFAULT` |
| Completed booking flow | `session end + 5 minutes` | `OPERATIONAL DEFAULT` |
| Escalated session | `session end + 24 hours` | `OPERATIONAL DEFAULT` |
| Abandoned session | `60 minutes` | `OPERATIONAL DEFAULT` |

### Primary State Object

```json
{
  "session_id": {
    "type": "string",
    "required": true,
    "default": "generated UUID",
    "purpose": "Stable identifier for the chat session.",
    "set_by": "session manager",
    "read_by": "all agents and tool dispatcher",
    "cleared_on": "never",
    "classification": "DESIGN-BASELINE"
  },
  "guest_user_id": {
    "type": "integer | null",
    "required": false,
    "default": "null",
    "purpose": "Authenticated guest user ID derived from the passthrough auth context.",
    "set_by": "chat gateway after auth resolution",
    "read_by": "tool dispatcher and write-path guards",
    "cleared_on": "auth expiry or session reset",
    "classification": "PROPOSED CONTRACT"
  },
  "auth_mode": {
    "type": "string | null",
    "required": false,
    "default": "null",
    "purpose": "How the guest arrived at the gateway: bearer or cookie.",
    "set_by": "chat gateway",
    "read_by": "tool dispatcher",
    "cleared_on": "session reset",
    "classification": "PROPOSED CONTRACT"
  },
  "auth_token_encrypted": {
    "type": "string | null",
    "required": false,
    "default": "null",
    "purpose": "Encrypted outbound credential only when an external HTTP tool dispatcher needs the guest bearer token.",
    "set_by": "chat gateway",
    "read_by": "tool dispatcher only",
    "cleared_on": "auth expiry, logout, or session reset",
    "classification": "PROPOSED CONTRACT"
  },
  "active_agent": {
    "type": "string",
    "required": true,
    "default": "orchestrator",
    "purpose": "Current active agent for the session.",
    "set_by": "orchestrator or session manager",
    "read_by": "all agents",
    "cleared_on": "session reset",
    "classification": "DESIGN-BASELINE"
  },
  "prior_agent": {
    "type": "string | null",
    "required": false,
    "default": "null",
    "purpose": "Most recent agent before the current one.",
    "set_by": "session manager during handoff",
    "read_by": "receiving agent",
    "cleared_on": "session reset",
    "classification": "DESIGN-BASELINE"
  },
  "intent_label": {
    "type": "string",
    "required": true,
    "default": "unknown",
    "purpose": "Most recent classified user intent.",
    "set_by": "Intent_Orchestrator",
    "read_by": "session manager and active worker agent",
    "cleared_on": "overwritten on reclassification",
    "classification": "DESIGN-BASELINE"
  },
  "clarify_streak": {
    "type": "integer",
    "required": true,
    "default": "0",
    "purpose": "Number of consecutive clarification turns without a stable route.",
    "set_by": "Intent_Orchestrator",
    "read_by": "Intent_Orchestrator",
    "cleared_on": "successful route or session reset",
    "classification": "PROPOSED CONTRACT"
  },
  "agent_switch_count": {
    "type": "integer",
    "required": true,
    "default": "0",
    "purpose": "Total handoffs inside the session.",
    "set_by": "session manager",
    "read_by": "observability and escalation rules",
    "cleared_on": "session reset",
    "classification": "PROPOSED CONTRACT"
  },
  "turn_count": {
    "type": "integer",
    "required": true,
    "default": "0",
    "purpose": "Guest turns handled under the current active agent.",
    "set_by": "session manager",
    "read_by": "session manager and observability",
    "cleared_on": "agent switch or session reset",
    "classification": "DESIGN-BASELINE"
  },
  "last_activity_at": {
    "type": "string",
    "required": true,
    "default": "current timestamp at session creation",
    "purpose": "Last guest activity timestamp used for TTL refresh.",
    "set_by": "session manager",
    "read_by": "TTL logic",
    "cleared_on": "overwritten each turn",
    "classification": "DESIGN-BASELINE"
  },
  "status": {
    "type": "string",
    "required": true,
    "default": "searching",
    "purpose": "Session lifecycle state: searching, booking_active, modify_active, escalated, completed, auth_expired.",
    "set_by": "session manager",
    "read_by": "tool dispatcher and handoff logic",
    "cleared_on": "overwritten on transition",
    "classification": "PROPOSED CONTRACT"
  },
  "collected_slots": {
    "type": "object",
    "required": true,
    "default": "{}",
    "purpose": "Booking and modification slots collected so far.",
    "set_by": "Booking_Agent and Modify_Agent",
    "read_by": "Booking_Agent, Modify_Agent, tool dispatcher",
    "cleared_on": "session reset or flow completion",
    "classification": "DESIGN-BASELINE"
  },
  "missing_slots": {
    "type": "array",
    "required": true,
    "default": "[location_id, check_in, check_out, room_id, guest_name, guest_email]",
    "purpose": "Still-missing fields before create_pending_booking is allowed.",
    "set_by": "Booking_Agent",
    "read_by": "Booking_Agent and write-path guard",
    "cleared_on": "all slots collected or session reset",
    "classification": "DESIGN-BASELINE"
  },
  "booking_snapshot": {
    "type": "object | null",
    "required": false,
    "default": "null",
    "purpose": "Current booking state known to the chat layer.",
    "set_by": "Booking_Agent after create_pending_booking and Modify_Agent after get_booking_detail",
    "read_by": "Modify_Agent and write-path guard",
    "cleared_on": "completion, expiry, or session reset",
    "classification": "DESIGN-BASELINE"
  },
  "modification_snapshot": {
    "type": "object | null",
    "required": false,
    "default": "null",
    "purpose": "Proposed changes before apply_modification.",
    "set_by": "Modify_Agent",
    "read_by": "Modify_Agent and write-path guard",
    "cleared_on": "successful modification, abandonment, or session reset",
    "classification": "PROPOSED CONTRACT"
  },
  "last_tool_result": {
    "type": "object | null",
    "required": false,
    "default": "null",
    "purpose": "Metadata summary of the most recent tool call.",
    "set_by": "tool dispatcher",
    "read_by": "active agent",
    "cleared_on": "overwritten by the next tool call",
    "classification": "DESIGN-BASELINE"
  },
  "tool_last_called_at": {
    "type": "object",
    "required": true,
    "default": "{}",
    "purpose": "Map of tool_name to last execution timestamp for staleness checks.",
    "set_by": "tool dispatcher",
    "read_by": "write-path guard",
    "cleared_on": "session reset",
    "classification": "PROPOSED CONTRACT"
  },
  "escalation_flags": {
    "type": "array",
    "required": true,
    "default": "[]",
    "purpose": "Append-only escalation reasons collected during the session.",
    "set_by": "any active agent",
    "read_by": "session manager and human handoff flow",
    "cleared_on": "session reset",
    "classification": "DESIGN-BASELINE"
  },
  "context_summary": {
    "type": "string | null",
    "required": false,
    "default": "null",
    "purpose": "Compact natural-language summary for handoffs and recovery.",
    "set_by": "summary generator",
    "read_by": "receiving agent",
    "cleared_on": "regenerated or session reset",
    "classification": "DESIGN-BASELINE"
  },
  "context_summary_generated_at": {
    "type": "string | null",
    "required": false,
    "default": "null",
    "purpose": "Timestamp for the current context summary.",
    "set_by": "summary generator",
    "read_by": "session manager",
    "cleared_on": "summary regeneration or session reset",
    "classification": "PROPOSED CONTRACT"
  }
}
```

---

## ARTIFACT 4 — Write-Path Safety Rules vFinal

---

### Operation 1: `create_pending_booking`

```text
Operation:              Create a pending booking [DESIGN-BASELINE]
Agent Alias:            create_pending_booking [DESIGN-BASELINE]
Backend Endpoint:       POST /api/v1/bookings [SOURCE-CONFIRMED]
Classification:         SOURCE-CONFIRMED endpoint + PROPOSED CONTRACT gateway enforcement

Preconditions (all must be true before agent calls this operation):
  1. guest_user_id is present in session state [PROPOSED CONTRACT]
  2. required slots are present: room_id, check_in, check_out, guest_name, guest_email [SOURCE-CONFIRMED]
  3. guest_count does not exceed the selected room's max_guests [PROPOSED CONTRACT]
  4. get_price_quote is fresh enough for a final summary review [PROPOSED CONTRACT]
  5. the guest explicitly confirmed the final booking summary [DESIGN-BASELINE]

Idempotency:
  Key Shape:         booking:{guest_user_id}:{room_id}:{check_in}:{check_out} [PROPOSED CONTRACT]
  Key Scope:         user [PROPOSED CONTRACT]
  Key TTL:           5 minutes [OPERATIONAL DEFAULT]
  Enforcement Layer: DB overlap protection + pessimistic locking exist today [SOURCE-CONFIRMED]
                     request-idempotency middleware does not exist today [SOURCE-CONFIRMED]
                     local session idem key should exist in Redis [PROPOSED CONTRACT]
  Current Status:    PROPOSED CONTRACT

Duplicate Handling:
  If already succeeded:  return the known booking_snapshot instead of replaying POST [PROPOSED CONTRACT]
  If in progress:        reject the second attempt locally and wait for the first result [PROPOSED CONTRACT]
  If previously failed:  require the agent to reconfirm intent before any new POST [PROPOSED CONTRACT]

Retry Rules:
  Agent layer:     never retry automatically after timeout or network failure [DESIGN-BASELINE]
  Transport layer: do not enable automatic POST retries [DESIGN-BASELINE]
  DB layer:        overlap checks and locking still protect room correctness [SOURCE-CONFIRMED]

Failure Handling:
  On tool timeout:       do not replay the POST; check read-path state only if a booking_id is already known [PROPOSED CONTRACT]
  On validation error:   report the validation/conflict error and ask the guest to correct inputs [PROPOSED CONTRACT]
  On constraint error:   explain that the room is no longer available and return to room search [PROPOSED CONTRACT]
  On auth error:         stop the write flow and tell the guest to log in or verify email [PROPOSED CONTRACT]

Post-Write Verification:
  Verify the response status is `pending`, persist booking_snapshot, and tell the guest that `pending` is not `confirmed` [SOURCE-CONFIRMED + PROPOSED CONTRACT]

Audit Logging:
  Required fields:   session_id, guest_user_id, room_id, check_in, check_out, booking_id, response_status, latency_ms [PROPOSED CONTRACT]
  Retention:         90 days [OPERATIONAL DEFAULT]

Classification Notes:
  Exact-once semantics above the DB layer are still missing if the gateway can retry writes [SOURCE-CONFIRMED].
  Inspect backend/app/Http/Middleware and backend/bootstrap/app.php if a request-idempotency middleware is required before launch [PROPOSED CONTRACT].
```

---

### Operation 2: `apply_modification`

```text
Operation:              Modify an existing booking [DESIGN-BASELINE]
Agent Alias:            apply_modification [DESIGN-BASELINE]
Backend Endpoint:       PUT /api/v1/bookings/{booking} [SOURCE-CONFIRMED]
Classification:         SOURCE-CONFIRMED endpoint + PROPOSED CONTRACT gateway enforcement

Preconditions (all must be true before agent calls this operation):
  1. booking_snapshot exists and matches the booking being modified [PROPOSED CONTRACT]
  2. check_modification_eligibility succeeded for the exact proposed dates [PROPOSED CONTRACT]
  3. the guest explicitly confirmed the before/after summary [DESIGN-BASELINE]
  4. the adapter has backfilled guest_name and guest_email because UpdateBookingRequest requires them even for a date-only change [SOURCE-CONFIRMED + PROPOSED CONTRACT]

Idempotency:
  Key Shape:         modify:{booking_id}:{check_in}:{check_out}:{guest_email} [PROPOSED CONTRACT]
  Key Scope:         user [PROPOSED CONTRACT]
  Key TTL:           5 minutes [OPERATIONAL DEFAULT]
  Enforcement Layer: overlap checks + DB locking in CreateBookingService::update [SOURCE-CONFIRMED]
                     request-idempotency middleware does not exist today [SOURCE-CONFIRMED]
                     local session idem key should exist in Redis [PROPOSED CONTRACT]
  Current Status:    PROPOSED CONTRACT

Duplicate Handling:
  If already succeeded:  return the refreshed booking_snapshot instead of replaying PUT [PROPOSED CONTRACT]
  If in progress:        reject the second attempt locally and wait for the first result [PROPOSED CONTRACT]
  If previously failed:  require a fresh eligibility check before any new PUT [PROPOSED CONTRACT]

Retry Rules:
  Agent layer:     never resend the same write automatically [DESIGN-BASELINE]
  Transport layer: do not enable automatic PUT retries [DESIGN-BASELINE]
  DB layer:        overlap checks and locking still protect room correctness [SOURCE-CONFIRMED]

Failure Handling:
  On tool timeout:       re-read get_booking_detail before deciding whether the update landed [PROPOSED CONTRACT]
  On validation error:   surface the backend reason and return to modification collection [PROPOSED CONTRACT]
  On constraint error:   explain the overlap and ask for new dates [PROPOSED CONTRACT]
  On auth error:         stop the write flow and explain the auth/ownership issue [PROPOSED CONTRACT]

Post-Write Verification:
  Compare response values to the proposed values, refresh booking_snapshot, and clear modification_snapshot [PROPOSED CONTRACT]

Audit Logging:
  Required fields:   session_id, guest_user_id, booking_id, old_dates, new_dates, response_status, latency_ms [PROPOSED CONTRACT]
  Retention:         90 days [OPERATIONAL DEFAULT]

Classification Notes:
  Partial-update UX is adapter work until the backend exposes a true patch-style contract. [SOURCE-CONFIRMED + PROPOSED CONTRACT]
```

---

### Operation 3: `cancel_booking`

```text
Operation:              Cancel an existing booking [DESIGN-BASELINE]
Agent Alias:            cancel_booking [DESIGN-BASELINE]
Backend Endpoint:       POST /api/v1/bookings/{booking}/cancel [SOURCE-CONFIRMED]
Classification:         SOURCE-CONFIRMED

Preconditions (all must be true before agent calls this operation):
  1. booking_snapshot exists or get_booking_detail has just confirmed the booking ID [PROPOSED CONTRACT]
  2. check_cancellation_eligibility succeeded and its result was shown to the guest [PROPOSED CONTRACT]
  3. the guest used unambiguous cancellation language [DESIGN-BASELINE]

Idempotency:
  Key Shape:         cancel:{booking_id} [PROPOSED CONTRACT]
  Key Scope:         booking [PROPOSED CONTRACT]
  Key TTL:           24 hours for the refund guard; session idem entry may mirror that window [SOURCE-CONFIRMED + PROPOSED CONTRACT]
  Enforcement Layer: already-cancelled early return + lockForUpdate + IdempotencyGuard around refunds [SOURCE-CONFIRMED]
  Current Status:    SOURCE-CONFIRMED

Duplicate Handling:
  If already succeeded:  backend short-circuits safely and returns the cancelled booking state [SOURCE-CONFIRMED]
  If in progress:        backend locking and IdempotencyGuard serialize the result [SOURCE-CONFIRMED]
  If previously failed:  escalate instead of replaying inside chat if refund work failed [PROPOSED CONTRACT]

Retry Rules:
  Agent layer:     do not replay cancellation automatically [DESIGN-BASELINE]
  Transport layer: transport retries are unnecessary; rely on source-confirmed service idempotency if a duplicate arrives [DESIGN-BASELINE]
  DB layer:        lockForUpdate and refund idempotency guard enforce safe duplicate handling [SOURCE-CONFIRMED]

Failure Handling:
  On tool timeout:       re-read booking status; if still ambiguous, escalate instead of replaying the cancel [PROPOSED CONTRACT]
  On validation error:   unexpected for this endpoint; escalate for operator review [PROPOSED CONTRACT]
  On constraint error:   surface the backend reason such as not cancellable or already started [PROPOSED CONTRACT]
  On auth error:         stop the flow and explain the auth/ownership problem [PROPOSED CONTRACT]

Post-Write Verification:
  If response status is cancelled, close the flow and tell the guest whether a refund was due; if refund failure is surfaced, move to staff escalation and do not report full success. [PROPOSED CONTRACT]

Audit Logging:
  Required fields:   session_id, guest_user_id, booking_id, pre_status, post_status, refund_status, refund_amount, latency_ms [PROPOSED CONTRACT]
  Retention:         1 year for financial-adjacent cancellation records [OPERATIONAL DEFAULT]

Classification Notes:
  Guest cancellation_reason is not persisted today [SOURCE-CONFIRMED].
  Inspect backend/app/Http/Controllers/BookingController.php and backend/app/Services/CancellationService.php only if product explicitly needs that audit field [REQUIRES SOURCE INSPECTION].
```

---

*Round 3 final implementation contracts completed on 2026-03-23.*
