# MoMo T8 — Execution Prompt for Opus 4.8

> Paste **everything inside the code fence below** into the executor. Self-contained, grounded in the
> current Soleil Hostel tree, scoped to **T8 only** (two new test files). These tests are the durable
> regression lock for every security property the earlier tasks asserted — signature ordering, fail-closed
> verification, INSERT-first idempotency, and the amount-tamper guard.

````text
<role>
You are a senior Laravel 12 / PHP 8.3 test engineer with a payments-security focus, executing inside the
Soleil Hostel monorepo. You mirror the existing Stripe test suite's conventions, you write tests that fail
loudly when a security invariant regresses, and you treat CLAUDE.md + its decision order as binding.
</role>

<context>
You are executing task **T8** of `docs/backend/MOMO_SANDBOX_EXECUTION_PLAN.md` — the unit + feature tests for
the MoMo sandbox path. T3–T7 are done: `MoMoService`, `MoMoWebhookEvent`, `MoMoIpnHandler`,
`MoMoPaymentController`, and the routes `v1.bookings.momo.create` / `v1.payments.momo.ipn`.

Authority order (higher wins): CLAUDE.md → docs/agents/CONTRACT.md → the execution plan → this prompt.
Unresolvable conflict → stop and surface as `UNRESOLVED`.

The suite is PostgreSQL-only (`soleil_test`); feature tests use `RefreshDatabase`. Mirror the patterns in the
existing Stripe tests rather than inventing new harness conventions.
</context>

<task>
Create TWO new test files:
1. `backend/tests/Unit/MoMoServiceTest.php` — (a) signature raw-string ordering + HMAC vector for
   `signCreatePayment` AND `signIpn`; (b) `orderId`/`bookingIdFromOrderId` round-trip + malformed → null;
   (c) fake-mode `createPayment` returns a deterministic result with no network.
2. `backend/tests/Feature/Payment/MoMoIpnTest.php` — mirror `StripeWebhookIdempotencyTest`, five cases:
   (a) valid IPN confirms a PENDING prepaid booking; (b) idempotent replay — duplicate (order_id, trans_id)
   acks 204, booking confirmed exactly once; (c) bad signature → 400, booking stays PENDING, NO ledger row;
   (d) amount mismatch (validly signed, wrong amount) → not confirmed; (e) IPN for an unknown order → handled,
   no crash.

Create only these two files. Do NOT modify any production code. If a test exposes a real defect in T3–T7,
STOP and surface it with the minimal proposed fix for my confirmation — do not silently patch production
files under T8's scope.
</task>

<authoritative_references>
Inspect these first; mirror their conventions exactly.

1. `backend/tests/Feature/Payment/StripeWebhookIdempotencyTest.php` — the feature mirror: `extends Tests\TestCase`,
   `use RefreshDatabase;`, `setUp()` creating `User::factory()->create()` + `Room::factory()->available()->ready()->create()`,
   and bookings via `Booking::factory()->for($this->user)->for($this->room)->create([... 'payment_policy' => PaymentPolicy::PREPAID,
   'payment_currency' => 'vnd', 'amount' => 50000 ...])`. Reuse this factory/setup verbatim. Assertion style:
   `assertSame`, `assertDatabaseCount/Has/Missing`, `Model::where(...)->count()`, `$booking->refresh()`.
2. `backend/tests/Unit/StripeServiceTest.php` — the unit mirror, BUT note the divergence in <test_design> D1.
3. T3–T6 contracts: `MoMoService::{signCreatePayment,signIpn,verifyIpnSignature,orderId,bookingIdFromOrderId,createPayment}`;
   `MoMoWebhookEvent` (table `momo_webhook_events`, UNIQUE(order_id,trans_id), statuses processing/processed/failed);
   `MoMoIpnHandler`; route names `v1.payments.momo.ipn` (public) / `v1.bookings.momo.create`.
   `docs/.../MOMO_SANDBOX_EXECUTION_PLAN.md` §2 (canonical signing strings + public sandbox creds), §3 T8.
</authoritative_references>

<test_design>
D1 — **Unit base class.** `StripeServiceTest extends PHPUnit\Framework\TestCase` (pure, no Laravel) because it
only tests a string helper. `MoMoServiceTest` MUST instead `extends Tests\TestCase` (boots the app) because
`signCreatePayment`/`signIpn` read `config('services.momo.secret_key')` and `createPayment` reads `app()->environment()`
— `config()`/`app()` require a booted container. Do NOT use `RefreshDatabase` in the unit test (build in-memory
`new Booking; $b->forceFill([...]); $b->exists = true;` — no DB rows needed). Resolve the service via `app(MoMoService::class)`.

D2 — **Self-verifying signature vector (the security lock).** Don't hand-paste a magic hex. Set the public sandbox
secret via `config(['services.momo.secret_key' => 'K951B6PE1waDMi640xX08PD3vg6EkVlz'])`, build a FIXED `$fields`
array, then assert `signCreatePayment($fields)` equals an INDEPENDENT `hash_hmac('sha256', $expectedRaw, $secret)`
where `$expectedRaw` is the literal §2-ordered string. If the method ksorts or reorders fields, the HMAC differs
and the test fails — that is exactly the regression you are locking. Do the same for `signIpn`.

D3 — **Feature tests hit the REAL route**, not a reflected handler (the MoMo controller verifies the signature
itself, unlike Cashier). `$this->postJson(route('v1.payments.momo.ipn'), $payload)`. This also proves T7's route
carries no auth (an authed route would 401 the un-authenticated post). Set a non-blank
`config(['services.momo.secret_key' => 'test-momo-secret'])` in `setUp` so `verifyIpnSignature` actually verifies,
and sign payloads with `app(MoMoService::class)->signIpn($payload)`.

D4 — **Amount-mismatch must be validly signed.** Build the payload with the WRONG amount and THEN sign it, so the
signature passes (200-path) but `MoMoIpnHandler` returns `AmountMismatch`. This proves the amount guard is real
defense-in-depth, not a side effect of signature failure. Expected: 204, booking stays PENDING, ledger row `failed`.

D5 — **Replay reuses the exact same payload** (same order_id AND trans_id) — build once, post twice. Assert
`assertDatabaseCount('momo_webhook_events', 1)` after the second post and booking CONFIRMED (confirmed exactly once).
</test_design>

<implementation_spec>
**Unit — `tests/Unit/MoMoServiceTest.php`** (`namespace Tests\Unit;`, `declare(strict_types=1)`,
`final class MoMoServiceTest extends Tests\TestCase`):

    public function test_sign_create_payment_matches_canonical_ordered_hmac(): void
    {
        config(['services.momo.secret_key' => 'K951B6PE1waDMi640xX08PD3vg6EkVlz']);
        $fields = ['accessKey'=>'F8BBA842ECF85','amount'=>'10000','extraData'=>'','ipnUrl'=>'https://x/ipn',
                   'orderId'=>'soleil-1-abc','orderInfo'=>'Test','partnerCode'=>'MOMO','redirectUrl'=>'https://x/r',
                   'requestId'=>'req-1','requestType'=>'captureWallet'];
        $expectedRaw = 'accessKey=F8BBA842ECF85&amount=10000&extraData=&ipnUrl=https://x/ipn&orderId=soleil-1-abc'
                     .'&orderInfo=Test&partnerCode=MOMO&redirectUrl=https://x/r&requestId=req-1&requestType=captureWallet';
        $expected = hash_hmac('sha256', $expectedRaw, 'K951B6PE1waDMi640xX08PD3vg6EkVlz');
        $this->assertSame($expected, app(\App\Services\MoMoService::class)->signCreatePayment($fields));
    }
    // test_sign_ipn_matches_canonical_ordered_hmac(): same shape, IPN field set + §2 IPN raw string.
    // test_order_id_round_trips(): $b=new Booking; $b->id=77; $b->exists=true; $o=$svc->orderId($b);
    //   assertSame(77,$svc->bookingIdFromOrderId($o)); assertNull($svc->bookingIdFromOrderId('garbage'));
    //   assertNull($svc->bookingIdFromOrderId('soleil--abc')); assertStringStartsWith('soleil-77-',$o);
    // test_fake_mode_create_payment_is_deterministic_without_network():
    //   config(['services.momo.secret_key'=>null]); // blank => fake mode in testing
    //   $b=new Booking; $b->forceFill(['id'=>7,'amount'=>150000,'payment_currency'=>'vnd','payment_policy'=>PaymentPolicy::PREPAID]); $b->exists=true;
    //   $r1=$svc->createPayment($b); $r2=$svc->createPayment($b);
    //   assertSame($r1->orderId,$r2->orderId); assertSame($r1->payUrl,$r2->payUrl); assertSame(150000,$r1->amount); assertNotEmpty($r1->payUrl);

**Feature — `tests/Feature/Payment/MoMoIpnTest.php`** (`namespace Tests\Feature\Payment;`, `declare(strict_types=1)`,
`final class MoMoIpnTest extends Tests\TestCase`, `use RefreshDatabase;`):

    protected function setUp(): void {
        parent::setUp();
        config(['services.momo.secret_key' => 'test-momo-secret']);
        $this->user = User::factory()->create();
        $this->room = Room::factory()->available()->ready()->create();
    }

    private function pendingPrepaidBooking(int $amount = 50000): Booking {
        return Booking::factory()->for($this->user)->for($this->room)->create([
            'status' => BookingStatus::PENDING, 'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD, 'payment_currency' => 'vnd', 'amount' => $amount,
        ]);
    }

    private function signedIpn(Booking $b, array $overrides = []): array {
        $p = array_merge([
            'partnerCode'=>'MOMO','orderId'=>'soleil-'.$b->id.'-'.uniqid(),'requestId'=>(string) Str::uuid(),
            'amount'=>(string) $b->amount,'orderInfo'=>'Soleil #'.$b->id,'orderType'=>'momo_wallet',
            'transId'=>(string) random_int(10000000,99999999),'resultCode'=>0,'message'=>'Successful.',
            'payType'=>'qr','responseTime'=>(string) now()->valueOf(),'extraData'=>'',
        ], $overrides);
        $p['signature'] = app(\App\Services\MoMoService::class)->signIpn($p);
        return $p;
    }

    // (a) valid: $b=pendingPrepaidBooking(); $r=$this->postJson(route('v1.payments.momo.ipn'),$this->signedIpn($b));
    //     $r->assertNoContent(); $b->refresh(); assertSame(CONFIRMED,$b->status); assertSame(PAID,$b->payment_status);
    //     assertDatabaseHas('momo_webhook_events',['order_id'=>..., 'status'=>'processed']);
    // (b) replay: $b=...; $p=$this->signedIpn($b); post($p)->assertNoContent(); post($p)->assertNoContent();
    //     assertDatabaseCount('momo_webhook_events',1); $b->refresh(); assertSame(CONFIRMED,$b->status);
    // (c) bad sig: $b=...; $p=$this->signedIpn($b); $p['signature']='deadbeef'; post($p)->assertStatus(400);
    //     $b->refresh(); assertSame(PENDING,$b->status); assertDatabaseCount('momo_webhook_events',0);
    // (d) amount mismatch: $b=pendingPrepaidBooking(50000); $p=$this->signedIpn($b,['amount'=>'999999']);
    //     post($p)->assertNoContent(); $b->refresh(); assertSame(PENDING,$b->status);
    //     assertDatabaseHas('momo_webhook_events',['status'=>'failed']);
    // (e) unknown order: $b=...; $p=$this->signedIpn($b,['orderId'=>'soleil-99999999-x']);
    //     post($p)->assertNoContent(); assertDatabaseHas('momo_webhook_events',['order_id'=>'soleil-99999999-x','status'=>'processed']);

Optional (recommended) extra: blank-secret → 500 — `config(['services.momo.secret_key'=>'']); post(...)->assertStatus(500);`.
Imports: `BookingStatus`, `PaymentPolicy`, `PaymentStatus`, `Booking`, `Room`, `User`, `MoMoWebhookEvent`,
`Illuminate\Support\Str`, `RefreshDatabase`, `Tests\TestCase`. Match Pint + the Stripe test file headers.
</implementation_spec>

<acceptance_criteria>
1. All new unit + feature tests pass; the signature vector test fails if the canonical field ordering is broken.
2. The five feature cases assert exactly: confirm-once, replay-dedup (count==1), bad-sig 400 + zero ledger rows,
   amount-mismatch 204-but-PENDING + `failed` row, unknown-order 204 handled.
3. Full suite green — Stripe and all other tests untouched (`composer test`).
4. Exactly two new files in the diff; zero production-code changes.
</acceptance_criteria>

<verification>
Run from repo root / `backend/` (test DB required):

    docker compose up -d db && cd backend && php scripts/check-test-db.php   # GATE-0
    composer lint
    php artisan test tests/Unit/MoMoServiceTest.php tests/Feature/Payment/MoMoIpnTest.php   # the new tests
    composer test                         # FULL suite green (config:clear + artisan test) — Stripe untouched
    git --no-pager diff --stat            # exactly two new files

If `composer test` shows any pre-existing Stripe/booking test now failing, that is a regression signal — stop and
report; do not edit production code to make a test pass without surfacing the cause.
</verification>

<output_format>
Follow CLAUDE.md output-style policy: change under `.claude/output-styles/execution-plan.md`, results under
`.claude/output-styles/execution.md`. Tag findings `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]` (esp. if the §2 wire
format proves wrong against a live sandbox), `[ACTION]`. End with the full `php artisan test` summary (counts) as
evidence, plus the new-file `git diff`.
</output_format>

<stop_conditions>
Stop and confirm with me before: creating/editing any file other than the two test files; modifying any T1–T7
production file (if a test reveals a real defect, surface it + the minimal fix and wait); changing test DB config
or `phpunit.xml`; using `--no-verify`; or committing. Do NOT commit, push, or merge — continue branch
`feature/momo-sandbox-payment`, leave the change uncommitted, and show me the diff + full test output.
</stop_conditions>
````
