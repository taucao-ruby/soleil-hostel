<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceSelectFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_booking_by_id_includes_payment_and_refund_fields(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $booking = Booking::factory()->forUser($user)->forRoom($room)->create([
            'status' => BookingStatus::CANCELLED,
            'amount' => 12500,
            'payment_intent_id' => 'pi_test_booking_select',
            'refund_id' => 're_test_booking_select',
            'refund_status' => 'succeeded',
            'refund_amount' => 8000,
            'refund_error' => null,
        ]);

        $service = app(BookingService::class);
        $loaded = $service->getBookingById($booking->id);

        $this->assertNotNull($loaded);
        $this->assertSame(12500, $loaded->amount);
        $this->assertSame('pi_test_booking_select', $loaded->payment_intent_id);
        $this->assertSame('re_test_booking_select', $loaded->refund_id);
        $this->assertSame('succeeded', $loaded->refund_status);
        $this->assertSame(8000, $loaded->refund_amount);
    }

    public function test_get_user_bookings_includes_payment_and_refund_fields(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->forUser($user)->forRoom($room)->create([
            'status' => BookingStatus::REFUND_FAILED,
            'amount' => 9900,
            'payment_intent_id' => 'pi_test_user_bookings',
            'refund_id' => 're_test_user_bookings',
            'refund_status' => 'failed',
            'refund_amount' => 0,
            'refund_error' => 'declined',
        ]);

        $service = app(BookingService::class);
        $loaded = $service->getUserBookings($user->id)->first();

        $this->assertNotNull($loaded);
        $this->assertSame(9900, $loaded->amount);
        $this->assertSame('pi_test_user_bookings', $loaded->payment_intent_id);
        $this->assertSame('re_test_user_bookings', $loaded->refund_id);
        $this->assertSame('failed', $loaded->refund_status);
        $this->assertSame(0, $loaded->refund_amount);
        $this->assertSame('declined', $loaded->refund_error);
    }
}
