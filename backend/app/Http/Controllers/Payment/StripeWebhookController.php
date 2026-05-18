<?php

namespace App\Http\Controllers\Payment;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\StripeRefundEvent;
use App\Models\StripeWebhookEvent;
use App\Services\Payment\PaymentIntentApplyOutcome;
use App\Services\Payment\StripePaymentIntentSucceededHandler;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

/**
 * Stripe webhook handler — extends Cashier's built-in controller.
 *
 * Cashier handles signature verification automatically via
 * the STRIPE_WEBHOOK_SECRET env var.
 *
 * To add custom event handling, create methods named:
 *   handle{EventType}(array $payload): Response
 * where EventType is the Stripe event in StudlyCase.
 * e.g., handlePaymentIntentSucceeded for payment_intent.succeeded
 */
class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle payment_intent.succeeded webhook.
     *
     * Confirms the associated booking when payment succeeds. Idempotent: if
     * the booking is already confirmed, no-op. The business effect is
     * delegated to StripePaymentIntentSucceededHandler so that this HTTP
     * path and the webhook:reconcile-stuck-events reaper share a single
     * audited entry point into the booking state machine.
     */
    protected function handlePaymentIntentSucceeded(array $payload): JsonResponse
    {
        $stripeEventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'payment_intent.succeeded';
        $paymentIntentId = $payload['data']['object']['id'] ?? null;

        if (! $stripeEventId || ! $paymentIntentId) {
            Log::warning('Stripe webhook: payment_intent.succeeded missing payment_intent_id');

            return response()->json(['handled' => false], 400);
        }

        // BL-3 idempotency: INSERT-first against the stripe_event_id UNIQUE
        // constraint. A SELECT-FOR-UPDATE-then-INSERT pattern would race
        // (two readers both find "no row" and both run side effects before
        // either INSERT lands); the constraint itself is the linearization
        // point. Duplicate delivery throws here and returns 200 immediately.
        // Locked in by tests/Feature/Payment/StripeWebhookIdempotencyTest.php.
        try {
            $webhookEvent = DB::transaction(fn () => StripeWebhookEvent::create([
                'stripe_event_id' => $stripeEventId,
                'type' => $eventType,
                'status' => 'processing',
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['handled' => true], 200);
        }

        Log::info('Stripe webhook: payment_intent.succeeded', [
            'payment_intent_id' => $paymentIntentId,
            'stripe_event_id' => $stripeEventId,
        ]);

        try {
            $outcome = app(StripePaymentIntentSucceededHandler::class)
                ->applyToBooking($paymentIntentId);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: failed to confirm booking', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            $webhookEvent->markFailed($e);

            return response()->json(['handled' => false], 500);
        }

        match ($outcome) {
            PaymentIntentApplyOutcome::Confirmed => Log::info('Stripe webhook: booking confirmed via payment', [
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::AlreadyConfirmed => Log::info('Stripe webhook: booking already confirmed', [
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::BookingNotFound => Log::warning('Stripe webhook: no booking found for payment_intent', [
                'payment_intent_id' => $paymentIntentId,
            ]),
            PaymentIntentApplyOutcome::InvalidState => Log::info('Stripe webhook: booking in non-pending state', [
                'payment_intent_id' => $paymentIntentId,
            ]),
        };

        // Preserves the pre-extraction HTTP behavior: every non-throwing
        // outcome (including AlreadyConfirmed and InvalidState) returns 200 so
        // Stripe stops retrying. The reaper applies a stricter rule for
        // InvalidState because its scope is operational health, not HTTP
        // ack semantics.
        $webhookEvent->markProcessed();

        return response()->json(['handled' => true]);
    }

    /**
     * Handle charge.refunded webhook.
     *
     * Updates booking refund status when Stripe processes a refund.
     * Idempotent: stripe_refund_events.stripe_refund_id is the durable replay guard.
     */
    protected function handleChargeRefunded(array $payload): JsonResponse
    {
        $stripeEventId = $payload['id'] ?? null;
        $chargeObject = $payload['data']['object'] ?? [];
        $chargeId = $chargeObject['id'] ?? null;
        $paymentIntentId = $chargeObject['payment_intent'] ?? null;

        if (! $stripeEventId || ! $paymentIntentId) {
            Log::warning('Stripe webhook: charge.refunded missing identifiers', [
                'stripe_event_id' => $stripeEventId,
                'charge_id' => $chargeId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => false], 400);
        }

        Log::info('Stripe webhook: charge.refunded', [
            'charge_id' => $chargeId,
            'payment_intent_id' => $paymentIntentId,
        ]);

        // Extract refund details from the charge's refunds list
        $refunds = $chargeObject['refunds']['data'] ?? [];
        $latestRefund = $refunds[0] ?? null;

        if (! $latestRefund) {
            Log::warning('Stripe webhook: charge.refunded but no refund data', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => true]);
        }

        $refundId = $latestRefund['id'] ?? null;
        $refundStatus = $latestRefund['status'] ?? 'unknown';
        $refundAmount = (int) ($chargeObject['amount_refunded'] ?? $latestRefund['amount'] ?? 0);
        $currency = $chargeObject['currency'] ?? null;

        if (! $refundId || ! $currency) {
            Log::warning('Stripe webhook: charge.refunded missing refund metadata', [
                'stripe_event_id' => $stripeEventId,
                'charge_id' => $chargeId,
                'refund_id' => $refundId,
                'currency' => $currency,
            ]);

            return response()->json(['handled' => false], 400);
        }

        try {
            $result = DB::transaction(function () use (
                $paymentIntentId,
                $stripeEventId,
                $refundId,
                $refundStatus,
                $refundAmount,
                $currency
            ): array {
                $booking = Booking::where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if (! $booking) {
                    return ['status' => 'missing_booking'];
                }

                StripeRefundEvent::create([
                    'stripe_refund_id' => $refundId,
                    'stripe_event_id' => $stripeEventId,
                    'booking_id' => $booking->id,
                    'amount_refunded' => $refundAmount,
                    'currency' => strtolower((string) $currency),
                ]);

                $newStatus = $refundStatus === 'succeeded'
                    ? BookingStatus::CANCELLED
                    : BookingStatus::REFUND_FAILED;

                if ($booking->status !== $newStatus) {
                    $booking = $booking->transitionTo($newStatus);
                }

                $booking->update([
                    'refund_id' => $refundId,
                    'refund_status' => $refundStatus,
                    'refund_amount' => $refundAmount,
                    'refund_error' => $refundStatus === 'failed'
                        ? 'Refund failed on Stripe'
                        : null,
                ]);

                return [
                    'status' => 'processed',
                    'booking_id' => $booking->id,
                ];
            });

            if ($result['status'] === 'missing_booking') {
                Log::warning('Stripe webhook: no booking found for payment_intent', [
                    'payment_intent_id' => $paymentIntentId,
                ]);

                return response()->json(['handled' => true]);
            }

            Log::info('Stripe webhook: booking refund status updated', [
                'booking_id' => $result['booking_id'],
                'refund_id' => $refundId,
                'refund_status' => $refundStatus,
                'refund_amount' => $refundAmount,
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::info('Stripe webhook: refund event replay skipped', [
                'stripe_event_id' => $stripeEventId,
                'refund_id' => $refundId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: failed to update refund status', [
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => $refundId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['handled' => true]);
    }

    /**
     * Handle payment_intent.payment_failed webhook.
     *
     * Logs the failure. Booking remains in pending state for retry.
     */
    protected function handlePaymentIntentPaymentFailed(array $payload): JsonResponse
    {
        $paymentIntentId = $payload['data']['object']['id'] ?? null;
        $failureMessage = $payload['data']['object']['last_payment_error']['message'] ?? 'Unknown';

        Log::warning('Stripe webhook: payment_intent.payment_failed', [
            'payment_intent_id' => $paymentIntentId,
            'failure_message' => $failureMessage,
        ]);

        if ($paymentIntentId) {
            $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

            if ($booking && $booking->status === BookingStatus::PENDING) {
                Log::info('Stripe webhook: payment failed for pending booking', [
                    'booking_id' => $booking->id,
                ]);
            }
        }

        return response()->json(['handled' => true]);
    }

    /**
     * Handle unhandled webhook events (log only).
     */
    protected function missingMethod($parameters = [])
    {
        return response()->json(['handled' => false]);
    }
}
