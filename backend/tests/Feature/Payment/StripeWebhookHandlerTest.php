<?php

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Http\Controllers\Payment\StripeWebhookController;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Stripe webhook handler logic.
 *
 * These tests call the handler methods directly to verify business logic,
 * bypassing Cashier's signature verification (which is tested in StripeWebhookTest).
 */
class StripeWebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    private StripeWebhookController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new StripeWebhookController;
    }

    // ========== payment_intent.succeeded ==========

    public function test_payment_intent_succeeded_confirms_pending_booking(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_test_123',
        ]);

        $payload = $this->makePaymentIntentPayload('pi_test_123');

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        $booking->refresh();
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
    }

    public function test_payment_intent_succeeded_is_idempotent_for_confirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::CONFIRMED,
            'payment_intent_id' => 'pi_test_456',
        ]);

        $payload = $this->makePaymentIntentPayload('pi_test_456');

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        $booking->refresh();
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
    }

    public function test_payment_intent_succeeded_handles_missing_booking(): void
    {
        $payload = $this->makePaymentIntentPayload('pi_nonexistent');

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);
    }

    public function test_payment_intent_succeeded_rejects_missing_id(): void
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => []],
        ];

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['handled']);
    }

    // ========== charge.refunded ==========

    public function test_charge_refunded_updates_booking_refund_status(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_refund_test',
            'amount' => 50000,
        ]);

        $payload = $this->makeChargeRefundedPayload(
            'ch_test_123',
            'pi_refund_test',
            'refund_succeeded',
            'succeeded',
            50000
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        $booking->refresh();
        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);
        $this->assertEquals('refund_succeeded', $booking->refund_id);
        $this->assertEquals('succeeded', $booking->refund_status);
        $this->assertEquals(50000, $booking->refund_amount);
    }

    public function test_charge_refunded_handles_failed_refund(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_refund_fail',
            'amount' => 30000,
        ]);

        $payload = $this->makeChargeRefundedPayload(
            'ch_test_456',
            'pi_refund_fail',
            'refund_failed',
            'failed',
            30000
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        $booking->refresh();
        $this->assertEquals(BookingStatus::REFUND_FAILED, $booking->status);
        $this->assertEquals('failed', $booking->refund_status);
        $this->assertEquals('Refund failed on Stripe', $booking->refund_error);
    }

    public function test_charge_refunded_is_idempotent(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::CANCELLED,
            'payment_intent_id' => 'pi_already_refunded',
            'refund_id' => 'refund_already',
            'refund_status' => 'succeeded',
            'refund_amount' => 40000,
        ]);

        $payload = $this->makeChargeRefundedPayload(
            'ch_test_789',
            'pi_already_refunded',
            'refund_already',
            'succeeded',
            40000
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        $booking->refresh();
        // Status should remain unchanged
        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);
    }

    public function test_charge_refunded_rejects_missing_payment_intent(): void
    {
        $payload = [
            'type' => 'charge.refunded',
            'data' => ['object' => ['id' => 'ch_test']],
        ];

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_charge_refunded_handles_missing_booking(): void
    {
        $payload = $this->makeChargeRefundedPayload(
            'ch_orphan',
            'pi_orphan',
            'refund_orphan',
            'succeeded',
            10000
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);
    }

    // ========== payment_intent.payment_failed ==========

    public function test_payment_failed_logs_and_returns_ok(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_failed_test',
        ]);

        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_test',
                    'last_payment_error' => [
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ];

        $response = $this->callHandler('handlePaymentIntentPaymentFailed', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        // Booking should remain pending (customer can retry)
        $booking->refresh();
        $this->assertEquals(BookingStatus::PENDING, $booking->status);
    }

    // ========== Helper methods ==========

    /**
     * Call a protected handler method on the controller via reflection.
     */
    private function callHandler(string $method, array $payload): \Illuminate\Http\JsonResponse
    {
        $reflection = new \ReflectionMethod($this->controller, $method);

        return $reflection->invoke($this->controller, $payload);
    }

    private function makePaymentIntentPayload(string $paymentIntentId): array
    {
        return [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'status' => 'succeeded',
                    'amount' => 50000,
                    'currency' => 'vnd',
                ],
            ],
        ];
    }

    private function makeChargeRefundedPayload(
        string $chargeId,
        string $paymentIntentId,
        string $refundId,
        string $refundStatus,
        int $refundAmount
    ): array {
        return [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => $chargeId,
                    'payment_intent' => $paymentIntentId,
                    'refunds' => [
                        'data' => [
                            [
                                'id' => $refundId,
                                'status' => $refundStatus,
                                'amount' => $refundAmount,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
