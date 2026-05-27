<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingStatusChanged;
use App\Jobs\ReconcileRefundsJob;
use App\Models\Booking;
use App\Models\Room;
use App\Models\StripeRefundEvent;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Cashier;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

/**
 * PAY-01 — the reconciler's retry path must create AT MOST ONE Stripe refund
 * per logical refund event.
 *
 * Closes the duplicate-refund hole: ReconcileRefundsJob::retryFailedRefunds used
 * to create refunds with no idempotency key and no existing-refund pre-check, so
 * a queue retry, timeout-after-accept, or concurrent worker could refund twice.
 *
 * Like ReconcileRefundsLedgerTest, we drive the real handle() entry point and
 * intercept Stripe at the HTTP layer (ApiRequestor::setHttpClient). The double
 * here additionally RECORDS each request so we can assert the Idempotency-Key
 * header and refund metadata, and can fail a POST on demand.
 */
final class ReconcileRefundIdempotencyTest extends TestCase
{
    private Room $room;

    private RecordingStripeHttpClient $http;

    protected function setUp(): void
    {
        parent::setUp();
        $this->room = Room::factory()->create();
        // Non-blank secret => resolveStripeClientFor returns Cashier::stripe()
        // for null-user bookings; the HTTP double below intercepts the calls.
        config(['cashier.secret' => 'sk_test_reconcile']);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_issue_path_sends_stable_idempotency_key_and_reconcilable_metadata(): void
    {
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->failedRefundBooking('pi_issue', 10000);
        $expectedAmount = $booking->calculateRefundAmount();
        $this->assertGreaterThan(0, $expectedAmount);
        $expectedKey = app(StripeService::class)->bookingRefundIdempotencyKey($booking);

        $this->installHttp(
            paymentIntent: $this->paymentIntentJson('pi_issue', []),         // pre-check: no existing refund
            createRefund: $this->refundJson('re_issued', 'succeeded', $expectedAmount),
        );

        (new ReconcileRefundsJob)->handle();

        $posts = $this->http->postsTo('/v1/refunds');
        $this->assertCount(1, $posts, 'exactly one refund create');

        $this->assertSame($expectedKey, $this->http->idempotencyKeyOf($posts[0]));
        $this->assertStringContainsString('booking:'.$booking->id.':refund:pi_issue', $expectedKey);
        $this->assertSame($expectedKey, $posts[0]['params']['metadata']['soleil_refund_event_id']);
        $this->assertSame((string) $booking->id, $posts[0]['params']['metadata']['booking_id']);
        $this->assertSame('pi_issue', $posts[0]['params']['metadata']['payment_intent_id']);

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('re_issued', $booking->refund_id);
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_issued')->count());
    }

    public function test_pre_check_syncs_existing_refund_by_metadata_and_skips_create(): void
    {
        // Models "Stripe accepted the refund but the local DB update never
        // landed" (timeout / crash): the refund already exists carrying our
        // identity stamp; the next run must NOT create a second one.
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->failedRefundBooking('pi_meta', 10000);
        $amount = $booking->calculateRefundAmount();
        $key = app(StripeService::class)->bookingRefundIdempotencyKey($booking);

        $existing = $this->refundJson('re_prior', 'succeeded', $amount, metadata: [
            'soleil_refund_event_id' => $key,
            'booking_id' => (string) $booking->id,
        ]);

        $this->installHttp(paymentIntent: $this->paymentIntentJson('pi_meta', [$existing]));

        (new ReconcileRefundsJob)->handle();

        $this->assertCount(0, $this->http->postsTo('/v1/refunds'), 'must not create a duplicate refund');

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('re_prior', $booking->refund_id);
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_prior')->count());
    }

    public function test_pre_check_matches_existing_refund_by_amount_when_metadata_absent(): void
    {
        // A single same-amount refund with no metadata (e.g. the live Cashier
        // path) is a confident match — sync it, never refund again.
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->failedRefundBooking('pi_amount', 10000);
        $amount = $booking->calculateRefundAmount();

        $existing = $this->refundJson('re_bare', 'succeeded', $amount);
        $this->installHttp(paymentIntent: $this->paymentIntentJson('pi_amount', [$existing]));

        (new ReconcileRefundsJob)->handle();

        $this->assertCount(0, $this->http->postsTo('/v1/refunds'));
        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
        $this->assertSame('re_bare', $booking->fresh()->refund_id);
    }

    public function test_ambiguous_existing_refunds_block_create_and_flag_manual_reconciliation(): void
    {
        // Two same-amount refunds, neither carrying our identity stamp: we
        // cannot tell which (if any) is ours, so we must NOT refund again.
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->failedRefundBooking('pi_ambig', 10000);
        $amount = $booking->calculateRefundAmount();

        $this->installHttp(paymentIntent: $this->paymentIntentJson('pi_ambig', [
            $this->refundJson('re_one', 'succeeded', $amount),
            $this->refundJson('re_two', 'succeeded', $amount),
        ]));

        (new ReconcileRefundsJob)->handle();

        $this->assertCount(0, $this->http->postsTo('/v1/refunds'), 'ambiguity must not create a refund');

        $booking->refresh();
        $this->assertSame(BookingStatus::REFUND_FAILED, $booking->status);
        $this->assertNull($booking->refund_id);
        $this->assertStringContainsString('Ambiguous', (string) $booking->refund_error);
        $this->assertStringContainsString('re_one', (string) $booking->refund_error);
        $this->assertStringContainsString('re_two', (string) $booking->refund_error);
    }

    public function test_two_reconcile_runs_create_exactly_one_refund(): void
    {
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->failedRefundBooking('pi_once', 10000);
        $amount = $booking->calculateRefundAmount();

        $this->installHttp(
            paymentIntent: $this->paymentIntentJson('pi_once', []),
            createRefund: $this->refundJson('re_once', 'succeeded', $amount),
        );

        (new ReconcileRefundsJob)->handle();
        (new ReconcileRefundsJob)->handle();

        // Run 1 issues the refund and finalizes the booking to CANCELLED, which
        // drops it out of retryFailedRefunds' query set, so run 2 is a no-op.
        $this->assertCount(1, $this->http->postsTo('/v1/refunds'));
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_once')->count());
        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    public function test_concurrency_claim_allows_only_one_worker(): void
    {
        $booking = $this->failedRefundBooking('pi_claim', 10000);

        $job = new ReconcileRefundsJob;
        $claim = new \ReflectionMethod($job, 'claimFailedRefund');
        $claim->setAccessible(true);

        $first = $claim->invoke($job, $booking, 15);
        $second = $claim->invoke($job, $booking->fresh(), 15);

        $this->assertTrue($first, 'first worker wins the lease');
        $this->assertFalse($second, 'second worker is excluded by the CAS on updated_at');
    }

    public function test_create_reconciliation_refund_reuses_same_key_and_survives_failure(): void
    {
        // Proves both "repeated retry uses the same key" and "an exception after
        // the attempt does not generate a fresh key": the key is a pure function
        // of (booking, payment_intent), independent of attempt/randomness.
        $booking = $this->failedRefundBooking('pi_stable', 10000);
        $service = app(StripeService::class);
        $key = $service->bookingRefundIdempotencyKey($booking);

        $this->installHttp(createRefund: $this->refundJson('re_stable', 'succeeded', 10000), failFirstPost: true);

        try {
            $service->createReconciliationRefund(Cashier::stripe(), $booking, 5000);
            $this->fail('expected the simulated network failure to propagate');
        } catch (ApiConnectionException) {
            // expected on the first attempt
        }

        $refund = $service->createReconciliationRefund(Cashier::stripe(), $booking, 5000);
        $this->assertSame('re_stable', $refund->id);

        $posts = $this->http->postsTo('/v1/refunds');
        $this->assertCount(2, $posts);
        $this->assertSame($key, $this->http->idempotencyKeyOf($posts[0]));
        $this->assertSame($key, $this->http->idempotencyKeyOf($posts[1]));
        $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}-/', $key, 'key must not be a random UUID');
    }

    public function test_create_reconciliation_refund_rejects_invalid_input_before_calling_stripe(): void
    {
        $service = app(StripeService::class);
        $this->installHttp();

        $booking = $this->failedRefundBooking('pi_guard', 10000);

        try {
            $service->createReconciliationRefund(Cashier::stripe(), $booking, 0);
            $this->fail('expected non-positive amount to be rejected');
        } catch (RuntimeException) {
            // expected
        }

        // Without a persisted booking + payment_intent there is no stable key to
        // derive, so no Stripe refund can be created (criterion 10 equivalent).
        try {
            app(StripeService::class)->bookingRefundIdempotencyKey(new Booking);
            $this->fail('expected an unpersisted booking to be rejected');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertCount(0, $this->http->postsTo('/v1/refunds'));
    }

    // ===== Fixtures =====

    private function failedRefundBooking(string $paymentIntentId, int $amount): Booking
    {
        $booking = Booking::factory()
            ->for($this->room)
            ->refundFailed()
            ->state([
                'user_id' => null,
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => null,
                'amount' => $amount,
                'check_in' => Carbon::now()->addDays(10),
                'check_out' => Carbon::now()->addDays(12),
            ])
            ->create();

        // Backdate so the >15m retry window selects the row.
        DB::table('bookings')
            ->where('id', $booking->id)
            ->update(['updated_at' => Carbon::now()->subMinutes(30)]);

        return $booking->fresh();
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array<string, mixed>
     */
    private function refundJson(string $id, string $status, int $amount, string $currency = 'vnd', array $metadata = []): array
    {
        return [
            'id' => $id,
            'object' => 'refund',
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => (object) $metadata,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $refunds
     * @return array<string, mixed>
     */
    private function paymentIntentJson(string $id, array $refunds): array
    {
        return [
            'id' => $id,
            'object' => 'payment_intent',
            'latest_charge' => [
                'id' => 'ch_'.$id,
                'object' => 'charge',
                'currency' => 'vnd',
                'refunds' => [
                    'object' => 'list',
                    'has_more' => false,
                    'url' => '/v1/charges/ch_'.$id.'/refunds',
                    'data' => $refunds,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $paymentIntent
     * @param  array<string, mixed>|null  $createRefund
     */
    private function installHttp(?array $paymentIntent = null, ?array $createRefund = null, bool $failFirstPost = false): void
    {
        $this->http = new RecordingStripeHttpClient($paymentIntent, $createRefund, $failFirstPost);
        ApiRequestor::setHttpClient($this->http);
    }
}

/**
 * Stripe HTTP double that records every request (so tests can assert the
 * Idempotency-Key header and metadata params) and can fail a POST on demand.
 */
final class RecordingStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: array<int, string>, params: array<string, mixed>}> */
    public array $calls = [];

    private bool $postFailureArmed;

    /**
     * @param  array<string, mixed>|null  $paymentIntent
     * @param  array<string, mixed>|null  $createRefund
     */
    public function __construct(
        private ?array $paymentIntent,
        private ?array $createRefund,
        bool $failFirstPost,
    ) {
        $this->postFailureArmed = $failFirstPost;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $method = strtolower((string) $method);
        $url = (string) $absUrl;

        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => is_array($headers) ? $headers : [],
            'params' => is_array($params) ? $params : [],
        ];

        if ($method === 'post' && str_contains($url, '/v1/refunds')) {
            if ($this->postFailureArmed) {
                $this->postFailureArmed = false;
                throw new ApiConnectionException('Simulated network timeout after Stripe accepted the request');
            }

            return $this->ok($this->createRefund ?? []);
        }

        if ($method === 'get' && str_contains($url, '/v1/payment_intents/')) {
            return $this->ok($this->paymentIntent ?? []);
        }

        throw new \RuntimeException("Unexpected Stripe call in test: {$method} {$url}");
    }

    /**
     * @return list<array{method: string, url: string, headers: array<int, string>, params: array<string, mixed>}>
     */
    public function postsTo(string $needle): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (array $c): bool => $c['method'] === 'post' && str_contains($c['url'], $needle),
        ));
    }

    /**
     * @param  array{headers: array<int, string>}  $call
     */
    public function idempotencyKeyOf(array $call): ?string
    {
        foreach ($call['headers'] as $header) {
            if (stripos($header, 'Idempotency-Key:') === 0) {
                return trim(substr($header, strlen('Idempotency-Key:')));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    private function ok(array $body): array
    {
        return [(string) json_encode($body), 200, []];
    }
}
