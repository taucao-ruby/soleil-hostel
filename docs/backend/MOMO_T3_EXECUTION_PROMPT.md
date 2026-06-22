# MoMo T3 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T3 only** (one new file: `MoMoService.php`). This is the
> security-critical task — HMAC signing, constant-time verification, fail-closed posture. Read the
> `<security_requirements>` block as binding, not advisory.

````text
<role>
You are a senior Laravel 12 / PHP 8.3 backend engineer with a payments-security specialization,
executing inside the Soleil Hostel monorepo. You mirror existing house patterns exactly, you treat
signature verification as a fail-closed security boundary, and you treat CLAUDE.md and its decision
order as binding. You write the minimum correct diff and prove it with deterministic checks.
</role>

<context>
You are executing task **T3** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the MoMo gateway
adapter for the additive sandbox payment path that runs fully parallel to Stripe. T1 (the
`services.momo` config block) and T2 (the `MoMoPaymentStartResult` DTO + `MoMoIpnOutcome` enum) are done.
T3 depends on T1 + T2 and is consumed later by T5 (IPN handler) and T6 (controller).

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → the execution plan → this prompt.
Unresolvable conflict → stop and surface as `UNRESOLVED`.

`MoMoService` is a pure adapter: it builds/sign/sends a create-payment request and verifies inbound IPN
signatures. It performs NO booking state mutation (that is T5 via `BookingService::markPaidAndConfirm`)
and opens NO DB transaction. Network I/O only; never call it inside a booking/room lock.
</context>

<task>
Create ONE new file `backend/app/Services/MoMoService.php` (namespace `App\Services`, `class MoMoService`,
mirroring `StripeService`'s shape — non-final, constructor DI, `declare(strict_types=1)`). Implement:

1. `createPayment(Booking $booking): MoMoPaymentStartResult`
2. `signCreatePayment(array $fields): string`
3. `signIpn(array $fields): string`
4. `verifyIpnSignature(array $payload): bool`
5. `orderId(Booking $booking): string`  →  `soleil-{bookingId}-{nonce}`
6. `bookingIdFromOrderId(string $orderId): ?int`  (strict parse/validate)
7. `private shouldUseTestingFake(): bool`  (mirror `StripeService` exactly)
8. private helpers `expectedAmount(Booking): int` / `expectedCurrency(Booking): string` (replicate
   `StripeService` semantics — do NOT import StripeService; keep the MoMo path independent).

Do NOT create the unit test (`MoMoServiceTest.php` is T8), the controller (T6), routes (T7), or the
webhook model (T4). T3 is exactly one new file.
</task>

<authoritative_references>
Inspect these first; copy conventions from the live files, don't trust this prompt's snippets over them.

1. `backend/app/Services/StripeService.php` — the pattern to mirror:
   - `shouldUseTestingFake()` (≈ line 325): `app()->environment('testing') && blank(config('cashier.secret'))`
     → for MoMo: `app()->environment('testing') && blank(config('services.momo.secret_key'))`. Keep it `private`.
   - `expectedAmount()` (≈ 368): `(int) $booking->amount`. `expectedCurrency()` (≈ 373):
     `strtolower((string) $booking->payment_currency)` with fallback `strtolower((string) config('cashier.currency','vnd'))`.
   - `createPaymentIntent()` (≈ 37–99): the guard order (`amount <= 0` → throw; `! $policy->requiresStripePaymentIntent()`
     → throw), the `shouldUseTestingFake()` short-circuit that returns a deterministic result derived from a
     STABLE key (`substr(hash('sha256', $idempotencyKey), 0, 12)`), and the post-response amount/currency guard.
2. `backend/app/Enums/PaymentPolicy.php` — `requiresStripePaymentIntent()` returns true for `PREPAID` and
   `AUTHORIZE_THEN_CAPTURE`. Reuse this exact predicate (the spec says reject non-`requiresStripePaymentIntent`
   policies). `[CONFIRMED]` `Booking` exposes `amount`, `payment_currency`, `payment_policy` (StripeService uses them).
3. `backend/app/Services/Payment/MoMoPaymentStartResult.php` + `MoMoIpnOutcome.php` (T2) — the return DTO and
   the outcome enum. `createPayment` returns the DTO; the enum is consumed by T5, not here.
4. `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` §2 (the exact create/IPN signing raw strings + sandbox creds)
   and §3 T3 (the spec). NOTE: §2 is tagged `[UNPROVEN]` "validate against current docs" — see security req S7.
</authoritative_references>

<security_requirements>
These are non-negotiable. A miss here is a payment-integrity defect, not a style nit.

S1 — Canonical signing, no dynamic sort. Build each raw signing string from an EXPLICIT, hardcoded field
order (MoMo's fixed canonical order from §2), reading values out of the input array by key. NEVER `ksort()`
or otherwise derive order from attacker-controlled array key order, and NEVER include any field outside
MoMo's canonical list. Each value is coerced to string; a missing field signs as empty (`(string) ($f['k'] ?? '')`).

S2 — HMAC. Signature = `hash_hmac('sha256', $raw, (string) config('services.momo.secret_key'))` (lowercase hex).

S3 — Constant-time verify. `verifyIpnSignature` MUST compare with `hash_equals($expected, $provided)` — never
`===`/`==` (timing-attack resistance). Guard that `$provided` is a non-empty string before comparing.

S4 — Fail closed. If `config('services.momo.secret_key')` is blank/non-string, `verifyIpnSignature` returns
`false` (cannot verify ⇒ reject). It never throws a secret into a message and never "passes" an unverifiable IPN.

S5 — No secret leakage. Never log, exception-message, or otherwise emit the secret key or the raw signing
string. On a MoMo API failure (`resultCode != 0` or non-2xx) throw a `RuntimeException` whose message contains
only non-sensitive fields (orderId, resultCode, message) — never the signature or secret.

S6 — Strict order-id parsing. `bookingIdFromOrderId` validates with a strict anchored pattern
(`/^soleil-(\d{1,18})-[A-Za-z0-9]+$/`), returns the captured id only when `> 0`, else `null`. No loose
`explode`. This mapping is trusted downstream, so reject anything malformed rather than coercing.

S7 — Verify the wire format. Before pinning a signature vector, confirm the §2 canonical field order against
MoMo's CURRENT AIO v2 docs if reachable (WebSearch/official docs). If you cannot confirm, implement the §2
ordering verbatim and tag it `[UNPROVEN]` with a one-line note that T8/live-sandbox must validate it.

S8 — Transport. Use the endpoint from `config('services.momo.endpoint')` (HTTPS). NEVER disable TLS
verification (no `->withoutVerifying()`). Apply the bounded timeouts from config (S in §implementation_spec).

S9 — No `env()` in runtime. Read everything via `config('services.momo.*')`. `env()` is forbidden outside config.
</security_requirements>

<implementation_spec>
Constructor: inject `Illuminate\Http\Client\Factory $http` as a readonly promoted property (so feature tests
drive it via `Http::fake()`); mirror `StripeService`'s constructor-injection style.

**createPayment(Booking $booking)**
- `$amount = $this->expectedAmount($booking); $currency = $this->expectedCurrency($booking); $policy = $booking->payment_policy;`
- Guard: `$amount <= 0` → `throw new RuntimeException('Booking amount must be greater than zero.')`.
- Guard: `! $policy->requiresStripePaymentIntent()` → throw (parallel to StripeService; PREPAID is the demo
  policy). `[INFERRED]` if you judge MoMo captureWallet should accept PREPAID only, flag it — but default to
  the StripeService predicate to honor the literal spec.
- Stable key for determinism: `$stableKey = sprintf('booking:%d:momo:create:v1', (int) $booking->getKey());`
  (require `$booking->exists`, mirror `paymentIntentIdempotencyKey`).
- `if ($this->shouldUseTestingFake())` → return a DETERMINISTIC `MoMoPaymentStartResult` with NO network/random/
  time: `$orderId = 'soleil-'.$booking->id.'-'.substr(hash('sha256', $stableKey), 0, 12);` requestId derived from
  the same key; `payUrl`/`qrCodeUrl`/`deeplink` deterministic strings built from `$orderId`; `amount`/`currency`
  as computed. (Mirrors StripeService's `pi_test_...` fake.)
- Real path: `$orderId = $this->orderId($booking); $requestId = (string) Str::uuid();`
  Build the canonical signing field map (the 10 keys from §2): accessKey (`config('services.momo.access_key')`),
  amount (string), extraData (''), ipnUrl (`config('services.momo.ipn_url')`), orderId, orderInfo (a Vietnamese
  label, e.g. "Thanh toán đặt phòng Soleil #{id}"), partnerCode (`config('services.momo.partner_code')`),
  redirectUrl (`config('services.momo.redirect_url')`), requestId, requestType (`config('services.momo.request_type')`).
  `$signature = $this->signCreatePayment($fields);`
- POST the full payload (canonical fields + `partnerName`, `storeId` from config, `lang='vi'`, `autoCapture=true`,
  `signature`) to `config('services.momo.endpoint')` via
  `$this->http->connectTimeout((int) config('services.momo.connect_timeout'))->timeout((int) config('services.momo.read_timeout'))->asJson()->post($endpoint, $payload)`.
- Parse: require `resultCode === 0` and a non-empty string `payUrl` else throw (S5). Return
  `new MoMoPaymentStartResult(orderId: $orderId, requestId: $requestId, payUrl: $payUrl,
  deeplink: $deeplink ?: null, qrCodeUrl: $qrCodeUrl ?: null, amount: $amount, currency: $currency)`.

**signCreatePayment(array $fields): string** — raw string EXACTLY (§2), then S2:
    accessKey={accessKey}&amount={amount}&extraData={extraData}&ipnUrl={ipnUrl}&orderId={orderId}&orderInfo={orderInfo}&partnerCode={partnerCode}&redirectUrl={redirectUrl}&requestId={requestId}&requestType={requestType}

**signIpn(array $fields): string** — raw string EXACTLY (§2), then S2:
    accessKey={accessKey}&amount={amount}&extraData={extraData}&message={message}&orderId={orderId}&orderInfo={orderInfo}&orderType={orderType}&partnerCode={partnerCode}&payType={payType}&requestId={requestId}&responseTime={responseTime}&resultCode={resultCode}&transId={transId}

Build both with an explicit `sprintf`/concatenation over the ordered key list (S1). `accessKey` is read from
the passed `$fields` (so the methods are vector-testable); the HMAC key (`secret_key`) is read from config.

**verifyIpnSignature(array $payload): bool** — S3 + S4:
    $secret = config('services.momo.secret_key');
    if (! is_string($secret) || $secret === '') return false;
    $provided = $payload['signature'] ?? null;
    if (! is_string($provided) || $provided === '') return false;
    return hash_equals($this->signIpn($payload), $provided);

**orderId(Booking): string** — require `$booking->exists`; `return 'soleil-'.((int) $booking->getKey()).'-'.Str::random(16);`
(nonce is for uniqueness, not secrecy — MoMo requires a unique orderId per create).

**bookingIdFromOrderId(string): ?int** — S6 strict regex; round-trips with `orderId()`.

Use `Illuminate\Support\Str`; `use App\Models\Booking;`, `use App\Enums\PaymentPolicy;`,
`use App\Services\Payment\MoMoPaymentStartResult;`, `use RuntimeException;`,
`use Illuminate\Http\Client\Factory;`. Match Pint formatting and the file header of `StripeService.php`.
</implementation_spec>

<acceptance_criteria>
1. `php -l` clean; autoloads; Pint shows no diff; static analysis (Larastan/PHPStan, Psalm) reports no new errors.
2. Signature determinism: for a FIXED field vector + the public sandbox secret
   (`K951B6PE1waDMi640xX08PD3vg6EkVlz`), `signCreatePayment` / `signIpn` produce a stable HMAC hex whose raw
   string matches the §2 ordering byte-for-byte (verify ad-hoc now; the durable pin lands in T8).
3. Round-trip: `bookingIdFromOrderId(orderId($booking)) === (int) $booking->id`; malformed inputs → `null`.
4. Fake mode: in `testing` with blank `services.momo.secret_key`, `createPayment` returns a deterministic
   `MoMoPaymentStartResult` with no network call (no random/time).
5. Exactly one new file in the diff (`MoMoService.php`); `StripeService`/`Booking`/`PaymentPolicy`/T2 types untouched.
</acceptance_criteria>

<verification>
Run from `backend/` (gates: `docs/COMMANDS_AND_GATES.md`, `docs/agents/COMMANDS.md`):

    php -l app/Services/MoMoService.php
    composer lint
    vendor/bin/phpstan analyse app/Services/MoMoService.php

    # Signature ordering self-check (no DB needed) — confirm the canonical raw string + HMAC:
    php -r '$s="K951B6PE1waDMi640xX08PD3vg6EkVlz";$raw="accessKey=F8BBA842ECF85&amount=10000&extraData=&ipnUrl=https://x/ipn&orderId=soleil-1-abc&orderInfo=test&partnerCode=MOMO&redirectUrl=https://x/r&requestId=req-1&requestType=captureWallet";echo hash_hmac("sha256",$raw,$s),PHP_EOL;'
    # Re-derive the same value from MoMoService::signCreatePayment with the matching $fields and assert equality.

    # Round-trip: a throwaway tinker check, NOT a committed test (that is T8). orderId() requires a
    # persisted booking, so set the key + exists flag explicitly on an in-memory model:
    php artisan tinker --execute="\$s=app(App\Services\MoMoService::class); \$b=new App\Models\Booking(); \$b->id=7; \$b->exists=true; \$o=\$s->orderId(\$b); var_dump(\$o, \$s->bookingIdFromOrderId(\$o)===7, \$s->bookingIdFromOrderId('garbage'));"

    composer test     # full suite stays green — T3 wires nothing in yet (no routes/controller)
    git --no-pager diff --stat   # exactly one new file

Do NOT add `MoMoServiceTest.php` here; the durable signature/round-trip/fake tests are T8.
</verification>

<output_format>
Follow CLAUDE.md output-style policy: produce the change under `.claude/output-styles/execution-plan.md`,
report under `.claude/output-styles/execution.md`. Tag every finding `[CONFIRMED]`, `[INFERRED]`,
`[UNPROVEN]` (esp. the §2 wire-format per S7), or `[ACTION]`. End with the `git diff` and the
lint / static-analysis / signature-vector / round-trip output as evidence.
</output_format>

<stop_conditions>
Stop and confirm with me before: creating or editing any file other than `MoMoService.php`; adding a Composer
dependency (HMAC is `hash_hmac`, HTTP is the Laravel client — no SDK); importing or modifying `StripeService`,
`Booking`, `PaymentPolicy`, or the booking state machine; weakening any S-rule (e.g. `===` compare, disabling
TLS, dynamic field sort); or committing. Do NOT commit, push, or merge — continue branch
`feature/momo-sandbox-payment`, leave the change uncommitted, and show me the diff + verification output.
</stop_conditions>
````
