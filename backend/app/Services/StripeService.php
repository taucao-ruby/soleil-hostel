<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentPolicy;
use App\Models\Booking;
use App\Services\Payment\PaymentIntentCancellationOutcome;
use App\Services\Payment\PaymentIntentStartResult;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\Refund;
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

    public function createPaymentIntent(Booking $booking): PaymentIntentStartResult
    {
        $amount = $this->expectedAmount($booking);
        $currency = $this->expectedCurrency($booking);
        $policy = $booking->payment_policy;

        if ($amount <= 0) {
            throw new RuntimeException('Booking amount must be greater than zero.');
        }

        if (! $policy->requiresStripePaymentIntent()) {
            throw new RuntimeException('Booking payment policy does not require a Stripe PaymentIntent.');
        }

        $idempotencyKey = $this->paymentIntentIdempotencyKey($booking);
        $metadata = $this->paymentIntentMetadata($booking);

        if ($this->shouldUseTestingFake()) {
            $id = 'pi_test_'.$booking->id.'_'.substr(hash('sha256', $idempotencyKey), 0, 12);

            return new PaymentIntentStartResult(
                id: $id,
                clientSecret: $id.'_secret_test',
                status: 'requires_payment_method',
                amount: $amount,
                currency: $currency,
            );
        }

        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'capture_method' => $policy === PaymentPolicy::AUTHORIZE_THEN_CAPTURE ? 'manual' : 'automatic',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => $metadata,
        ];

        $paymentIntent = $this->stripeClient->paymentIntents->create(
            $payload,
            [
                'idempotency_key' => $idempotencyKey,
            ],
        );

        $this->assertPaymentIntentMatchesBooking($paymentIntent, $booking, $amount, $currency);

        $paymentIntentId = data_get($paymentIntent, 'id');
        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            throw new RuntimeException('Stripe PaymentIntent response is missing an id.');
        }

        return new PaymentIntentStartResult(
            id: $paymentIntentId,
            clientSecret: $this->paymentIntentClientSecret($paymentIntent),
            status: (string) data_get($paymentIntent, 'status', 'requires_payment_method'),
            amount: (int) data_get($paymentIntent, 'amount', $amount),
            currency: (string) data_get($paymentIntent, 'currency', $currency),
            amountCapturable: (int) data_get($paymentIntent, 'amount_capturable', 0),
            amountReceived: (int) data_get($paymentIntent, 'amount_received', 0),
        );
    }

    public function paymentIntentIdempotencyKey(Booking $booking): string
    {
        if (! $booking->exists) {
            throw new RuntimeException('Booking must be persisted before creating a PaymentIntent.');
        }

        return sprintf('booking:%d:payment_intent:create:v1', (int) $booking->getKey());
    }

    public function retrievePaymentIntent(string $paymentIntentId): mixed
    {
        if ($this->shouldUseTestingFake()) {
            return (object) [
                'id' => $paymentIntentId,
                'status' => 'succeeded',
                'amount' => 0,
                'currency' => strtolower((string) config('cashier.currency', 'vnd')),
                'metadata' => (object) [],
                'amount_capturable' => 0,
                'amount_received' => 0,
            ];
        }

        return $this->stripeClient->paymentIntents->retrieve($paymentIntentId);
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

    /**
     * Issue (or idempotently replay) the single reconciliation refund for a
     * booking (PAY-01).
     *
     * Returns the full Stripe Refund so the caller can persist its id/currency
     * into the refund ledger. The idempotency key is derived from the durable
     * booking + payment_intent (bookingRefundIdempotencyKey) — NOT the job
     * attempt — so any number of reconciler retries, queue redeliveries,
     * timeouts, or concurrent workers collapse to AT MOST ONE Stripe refund.
     * It is identical to createBookingRefund's key, so a refund the live
     * cancellation no-user path already issued is deduplicated by Stripe rather
     * than duplicated here.
     *
     * The caller passes the Stripe client it resolved for the booking so the
     * CONC-006 orphaned-user fallback (user->stripe() vs application client) is
     * preserved; this class never decides which client a reconciler refund uses.
     *
     * @param  StripeClient  $stripe  client resolved by the caller for this booking
     * @param  int  $amount  refund amount in minor units (must be > 0)
     */
    public function createReconciliationRefund(StripeClient $stripe, Booking $booking, int $amount): Refund
    {
        if ($amount <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        $paymentIntentId = $this->refundPaymentIntentId($booking);
        $idempotencyKey = $this->bookingRefundIdempotencyKey($booking);

        return $stripe->refunds->create(
            [
                'payment_intent' => $paymentIntentId,
                'amount' => $amount,
                'metadata' => $this->reconciliationRefundMetadata($booking, $paymentIntentId, $idempotencyKey),
            ],
            ['idempotency_key' => $idempotencyKey],
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
        /** @var PaymentPolicy $paymentPolicy */
        $paymentPolicy = $booking->payment_policy;

        $metadata = [
            'booking_id' => (string) $booking->id,
            'room_id' => (string) $booking->room_id,
            'payment_policy' => $paymentPolicy->value,
        ];

        if ($booking->location_id !== null) {
            $metadata['location_id'] = (string) $booking->location_id;
        }

        if ($booking->user_id !== null) {
            $metadata['user_id'] = (string) $booking->user_id;
        }

        return $metadata;
    }

    public function expectedAmount(Booking $booking): int
    {
        return (int) $booking->amount;
    }

    public function expectedCurrency(Booking $booking): string
    {
        $currency = (string) $booking->payment_currency;

        if ($currency !== '') {
            return strtolower($currency);
        }

        return strtolower((string) config('cashier.currency', 'vnd'));
    }

    /**
     * Non-PII metadata stamped on a reconciliation refund so a remote Stripe
     * refund can be reconciled back to the local booking/logical refund event.
     * soleil_refund_event_id carries the stable idempotency key, which the
     * reconciler's pre-check matches against (PAY-01).
     *
     * @return array<string, string>
     */
    private function reconciliationRefundMetadata(Booking $booking, string $paymentIntentId, string $idempotencyKey): array
    {
        return [
            'booking_id' => (string) $booking->id,
            'soleil_refund_event_id' => $idempotencyKey,
            'payment_intent_id' => $paymentIntentId,
            'kind' => 'reconcile_refund',
            'source' => 'reconcile_refunds_job',
        ];
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

    public function assertPaymentIntentMatchesBooking(
        mixed $paymentIntent,
        Booking $booking,
        ?int $amount = null,
        ?string $currency = null
    ): void {
        $amount ??= $this->expectedAmount($booking);
        $currency ??= $this->expectedCurrency($booking);

        if ((int) data_get($paymentIntent, 'amount') !== $amount) {
            throw new RuntimeException("Stripe PaymentIntent amount mismatch for booking #{$booking->id}.");
        }

        if (strtolower((string) data_get($paymentIntent, 'currency')) !== strtolower($currency)) {
            throw new RuntimeException("Stripe PaymentIntent currency mismatch for booking #{$booking->id}.");
        }

        if ((string) data_get($paymentIntent, 'metadata.booking_id') !== (string) $booking->id) {
            throw new RuntimeException("Stripe PaymentIntent metadata mismatch for booking #{$booking->id}.");
        }
    }

    private function paymentIntentClientSecret(mixed $paymentIntent): ?string
    {
        $clientSecret = data_get($paymentIntent, 'client_secret');

        return is_string($clientSecret) && $clientSecret !== '' ? $clientSecret : null;
    }
}
