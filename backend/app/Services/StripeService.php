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

    private function shouldUseTestingFake(): bool
    {
        return app()->environment('testing') && blank(config('cashier.secret'));
    }
}
