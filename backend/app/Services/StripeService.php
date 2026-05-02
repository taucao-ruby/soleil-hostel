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

        if ($amount <= 0) {
            throw new RuntimeException('Booking amount must be greater than zero.');
        }

        if ($this->shouldUseTestingFake()) {
            return 'pi_test_'.$booking->id.'_'.Str::lower(Str::random(12));
        }

        $paymentIntent = Cashier::stripe()->paymentIntents->create([
            'amount' => $amount,
            'currency' => (string) config('cashier.currency', 'vnd'),
            'capture_method' => 'manual',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'room_id' => (string) $booking->room_id,
                'user_id' => (string) $booking->user_id,
            ],
        ]);

        return $paymentIntent->id;
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
}
