<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Room;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Tests\TestCase;

/**
 * SH-02 / F-76 — the booking-cancellation refund idempotency-key contract.
 *
 * The synchronous cancellation refund (StripeService::createBookingRefund) and
 * the reconciler refund (StripeService::createReconciliationRefund) MUST derive
 * the SAME idempotency key for the same logical booking refund, so a refund
 * Stripe accepted on one path is de-duplicated on the other. Distinct logical
 * refunds (different bookings / payment intents) MUST derive distinct keys so
 * they can never accidentally collapse into one another.
 */
final class BookingRefundIdempotencyKeyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test C — cancellation path and reconciler path send the same key.
     */
    public function test_cancellation_and_reconciler_refunds_share_one_idempotency_key(): void
    {
        // Non-blank secret => StripeService skips its testing-fake short-circuit
        // and actually issues through the bound (recording) Stripe client.
        config()->set('cashier.secret', 'sk_test_local');

        $booking = Booking::factory()
            ->for(Room::factory())
            ->confirmed()
            ->create([
                'payment_intent_id' => 'pi_shared_key',
                'amount' => 10_000,
            ]);

        $capture = $this->bindRecordingStripeClient('re_shared');
        $service = app(StripeService::class);

        $expectedKey = $service->bookingRefundIdempotencyKey($booking);

        // Cancellation path (orphaned-user branch passes no client) ...
        $service->createBookingRefund($booking, 5_000);
        // ... and reconciler path (caller supplies the resolved client).
        $service->createReconciliationRefund(Cashier::stripe(), $booking, 5_000);

        $this->assertCount(2, $capture->calls);
        $this->assertSame($expectedKey, $capture->calls[0]['options']['idempotency_key'] ?? null);
        $this->assertSame($expectedKey, $capture->calls[1]['options']['idempotency_key'] ?? null);
        $this->assertSame("booking:{$booking->id}:refund:pi_shared_key", $expectedKey);
    }

    /**
     * Test D — distinct logical refunds derive distinct keys.
     */
    public function test_distinct_logical_refunds_use_distinct_idempotency_keys(): void
    {
        $room = Room::factory()->create();
        $service = app(StripeService::class);

        $a = Booking::factory()->for($room)->confirmed()->create([
            'payment_intent_id' => 'pi_a',
            'amount' => 10_000,
        ]);
        $b = Booking::factory()->for($room)->confirmed()->create([
            'payment_intent_id' => 'pi_b',
            'amount' => 10_000,
        ]);

        // Different bookings => different keys (no cross-booking dedup).
        $this->assertNotSame(
            $service->bookingRefundIdempotencyKey($a),
            $service->bookingRefundIdempotencyKey($b),
        );

        // The key binds the payment_intent, so a re-issued intent yields a new
        // key (a genuinely distinct logical refund is never deduped against the
        // prior one).
        $keyBefore = $service->bookingRefundIdempotencyKey($a);
        $a->forceFill(['payment_intent_id' => 'pi_a_reissued'])->save();
        $this->assertNotSame($keyBefore, $service->bookingRefundIdempotencyKey($a->fresh()));
    }

    /**
     * Bind a Stripe client that records each refunds->create call and returns a
     * real Stripe\Refund (so createReconciliationRefund's return type holds).
     *
     * @return object{calls: list<array{payload: array<string, mixed>, options: array<string, mixed>}>}
     */
    private function bindRecordingStripeClient(string $refundId): object
    {
        $capture = (object) ['calls' => []];

        $fakeStripe = new class($capture, $refundId) extends \Stripe\StripeClient
        {
            public object $refunds;

            public function __construct(object $capture, string $refundId)
            {
                $this->refunds = new class($capture, $refundId)
                {
                    public function __construct(
                        private object $capture,
                        private string $refundId,
                    ) {}

                    /**
                     * @param  array<string, mixed>  $payload
                     * @param  array<string, mixed>  $options
                     */
                    public function create(array $payload, array $options = []): \Stripe\Refund
                    {
                        $this->capture->calls[] = ['payload' => $payload, 'options' => $options];

                        return \Stripe\Refund::constructFrom(['id' => $this->refundId, 'object' => 'refund']);
                    }
                };
            }
        };

        $this->app->bind(\Stripe\StripeClient::class, static fn () => $fakeStripe);

        return $capture;
    }
}
