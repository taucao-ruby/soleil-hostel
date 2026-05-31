<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\StripeService;
use RuntimeException;

/**
 * Shared idempotent business effect for a Stripe `payment_intent.succeeded`
 * webhook event.
 *
 * Two call sites:
 *   1. The live HTTP webhook controller (signature-verified by Cashier).
 *   2. The `webhook:reconcile-stuck-events` artisan reaper (replays events
 *      whose ledger row got stuck in 'processing' because the worker died
 *      between INSERT and markProcessed/markFailed).
 *
 * Both call sites MUST go through this single entry point so the booking
 * state machine cannot be reached two different ways and quietly diverge.
 * The reaper performs additional Stripe-side verification (status, amount,
 * currency match) before invoking this handler — see
 * ReconcileStuckStripeWebhookEvents.
 */
final class StripePaymentIntentSucceededHandler
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly StripeService $stripeService,
    ) {}

    /**
     * Apply the confirmation effect for a PaymentIntent id.
     *
     * Idempotent under concurrent invocation: the booking row is acquired
     * with lockForUpdate before any state inspection, so two callers racing
     * to confirm the same booking serialize and the second observes
     * AlreadyConfirmed instead of running confirmBooking twice.
     *
     * Returns the outcome rather than throwing for control flow because both
     * call sites need to map the outcome to different reporting (HTTP
     * response code vs. webhook-event status). Genuine downstream errors
     * (BookingService::confirmBooking throwing) still propagate.
     */
    public function applyToBooking(mixed $paymentIntent): PaymentIntentApplyOutcome
    {
        $paymentIntentId = $this->paymentIntentId($paymentIntent);

        $booking = Booking::query()
            ->where('payment_intent_id', $paymentIntentId)
            ->first();

        if ($booking === null) {
            return PaymentIntentApplyOutcome::BookingNotFound;
        }

        if ((string) data_get($paymentIntent, 'status') !== 'succeeded') {
            return PaymentIntentApplyOutcome::InvalidState;
        }

        if (! $booking->payment_policy->requiresStripePaymentIntent()) {
            return PaymentIntentApplyOutcome::InvalidState;
        }

        $this->stripeService->assertPaymentIntentMatchesBooking($paymentIntent, $booking);
        $this->assertPaymentIntentUserMatchesBooking($paymentIntent, $booking);

        if ($booking->status === BookingStatus::CONFIRMED) {
            $booking->forceFill([
                'payment_status' => PaymentStatus::PAID,
                'amount_received' => (int) data_get($paymentIntent, 'amount_received', $booking->amount),
                'amount_capturable' => (int) data_get($paymentIntent, 'amount_capturable', 0),
                'paid_at' => $booking->paid_at ?? now(),
                'payment_failed_reason' => null,
            ])->save();

            return PaymentIntentApplyOutcome::AlreadyConfirmed;
        }

        if ($booking->status !== BookingStatus::PENDING) {
            // refund_pending / cancelled / refund_failed: BookingStatus forbids
            // the transition to CONFIRMED. Surface this as InvalidState so the
            // caller can mark the webhook event failed with explicit context.
            return PaymentIntentApplyOutcome::InvalidState;
        }

        $this->bookingService->markPaidAndConfirm(
            $booking,
            (int) data_get($paymentIntent, 'amount_received', $booking->amount),
            (int) data_get($paymentIntent, 'amount_capturable', 0),
        );

        return PaymentIntentApplyOutcome::Confirmed;
    }

    private function paymentIntentId(mixed $paymentIntent): string
    {
        $paymentIntentId = data_get($paymentIntent, 'id');

        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            throw new RuntimeException('Stripe PaymentIntent payload is missing an id.');
        }

        return $paymentIntentId;
    }

    private function assertPaymentIntentUserMatchesBooking(mixed $paymentIntent, Booking $booking): void
    {
        $metadataUserId = (string) data_get($paymentIntent, 'metadata.user_id', '');

        if ($metadataUserId !== '' && $metadataUserId !== (string) $booking->user_id) {
            throw new RuntimeException("Stripe PaymentIntent user mismatch for booking #{$booking->id}.");
        }
    }
}
