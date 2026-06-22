# MoMo T6 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T6 only** (one new file: `MoMoPaymentController.php`). The `ipn`
> action is the unauthenticated, signature-as-auth entry point — its fail-closed ladder is the security
> contract. Read `<fail_closed_ladder>` as binding.

````text
<role>
You are a senior Laravel 12 / PHP 8.3 backend engineer with a payments-security focus, executing inside
the Soleil Hostel monorepo. You mirror the established Stripe controllers exactly, you fail closed on every
unverifiable input, and you treat CLAUDE.md + its decision order as binding. Minimum correct diff, proven
behaviorally (forged → 400 untouched; valid → 204 confirmed).
</role>

<context>
You are executing task **T6** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the HTTP surface for the
additive MoMo sandbox payment path. Dependencies (all done): T3 `MoMoService` (createPayment / verifyIpnSignature),
T4 `MoMoWebhookEvent` (INSERT-first ledger), T5 `MoMoIpnHandler` (idempotent business effect), T2
`MoMoIpnOutcome`. T6 is consumed by T7 (routes).

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → the execution plan → this prompt.
Unresolvable conflict → stop and surface as `UNRESOLVED`.

`create` is an authenticated guest action; `ipn` is a PUBLIC server→server callback whose ONLY authentication
is the MoMo HMAC signature. There is no session, no Sanctum token, no CSRF on this path — so the controller
itself must verify the signature and fail closed on anything it cannot prove.
</context>

<task>
Create ONE new file `backend/app/Http/Controllers/Payment/MoMoPaymentController.php` —
`final class MoMoPaymentController extends Controller`, namespace `App\Http\Controllers\Payment`,
`declare(strict_types=1)`. Implement:
1. `create(Booking $booking): JsonResponse` — mirror `BookingPaymentController::createPaymentIntent`.
2. `ipn(Request $request): Response` — mirror `StripeWebhookController::handleWebhook` +
   `handlePaymentIntentSucceeded` (fail-closed verify + INSERT-first dedup + handler delegation + ack).
3. Private helpers `assertBookingPayable(Booking): void` and `paymentRejectedResponse(string): JsonResponse`
   ported from `BookingPaymentController`.

DEFER the optional `return()` redirect-UX handler (out of scope for T6). Do NOT create routes (T7), tests (T8),
or modify any existing file.
</task>

<authoritative_references>
Inspect these first; mirror conventions and control flow from the live files.

1. `backend/app/Http/Controllers/Payment/BookingPaymentController.php` — for `create`: `$this->authorize('pay', $booking)`
   (reuse the existing 'pay' policy), the `try { assertBookingPayable } catch (RuntimeException) { paymentRejectedResponse }`
   (422) shape, and the `{success, data}` envelope. Port `assertBookingPayable` (≈ line 138: trashed/non-PENDING →
   not payable; `! requiresStripePaymentIntent()` → no online payment; `amount <= 0` → throw) and
   `paymentRejectedResponse` (≈ line 244, HTTP 422) verbatim.
2. `backend/app/Http/Controllers/Payment/StripeWebhookController.php` — for `ipn`: the fail-closed secret check
   (≈ line 67: missing secret → 500 + `Log::error`), the 400 branches for bad input, and the INSERT-first
   idempotency block (≈ line 129): `try { $event = DB::transaction(fn () => …::create([... 'status' => 'processing' ...])); }
   catch (UniqueConstraintViolationException) { return ack; }`, then delegate to the handler in a try/catch
   (Throwable → `markFailed` + 500), then `markProcessed` + ack.
3. T3/T4/T5 outputs: `MoMoService::verifyIpnSignature(array): bool` + `createPayment(Booking): MoMoPaymentStartResult`;
   `MoMoWebhookEvent` (fillable `order_id,request_id,trans_id,type,status,result_code,payload,…`);
   `MoMoIpnHandler::applyToBooking(array): MoMoIpnOutcome` (cases Confirmed/AlreadyConfirmed/BookingNotFound/
   InvalidState/AmountMismatch). `docs/.../MOMO_SANDBOX_EXECUTION_PLAN.md` §2 (MoMo IPN fields, 204 ack) + §3 T6.
</authoritative_references>

<fail_closed_ladder>
The `ipn` method MUST implement exactly this order; each rung returns before the next runs.

F1 — Secret not configured ⇒ **500**. `$secret = config('services.momo.secret_key'); if (! is_string($secret) || $secret === '')`
→ `Log::error('MoMo IPN rejected: MOMO_SECRET_KEY is not configured')`, return 500 JSON. This is server
misconfiguration, not attacker input — distinct from a 400. (Mirror StripeWebhookController's secret check.)

F2 — Malformed body ⇒ **400**. `$payload = json_decode($request->getContent(), true); if (! is_array($payload))`
→ 400. Never feed a non-array to the verifier.

F3 — Bad/missing signature ⇒ **400**. `if (! $this->momoService->verifyIpnSignature($payload))` → `Log::warning`
(no secret/signature in the log), return 400. `verifyIpnSignature` is constant-time + fail-closed (T3). Nothing
downstream runs on an unverified payload.

F4 — Duplicate delivery ⇒ **204 ack**. INSERT-first claim is the linearization point:
    try { $event = DB::transaction(fn () => MoMoWebhookEvent::create([
        'order_id'    => (string) data_get($payload, 'orderId', ''),
        'request_id'  => (string) data_get($payload, 'requestId', ''),
        'trans_id'    => (string) data_get($payload, 'transId', ''),   // coerce to '' (never null) — see note
        'type'        => 'momo.ipn',
        'status'      => 'processing',
        'result_code' => (int) data_get($payload, 'resultCode'),
        'payload'     => $payload,
    ])); } catch (UniqueConstraintViolationException) { return response()->noContent(); }   // 204

F5 — Handler error ⇒ **500** (so MoMo retries). `try { $outcome = $this->ipnHandler->applyToBooking($payload); }
catch (\Throwable $e) { $event->markFailed($e); return response()->json([...], 500); }`. (markFailed sanitizes.)

F6 — Outcome → record + ack. Every non-throwing outcome acks **204** so MoMo stops retrying:
    match ($outcome) {
        MoMoIpnOutcome::Confirmed,
        MoMoIpnOutcome::AlreadyConfirmed,
        MoMoIpnOutcome::BookingNotFound,
        MoMoIpnOutcome::InvalidState   => $event->markProcessed(),
        MoMoIpnOutcome::AmountMismatch => $event->markFailed('MoMo IPN amount mismatch for order '.(string) data_get($payload,'orderId','')),
    };
    return response()->noContent();   // 204
`AmountMismatch` is signature-valid but permanent (a retry won't fix it) and the booking was NOT confirmed by
T5 — so mark it failed for operator review yet still 204 to stop the retry storm. `[INFERRED]` confirm this
ack choice; the security invariant (no confirmation on mismatch) is already guaranteed by T5.

F7 — No `authorize()` / auth middleware on `ipn` (the signature IS the auth; the public route is T7). Never log
or echo the secret or the signature. Use `config()`, never `env()`.

`trans_id` note: coerce with `(string) (… ?? '')` so it is never NULL (the T4 UNIQUE(order_id,trans_id) needs
non-null columns to dedup; '' is a valid non-null value and still collides on replay).
</fail_closed_ladder>

<implementation_spec>
Constructor: `private readonly MoMoService $momoService, private readonly MoMoIpnHandler $ipnHandler` (Laravel
resolves the handler's own deps). Extend `App\Http\Controllers\Controller` (gives `authorize`).

**create(Booking $booking): JsonResponse**
    $this->authorize('pay', $booking);
    try { $this->assertBookingPayable($booking); }
    catch (RuntimeException $e) { return $this->paymentRejectedResponse($e->getMessage()); }
    $started = $this->momoService->createPayment($booking);
    return response()->json(['success' => true, 'data' => [
        'payUrl'    => $started->payUrl,
        'qrCodeUrl' => $started->qrCodeUrl,
        'deeplink'  => $started->deeplink,
        'orderId'   => $started->orderId,
    ]]);
(No payment_intent_id reuse branch — MoMo stores nothing on `bookings`; each create mints a fresh order, and
confirmation idempotency lives in the T4 ledger + T5 handler.)

**ipn(Request $request): Response** — implement the F1→F6 ladder above, in that exact order.

**assertBookingPayable / paymentRejectedResponse** — port verbatim from `BookingPaymentController`.

Imports: `App\Enums\BookingStatus`, `App\Http\Controllers\Controller`, `App\Models\Booking`,
`App\Models\MoMoWebhookEvent`, `App\Services\MoMoService`, `App\Services\Payment\MoMoIpnHandler`,
`App\Services\Payment\MoMoIpnOutcome`, `Illuminate\Database\UniqueConstraintViolationException`,
`Illuminate\Http\JsonResponse`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\DB`,
`Illuminate\Support\Facades\Log`, `Symfony\Component\HttpFoundation\Response`, `RuntimeException`.
Match Pint formatting and the file header of the two reference controllers.
</implementation_spec>

<acceptance_criteria>
1. `php -l` clean; autoloads; Pint no diff; static analysis (Larastan/PHPStan, Psalm) reports no new errors.
2. Forged/missing signature ⇒ HTTP 400 and the booking is untouched (no ledger row, no state change).
3. Valid IPN for a PENDING PREPAID booking ⇒ HTTP 204 and the booking is CONFIRMED; a replayed identical IPN ⇒
   204 with no second confirm (UNIQUE dedup).
4. Blank `services.momo.secret_key` ⇒ HTTP 500 (not 400). Amount-mismatch IPN ⇒ 204, ledger row `failed`,
   booking NOT confirmed.
5. Exactly one new file in the diff; no existing file touched.
</acceptance_criteria>

<verification>
Run from `backend/` (test DB required):

    php -l app/Http/Controllers/Payment/MoMoPaymentController.php
    composer lint && vendor/bin/phpstan analyse app/Http/Controllers/Payment/MoMoPaymentController.php

    # Throwaway behavioral check of the security ladder (NOT a committed test — durable MoMoIpnTest is T8, over
    # the T7 route). Calls the controller method directly so no route is needed; uses the project Booking factory.
    php artisan tinker --execute="
      config(['services.momo.secret_key'=>'K951B6PE1waDMi640xX08PD3vg6EkVlz']);
      \$svc=app(App\Services\MoMoService::class); \$ctrl=app(App\Http\Controllers\Payment\MoMoPaymentController::class);
      \$b=App\Models\Booking::factory()->create(['status'=>App\Enums\BookingStatus::PENDING,'payment_policy'=>App\Enums\PaymentPolicy::PREPAID,'amount'=>150000,'payment_currency'=>'vnd']);
      \$p=['partnerCode'=>'MOMO','orderId'=>'soleil-'.\$b->id.'-x','requestId'=>'r1','amount'=>'150000','orderInfo'=>'t','orderType'=>'momo_wallet','transId'=>'123','resultCode'=>0,'message'=>'ok','payType'=>'qr','responseTime'=>'1','extraData'=>''];
      \$mk=fn(\$pl)=>Illuminate\Http\Request::create('/x','POST',[],[],[],['CONTENT_TYPE'=>'application/json'],json_encode(\$pl));
      \$forged=\$p; \$forged['signature']='deadbeef';
      echo 'forged->'.\$ctrl->ipn(\$mk(\$forged))->getStatusCode().' status='.\$b->fresh()->status->value.PHP_EOL; // 400 pending
      \$p['signature']=\$svc->signIpn(\$p);
      echo 'valid->'.\$ctrl->ipn(\$mk(\$p))->getStatusCode().' status='.\$b->fresh()->status->value.PHP_EOL;       // 204 confirmed
      echo 'replay->'.\$ctrl->ipn(\$mk(\$p))->getStatusCode().PHP_EOL;                                              // 204 (dedup)
    "

    composer test                       # full suite green — T6 adds no route yet
    git --no-pager diff --stat          # exactly one new file

The durable forged/valid/replay/amount-mismatch assertions over the real HTTP route are the T8 feature test.
</verification>

<output_format>
Follow CLAUDE.md output-style policy: change under `.claude/output-styles/execution-plan.md`, results under
`.claude/output-styles/execution.md`. Tag findings `[CONFIRMED]`, `[INFERRED]` (esp. the F6 AmountMismatch ack),
`[UNPROVEN]` (the §2 "MoMo expects 204" assumption), `[ACTION]`. End with the `git diff` plus the
forged→400 / valid→204 / replay→204 tinker output as evidence.
</output_format>

<stop_conditions>
Stop and confirm with me before: creating/editing any file beyond `MoMoPaymentController.php`; adding the `ipn`
route or any middleware (that is T7 — and the `ipn` route must NOT get auth/CSRF/`check_token_valid`); modifying
`MoMoService`/`MoMoIpnHandler`/`MoMoWebhookEvent`/`BookingPaymentController`/the 'pay' policy; weakening any
fail-closed rung (e.g. acking before signature verify, returning 200/204 on bad signature, logging the secret);
or committing. Do NOT commit, push, or merge — continue branch `feature/momo-sandbox-payment`, leave the change
uncommitted, and show me the diff + verification output.
</stop_conditions>
````
