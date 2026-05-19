<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;

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
    public function applyToBooking(string $paymentIntentId): PaymentIntentApplyOutcome
    {
        $booking = Booking::query()
            ->where('payment_intent_id', $paymentIntentId)
            ->first();

        if ($booking === null) {
            return PaymentIntentApplyOutcome::BookingNotFound;
        }

        return DB::transaction(function () use ($booking): PaymentIntentApplyOutcome {
            // Re-read under row lock; the unlocked read above is purely for
            // the not-found fast path. Two reapers racing on the same
            // booking now serialize here.
            $locked = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === BookingStatus::CONFIRMED) {
                return PaymentIntentApplyOutcome::AlreadyConfirmed;
            }

            if ($locked->status !== BookingStatus::PENDING) {
                // refund_pending / cancelled / refund_failed: BookingStatus
                // forbids the transition to CONFIRMED. Surfacing this as
                // InvalidState lets the reaper mark the webhook event failed
                // with explicit context for human review, rather than crash
                // inside transitionTo.
                return PaymentIntentApplyOutcome::InvalidState;
            }

            // Delegates to the existing confirmation path: status transition
            // under lockForUpdate, operational Stay creation, cache
            // invalidation, and the recipient-throttled confirmation email
            // (queued afterCommit). See BookingService::confirmBooking.
            $this->bookingService->confirmBooking($locked);

            return PaymentIntentApplyOutcome::Confirmed;
        });
    }
}
