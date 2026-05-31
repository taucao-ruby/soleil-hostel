<?php

declare(strict_types=1);

namespace Tests\Feature\Room;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SH-08 / F-43 regression lock.
 *
 * hasOverlappingActiveBookings() must count BOTH pending and confirmed bookings
 * (BookingStatus::ACTIVE_STATUSES), ignore inactive and soft-deleted ones, and
 * respect half-open [check_in, check_out) boundaries. The finding feared a
 * confirmed-only check; the pending test below fails if the active-status set is
 * ever narrowed back to confirmed-only.
 */
class RoomOverlapActiveStatusTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomRepositoryInterface $rooms;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::factory()->create();
        $this->room = Room::factory()->create(['location_id' => $location->id]);
        $this->rooms = app(RoomRepositoryInterface::class);
    }

    private function booking(BookingStatus $status, string $checkIn, string $checkOut): Booking
    {
        return Booking::factory()->create([
            'room_id' => $this->room->id,
            'status' => $status,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);
    }

    public function test_pending_booking_blocks_overlap(): void
    {
        $this->booking(BookingStatus::PENDING, '2026-06-10', '2026-06-15');

        $this->assertTrue(
            $this->rooms->hasOverlappingActiveBookings($this->room->id, '2026-06-12', '2026-06-14')
        );
    }

    public function test_confirmed_booking_blocks_overlap(): void
    {
        $this->booking(BookingStatus::CONFIRMED, '2026-06-10', '2026-06-15');

        $this->assertTrue(
            $this->rooms->hasOverlappingActiveBookings($this->room->id, '2026-06-12', '2026-06-14')
        );
    }

    public function test_cancelled_booking_does_not_block_overlap(): void
    {
        $this->booking(BookingStatus::CANCELLED, '2026-06-10', '2026-06-15');

        $this->assertFalse(
            $this->rooms->hasOverlappingActiveBookings($this->room->id, '2026-06-12', '2026-06-14')
        );
    }

    public function test_soft_deleted_booking_does_not_block_overlap(): void
    {
        $booking = $this->booking(BookingStatus::PENDING, '2026-06-10', '2026-06-15');
        $booking->delete();

        $this->assertFalse(
            $this->rooms->hasOverlappingActiveBookings($this->room->id, '2026-06-12', '2026-06-14')
        );
    }

    public function test_half_open_boundary_does_not_overlap(): void
    {
        // Existing [2026-06-10, 2026-06-12); requested [2026-06-12, 2026-06-14).
        $this->booking(BookingStatus::PENDING, '2026-06-10', '2026-06-12');

        $this->assertFalse(
            $this->rooms->hasOverlappingActiveBookings($this->room->id, '2026-06-12', '2026-06-14')
        );
    }
}
