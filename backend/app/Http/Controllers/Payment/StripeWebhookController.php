<?php

namespace App\Http\Controllers\Payment;

use Illuminate\Http\JsonResponse;
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
     * TODO(PAY-002): Implement booking confirmation after successful payment.
     */
    protected function handlePaymentIntentSucceeded(array $payload): JsonResponse
    {
        Log::info('Stripe webhook: payment_intent.succeeded', [
            'payment_intent_id' => $payload['data']['object']['id'] ?? null,
        ]);

        return response()->json(['handled' => true]);
    }

    /**
     * Handle charge.refunded webhook.
     *
     * TODO(PAY-003): Implement refund status update on booking.
     */
    protected function handleChargeRefunded(array $payload): JsonResponse
    {
        Log::info('Stripe webhook: charge.refunded', [
            'charge_id' => $payload['data']['object']['id'] ?? null,
        ]);

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
