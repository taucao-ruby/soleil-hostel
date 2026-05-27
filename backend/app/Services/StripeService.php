<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Services\Payment\PaymentIntentCancellationOutcome;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\StripeClient;

class StripeService
{
    /**
     * PaymentIntent statuses from which a cancel() is valid and safe. Notably
     * excludes 'succeeded' (money moved — not cancellable) and 'processing'
     * (settling toward success; leave it for the webhook/reconciliation path).
     * 'requires_capture' IS included: that is an authorized-but-uncaptured hold,
     * and cancelling it releases the customer's funds — the main reason expiry
     * must cancel reliably.
     */
    private const CANCELLABLE_PAYMENT_INTENT_STATUSES = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
        'requires_capture',
    ];

    public function __construct(
        private readonly StripeClient $stripeClient
    ) {}

    public function createPaymentIntent(Booking $booking): string
    {
        $amount = (int) $booking->amount;
        $currency = (string) config('cashier.currency', 'vnd');

        if ($amount <= 0) {
            throw new RuntimeException('Booking amount must be greater than zero.');
        }

        $idempotencyKey = $this->paymentIntentIdempotencyKey($booking);
        $metadata = $this->paymentIntentMetadata($booking);

        if ($this->shouldUseTestingFake()) {
            return 'pi_test_'.$booking->id.'_'.substr(hash('sha256', $idempotencyKey), 0, 12);
        }

        $paymentIntent = $this->stripeClient->paymentIntents->create(
            [
                'amount' => $amount,
                'currency' => $currency,
                'capture_method' => 'manual',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => $metadata,
            ],
            [
                'idempotency_key' => $idempotencyKey,
            ],
        );

        $this->assertPaymentIntentMatchesBooking($paymentIntent, $booking, $amount, $currency);

        $paymentIntentId = data_get($paymentIntent, 'id');
        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            throw new RuntimeException('Stripe PaymentIntent response is missing an id.');
        }

        return $paymentIntentId;
    }

    public function paymentIntentIdempotencyKey(Booking $booking): string
    {
        if (! $booking->exists) {
            throw new RuntimeException('Booking must be persisted before creating a PaymentIntent.');
        }

        return sprintf('booking_payment_intent_%d', (int) $booking->getKey());
    }

    /**
     * Cancel the PaymentIntent backing an expired booking (PAY-03).
     *
     * MUST be called outside any booking/room DB lock — it performs Stripe
     * network I/O. The drainer (ProcessPaymentCancellationOutbox) owns that
     * boundary; this method never opens a transaction.
     *
     * Behavior:
     * - Retrieves the PaymentIntent (source of truth) and verifies it still
     *   belongs to this booking before mutating anything.
     * - Already canceled -> idempotent success.
     * - Cancellable state -> cancel with the caller's stable idempotency key.
     * - succeeded / non-cancellable -> NotCancellable (operator review; we do
     *   NOT force a cancel that Stripe would reject and we never resurrect the
     *   booking).
     *
     * Transient failures (ApiConnection / RateLimit / 5xx / timeout) propagate
     * as Stripe exceptions so the worker retries with backoff.
     *
     * @param  string  $idempotencyKey  stable per-booking key, e.g. PaymentCancellationTask::idempotencyKey()
     */
    public function cancelPaymentIntentForBooking(Booking $booking, string $idempotencyKey): PaymentIntentCancellationOutcome
    {
        $paymentIntentId = $booking->payment_intent_id;

        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            throw new RuntimeException(
                "Booking #{$booking->id} has no payment_intent_id to cancel.",
            );
        }

        if ($this->shouldUseTestingFake()) {
            return PaymentIntentCancellationOutcome::Canceled;
        }

        $paymentIntent = $this->stripeClient->paymentIntents->retrieve($paymentIntentId);

        $this->assertPaymentIntentOwnedByBooking($paymentIntent, $paymentIntentId, $booking);

        $status = (string) data_get($paymentIntent, 'status', '');

        if ($status === 'canceled') {
            return PaymentIntentCancellationOutcome::AlreadyCanceled;
        }

        if (! in_array($status, self::CANCELLABLE_PAYMENT_INTENT_STATUSES, true)) {
            return PaymentIntentCancellationOutcome::NotCancellable;
        }

        $this->stripeClient->paymentIntents->cancel(
            $paymentIntentId,
            [],
            ['idempotency_key' => $idempotencyKey],
        );

        return PaymentIntentCancellationOutcome::Canceled;
    }

    /**
     * Create a Stripe refund for a booking's payment intent (CONC-005).
     *
     * Returns the resulting refund id. Uses the booking-id as
     * idempotency-key + metadata for downstream Stripe webhook reconciliation.
     *
     * @param  Booking  $booking  the booking whose payment_intent_id is being refunded
     * @param  int  $amount  refund amount in cents (must be > 0)
     * @param  string  $reason  short policy reason captured in metadata
     */
    public function createRefund(Booking $booking, int $amount, string $reason): string
    {
        if ($amount <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        if (blank($booking->payment_intent_id)) {
            throw new RuntimeException(
                "Booking #{$booking->id} has no payment_intent_id; cannot refund.",
            );
        }

        if ($this->shouldUseTestingFake()) {
            return 're_test_'.$booking->id.'_'.Str::lower(Str::random(12));
        }

        $idempotencyKey = sprintf('deposit_refund_%d_%s', $booking->id, $reason);

        $refund = $this->stripeClient->refunds->create(
            [
                'payment_intent' => $booking->payment_intent_id,
                'amount' => $amount,
                'metadata' => [
                    'booking_id' => (string) $booking->id,
                    'reason' => $reason,
                    'kind' => 'deposit_refund',
                ],
            ],
            ['idempotency_key' => $idempotencyKey],
        );

        return $refund->id;
    }

    /**
     * Create a booking-level cancellation refund when no Cashier billable user exists.
     */
    public function createBookingRefund(Booking $booking, int $amount): string
    {
        if ($amount <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        $paymentIntentId = $this->refundPaymentIntentId($booking);
        $idempotencyKey = $this->bookingRefundIdempotencyKey($booking);

        if ($this->shouldUseTestingFake()) {
            return 're_test_booking_'.$booking->id.'_'.substr(hash('sha256', $idempotencyKey), 0, 12);
        }

        $refund = $this->stripeClient->refunds->create(
            [
                'payment_intent' => $paymentIntentId,
                'amount' => $amount,
                'metadata' => [
                    'booking_id' => (string) $booking->id,
                    'kind' => 'booking_cancellation_refund',
                    'source' => 'cancellation_service',
                ],
            ],
            ['idempotency_key' => $idempotencyKey],
        );

        return $refund->id;
    }

    public function bookingRefundIdempotencyKey(Booking $booking): string
    {
        if (! $booking->exists) {
            throw new RuntimeException('Booking must be persisted before creating a refund.');
        }

        return sprintf(
            'booking:%d:refund:%s',
            (int) $booking->getKey(),
            $this->refundPaymentIntentId($booking),
        );
    }

    private function shouldUseTestingFake(): bool
    {
        return app()->environment('testing') && blank(config('cashier.secret'));
    }

    private function refundPaymentIntentId(Booking $booking): string
    {
        $paymentIntentId = $booking->payment_intent_id;

        if (! is_string($paymentIntentId) || blank($paymentIntentId)) {
            throw new RuntimeException(
                "Booking #{$booking->id} has no payment_intent_id; cannot refund.",
            );
        }

        return $paymentIntentId;
    }

    /**
     * @return array<string, string>
     */
    private function paymentIntentMetadata(Booking $booking): array
    {
        $metadata = [
            'booking_id' => (string) $booking->id,
            'room_id' => (string) $booking->room_id,
        ];

        if ($booking->location_id !== null) {
            $metadata['location_id'] = (string) $booking->location_id;
        }

        if ($booking->user_id !== null) {
            $metadata['user_id'] = (string) $booking->user_id;
        }

        return $metadata;
    }

    /**
     * Defense in depth: never cancel a PaymentIntent that no longer belongs to
     * this booking. The booking_id is stamped into PaymentIntent metadata at
     * creation time (paymentIntentMetadata); if Stripe returns a different
     * owner the local payment_intent_id has drifted and cancelling would be a
     * cross-booking side effect. A missing metadata.booking_id (older intents)
     * is tolerated — we only reject an explicit mismatch.
     */
    private function assertPaymentIntentOwnedByBooking(
        mixed $paymentIntent,
        string $paymentIntentId,
        Booking $booking,
    ): void {
        $metadataBookingId = (string) data_get($paymentIntent, 'metadata.booking_id', '');

        if ($metadataBookingId !== '' && $metadataBookingId !== (string) $booking->id) {
            throw new RuntimeException(sprintf(
                'PaymentIntent %s metadata booking_id (%s) does not match booking #%d; refusing to cancel.',
                $paymentIntentId,
                $metadataBookingId,
                $booking->id,
            ));
        }
    }

    private function assertPaymentIntentMatchesBooking(
        mixed $paymentIntent,
        Booking $booking,
        int $amount,
        string $currency
    ): void {
        if ((int) data_get($paymentIntent, 'amount') !== $amount) {
            throw new RuntimeException("Stripe PaymentIntent amount mismatch for booking #{$booking->id}.");
        }

        if ((string) data_get($paymentIntent, 'currency') !== $currency) {
            throw new RuntimeException("Stripe PaymentIntent currency mismatch for booking #{$booking->id}.");
        }

        if ((string) data_get($paymentIntent, 'metadata.booking_id') !== (string) $booking->id) {
            throw new RuntimeException("Stripe PaymentIntent metadata mismatch for booking #{$booking->id}.");
        }
    }
}
