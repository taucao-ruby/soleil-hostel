<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use RuntimeException;

class StripeService
{
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

        $paymentIntent = Cashier::stripe()->paymentIntents->create(
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

    public function cancelPaymentIntent(string $paymentIntentId): void
    {
        if ($this->shouldUseTestingFake()) {
            return;
        }

        Cashier::stripe()->paymentIntents->cancel($paymentIntentId);
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

        $refund = Cashier::stripe()->refunds->create(
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

    private function shouldUseTestingFake(): bool
    {
        return app()->environment('testing') && blank(config('cashier.secret'));
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
