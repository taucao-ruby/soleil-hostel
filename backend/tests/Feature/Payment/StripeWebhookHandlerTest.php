<?php

namespace Tests\Feature\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentPolicy;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Payment\StripeWebhookController;
use App\Models\Booking;
use App\Models\StripeRefundEvent;
use App\Models\StripeWebhookEvent;
use App\Services\Payment\StripeRefundEventRecorder;
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

    // ========== charge.refunded — SH-04 hardening ==========

    /**
     * SH-04: charge.refunded now follows the same INSERT-first stripe_webhook_events
     * claim as handlePaymentIntentSucceeded — it records the event, writes the refund
     * ledger row, transitions the booking, and marks the event processed.
     */
    public function test_charge_refunded_claims_webhook_event_and_marks_it_processed(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_refund_claim',
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = $this->makeChargeRefundedPayload(
            'ch_claim',
            'pi_refund_claim',
            're_claim',
            'succeeded',
            50000
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        // Insert-first webhook-event row, marked processed only after success.
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_re_claim',
            'type' => 'charge.refunded',
            'status' => 'processed',
        ]);
        // Refund ledger row written.
        $this->assertDatabaseHas('stripe_refund_events', [
            'stripe_refund_id' => 're_claim',
            'booking_id' => $booking->id,
            'amount_refunded' => 50000,
        ]);

        $booking->refresh();
        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);
    }

    /**
     * SH-04: duplicate delivery of the same charge.refunded event is deduped at the
     * stripe_webhook_events.stripe_event_id claim — exactly one event row, one ledger
     * row, one transition.
     */
    public function test_charge_refunded_duplicate_event_is_idempotent(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_refund_dup',
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = $this->makeChargeRefundedPayload(
            'ch_dup',
            'pi_refund_dup',
            're_dup',
            'succeeded',
            50000
        );

        $first = $this->callHandler('handleChargeRefunded', $payload);
        $second = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(200, $first->getStatusCode());
        $this->assertEquals(200, $second->getStatusCode());

        $this->assertEquals(1, StripeWebhookEvent::where('stripe_event_id', 'evt_re_dup')->count());
        $this->assertEquals(1, StripeRefundEvent::where('stripe_refund_id', 're_dup')->count());

        $booking->refresh();
        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);
    }

    /**
     * SH-04: an unexpected (non-unique) processing failure must NOT be swallowed
     * into a 200. The handler marks the event failed and returns 500 so Stripe
     * retries, and the booking is left untouched (transaction rolled back).
     */
    public function test_charge_refunded_returns_500_and_marks_event_failed_on_processing_error(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::REFUND_PENDING,
            'payment_intent_id' => 'pi_refund_boom',
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        // Force an unexpected failure during the ledger write. The handler
        // resolves the recorder via app(StripeRefundEventRecorder::class); the real
        // recorder is final, so bind a duck-typed stand-in rather than a Mockery
        // subclass.
        $this->app->instance(StripeRefundEventRecorder::class, new class
        {
            public function record(mixed ...$args): never
            {
                throw new \RuntimeException('simulated ledger write failure');
            }
        });

        $payload = $this->makeChargeRefundedPayload(
            'ch_boom',
            'pi_refund_boom',
            're_boom',
            'succeeded',
            50000
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['handled']);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_re_boom',
            'status' => 'failed',
        ]);

        // Booking untouched: the business transaction rolled back.
        $booking->refresh();
        $this->assertEquals(BookingStatus::REFUND_PENDING, $booking->status);
        $this->assertNull($booking->refund_id);
        $this->assertDatabaseMissing('stripe_refund_events', [
            'stripe_refund_id' => 're_boom',
        ]);
    }

    /**
     * SH-04: a non-succeeded refund for a still-CONFIRMED booking would map to the
     * illegal CONFIRMED -> REFUND_FAILED transition. The handler must guard it: ack
     * 200 (permanent state, no retry storm), record the invalid state on the event,
     * and leave the booking unmutated with no ledger row.
     */
    public function test_charge_refunded_guards_illegal_confirmed_to_refund_failed_transition(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::CONFIRMED,
            'payment_intent_id' => 'pi_confirmed_badrefund',
            'payment_currency' => 'vnd',
            'amount' => 50000,
        ]);

        $payload = $this->makeChargeRefundedPayload(
            'ch_badrefund',
            'pi_confirmed_badrefund',
            're_badrefund',
            'failed',
            50000
        );

        $response = $this->callHandler('handleChargeRefunded', $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['handled']);

        // Booking is NOT mutated into REFUND_FAILED.
        $booking->refresh();
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
        $this->assertNull($booking->refund_id);
        $this->assertNull($booking->refund_status);

        // Invalid state recorded on the webhook event, not the booking; no ledger
        // row for the ignored refund.
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_re_badrefund',
            'status' => 'failed',
        ]);
        $this->assertDatabaseMissing('stripe_refund_events', [
            'stripe_refund_id' => 're_badrefund',
        ]);
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
