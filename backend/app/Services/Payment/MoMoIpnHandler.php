<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\MoMoService;

/**
 * Idempotent business effect for a verified, deduped MoMo IPN (server→server
 * payment notification).
 *
 * Called by the MoMo IPN controller AFTER it has (1) verified the HMAC signature
 * and (2) won the INSERT-first (order_id, trans_id) dedup race. This handler does
 * NO signature work, but it still independently enforces the amount/currency guard
 * as defense in depth — a forged or replayed notification that somehow reaches here
 * must never confirm a booking it does not match.
 *
 * Like the Stripe handler, it returns an outcome enum rather than throwing for
 * control flow, so the controller can map each outcome to the right ledger status
 * and HTTP ack. Confirmation goes ONLY through BookingService::markPaidAndConfirm —
 * the single audited entry into the booking state machine — so the MoMo and Stripe
 * paths cannot reach booking state two different ways and quietly diverge. Genuine
 * downstream errors (markPaidAndConfirm throwing) still propagate.
 */
final class MoMoIpnHandler
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly MoMoService $momoService,
    ) {}

    public function applyToBooking(array $payload): MoMoIpnOutcome
    {
        $bookingId = $this->momoService->bookingIdFromOrderId((string) data_get($payload, 'orderId', ''));

        if ($bookingId === null) {
            return MoMoIpnOutcome::BookingNotFound;
        }

        $booking = Booking::query()->whereKey($bookingId)->first();

        if ($booking === null) {
            return MoMoIpnOutcome::BookingNotFound;
        }

        // C2: a failure/cancel IPN (resultCode != 0) is acknowledged by the
        // controller but never confirms — mirror of Stripe's status != 'succeeded'.
        if ((int) data_get($payload, 'resultCode') !== 0) {
            return MoMoIpnOutcome::InvalidState;
        }

        if (! $booking->payment_policy->requiresStripePaymentIntent()) {
            return MoMoIpnOutcome::InvalidState;
        }

        // C1: defense-in-depth amount/currency guard. Unlike Stripe's
        // assertPaymentIntentMatchesBooking (which throws), this RETURNS
        // AmountMismatch and does NOT confirm, so the controller can record the
        // event and ack without a 500.
        $expectedAmount = (int) $booking->amount;
        $currency = (string) $booking->payment_currency;
        $expectedCurrency = $currency !== ''
            ? strtolower($currency)
            : strtolower((string) config('cashier.currency', 'vnd'));
        $notifiedAmount = (int) data_get($payload, 'amount');

        // MoMo IPN carries no currency field (it is a VND-only channel), so the
        // currency half asserts the booking expects the VND that MoMo settles.
        if ($notifiedAmount !== $expectedAmount || $expectedCurrency !== 'vnd') {
            return MoMoIpnOutcome::AmountMismatch;
        }

        // Idempotent top-up for an already-CONFIRMED booking (e.g. a replayed IPN
        // whose amount still matches) — mirror the Stripe handler's CONFIRMED branch.
        if ($booking->status === BookingStatus::CONFIRMED) {
            $booking->forceFill([
                'payment_status' => PaymentStatus::PAID,
                'amount_received' => $expectedAmount,
                'amount_capturable' => 0,
                'paid_at' => $booking->paid_at ?? now(),
                'payment_failed_reason' => null,
            ])->save();

            return MoMoIpnOutcome::AlreadyConfirmed;
        }

        if ($booking->status !== BookingStatus::PENDING) {
            // refund_pending / cancelled / refund_failed: BookingStatus forbids the
            // transition to CONFIRMED. Surface as InvalidState so the controller can
            // mark the webhook event failed with explicit context.
            return MoMoIpnOutcome::InvalidState;
        }

        // Single audited entry point. It opens its own transaction + lockForUpdate
        // and re-checks CONFIRMED under the lock (the real linearization point), so
        // we deliberately do NOT wrap this in an outer transaction. 0 capturable:
        // MoMo captureWallet is a full capture, never an authorize-then-capture hold.
        $this->bookingService->markPaidAndConfirm($booking, $expectedAmount, 0);

        return MoMoIpnOutcome::Confirmed;
    }
}
