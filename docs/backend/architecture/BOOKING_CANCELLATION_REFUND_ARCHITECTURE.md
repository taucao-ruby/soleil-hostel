# 📋 Booking Cancellation & Refund Architecture

> **As-built reference** for Soleil Hostel on Laravel 12 + Cashier (Stripe).
> **Last Updated:** May 31, 2026
>
> This is the single canonical cancellation/refund design document. The former
> `docs/backend/BOOKING_CANCELLATION_FLOW.md` is now a redirect stub pointing here.

---

## Scope & canonical sources

This document describes how a booking is cancelled and how its payment is
refunded **as the code actually does it today**. Where a fact is owned by a
higher layer, defer to it rather than restating:

- Column semantics & invariants: [`docs/agents/ARCHITECTURE_FACTS.md`](../../agents/ARCHITECTURE_FACTS.md) (especially *§bookings.refund_id semantics*)
- Schema / constraints / indexes: [`docs/DB_FACTS.md`](../../DB_FACTS.md)
- Booking feature overview & double-booking prevention: [`docs/backend/features/BOOKING.md`](../features/BOOKING.md)
- Stuck-webhook reaper: [`docs/backend/STRIPE_WEBHOOK_RECONCILIATION.md`](../STRIPE_WEBHOOK_RECONCILIATION.md)

---

## 1. High-level model

Cancellation is **synchronous on the happy path, durable underneath**:

1. The cancel request transitions the booking under a row lock, then issues the
   Stripe refund **outside** the transaction (network I/O must not hold locks).
2. The refund is recorded in an **authoritative ledger** (`stripe_refund_events`)
   transactionally coupled with the booking's refund projection.
3. If the process dies mid-flight, or Stripe is slow, or a webhook is lost, the
   **`ReconcileRefundsJob` scheduler** converges the state every 5 minutes.

Every write path is idempotent. A refund that Stripe accepted but that was lost
to a timeout is **never** issued twice (stable idempotency keys + a pre-check),
and a refund observed by two sources (live cancel, webhook, reconciler) produces
exactly **one** ledger row (UNIQUE replay guard).

There is **no** `RefundPolicyService`, no `ProcessRefundJob`, and the webhook is
**not** handled by `Cashier::handleWebhook()` — see §10 for what replaced the
original design draft.

### Component map

| Concern | Class | Path |
| --- | --- | --- |
| Cancellation orchestration | `CancellationService` | `app/Services/CancellationService.php` |
| Refund / PI Stripe calls | `StripeService` | `app/Services/StripeService.php` |
| Authoritative refund ledger writer | `StripeRefundEventRecorder` | `app/Services/Payment/StripeRefundEventRecorder.php` |
| Webhook ingestion (custom, fail-closed) | `StripeWebhookController` | `app/Http/Controllers/Payment/StripeWebhookController.php` |
| Orphaned-state recovery | `ReconcileRefundsJob` | `app/Jobs/ReconcileRefundsJob.php` |
| PI cancellation outbox (PAY-03) | `ProcessPaymentCancellationOutbox` | `app/Jobs/ProcessPaymentCancellationOutbox.php` |
| Deposit refund (async) | `ProcessDepositRefund` | `app/Jobs/ProcessDepositRefund.php` |
| Booking status machine | `BookingStatus` | `app/Enums/BookingStatus.php` |
| Refund status projection | `RefundStatus` | `app/Enums/RefundStatus.php` |

---

## 2. Booking state machine

`BookingStatus` (`app/Enums/BookingStatus.php`) is a 5-state machine. The only
legal mutation is `Booking::transitionTo()` (`app/Models/Booking.php:338`), which
takes a row lock, validates `canTransitionTo()`, and fires `BookingStatusChanged`.
Direct `UPDATE bookings SET status = …` is forbidden.

| State | Value | Meaning |
| --- | --- | --- |
| `PENDING` | `pending` | Created, not yet confirmed (holds the room) |
| `CONFIRMED` | `confirmed` | Payment confirmed (holds the room) |
| `REFUND_PENDING` | `refund_pending` | Cancelled, refund in flight |
| `CANCELLED` | `cancelled` | Terminal — refunded (or no refund due) |
| `REFUND_FAILED` | `refund_failed` | Refund failed; retryable by the reconciler |

`ACTIVE_STATUSES = [pending, confirmed]` — only these block overlap (the
half-open `[check_in, check_out)` exclusion constraint filters to them).

### Allowed transitions (exhaustive)

`BookingStatus::canTransitionTo()` (`app/Enums/BookingStatus.php:72`):

| From → To | `CONFIRMED` | `REFUND_PENDING` | `CANCELLED` | `REFUND_FAILED` |
| --- | :---: | :---: | :---: | :---: |
| `PENDING` | ✅ | ✅ | ✅ | — |
| `CONFIRMED` | — | ✅ | ✅ | — |
| `REFUND_PENDING` | — | — | ✅ | ✅ |
| `CANCELLED` | — | — | — | — |
| `REFUND_FAILED` | — | ✅ (retry) | ✅ (force) | — |

`CANCELLED` is terminal. `isCancellable()` is true for `PENDING`, `CONFIRMED`,
and `REFUND_FAILED` (retry after failure).

---

## 3. Refund status projection (`RefundStatus`)

`bookings.refund_status` is a **closed 3-state string projection** — `pending`,
`succeeded`, `failed` (`app/Enums/RefundStatus.php`). It is intentionally **not**
an enum cast on the model; it is persisted as the `->value` string and also backs
the published OpenAPI `Booking.refund_status` enum (locked by
`OpenApiEnumContractTest`).

Stripe emits a wider set (`pending`, `requires_action`, `succeeded`, `failed`,
`canceled`). Those raw values **must** be normalized via
`RefundStatus::tryFromStripe()` (`app/Enums/RefundStatus.php:50`) before they
touch the column. Callers **fail closed** on `null`:

| Stripe status | Internal |
| --- | --- |
| `pending`, `requires_action` | `pending` |
| `succeeded` | `succeeded` |
| `failed`, `canceled` | `failed` |
| anything else | `null` → reject, never persist |

### Latest-pointer vs ledger (do not confuse)

Per [`ARCHITECTURE_FACTS.md` §bookings.refund_id semantics](../../agents/ARCHITECTURE_FACTS.md):

- **`bookings.refund_id`** — latest Stripe refund pointer for operational lookup.
  Overwritten by each subsequent refund under partial refunds. **Not** a ledger.
- **`stripe_refund_events`** — the authoritative refund history ledger. UNIQUE on
  `stripe_refund_id` is the durable replay guard. Refund history, total-refunded,
  full-refund detection, and reconciliation MUST read from this table.

---

## 4. Cancellation lifecycle

Entry point: `POST /api/v1/bookings/{booking}/cancel` →
`BookingController::cancel` (route `v1.bookings.cancel`, `throttle:10,1`,
`routes/api/v1.php:66`). Authorization is `Gate::authorize('cancel', $booking)`
via `BookingPolicy::cancel`, with a service-layer ownership re-check as
defense-in-depth (§8).

`CancellationService::cancel()` (`app/Services/CancellationService.php:67`) runs
in three phases:

```
cancel(Booking, User actor)
│
├─ 0. Idempotency (BL-6): status == CANCELLED → log + return fresh(), no-op.
│      No event, no Stripe, no deposit mutation, no audit overwrite.
│
├─ 1. validateCancellation()                        :184
│      ownership (admin|owner) · isCancellable() · isStarted() guard
│
├─ 2. transitionToRefundPending()  [DB::transaction + lockForUpdate]   :206
│      re-read under lock → REFUND_PENDING if isRefundable(), else CANCELLED
│      write cancelled_at + immutable actor snapshot
│      not-refundable → dispatch BookingCancelled now
│
├─ 3. processRefund()  [OUTSIDE transaction — Stripe I/O]              :277
│      amount = Booking::calculateRefundAmount()
│      StripeService::createBookingRefund(booking, amount, client)   (SH-02)
│      success → finalizeCancellation()                               :373
│      failure → handleRefundFailure() → REFUND_FAILED + throw        :472
│
└─ 4. transitionDepositForCancellation()  (CONC-005)                  :122
       Deposit::transitionTo() writes deposit_events;
       refundPercent>0 → dispatch ProcessDepositRefund (async)
```

`finalizeCancellation()` (`:373`, **F-33**) re-acquires the row lock and re-reads
fresh (the Stripe call ran lock-free, so an out-of-band path may have moved the
row). It then, **inside one transaction**:

1. Writes the ledger row first via `StripeRefundEventRecorder::record()` (only
   when a refund was issued) — **SH-03 / F-74**.
2. `transitionTo(CANCELLED)` and projects `refund_id`, `refund_status='succeeded'`,
   `refund_amount`, `refund_error=null`.
3. Dispatches `BookingCancelled`.

If a racing webhook/reconciler already recorded the refund, the ledger INSERT
throws `UniqueConstraintViolationException`, caught **outside** the transaction
and treated as idempotent convergence (`:432`).

`isRefundable()` (`:250`) = `payment_intent_id !== null && refund_id === null &&
payment_status === PAID && status->isCancellable()`.

`forceCancel()` (`:496`) is the admin path: straight to `CANCELLED`, always
forfeits a held deposit (policy bypassed at the booking layer, but the deposit
FSM must still leave `collected`).

---

## 5. Refund execution & idempotency

Refunds are issued through `StripeService`, **not** Cashier's `$user->refund()`.
Both the live cancellation path and the reconciler call the same centralized
methods so the idempotency key is identical across them:

- `StripeService::createBookingRefund()` — live cancellation (SH-02 / F-76).
- `StripeService::createReconciliationRefund()` — reconciler retry (PAY-01).

The key is `bookingRefundIdempotencyKey($booking)` — a pure function of the
durable `(booking, payment_intent)`. A refund Stripe accepted but lost to an HTTP
timeout is therefore **de-duplicated by Stripe on retry** instead of double-refunding
the guest. Each refund also carries `metadata.booking_id` and
`metadata.soleil_refund_event_id` so the reconciler can recognize its own prior
refunds.

### The idempotency stack

| Layer | Guard | Where |
| --- | --- | --- |
| **BL-6** terminal no-op | already-`CANCELLED` cancel returns fresh row, zero side effects | `CancellationService:76` |
| Row lock + re-read | `transitionToRefundPending` / `finalizeCancellation` lock before mutating | `CancellationService:206,373` |
| **SH-02** Stripe idempotency key | stable per-booking key → Stripe dedups a lost-timeout refund | `StripeService::createBookingRefund` |
| **SH-03** ledger-first write | ledger row written in the same tx as the booking projection | `CancellationService:409` |
| **PAY-04** ledger UNIQUE | `stripe_refund_events.stripe_refund_id` UNIQUE = the linearization point | `StripeRefundEventRecorder:58` |
| **BL-3** webhook dedup | INSERT-first on `stripe_webhook_events.stripe_event_id` UNIQUE | `StripeWebhookController:129` |
| **PAY-01** reconciler claim + pre-check | CAS lease on `updated_at` + existing-refund discovery before any create | `ReconcileRefundsJob:180,588` |

The recorder contract (`StripeRefundEventRecorder`, PAY-04): callers **must**
`record()` inside the same `DB::transaction` that writes the booking projection
and catch `UniqueConstraintViolationException` **outside** it, so the booking can
never drift ahead of the ledger. `booking_id` always comes from the trusted DB
row, never from Stripe metadata.

---

## 6. Webhook handling

`StripeWebhookController extends Cashier's WebhookController` but **owns signature
verification** — route `POST /api/webhooks/stripe`
(`routes/api.php:124` → `handleWebhook`).

It deliberately does **not** call `parent::__construct()`: Cashier registers its
`VerifyWebhookSignature` middleware only when `cashier.webhook.secret` is truthy,
so an empty/unset secret would silently accept every unsigned webhook — an
unauthenticated path into booking confirmation and refund state. Instead it
**fails closed** in `handleWebhook()` (`:65`):

| Condition | Response |
| --- | --- |
| secret not configured | `500` (server misconfiguration) |
| missing `Stripe-Signature` header | `400` |
| malformed JSON payload | `400` |
| signature mismatch / expired | `400` |
| verified | delegate to `parent::handleWebhook()` dispatcher |

### Handled events

| Event | Handler | Effect |
| --- | --- | --- |
| `payment_intent.succeeded` | `:110` | Confirm booking via `StripePaymentIntentSucceededHandler` (idempotent) |
| `charge.refunded` | `:190` | Normalize status (`tryFromStripe`), guard illegal transition, ledger-first record, project refund → `CANCELLED`/`REFUND_FAILED` |
| `payment_intent.payment_failed` | `:459` | `payment_status = FAILED`; booking stays `PENDING` for retry |
| `payment_intent.canceled` | `:535` | `payment_status = CANCELLED`; a `PENDING` booking → `CANCELLED` |
| `payment_intent.amount_capturable_updated` | `:607` | Only for `AUTHORIZE_THEN_CAPTURE`: `payment_status = AUTHORIZED` |

`charge.refunded` is **fail-closed and self-healing**: an unrecognized refund
status (SH-05 / F-73) or an illegal state transition is logged, marks the webhook
event `failed` for operator visibility, and acks `200` (a retry never reclassifies
a permanent business-state error) — the booking is never mutated. Unexpected
runtime errors return `500` so Stripe retries and `ReconcileRefundsJob` remains
the durable recovery path.

---

## 7. Reconciliation & recovery

`ReconcileRefundsJob` (`app/Jobs/ReconcileRefundsJob.php`) runs **every 5 minutes**
(`Schedule::job(new ReconcileRefundsJob)->everyFiveMinutes()->withoutOverlapping()
->onOneServer()`, name `reconcile-refunds`, `routes/console.php:26`). `tries = 3`,
`backoff = [60, 300, 900]`.

It has two passes:

1. **`reconcilePendingRefunds()`** (`:102`) — bookings stuck in `REFUND_PENDING`
   older than `booking.reconciliation.stale_threshold_minutes` (default 5).
   Queries Stripe for the real refund status, records the ledger, and finalizes
   (`succeeded → CANCELLED`, `failed → REFUND_FAILED`, `pending → leave`).
2. **`retryFailedRefunds()`** (`:119`) — `REFUND_FAILED`, older than 15 min, with
   a `payment_intent_id` and no `refund_id`. Retry count is parsed from
   `refund_error` (`[Attempt N]`), capped at `booking.reconciliation.max_attempts`
   (default 5).

**PAY-01** prevents double-refunding under concurrency:

- `claimFailedRefund()` (`:180`) — atomic compare-and-swap on `updated_at`; a
  second worker's identical claim matches zero rows. No row lock is held across
  the Stripe call.
- `findExistingStripeRefundForBooking()` (`:588`) — before creating any refund,
  retrieve the PaymentIntent's refunds and match by identity
  (`metadata.soleil_refund_event_id`) or fallback (amount + currency +
  `booking_id`). Exactly one usable match → sync it; more than one → mark
  **ambiguous** for manual reconciliation; none → safe to create.

Null-user safe (CONC-006): a deleted guest leaves `user_id = NULL` (FK
`ON DELETE SET NULL`); `resolveStripeClientFor()` falls back to the
application-level `Cashier::stripe()` client.

### Related schedulers (all every 5 min, `routes/console.php`)

| Name | Job/command | Purpose |
| --- | --- | --- |
| `reconcile-refunds` | `ReconcileRefundsJob` | Orphaned refund recovery (this doc) |
| `reconcile-stuck-stripe-webhooks` | `webhook:reconcile-stuck-events` | Replays webhook events stuck in `processing` — see [STRIPE_WEBHOOK_RECONCILIATION.md](../STRIPE_WEBHOOK_RECONCILIATION.md) |
| `expire-stale-bookings` | `ExpireStaleBookings` | Auto-cancels unconfirmed `PENDING` past TTL (`booking.pending_ttl_minutes`, default 30) |
| `process-payment-cancellation-outbox` | `ProcessPaymentCancellationOutbox` | Drains the PI-cancellation outbox off the booking lock (PAY-03) |

---

## 8. Authorization

Cancellation ownership is enforced at **two independent layers**
(see [`ARCHITECTURE_FACTS.md` §Cancellation Ownership: Defense-in-Depth](../../agents/ARCHITECTURE_FACTS.md)):

1. **Policy / controller** — `Gate::authorize('cancel', $booking)` →
   `BookingPolicy::cancel` (ownership, status, refund window).
2. **Service** — `CancellationService::validateCancellation()` (`:184`) re-checks
   `! $actor->isAdmin() && booking.user_id !== actor.id` and throws
   `BookingCancellationException::unauthorized()`. This guards alternate callers
   that reach the service without the controller policy — notably
   `ProposalConfirmationController::executeCancellation`.

Admins are exempt from the ownership and the post-check-in guards, matching
`BookingPolicy::cancel`.

---

## 9. Cancellation & refund policy

Refund amount is computed by `Booking::calculateRefundAmount()`
(`app/Models/Booking.php:266`); the matching value object is
`Booking::cancellationPolicy()` (`:171`, shared with the deposit FSM). All windows
are evaluated in **hostel-local civil time** (`Asia/Ho_Chi_Minh` by default) via
`HostelClock` — not UTC. Money is integer minor units (cents).

| Window (hours before check-in) | Refund | Config key (`config/booking.php`) | Default |
| --- | --- | --- | --- |
| `≥ full_refund_hours` | 100% | `cancellation.full_refund_hours` | 48 |
| `≥ partial_refund_hours` (and `< full`) | `partial_refund_pct`% | `cancellation.partial_refund_pct` | 50 |
| `< partial_refund_hours` | 0% | — | — |
| past check-in (`hours < 0`) | 0% | — | — |

If `cancellation.allow_fee` is true (default `false`), the refund percentage is
reduced by `cancellation.fee_pct` (default `0`): `pct = max(0, pct - fee_pct)`.

`cancellation.allow_after_checkin` (default `false`) gates whether a started
booking can be cancelled by a non-admin.

> **SH-01 date immutability.** A *money-final* booking — `isMoneyFinal()` =
> `CONFIRMED || PAID` (`Booking.php:229`) — cannot have its dates edited;
> re-pricing a captured stay would desync the charged amount. Guests must
> cancel + rebook. Enforced at `UpdateBookingRequest` and
> `CreateBookingService::update` (defense in depth).

---

## 10. Failure modes

| Scenario | Booking ends in | Recovery |
| --- | --- | --- |
| Stripe refund API error during cancel | `REFUND_FAILED` (+ `refund_error`) | `retryFailedRefunds()` (capped) |
| Process crashes after Stripe success, before DB write | `REFUND_PENDING` | `reconcilePendingRefunds()` records ledger + finalizes |
| `charge.refunded` webhook lost | `REFUND_PENDING` | reconciler converges from Stripe |
| Duplicate `charge.refunded` delivery | unchanged | BL-3 webhook UNIQUE → ack 200 |
| Live cancel races webhook on the same refund | `CANCELLED` once | PAY-04 ledger UNIQUE; loser converges |
| Two reconciler workers on one row | one refund | PAY-01 CAS claim + pre-check |
| Refund Stripe accepted but our HTTP timed out | `CANCELLED` once | SH-02 key dedups; reconciler pre-check syncs |
| Multiple ambiguous Stripe refunds found | `REFUND_FAILED` | flagged for manual reconciliation |
| Unrecognized raw Stripe refund status | unchanged | SH-05 fail-closed; event marked failed, ack 200 |

---

## 11. Decision log

| Decision | Choice | Why |
| --- | --- | --- |
| Webhook handling | **Custom controller, signature verified in `handleWebhook()`** | Cashier's middleware silently no-ops on an empty secret; we fail closed |
| Refund issuance | **`StripeService` with stable idempotency keys** (not Cashier `$user->refund()`) | One key across live + reconciler paths → Stripe dedups lost-timeout refunds (SH-02) |
| Refund policy | **`Booking::calculateRefundAmount()` / `cancellationPolicy()`** | Single source of truth; no separate `RefundPolicyService` (that draft class was never built) |
| Refund timing | **Synchronous happy path + 5-min reconciler** | Immediate UX, but crash/slow-Stripe/lost-webhook all converge durably |
| Refund history | **`stripe_refund_events` ledger** (not `bookings.refund_id`) | `refund_id` is a latest-pointer; partial refunds need a real ledger (PAY-04) |
| Deposit refund | **Async `ProcessDepositRefund`** | Decoupled from the booking refund; driven by the deposit FSM (CONC-005) |
| Time semantics | **Hostel-local (`HostelClock`) for windows** | Refund windows are civil-time business rules, not UTC instants (SH-01) |

---

## 12. Source map

| Topic | File |
| --- | --- |
| Orchestration | `app/Services/CancellationService.php` |
| Stripe refund/PI calls | `app/Services/StripeService.php` |
| Ledger writer | `app/Services/Payment/StripeRefundEventRecorder.php` |
| Webhook | `app/Http/Controllers/Payment/StripeWebhookController.php` |
| Reconciler | `app/Jobs/ReconcileRefundsJob.php` |
| Status machine | `app/Enums/BookingStatus.php` |
| Refund projection | `app/Enums/RefundStatus.php` |
| Policy & refund math | `app/Models/Booking.php` |
| Config | `config/booking.php` |
| Routes | `routes/api/v1.php` (cancel), `routes/api.php` (webhook), `routes/console.php` (schedulers) |
