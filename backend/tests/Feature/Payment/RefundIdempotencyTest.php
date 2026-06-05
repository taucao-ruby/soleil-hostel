<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Events\BookingStatusChanged;
use App\Http\Controllers\Payment\StripeWebhookController;
use App\Models\Booking;
use App\Models\StripeRefundEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('booking')]
final class RefundIdempotencyTest extends TestCase
{
    public function test_replaying_same_charge_refunded_event_five_times_results_in_one_state_transition(): void
    {
        Event::fake([BookingStatusChanged::class]);

        $booking = $this->refundPendingBooking('pi_refund_replay');
        $payload = $this->chargeRefundedPayload(
            eventId: 'evt_refund_replay',
            chargeId: 'ch_refund_replay',
            paymentIntentId: 'pi_refund_replay',
            refunds: [
                ['id' => 're_refund_replay', 'status' => 'succeeded', 'amount' => 50000],
            ],
            amountRefunded: 50000
        );

        for ($i = 0; $i < 5; $i++) {
            $response = $this->callChargeRefunded($payload);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getData(true)['handled']);
        }

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame('re_refund_replay', $booking->refund_id);
        $this->assertSame('succeeded', $booking->refund_status);
        $this->assertSame(50000, $booking->refund_amount);
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_refund_replay')->count());
        Event::assertDispatchedTimes(BookingStatusChanged::class, 1);
    }

    public function test_simulated_concurrent_refund_webhooks_for_same_refund_id_are_deduped_by_database(): void
    {
        Event::fake([BookingStatusChanged::class]);

        $booking = $this->refundPendingBooking('pi_refund_concurrent');
        $payloads = [
            $this->chargeRefundedPayload(
                eventId: 'evt_refund_concurrent_a',
                chargeId: 'ch_refund_concurrent',
                paymentIntentId: 'pi_refund_concurrent',
                refunds: [
                    ['id' => 're_refund_concurrent', 'status' => 'succeeded', 'amount' => 42000],
                ],
                amountRefunded: 42000
            ),
            $this->chargeRefundedPayload(
                eventId: 'evt_refund_concurrent_b',
                chargeId: 'ch_refund_concurrent',
                paymentIntentId: 'pi_refund_concurrent',
                refunds: [
                    ['id' => 're_refund_concurrent', 'status' => 'succeeded', 'amount' => 42000],
                ],
                amountRefunded: 42000
            ),
        ];

        foreach ($payloads as $payload) {
            $response = $this->callChargeRefunded($payload);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getData(true)['handled']);
        }

        $booking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $booking->status);
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_refund_concurrent')->count());
        Event::assertDispatchedTimes(BookingStatusChanged::class, 1);
    }

    public function test_refund_replay_idempotency_survives_unavailable_redis_cache(): void
    {
        config([
            'cache.default' => 'redis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,
            'database.redis.cache.host' => '127.0.0.1',
            'database.redis.cache.port' => 1,
        ]);

        $booking = $this->refundPendingBooking('pi_refund_redis_down');
        $payload = $this->chargeRefundedPayload(
            eventId: 'evt_refund_redis_down',
            chargeId: 'ch_refund_redis_down',
            paymentIntentId: 'pi_refund_redis_down',
            refunds: [
                ['id' => 're_refund_redis_down', 'status' => 'succeeded', 'amount' => 25000],
            ],
            amountRefunded: 25000
        );

        $first = $this->callChargeRefunded($payload);
        $second = $this->callChargeRefunded($payload);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
        $this->assertSame(1, StripeRefundEvent::where('stripe_refund_id', 're_refund_redis_down')->count());
    }

    public function test_single_refund_object_is_persisted_as_the_idempotency_key(): void
    {
        $booking = $this->refundPendingBooking('pi_refund_single');
        $payload = $this->chargeRefundedPayload(
            eventId: 'evt_refund_single',
            chargeId: 'ch_refund_single',
            paymentIntentId: 'pi_refund_single',
            refunds: [
                ['id' => 're_refund_single', 'status' => 'succeeded', 'amount' => 12000],
            ],
            amountRefunded: 12000
        );

        $response = $this->callChargeRefunded($payload);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_refund_single',
            'stripe_event_id' => 'evt_refund_single',
            'booking_id' => $booking->id,
            'amount_refunded' => 12000,
            'currency' => 'vnd',
        ]);
    }

    public function test_multiple_refund_objects_use_latest_refund_id_and_charge_total(): void
    {
        $booking = $this->refundPendingBooking('pi_refund_multiple');
        $payload = $this->chargeRefundedPayload(
            eventId: 'evt_refund_multiple',
            chargeId: 'ch_refund_multiple',
            paymentIntentId: 'pi_refund_multiple',
            refunds: [
                ['id' => 're_refund_latest', 'status' => 'succeeded', 'amount' => 6000],
                ['id' => 're_refund_previous', 'status' => 'succeeded', 'amount' => 4000],
            ],
            amountRefunded: 10000
        );

        $response = $this->callChargeRefunded($payload);

        $this->assertSame(200, $response->getStatusCode());
        $booking->refresh();

        $this->assertSame('re_refund_latest', $booking->refund_id);
        $this->assertSame(10000, $booking->refund_amount);
        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_refund_latest',
            'booking_id' => $booking->id,
            'amount_refunded' => 10000,
        ]);
        $this->assertDatabaseMissing('stripe_refund_events', [
            'stripe_refund_id' => 're_refund_previous',
        ]);
    }

    public function test_stripe_refund_events_has_unique_refund_id_index(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL index metadata assertion');
        }

        $index = collect(DB::select(
            "select indexname, indexdef from pg_indexes where schemaname = current_schema() and tablename = 'stripe_refund_events' and indexname = 'idx_stripe_refund_events_refund_id'"
        ))->first();

        $this->assertNotNull($index, 'Expected idx_stripe_refund_events_refund_id to exist');
        $this->assertStringContainsString('UNIQUE', strtoupper($index->indexdef));
        $this->assertStringContainsString('stripe_refund_id', $index->indexdef);
    }

    private function refundPendingBooking(string $paymentIntentId): Booking
    {
        return Booking::factory()
            ->refundPending()
            ->create([
                'payment_intent_id' => $paymentIntentId,
                'amount' => 50000,
            ]);
    }

    /**
     * @param  array<int, array{id: string, status: string, amount: int}>  $refunds
     */
    private function chargeRefundedPayload(
        string $eventId,
        string $chargeId,
        string $paymentIntentId,
        array $refunds,
        int $amountRefunded,
        string $currency = 'vnd'
    ): array {
        return [
            'id' => $eventId,
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => $chargeId,
                    'payment_intent' => $paymentIntentId,
                    'amount_refunded' => $amountRefunded,
                    'currency' => $currency,
                    'refunds' => [
                        'data' => $refunds,
                    ],
                ],
            ],
        ];
    }

    private function callChargeRefunded(array $payload): JsonResponse
    {
        $controller = new StripeWebhookController;
        $reflection = new \ReflectionMethod($controller, 'handleChargeRefunded');

        return $reflection->invoke($controller, $payload);
    }
}
