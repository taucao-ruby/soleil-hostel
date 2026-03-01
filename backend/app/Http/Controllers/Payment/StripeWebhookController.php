<?php

namespace App\Http\Controllers\Payment;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
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
     * Confirms the associated booking when payment succeeds.
     * Idempotent: if booking is already confirmed, no-op.
     */
    protected function handlePaymentIntentSucceeded(array $payload): JsonResponse
    {
        $paymentIntentId = $payload['data']['object']['id'] ?? null;

        if (! $paymentIntentId) {
            Log::warning('Stripe webhook: payment_intent.succeeded missing payment_intent_id');

            return response()->json(['handled' => false], 400);
        }

        Log::info('Stripe webhook: payment_intent.succeeded', [
            'payment_intent_id' => $paymentIntentId,
        ]);

        $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

        if (! $booking) {
            Log::warning('Stripe webhook: no booking found for payment_intent', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => true]);
        }

        // Idempotent: skip if already confirmed or in a terminal state
        if ($booking->status !== BookingStatus::PENDING) {
            Log::info('Stripe webhook: booking already processed', [
                'booking_id' => $booking->id,
                'status' => $booking->status->value,
            ]);

            return response()->json(['handled' => true]);
        }

        try {
            app(BookingService::class)->confirmBooking($booking);

            Log::info('Stripe webhook: booking confirmed via payment', [
                'booking_id' => $booking->id,
                'payment_intent_id' => $paymentIntentId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: failed to confirm booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['handled' => true]);
    }

    /**
     * Handle charge.refunded webhook.
     *
     * Updates booking refund status when Stripe processes a refund.
     * Idempotent: if booking already has this refund_id recorded, no-op.
     */
    protected function handleChargeRefunded(array $payload): JsonResponse
    {
        $chargeObject = $payload['data']['object'] ?? [];
        $chargeId = $chargeObject['id'] ?? null;
        $paymentIntentId = $chargeObject['payment_intent'] ?? null;

        if (! $paymentIntentId) {
            Log::warning('Stripe webhook: charge.refunded missing payment_intent', [
                'charge_id' => $chargeId,
            ]);

            return response()->json(['handled' => false], 400);
        }

        Log::info('Stripe webhook: charge.refunded', [
            'charge_id' => $chargeId,
            'payment_intent_id' => $paymentIntentId,
        ]);

        $booking = Booking::where('payment_intent_id', $paymentIntentId)->first();

        if (! $booking) {
            Log::warning('Stripe webhook: no booking found for payment_intent', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return response()->json(['handled' => true]);
        }

        // Extract refund details from the charge's refunds list
        $refunds = $chargeObject['refunds']['data'] ?? [];
        $latestRefund = $refunds[0] ?? null;

        if (! $latestRefund) {
            Log::warning('Stripe webhook: charge.refunded but no refund data', [
                'booking_id' => $booking->id,
            ]);

            return response()->json(['handled' => true]);
        }

        $refundId = $latestRefund['id'] ?? null;
        $refundStatus = $latestRefund['status'] ?? 'unknown';
        $refundAmount = $latestRefund['amount'] ?? 0;

        // Idempotent: skip if this refund is already recorded
        if ($booking->refund_id === $refundId && $booking->refund_status === 'succeeded') {
            Log::info('Stripe webhook: refund already recorded', [
                'booking_id' => $booking->id,
                'refund_id' => $refundId,
            ]);

            return response()->json(['handled' => true]);
        }

        try {
            DB::transaction(function () use ($booking, $refundId, $refundStatus, $refundAmount) {
                $newStatus = $refundStatus === 'succeeded'
                    ? BookingStatus::CANCELLED
                    : BookingStatus::REFUND_FAILED;

                $booking->update([
                    'status' => $newStatus,
                    'refund_id' => $refundId,
                    'refund_status' => $refundStatus,
                    'refund_amount' => $refundAmount,
                    'refund_error' => $refundStatus === 'failed'
                        ? 'Refund failed on Stripe'
                        : null,
                ]);
            });

            Log::info('Stripe webhook: booking refund status updated', [
                'booking_id' => $booking->id,
                'refund_id' => $refundId,
                'refund_status' => $refundStatus,
                'refund_amount' => $refundAmount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: failed to update refund status', [
                'booking_id' => $booking->id,
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
