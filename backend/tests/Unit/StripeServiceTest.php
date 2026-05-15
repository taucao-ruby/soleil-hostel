<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Booking;
use App\Services\StripeService;
use PHPUnit\Framework\TestCase;

final class StripeServiceTest extends TestCase
{
    public function test_payment_intent_idempotency_key_is_deterministic_for_booking(): void
    {
        $booking = new Booking;
        $booking->forceFill([
            'id' => 123,
        ]);
        $booking->exists = true;

        $service = new StripeService($this->createMock(\Stripe\StripeClient::class));

        $this->assertSame('booking_payment_intent_123', $service->paymentIntentIdempotencyKey($booking));
        $this->assertSame($service->paymentIntentIdempotencyKey($booking), $service->paymentIntentIdempotencyKey($booking));
    }
}
