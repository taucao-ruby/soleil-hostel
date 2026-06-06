<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use App\Enums\BookingStatus;
use App\Enums\StayStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\Stay;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('booking')]
final class BookingCancellationStayPropagationTest extends TestCase
{
    public function test_booking_cancelled_event_cancels_active_stay(): void
    {
        $actor = User::factory()->admin()->create();
        $booking = Booking::factory()->cancelled()->create([
            'cancelled_by' => $actor->id,
        ]);
        $stay = Stay::factory()->forBooking($booking)->inHouse()->create();

        event(new BookingCancelled($booking, $actor));

        $this->assertSame(StayStatus::CANCELLED, $stay->fresh()->stay_status);
    }

    public function test_booking_cancelled_event_without_stay_is_no_op(): void
    {
        $booking = Booking::factory()->cancelled()->create();

        event(new BookingCancelled($booking));

        $this->assertDatabaseMissing('stays', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_booking_cancelled_event_with_checked_out_stay_is_no_op(): void
    {
        $booking = Booking::factory()->cancelled()->create();
        $stay = Stay::factory()->forBooking($booking)->checkedOut()->create();

        event(new BookingCancelled($booking));

        $this->assertSame(StayStatus::CHECKED_OUT, $stay->fresh()->stay_status);
    }

    public function test_booking_cancelled_listener_is_idempotent(): void
    {
        $booking = Booking::factory()->cancelled()->create();
        $stay = Stay::factory()->forBooking($booking)->inHouse()->create();
        $firstTransitionTime = Carbon::parse('2026-05-03 10:00:00');

        try {
            Carbon::setTestNow($firstTransitionTime);
            event(new BookingCancelled($booking));
            $afterFirstEvent = $stay->fresh();

            Carbon::setTestNow($firstTransitionTime->copy()->addHour());
            event(new BookingCancelled($booking));
            $afterSecondEvent = $stay->fresh();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(StayStatus::CANCELLED, $afterSecondEvent->stay_status);
        $this->assertTrue($afterFirstEvent->updated_at->equalTo($afterSecondEvent->updated_at));
    }

    public function test_cancelled_bookings_have_no_active_stays_for_operational_dashboard_query(): void
    {
        $booking = Booking::factory()->cancelled()->create();
        Stay::factory()->forBooking($booking)->lateCheckout()->create();

        event(new BookingCancelled($booking));

        $ghostOccupancyCount = Stay::query()
            ->whereIn('stay_status', [
                StayStatus::IN_HOUSE->value,
                StayStatus::LATE_CHECKOUT->value,
            ])
            ->whereHas('booking', fn ($query) => $query->where('status', BookingStatus::CANCELLED->value))
            ->count();

        $this->assertSame(0, $ghostOccupancyCount);
    }
}
