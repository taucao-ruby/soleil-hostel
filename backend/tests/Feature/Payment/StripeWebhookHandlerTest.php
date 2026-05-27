<?php

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
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
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = $this->makePaymentIntentPayload('pi_test_123', $booking);

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        $booking->refresh();
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
        $this->assertEquals(PaymentStatus::PAID, $booking->payment_status);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_payment_intent_succeeded_is_idempotent_for_confirmed_booking(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::CONFIRMED,
            'payment_intent_id' => 'pi_test_456',
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::PAID,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = $this->makePaymentIntentPayload('pi_test_456', $booking);

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        $booking->refresh();
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
        $this->assertEquals(PaymentStatus::PAID, $booking->payment_status);
    }

    public function test_payment_intent_succeeded_rejects_amount_mismatch(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_amount_mismatch',
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = $this->makePaymentIntentPayload('pi_amount_mismatch', $booking);
        $payload['data']['object']['amount'] = 40000;

        $response = $this->callHandler('handlePaymentIntentSucceeded', $payload);

        $this->assertEquals(500, $response->getStatusCode());

        $booking->refresh();
        $this->assertEquals(BookingStatus::PENDING, $booking->status);
        $this->assertEquals(PaymentStatus::REQUIRES_PAYMENT_METHOD, $booking->payment_status);
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
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = [
            'id' => 'evt_pi_failed_test',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_test',
                    'status' => 'requires_payment_method',
                    'amount' => 50000,
                    'currency' => 'vnd',
                    'metadata' => [
                        'booking_id' => (string) $booking->id,
                        'user_id' => (string) $booking->user_id,
                    ],
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
        $this->assertEquals(PaymentStatus::FAILED, $booking->payment_status);
    }

    public function test_payment_intent_canceled_releases_pending_payment_hold(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_canceled_test',
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_PAYMENT_METHOD,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = $this->makePaymentIntentPayload('pi_canceled_test', $booking);
        $payload['id'] = 'evt_pi_canceled_test';
        $payload['type'] = 'payment_intent.canceled';
        $payload['data']['object']['status'] = 'canceled';

        $response = $this->callHandler('handlePaymentIntentCanceled', $payload);

        $this->assertEquals(200, $response->getStatusCode());

        $booking->refresh();
        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);
        $this->assertEquals(PaymentStatus::CANCELLED, $booking->payment_status);
        $this->assertEquals('payment_intent_canceled', $booking->cancellation_reason);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_amount_capturable_updated_authorizes_only_manual_capture_bookings(): void
    {
        $manualBooking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_manual_auth',
            'payment_policy' => PaymentPolicy::AUTHORIZE_THEN_CAPTURE,
            'payment_status' => PaymentStatus::REQUIRES_ACTION,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);
        $prepaidBooking = Booking::factory()->create([
            'status' => BookingStatus::PENDING,
            'payment_intent_id' => 'pi_prepaid_auth_ignored',
            'payment_policy' => PaymentPolicy::PREPAID,
            'payment_status' => PaymentStatus::REQUIRES_ACTION,
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $manualPayload = $this->makePaymentIntentPayload('pi_manual_auth', $manualBooking);
        $manualPayload['id'] = 'evt_pi_manual_auth';
        $manualPayload['type'] = 'payment_intent.amount_capturable_updated';
        $manualPayload['data']['object']['status'] = 'requires_capture';
        $manualPayload['data']['object']['amount_capturable'] = 50000;

        $prepaidPayload = $this->makePaymentIntentPayload('pi_prepaid_auth_ignored', $prepaidBooking);
        $prepaidPayload['id'] = 'evt_pi_prepaid_auth_ignored';
        $prepaidPayload['type'] = 'payment_intent.amount_capturable_updated';
        $prepaidPayload['data']['object']['status'] = 'requires_capture';
        $prepaidPayload['data']['object']['amount_capturable'] = 50000;

        $manualResponse = $this->callHandler('handlePaymentIntentAmountCapturableUpdated', $manualPayload);
        $prepaidResponse = $this->callHandler('handlePaymentIntentAmountCapturableUpdated', $prepaidPayload);

        $this->assertEquals(200, $manualResponse->getStatusCode());
        $this->assertEquals(200, $prepaidResponse->getStatusCode());

        $manualBooking->refresh();
        $prepaidBooking->refresh();

        $this->assertEquals(PaymentStatus::AUTHORIZED, $manualBooking->payment_status);
        $this->assertEquals(50000, $manualBooking->amount_capturable);
        $this->assertNotNull($manualBooking->authorized_at);
        $this->assertEquals(PaymentStatus::REQUIRES_ACTION, $prepaidBooking->payment_status);
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

    private function makePaymentIntentPayload(string $paymentIntentId, ?Booking $booking = null): array
    {
        $bookingId = $booking?->id ?? 999999;
        $userId = $booking?->user_id ?? 999999;

        return [
            'id' => 'evt_'.$paymentIntentId,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'status' => 'succeeded',
                    'amount' => 50000,
                    'currency' => 'vnd',
                    'amount_capturable' => 0,
                    'amount_received' => 50000,
                    'metadata' => [
                        'booking_id' => (string) $bookingId,
                        'user_id' => (string) $userId,
                    ],
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
            'id' => 'evt_'.$refundId,
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => $chargeId,
                    'payment_intent' => $paymentIntentId,
                    'amount_refunded' => $refundAmount,
                    'currency' => 'vnd',
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
