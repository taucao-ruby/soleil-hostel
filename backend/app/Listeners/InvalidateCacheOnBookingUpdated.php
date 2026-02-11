<?php

namespace App\Listeners;

use App\Events\BookingUpdated;
use App\Services\RoomAvailabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class InvalidateCacheOnBookingUpdated implements ShouldQueue
{
    use InteractsWithQueue;

    public int $timeout = 15;

    public int $delay = 0;

    public function __construct(private RoomAvailabilityService $availabilityService)
    {
        //
    }

    /**
     * Handle the event - invalidate cache for old AND new room
     * (in case user changed rooms)
     */
    public function handle(BookingUpdated $event): void
    {
        $booking = $event->booking;
        $originalBooking = $event->originalBooking;

        // Invalidate cache for new room
        $this->availabilityService->invalidateRoomCache($booking->room_id);

        // Invalidate cache for old room if it changed
        if ($originalBooking->room_id !== $booking->room_id) {
            $this->availabilityService->invalidateRoomCache($originalBooking->room_id);
        }

        \Log::info('Cache invalidated for bookings update', [
            'booking_id' => $booking->id,
            'rooms_affected' => [$booking->room_id, $originalBooking->room_id],
        ]);
    }

    public function failed(BookingUpdated $event, \Throwable $exception): void
    {
        \Log::error('Cache invalidation failed on booking update: '.$exception->getMessage());
    }
}
