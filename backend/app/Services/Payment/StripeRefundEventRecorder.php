<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\StripeRefundEvent;

/**
 * Single audited entry point for writing the authoritative refund ledger
 * (`stripe_refund_events`).
 *
 * Three call sites converge here so the ledger-write contract lives in one
 * place: the live `charge.refunded` webhook, ReconcileRefundsJob's discovery
 * paths, and ReconcileRefundsJob's reconciler-issued refunds. See
 * docs/agents/ARCHITECTURE_FACTS.md §bookings.refund_id semantics — this table
 * is the authoritative history ledger; `bookings.refund_id` is only a
 * latest-pointer.
 *
 * Idempotency / race contract (PAY-04):
 *   - The row's identity is `stripe_refund_id` (the table's only UNIQUE
 *     constraint). One row per Stripe refund, regardless of how many events or
 *     reconciler passes observe it.
 *   - record() performs a plain INSERT and lets the UNIQUE constraint be the
 *     linearization point. The first writer (webhook OR reconciler) wins; a
 *     racing/duplicate writer's INSERT throws UniqueConstraintViolationException.
 *   - Callers MUST invoke record() INSIDE the same DB::transaction that writes
 *     the booking refund projection, and catch UniqueConstraintViolationException
 *     OUTSIDE that transaction. A ledger-write failure then rolls the projection
 *     back (savepoint under a surrounding tx), so the booking can never drift
 *     ahead of the ledger. This mirrors StripeWebhookController::handleChargeRefunded.
 *
 * Audit / safety:
 *   - booking_id is always taken from the trusted DB Booking row passed by the
 *     caller, never from Stripe metadata.
 *   - Only the schema-backed business columns are written; no raw Stripe object
 *     or customer PII is persisted.
 *   - `stripe_event_id` carries the originating event identity. The webhook
 *     passes the real `evt_…`; non-webhook discoveries pass a deterministic
 *     synthetic key (see reconcileEventKey/reconcileIssueEventKey) that also
 *     records the source. `stripe_event_id` has no UNIQUE constraint and is read
 *     by nothing, so synthetic values are safe.
 */
final class StripeRefundEventRecorder
{
    /**
     * Upsert-by-replay-guard the ledger row for a single Stripe refund.
     *
     * @param  Booking  $booking  Trusted DB row the refund belongs to (source of booking_id).
     * @param  string  $refundId  Stripe refund id (`re_…`) — the ledger identity.
     * @param  int  $amountRefunded  Refund amount in minor units, as reported by Stripe.
     * @param  string  $currency  ISO currency; stored lower-cased to match the webhook.
     * @param  string  $eventKey  Originating event identity (`evt_…` for webhook, or a synthetic key).
     *
     * @throws \Illuminate\Database\UniqueConstraintViolationException when the refund is already recorded.
     */
    public function record(
        Booking $booking,
        string $refundId,
        int $amountRefunded,
        string $currency,
        string $eventKey,
    ): StripeRefundEvent {
        return StripeRefundEvent::create([
            'stripe_refund_id' => $refundId,
            'stripe_event_id' => $eventKey,
            'booking_id' => $booking->id,
            'amount_refunded' => $amountRefunded,
            'currency' => strtolower($currency),
        ]);
    }

    /**
     * Deterministic synthetic event key for a refund recorded synchronously by
     * CancellationService::finalizeCancellation on the cancel-with-refund happy
     * path (no originating Stripe webhook event exists yet). SH-03 / F-74.
     */
    public static function cancellationEventKey(string $refundId): string
    {
        return 'cancellation:refund:'.$refundId;
    }

    /**
     * Deterministic synthetic event key for a refund discovered by reconciliation
     * (no originating Stripe webhook event exists).
     */
    public static function reconcileEventKey(string $refundId): string
    {
        return 'reconcile:refund:'.$refundId;
    }

    /**
     * Deterministic synthetic event key for a refund issued by the reconciler's
     * retry path.
     */
    public static function reconcileIssueEventKey(string $refundId): string
    {
        return 'reconcile_issue:refund:'.$refundId;
    }
}
