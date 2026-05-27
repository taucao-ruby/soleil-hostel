<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Definitive (non-transient) result of attempting to cancel a Stripe
 * PaymentIntent for an expired booking (PAY-03).
 *
 * Transient failures (timeouts, connection errors, rate limits, 5xx) are NOT
 * represented here — they surface as thrown Stripe exceptions so the outbox
 * worker can retry with backoff. This enum captures only outcomes that are
 * safe to act on immediately.
 */
enum PaymentIntentCancellationOutcome
{
    /** We canceled the PaymentIntent on this call. */
    case Canceled;

    /** Stripe reports it was already canceled (idempotent success). */
    case AlreadyCanceled;

    /**
     * The PaymentIntent is in a terminal/non-cancellable state (e.g. succeeded
     * / captured). The booking stays terminal locally; this needs operator
     * review because money may have moved.
     */
    case NotCancellable;
}
