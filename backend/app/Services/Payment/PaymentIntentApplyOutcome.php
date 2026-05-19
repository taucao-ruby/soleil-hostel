<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Outcome of applying a payment_intent.succeeded event to the local booking.
 *
 * Used by both the live Stripe webhook controller and the reconciliation
 * reaper so that they share a single, audited contract over what "the
 * business effect happened" means.
 */
enum PaymentIntentApplyOutcome: string
{
    /** Booking transitioned PENDING → CONFIRMED. */
    case Confirmed = 'confirmed';

    /** Booking was already CONFIRMED for this PaymentIntent; idempotent no-op. */
    case AlreadyConfirmed = 'already_confirmed';

    /** No local booking row carries this payment_intent_id. */
    case BookingNotFound = 'booking_not_found';

    /**
     * Booking exists but is in a state that forbids auto-confirmation
     * (refund_pending, cancelled, refund_failed). Auto-confirming would
     * silently violate the state machine, so the caller must surface this
     * for human review.
     */
    case InvalidState = 'invalid_state';
}
