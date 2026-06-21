# MoMo Sandbox Payment Adapter — Execution Plan

> Created 2026-06-19. Status: **READY TO EXECUTE** (no code written yet).
> Purpose: a parallel, additive MoMo (sandbox) direct-payment path for the
> "Giữ hoa hồng" direct-booking demo — create payment → guest pays via QR/deeplink
> → IPN confirms the booking. Mirrors the existing Stripe-grade idempotency + confirm
> patterns. **Touches none of the Stripe flow or the booking overlap logic.**
>
> Companion strategy doc: [`docs/business/saas-validation.md`](../business/saas-validation.md).
> Evidence tags: `[CONFIRMED]` verified in source · `[INFERRED]` reasonable assumption ·
> `[UNPROVEN]` needs runtime/spec validation · `[ACTION]` concrete step.

---

## 0. Objective & guardrails

**Objective:** Add a credential-free MoMo sandbox payment path reusing
`BookingService::markPaidAndConfirm` ([`BookingService.php:158`](../../backend/app/Services/BookingService.php)) as the single
audited entry point into the booking state machine — usable as a live sales demo.

**Guardrails (from `CLAUDE.md`):**
- `[CONFIRMED]` Parallel path only — no edits to the Stripe flow, `BookingStatus`, or overlap logic.
- `[CONFIRMED]` No `env()` in runtime code — new `services.momo` config block, read via `config()`.
- `[CONFIRMED]` One additive migration (new table only — **no** constraint changes to `bookings`).
- `[CONFIRMED]` Scope ceiling: **11 files** (2 edits, 9 new) — well under the 25-file stop-and-confirm bar.
- `[CONFIRMED]` No new Composer dependency (HMAC via `hash_hmac`, HTTP via Laravel client).
- `[INFERRED]` No existing symbol modified → soleil impact analysis not required. **If** execution
  deviates to edit `markPaidAndConfirm` or any existing symbol, run
  `soleil-ai-review-engine_impact({target, direction:"upstream"})` first (CLAUDE.md rule).

---

## 1. Pre-session preparation checklist (do BEFORE the execute session)

| # | `[ACTION]` Prep item | Why | How |
|---|---------------------|-----|-----|
| P1 | Start the test DB | `php artisan test` uses PostgreSQL `soleil_test`, not SQLite | `docker compose up -d db` |
| P2 | Verify GATE-0 test-DB preflight passes | Catches a down/misconfigured DB before the suite | `cd backend && php scripts/check-test-db.php` |
| P3 | Create the feature branch | Branch flow `feature/* → dev` | `git checkout dev && git checkout -b feature/momo-sandbox-payment` |
| P4 | Decide MoMo creds | Sandbox needs partnerCode/accessKey/secretKey | Use MoMo **public** sandbox values (T1) **or** your own MoMo Business sandbox keys (env only, never committed) |
| P5 | (LIVE demo only) Public tunnel for IPN | `[UNPROVEN]` MoMo's IPN is server→server; it **cannot reach `localhost`** | `ngrok http 8000` → set `MOMO_IPN_URL` to the public URL. **Not needed for the test suite** (IPN is simulated). |
| P6 | Backend deps installed | Windows needs platform-req flags | `composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` |
| P7 | (Optional) Vietnamese demo copy | User-facing strings are Vietnamese | Decide confirmation-page wording — can be deferred to a later UI task |

**Decision still open for you:** whether to demo with the public sandbox creds (zero setup, shared
across all MoMo sandbox users) or register a real MoMo Business sandbox account (cleaner, your own
transId history). The plan defaults to public creds; swapping is a one-line env change.

---

## 2. MoMo API reference (for the executor — validate against current docs)

`[UNPROVEN]` Recalled from MoMo AIO v2; confirm field lists against the live sandbox during T3/T8.

- **Create endpoint (sandbox):** `POST https://test-payment.momo.vn/v2/gateway/api/create`
- **Query endpoint (optional):** `POST https://test-payment.momo.vn/v2/gateway/api/query`
- **Public sandbox creds:** `partnerCode=MOMO`, `accessKey=F8BBA842ECF85`,
  `secretKey=K951B6PE1waDMi640xX08PD3vg6EkVlz` (MoMo-published test values — not secrets).
- **createPayment request fields:** `partnerCode, partnerName, storeId, requestId, amount, orderId,
  orderInfo, redirectUrl, ipnUrl, lang(vi), requestType(captureWallet), extraData, autoCapture, signature`.
- **createPayment signature raw string (alphabetical):**
  ```
  accessKey={accessKey}&amount={amount}&extraData={extraData}&ipnUrl={ipnUrl}&orderId={orderId}&orderInfo={orderInfo}&partnerCode={partnerCode}&redirectUrl={redirectUrl}&requestId={requestId}&requestType={requestType}
  ```
  → `hash_hmac('sha256', $raw, $secretKey)` (hex).
- **createPayment response:** `resultCode(0=success), payUrl, deeplink, qrCodeUrl, message`.
- **IPN (server→server POST) fields:** `partnerCode, orderId, requestId, amount, orderInfo,
  orderType, transId, resultCode, message, payType, responseTime, extraData, signature`.
- **IPN signature raw string (alphabetical):**
  ```
  accessKey={accessKey}&amount={amount}&extraData={extraData}&message={message}&orderId={orderId}&orderInfo={orderInfo}&orderType={orderType}&partnerCode={partnerCode}&payType={payType}&requestId={requestId}&responseTime={responseTime}&resultCode={resultCode}&transId={transId}
  ```
- `[UNPROVEN]` MoMo expects the IPN endpoint to return **HTTP 204 No Content**. Confirm; our handler
  will ack 204 on success and a controlled 400 on bad signature (fail-closed).

---

## 3. Task breakdown (execute in order)

Dependency order: **T1 → T2 → T3 → {T4, T5} → T6 → T7 → T8 → T9.**

### T1 — Config block + `.env.example`
- **Files:** `backend/config/services.php` (edit), `backend/.env.example` (edit).
- **Do:** Add a `momo` block reading env: `endpoint`, `partner_code`, `access_key`, `secret_key`,
  `ipn_url`, `redirect_url`, `store_id`, `request_type`, plus bounded `connect_timeout`/`read_timeout`
  (mirror the existing `services.stripe` timeout pattern — 2s/5s). Document the public sandbox values
  in `.env.example`.
- **Accept:** `config('services.momo.partner_code')` resolves; `[UNPROVEN]` `AssertProductionConfigTest`
  still passes (keep the block nullable so prod-config assertions never require it).
- **Depends on:** none.

### T2 — DTO + outcome enum
- **Files (new):** `backend/app/Services/Payment/MoMoPaymentStartResult.php`,
  `backend/app/Services/Payment/MoMoIpnOutcome.php`.
- **Do:** `MoMoPaymentStartResult` (readonly): `orderId, requestId, payUrl, deeplink, qrCodeUrl,
  amount, currency` — mirror `PaymentIntentStartResult`. `MoMoIpnOutcome` enum:
  `Confirmed, AlreadyConfirmed, BookingNotFound, InvalidState, AmountMismatch` — mirror
  `PaymentIntentApplyOutcome`.
- **Accept:** Compiles; typed.
- **Depends on:** none (but consumed by T3/T5).

### T3 — `MoMoService` (the gateway adapter)
- **File (new):** `backend/app/Services/MoMoService.php`.
- **Do:**
  - `createPayment(Booking $booking): MoMoPaymentStartResult` — build orderId, requestId, amount/currency
    (reuse `expectedAmount`/`expectedCurrency` semantics from `StripeService`), sign, POST to the endpoint,
    parse `payUrl`/`qrCodeUrl`/`deeplink`. Reject `amount <= 0` and non-`requiresStripePaymentIntent`
    policies (PREPAID demo).
  - `signCreatePayment(array $fields): string` and `signIpn(array $fields): string` — exact alphabetical
    raw strings from §2.
  - `verifyIpnSignature(array $payload): bool` — recompute and `hash_equals`.
  - `orderId(Booking): string` = `soleil-{bookingId}-{nonce}`; `bookingIdFromOrderId(string): ?int` —
    parse + validate. **This is the booking↔order mapping** that avoids a `bookings` migration.
  - `shouldUseTestingFake(): bool` — mirror `StripeService` (`environment('testing') && blank secret`);
    in fake mode return a deterministic `payUrl`/`orderId` so feature tests need no network.
- **Accept:** Unit-tested signature output matches a known MoMo example vector; encode/decode round-trips.
- **Depends on:** T1, T2.

### T4 — IPN idempotency ledger (model + migration)
- **Files (new):** `backend/app/Models/MoMoWebhookEvent.php`,
  `backend/database/migrations/{ts}_create_momo_webhook_events_table.php`.
- **Do:** Table columns: `id, order_id, request_id, trans_id, type, status(processing|processed|failed),
  result_code, payload(jsonb), processed_at, failed_at, error, timestamps`. **UNIQUE(`order_id`,`trans_id`)**
  is the linearization point (INSERT-first dedup, like `stripe_webhook_events.stripe_event_id`). Model:
  `markProcessed()`, `markFailed()`, error sanitization (`hash_hmac`/secret patterns) — port from
  `StripeWebhookEvent`. **Additive table only — no FK to or alteration of `bookings`.**
- **Accept:** `php artisan migrate` up+down clean on `soleil_test`; duplicate insert throws
  `UniqueConstraintViolationException`.
- **Depends on:** none (parallel to T3).

### T5 — `MoMoIpnHandler` (idempotent business effect)
- **File (new):** `backend/app/Services/Payment/MoMoIpnHandler.php`.
- **Do:** `applyToBooking(array $payload): MoMoIpnOutcome` — mirror
  `StripePaymentIntentSucceededHandler`: resolve booking via `bookingIdFromOrderId`, require
  `resultCode === 0`, **guard amount/currency against the booking** (the MoMo analogue of
  `assertPaymentIntentMatchesBooking`), then under `lockForUpdate` reuse
  `BookingService::markPaidAndConfirm($booking, $amount, 0)`. Already-CONFIRMED → `AlreadyConfirmed`;
  non-PENDING → `InvalidState`; amount mismatch → `AmountMismatch` (do **not** confirm).
- **Accept:** Confirms a PENDING prepaid booking once; second call is a no-op `AlreadyConfirmed`.
- **Depends on:** T2, T4, and `BookingService` (unchanged).

### T6 — `MoMoPaymentController`
- **File (new):** `backend/app/Http/Controllers/Payment/MoMoPaymentController.php`.
- **Do:**
  - `create(Booking $booking)` — `$this->authorize('pay', $booking)` (reuse the existing policy),
    `assertBookingPayable`-style guard, call `MoMoService::createPayment`, return `{payUrl, qrCodeUrl,
    deeplink, orderId}`. Mirror `BookingPaymentController::createPaymentIntent`.
  - `ipn(Request $request)` — **fail-closed**: missing/blank secret → 500; verify signature → 400 on
    mismatch (mirror `StripeWebhookController::handleWebhook`); INSERT-first into `momo_webhook_events`
    (duplicate → 204 ack); delegate to `MoMoIpnHandler`; `markProcessed`/`markFailed`; ack **204** on
    success. No auth middleware on IPN (signature is the auth).
  - (Optional) `return(Request $request)` — redirect-back UX handler; can be deferred.
- **Accept:** Forged signature → 400, booking untouched; valid IPN → 204, booking CONFIRMED.
- **Depends on:** T3, T5.

### T7 — Routes
- **File:** `backend/routes/api/v1.php` (edit).
- **Do:** Inside the authed/verified group: `POST /bookings/{booking}/momo/create`
  (`throttle:10,1`). Outside auth (public, signature-verified): `POST /payments/momo/ipn`. Name them
  `v1.bookings.momo.create` and `v1.payments.momo.ipn`.
- **Accept:** `php artisan route:list` shows both; IPN route has **no** `check_token_valid`.
- **Depends on:** T6.

### T8 — Tests
- **Files (new):** `backend/tests/Unit/MoMoServiceTest.php`,
  `backend/tests/Feature/Payment/MoMoIpnTest.php`.
- **Unit:** signature raw-string ordering + HMAC vector; `orderId`/`bookingIdFromOrderId` round-trip;
  fake-mode returns deterministic result.
- **Feature (mirror `StripeWebhookIdempotencyTest`):** (a) valid IPN confirms a PENDING prepaid booking;
  (b) **idempotent replay** — duplicate `(order_id, trans_id)` acks 204, booking confirmed exactly once;
  (c) **bad signature** → 400, booking stays PENDING, no ledger row side-effect; (d) **amount mismatch**
  → not confirmed; (e) IPN for unknown order → handled, no crash.
- **Accept:** New tests green; full suite green (Stripe untouched).
- **Depends on:** T3–T7.

### T9 — Gate + commit
- **`[ACTION]` Verify:** `docker compose up -d db` → `cd backend && php artisan test`.
- **`[ACTION]` Pre-commit:** soleil `detect_changes` (cross-check vs raw `git diff` — it over-reports;
  pass `repo:"soleil-hostel"`). Confirm scope = only the 11 planned files.
- **`[ACTION]` Commit:** `feat(backend): add MoMo sandbox payment adapter` (scope `backend` is allowlisted;
  no `Co-Authored-By` trailer). **Do not push** until the user confirms; **do not merge to dev** until
  gates green and user OK.

---

## 4. Risk & containment

| Risk | Likelihood | Impact | Containment |
|------|-----------|--------|-------------|
| Touches booking state machine | Low | High | Reuse `markPaidAndConfirm` verbatim; zero edits to `BookingStatus`/overlap |
| Forged/replayed IPN confirms a booking | Med | High | Fail-closed HMAC verify + INSERT-first `(order_id,trans_id)` UNIQUE + amount/currency guard |
| MoMo sandbox signature format drift | Med | Low (demo) | T8 unit test pins exact raw-string ordering; validate vs live sandbox |
| IPN can't reach localhost in live demo | High | Low | Prep P5: ngrok tunnel; tests simulate IPN so suite is unaffected |
| Stripe regression | Very low | High | Fully parallel path; no shared mutable code |

---

## 5. Deliverables (11 files)

**New (9):** `MoMoService.php` · `Payment/MoMoPaymentStartResult.php` · `Payment/MoMoIpnOutcome.php` ·
`Payment/MoMoIpnHandler.php` · `Models/MoMoWebhookEvent.php` ·
`migrations/{ts}_create_momo_webhook_events_table.php` · `Controllers/Payment/MoMoPaymentController.php` ·
`tests/Unit/MoMoServiceTest.php` · `tests/Feature/Payment/MoMoIpnTest.php`

**Modified (2 + 1):** `config/services.php` · `routes/api/v1.php` · `.env.example`

---

## 6. Next-session kickoff prompt (paste this to start execution)

> Execute `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md`, tasks **T1→T9**, on branch
> `feature/momo-sandbox-payment`. Additive only — reuse `markPaidAndConfirm`, do not modify any
> existing symbol (if you must, run soleil impact first). After T8, run `docker compose up -d db` then
> `cd backend && php artisan test` as the gate. Show me the diff and test results; **do not commit or
> push** until I confirm.

**Scope note for the executor:** stop and confirm with the user before exceeding the 11-file list,
adding a Composer dependency, or altering any existing payment/booking symbol.
