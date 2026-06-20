<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Outcome of applying a MoMo IPN (server→server payment notification) to the
 * local booking. Shared, audited contract between the MoMo IPN controller and
 * the IPN handler over what "the business effect happened" means — the MoMo
 * analogue of PaymentIntentApplyOutcome.
 */
enum MoMoIpnOutcome: string
{
    /** Booking transitioned PENDING → CONFIRMED. */
    case Confirmed = 'confirmed';

    /** Booking was already CONFIRMED for this order/transId; idempotent no-op. */
    case AlreadyConfirmed = 'already_confirmed';

    /** No local booking row maps to this MoMo orderId. */
    case BookingNotFound = 'booking_not_found';

    /**
     * Booking exists but is not PENDING, so auto-confirmation is forbidden
     * (e.g. cancelled, refund_pending). Confirming would silently violate the
     * state machine, so the caller must surface this for review.
     */
    case InvalidState = 'invalid_state';

    /**
     * Security guard: IPN reports success but the notified amount/currency does
     * not match the booking's expected amount. The booking is NOT confirmed and
     * the event must be surfaced — this is the anti-tamper check against a forged
     * or replayed under/over-payment notification.
     */
    case AmountMismatch = 'amount_mismatch';
}
