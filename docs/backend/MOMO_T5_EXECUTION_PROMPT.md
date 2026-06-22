# MoMo T5 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T5 only** (one new file: `MoMoIpnHandler.php`). This is the
> single audited entry point from a verified IPN into the booking state machine — the amount guard here
> is the last line of defense, post-signature.

````text
<role>
You are a senior Laravel 12 / PHP 8.3 backend engineer with a payments-correctness focus, executing
inside the Soleil Hostel monorepo. You mirror the established Stripe handler precisely, you reuse the
single audited booking-confirm entry point rather than reimplementing it, and you treat CLAUDE.md + its
decision order as binding. Minimum correct diff, proven behaviorally.
</role>

<context>
You are executing task **T5** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the idempotent business
effect for a MoMo IPN. T2 (`MoMoIpnOutcome` enum), T4 (`MoMoWebhookEvent` ledger), and `BookingService`
(unchanged) are its dependencies. T3 (`MoMoService`) supplies the order↔booking mapping. T5 is consumed by
T6 (the controller calls it AFTER signature-verify + INSERT-first dedup).

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → the execution plan → this prompt.
Unresolvable conflict → stop and surface as `UNRESOLVED`.

`markPaidAndConfirm` is the ONLY audited way into the booking state machine; the Stripe path and this MoMo
path must both go through it so booking state cannot be reached two ways and quietly diverge. T5 performs no
signature work (that is T6) — it assumes a verified, deduped payload but still independently enforces the
amount/currency guard as defense in depth.
</context>

<task>
Create ONE new file `backend/app/Services/Payment/MoMoIpnHandler.php` — `final class MoMoIpnHandler`,
namespace `App\Services\Payment`, `declare(strict_types=1)`, mirroring `StripePaymentIntentSucceededHandler`.
Implement `applyToBooking(array $payload): MoMoIpnOutcome`.

Create only this file. Do NOT create the controller (T6), routes (T7), tests (T8), or modify `BookingService`,
`MoMoService`, `Booking`, or the status enums.
</task>

<authoritative_references>
Inspect these first; mirror conventions and control flow from the live files.

1. `backend/app/Services/Payment/StripePaymentIntentSucceededHandler.php` — the handler to mirror:
   constructor DI of services (readonly), `applyToBooking` returns an outcome enum (does NOT throw for control
   flow), the exact decision ladder (resolve → not-success → policy → match-guard → already-CONFIRMED idempotent
   forceFill → non-PENDING → markPaidAndConfirm), and the comment discipline.
2. `backend/app/Services/BookingService.php` `markPaidAndConfirm(Booking $booking, int $amountReceived, int $amountCapturable = 0): Booking`
   (≈ line 158) — it OPENS ITS OWN `DB::transaction` + `lockForUpdate()->firstOrFail()` and re-checks
   `status === CONFIRMED` inside the lock (returns fresh) else `confirmBooking`. That internal locked re-check is
   the real linearization point — so DO NOT wrap your handler in an outer transaction; just call it.
3. `backend/app/Services/Payment/MoMoIpnOutcome.php` (T2) — same namespace as the handler, so reference the
   cases unqualified (no `use`), exactly as the Stripe handler references `PaymentIntentApplyOutcome`.
4. `backend/app/Services/MoMoService.php` (T3) — use `bookingIdFromOrderId(string): ?int` for the order↔booking
   mapping. For the expected amount/currency: reuse `MoMoService::expectedAmount`/`expectedCurrency` IF they are
   public (StripeService exposes its equivalents publicly); if T3 left them private, compute inline from
   `$booking->amount` / `$booking->payment_currency` with identical semantics rather than widening them here.
5. `backend/app/Enums/BookingStatus.php` (`PENDING`, `CONFIRMED`) and `App\Enums\PaymentStatus` (`PAID`) — as
   imported by the Stripe handler. `docs/.../MOMO_SANDBOX_EXECUTION_PLAN.md` §3 T5 + §4 (forged/replayed-IPN row).
</authoritative_references>

<security_and_correctness>
C1 — **Amount guard RETURNS, never throws (divergence from Stripe).** Stripe's `assertPaymentIntentMatchesBooking`
THROWS on mismatch. T5 must instead RETURN `MoMoIpnOutcome::AmountMismatch` and NOT confirm. This lets T6 record
the event and ack without a 500. A forged/replayed IPN carrying a different amount must never confirm a booking —
this guard runs even though the signature already verified (defense in depth).

C2 — **`resultCode !== 0` ⇒ `InvalidState`, no confirm.** Mirror of Stripe's `status !== 'succeeded' ⇒ InvalidState`.
A failure/cancel IPN is acknowledged by T6 but never confirms.

C3 — **Idempotency comes from `markPaidAndConfirm`'s locked re-check**, plus T6's INSERT-first dedup. Do not add a
second transaction/lock. Second call on an already-CONFIRMED booking ⇒ `AlreadyConfirmed` (idempotent no-op,
matching the Stripe handler's CONFIRMED branch).

C4 — **Reuse the single entry point.** Confirmation MUST go through `BookingService::markPaidAndConfirm`; never
call `confirmBooking` directly or hand-roll the PENDING→CONFIRMED transition. `BookingService` stays unchanged.

C5 — **No throwing for control flow.** Return outcomes; only genuine downstream errors (markPaidAndConfirm
throwing) propagate. No signature/secret handling in this file (that is T6). No `env()` (config only if needed).
</security_and_correctness>

<implementation_spec>
Constructor: `private readonly BookingService $bookingService, private readonly MoMoService $momoService`
(mirror the Stripe handler's two-service constructor; swap StripeService → MoMoService).

`applyToBooking(array $payload): MoMoIpnOutcome` — decision ladder (MoMo field names are camelCase: `orderId`,
`resultCode`, `amount`):

    $bookingId = $this->momoService->bookingIdFromOrderId((string) data_get($payload, 'orderId', ''));
    if ($bookingId === null) return MoMoIpnOutcome::BookingNotFound;

    $booking = Booking::query()->whereKey($bookingId)->first();
    if ($booking === null) return MoMoIpnOutcome::BookingNotFound;

    if ((int) data_get($payload, 'resultCode') !== 0) return MoMoIpnOutcome::InvalidState;   // C2

    if (! $booking->payment_policy->requiresStripePaymentIntent()) return MoMoIpnOutcome::InvalidState;

    // C1 — defense-in-depth amount/currency guard; RETURNS AmountMismatch, never throws, never confirms.
    $expectedAmount = (int) $booking->amount;                          // == MoMoService::expectedAmount semantics
    $expectedCurrency = ($booking->payment_currency !== null && (string) $booking->payment_currency !== '')
        ? strtolower((string) $booking->payment_currency)
        : strtolower((string) config('cashier.currency', 'vnd'));
    $notifiedAmount = (int) data_get($payload, 'amount');
    // MoMo IPN carries no currency field (VND-only channel), so the currency half asserts the booking expects
    // the VND that MoMo settles. [INFERRED] confirm this against your booking/currency model.
    if ($notifiedAmount !== $expectedAmount || $expectedCurrency !== 'vnd') {
        return MoMoIpnOutcome::AmountMismatch;
    }

    if ($booking->status === BookingStatus::CONFIRMED) {              // idempotent top-up, mirror Stripe
        $booking->forceFill([
            'payment_status' => PaymentStatus::PAID,
            'amount_received' => $expectedAmount,
            'amount_capturable' => 0,
            'paid_at' => $booking->paid_at ?? now(),
            'payment_failed_reason' => null,
        ])->save();
        return MoMoIpnOutcome::AlreadyConfirmed;
    }

    if ($booking->status !== BookingStatus::PENDING) return MoMoIpnOutcome::InvalidState;   // refunded/cancelled

    $this->bookingService->markPaidAndConfirm($booking, $expectedAmount, 0);                // 0 capturable: MoMo captureWallet is full capture
    return MoMoIpnOutcome::Confirmed;

Imports: `App\Enums\BookingStatus`, `App\Enums\PaymentStatus`, `App\Models\Booking`, `App\Services\BookingService`,
`App\Services\MoMoService`. `MoMoIpnOutcome` is same-namespace → no `use`. Match Pint formatting and the file
header of the Stripe handler.
</implementation_spec>

<acceptance_criteria>
1. `php -l` clean; autoloads; Pint shows no diff; static analysis (Larastan/PHPStan, Psalm) reports no new errors.
2. Behavior: a PENDING PREPAID booking + a matching IPN (`resultCode=0`, `amount=expected`) ⇒ `Confirmed` and the
   booking is CONFIRMED + PAID. Second identical call ⇒ `AlreadyConfirmed`, no double-confirm.
3. Guards: `amount != expected` ⇒ `AmountMismatch` and booking stays PENDING (no confirm); `resultCode != 0` ⇒
   `InvalidState`; unknown/garbage `orderId` ⇒ `BookingNotFound`; cancelled/refund_pending booking ⇒ `InvalidState`.
4. Exactly one new file in the diff; `BookingService`/`MoMoService`/`Booking`/enums untouched.
</acceptance_criteria>

<verification>
Run from `backend/` (test DB required):

    php -l app/Services/Payment/MoMoIpnHandler.php
    composer lint && vendor/bin/phpstan analyse app/Services/Payment/MoMoIpnHandler.php

    # Throwaway behavioral check (NOT a committed test — the durable MoMoIpnTest is T8). Use the project's
    # Booking factory exactly as tests/Feature/Payment/StripeWebhookIdempotencyTest.php does; adapt required fields.
    php artisan tinker --execute="
      \$b = App\Models\Booking::factory()->create(['status'=>App\Enums\BookingStatus::PENDING,'payment_policy'=>App\Enums\PaymentPolicy::PREPAID,'amount'=>150000,'payment_currency'=>'vnd']);
      \$h = app(App\Services\Payment\MoMoIpnHandler::class);
      \$ok = ['orderId'=>'soleil-'.\$b->id.'-x','resultCode'=>0,'amount'=>150000];
      var_dump(\$h->applyToBooking(\$ok)->value, \$h->applyToBooking(\$ok)->value, \$h->applyToBooking(['orderId'=>'soleil-'.\$b->id.'-y','resultCode'=>0,'amount'=>999999])->value, \$h->applyToBooking(['orderId'=>'garbage','resultCode'=>0,'amount'=>1])->value);
      // expect: 'confirmed' 'already_confirmed' 'amount_mismatch' 'booking_not_found'
    "

    composer test                         # full suite green — T5 wires nothing into routes yet
    git --no-pager diff --stat            # exactly one new file

If the Booking factory needs more required fields (room/location), set them as the Stripe idempotency test does.
The durable assertions (confirm-once, replay no-op, bad-amount no-confirm) become the T8 feature test.
</verification>

<output_format>
Follow CLAUDE.md output-style policy: change under `.claude/output-styles/execution-plan.md`, results under
`.claude/output-styles/execution.md`. Tag findings `[CONFIRMED]`, `[INFERRED]` (esp. the currency half of C1),
`[UNPROVEN]`, `[ACTION]`. End with the `git diff` plus the behavioral tinker output as evidence.
</output_format>

<stop_conditions>
Stop and confirm with me before: creating/editing any file beyond `MoMoIpnHandler.php`; modifying `BookingService`,
`MoMoService`, `Booking`, `BookingStatus`, or `PaymentStatus` (if you believe you must edit any existing symbol,
run `soleil-ai-review-engine_impact({target, direction:"upstream"})` first per CLAUDE.md and surface the blast
radius); calling `confirmBooking` directly instead of `markPaidAndConfirm`; making the amount guard throw instead
of returning `AmountMismatch`; or committing. Do NOT commit, push, or merge — continue branch
`feature/momo-sandbox-payment`, leave the change uncommitted, and show me the diff + verification output.
</stop_conditions>
````
