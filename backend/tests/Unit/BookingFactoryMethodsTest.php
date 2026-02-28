<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFactoryMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_factory_creates_past_booking(): void
    {
        $booking = Booking::factory()->expired()->create();

        $this->assertTrue($booking->check_out->isPast());
        $this->assertTrue($booking->check_in->isPast());
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->status);
    }

    public function test_cancelled_by_admin_factory_sets_admin_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = Booking::factory()->cancelledByAdmin($admin)->create();

        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);
        $this->assertEquals($admin->id, $booking->cancelled_by);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_multi_day_factory_creates_correct_duration(): void
    {
        $booking = Booking::factory()->multiDay(5)->create();

        $days = $booking->check_in->diffInDays($booking->check_out);
        $this->assertEquals(5, $days);
    }

    public function test_multi_day_defaults_to_three_days(): void
    {
        $booking = Booking::factory()->multiDay()->create();

        $days = $booking->check_in->diffInDays($booking->check_out);
        $this->assertEquals(3, $days);
    }
}
