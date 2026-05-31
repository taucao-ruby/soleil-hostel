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
use App\Services\Payment\StripeRefundEventRecorder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

/**
 * PAY-04 — ReconcileRefundsJob must write the authoritative refund ledger
 * (stripe_refund_events) for every refund it discovers or issues, so a missed
 * `charge.refunded` webhook cannot leave a permanent ledger gap.
 *
 * The reconciler talks to Stripe through Cashier's StripeClient. Rather than
 * add a seam to the (final) job, we install a Stripe HTTP-client double
 * (ApiRequestor::setHttpClient) — stripe-php v17 routes instance-client calls
 * through it — and drive the real handle() entry point with canned API
 * responses. Bookings are null-user so the application-level Cashier client is
 * used (CONC-006), keeping fixtures minimal.
 */
final class ReconcileRefundsLedgerTest extends TestCase
{
    private Room $room;

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
        // Never leak the HTTP double into sibling tests in the same process.
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_discovers_missed_refund_via_payment_intent_and_writes_ledger(): void
    {
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->staleBooking([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_discover',
            'refund_id' => null,
            'amount' => 50000,
        ]);

        $this->installStripeHttp([
            'payment_intent' => $this->paymentIntentJson('pi_discover', $this->refundJson('re_discovered', 'succeeded', 50000)),
        ]);

        (new ReconcileRefundsJob)->handle();

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('re_discovered', $booking->refund_id);
        $this->assertSame('succeeded', $booking->refund_status);
        $this->assertSame(50000, $booking->refund_amount);

        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_discovered',
            'stripe_event_id' => 'reconcile:refund:re_discovered',
            'booking_id' => $booking->id,
            'amount_refunded' => 50000,
            'currency' => 'vnd',
        ]);
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_discovered')->count());
    }

    public function test_verifies_existing_succeeded_refund_and_writes_ledger(): void
    {
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->staleBooking([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_verify',
            'refund_id' => 're_existing',
            'amount' => 30000,
        ]);

        $this->installStripeHttp([
            'retrieve_refund' => $this->refundJson('re_existing', 'succeeded', 30000),
        ]);

        (new ReconcileRefundsJob)->handle();

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('succeeded', $booking->refund_status);

        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_existing',
            'stripe_event_id' => 'reconcile:refund:re_existing',
            'booking_id' => $booking->id,
            'amount_refunded' => 30000,
            'currency' => 'vnd',
        ]);
    }

    public function test_verifies_existing_failed_refund_and_writes_ledger(): void
    {
        Event::fake([BookingStatusChanged::class]);

        $booking = $this->staleBooking([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_failed',
            'refund_id' => 're_failed',
            'amount' => 20000,
        ]);

        $this->installStripeHttp([
            'retrieve_refund' => $this->refundJson('re_failed', 'failed', 20000, failureReason: 'expired_or_canceled_card'),
        ]);

        (new ReconcileRefundsJob)->handle();

        $booking->refresh();
        $this->assertSame(BookingStatus::REFUND_FAILED, $booking->status);
        $this->assertSame('failed', $booking->refund_status);

        // A discovered failed refund is still part of refund history — the
        // webhook records it too, so the reconciler must mirror that.
        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_failed',
            'stripe_event_id' => 'reconcile:refund:re_failed',
            'booking_id' => $booking->id,
            'amount_refunded' => 20000,
            'currency' => 'vnd',
        ]);
    }

    public function test_issuer_retry_path_writes_ledger(): void
    {
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->staleBooking([
            'user_id' => null,
            'status' => BookingStatus::REFUND_FAILED,
            'payment_intent_id' => 'pi_issue',
            'refund_id' => null,
            'amount' => 10000,
            'check_in' => Carbon::now()->addDays(10),
            'check_out' => Carbon::now()->addDays(12),
            'refund_error' => 'Card declined',
        ]);

        $expectedAmount = $booking->calculateRefundAmount();
        $this->assertGreaterThan(0, $expectedAmount);

        $this->installStripeHttp([
            // PAY-01: the issue path now pre-checks for an existing refund (GET
            // payment_intent) before creating one. No refund exists yet here.
            'payment_intent' => $this->paymentIntentJsonWithoutRefunds('pi_issue'),
            'create_refund' => $this->refundJson('re_issued', 'succeeded', $expectedAmount),
        ]);

        (new ReconcileRefundsJob)->handle();

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('re_issued', $booking->refund_id);

        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_issued',
            'stripe_event_id' => 'reconcile_issue:refund:re_issued',
            'booking_id' => $booking->id,
            'amount_refunded' => $expectedAmount,
            'currency' => 'vnd',
        ]);
    }

    public function test_reconciliation_is_idempotent_on_rerun(): void
    {
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->staleBooking([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_idem',
            'refund_id' => null,
            'amount' => 50000,
        ]);

        $this->installStripeHttp([
            'payment_intent' => $this->paymentIntentJson('pi_idem', $this->refundJson('re_idem', 'succeeded', 50000)),
        ]);

        (new ReconcileRefundsJob)->handle();
        (new ReconcileRefundsJob)->handle();

        // Exactly one canonical ledger row; the second pass finds the booking
        // already CANCELLED (out of the reconciler's REFUND_PENDING query set).
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_idem')->count());
        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    public function test_existing_ledger_row_blocks_duplicate_and_rolls_back_projection(): void
    {
        Event::fake([BookingCancelled::class, BookingStatusChanged::class]);

        $booking = $this->staleBooking([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_race',
            'refund_id' => null,
            'refund_status' => null,
            'refund_amount' => null,
            'amount' => 42000,
        ]);

        // The webhook got there first: a ledger row stamped with the real evt_ id.
        // This makes the reconciler's INSERT fail on the UNIQUE(stripe_refund_id)
        // guard mid-transaction.
        StripeRefundEvent::create([
            'stripe_refund_id' => 're_race',
            'stripe_event_id' => 'evt_webhook_first',
            'booking_id' => $booking->id,
            'amount_refunded' => 42000,
            'currency' => 'vnd',
        ]);

        $this->installStripeHttp([
            'payment_intent' => $this->paymentIntentJson('pi_race', $this->refundJson('re_race', 'succeeded', 42000)),
        ]);

        (new ReconcileRefundsJob)->handle();

        // No duplicate, and the webhook's evt_ id is not clobbered (first writer wins).
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_race')->count());
        $this->assertSame(
            'evt_webhook_first',
            StripeRefundEvent::where('stripe_refund_id', 're_race')->value('stripe_event_id'),
        );

        // Transactional coupling: the failed ledger write rolled the booking
        // projection back — no field was partially advanced.
        $booking->refresh();
        $this->assertSame(BookingStatus::REFUND_PENDING, $booking->status);
        $this->assertNull($booking->refund_id);
        $this->assertNull($booking->refund_status);
        $this->assertNull($booking->refund_amount);
    }

    public function test_recorder_persists_only_schema_columns_and_lowercases_currency(): void
    {
        $booking = $this->staleBooking([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_recorder',
            'amount' => 12345,
        ]);

        app(StripeRefundEventRecorder::class)->record(
            $booking,
            're_recorder',
            12345,
            'VND',
            StripeRefundEventRecorder::reconcileEventKey('re_recorder'),
        );

        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_recorder',
            'stripe_event_id' => 'reconcile:refund:re_recorder',
            'booking_id' => $booking->id,
            'amount_refunded' => 12345,
            'currency' => 'vnd',
        ]);
    }

    public function test_event_key_helpers_are_deterministic(): void
    {
        $this->assertSame('reconcile:refund:re_abc', StripeRefundEventRecorder::reconcileEventKey('re_abc'));
        $this->assertSame('reconcile_issue:refund:re_abc', StripeRefundEventRecorder::reconcileIssueEventKey('re_abc'));
        $this->assertSame(
            StripeRefundEventRecorder::reconcileEventKey('re_abc'),
            StripeRefundEventRecorder::reconcileEventKey('re_abc'),
        );
    }

    // ===== Helpers =====

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function staleBooking(array $attributes): Booking
    {
        $booking = Booking::factory()
            ->for($this->room)
            ->state($attributes)
            ->create();

        // Backdate so both reconcilePendingRefunds (>5m) and retryFailedRefunds
        // (>15m) stale-thresholds select the row.
        DB::table('bookings')
            ->where('id', $booking->id)
            ->update(['updated_at' => Carbon::now()->subMinutes(30)]);

        return $booking->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function refundJson(string $id, string $status, int $amount, string $currency = 'vnd', ?string $failureReason = null): array
    {
        $refund = [
            'id' => $id,
            'object' => 'refund',
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
        ];

        if ($failureReason !== null) {
            $refund['failure_reason'] = $failureReason;
        }

        return $refund;
    }

    /**
     * @param  array<string, mixed>  $latestRefund
     * @return array<string, mixed>
     */
    private function paymentIntentJson(string $id, array $latestRefund): array
    {
        return [
            'id' => $id,
            'object' => 'payment_intent',
            'latest_charge' => [
                'id' => 'ch_'.$id,
                'object' => 'charge',
                'refunds' => [
                    'object' => 'list',
                    'has_more' => false,
                    'url' => '/v1/charges/ch_'.$id.'/refunds',
                    'data' => [$latestRefund],
                ],
            ],
        ];
    }

    /**
     * A PaymentIntent whose charge has no refunds yet — the PAY-01 pre-check
     * shape for "nothing exists, safe to create".
     *
     * @return array<string, mixed>
     */
    private function paymentIntentJsonWithoutRefunds(string $id): array
    {
        return [
            'id' => $id,
            'object' => 'payment_intent',
            'latest_charge' => [
                'id' => 'ch_'.$id,
                'object' => 'charge',
                'refunds' => [
                    'object' => 'list',
                    'has_more' => false,
                    'url' => '/v1/charges/ch_'.$id.'/refunds',
                    'data' => [],
                ],
            ],
        ];
    }

    /**
     * Install a Stripe HTTP double that returns canned JSON per endpoint.
     *
     * @param  array{retrieve_refund?: array<string, mixed>, payment_intent?: array<string, mixed>, create_refund?: array<string, mixed>}  $responses
     */
    private function installStripeHttp(array $responses): void
    {
        ApiRequestor::setHttpClient(new class($responses) implements ClientInterface
        {
            /** @param  array<string, array<string, mixed>>  $responses */
            public function __construct(private array $responses) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $method = strtolower((string) $method);
                $url = (string) $absUrl;

                if ($method === 'post' && str_contains($url, '/v1/refunds')) {
                    return $this->ok($this->responses['create_refund'] ?? []);
                }

                if ($method === 'get' && str_contains($url, '/v1/refunds/')) {
                    return $this->ok($this->responses['retrieve_refund'] ?? []);
                }

                if ($method === 'get' && str_contains($url, '/v1/payment_intents/')) {
                    return $this->ok($this->responses['payment_intent'] ?? []);
                }

                throw new \RuntimeException("Unexpected Stripe call in test: {$method} {$url}");
            }

            /**
             * @param  array<string, mixed>  $body
             * @return array{0: string, 1: int, 2: array<string, string>}
             */
            private function ok(array $body): array
            {
                return [(string) json_encode($body), 200, []];
            }
        });
    }
}
