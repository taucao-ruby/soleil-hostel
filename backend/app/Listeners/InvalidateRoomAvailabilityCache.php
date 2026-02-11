<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Services\RoomAvailabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class InvalidateRoomAvailabilityCache implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Time to live for the job (15 seconds)
     */
    public int $timeout = 15;

    /**
     * Delay before processing (0 = immediate)
     */
    public int $delay = 0;

    /**
     * Constructor
     */
    public function __construct(private RoomAvailabilityService $availabilityService)
    {
        //
    }

    /**
     * Handle the event.
     *
     * Invalidates room availability cache when a booking is created.
     * This ensures other users see the correct room status immediately.
     */
    public function handle(BookingCreated $event): void
    {
        $roomId = $event->booking->room_id;

        // Invalidate cache for this specific room
        $this->availabilityService->invalidateRoomCache($roomId);

        \Log::info("Cache invalidated for room {$roomId} after booking created");
    }

    /**
     * Handle a failed job
     */
    public function failed(BookingCreated $event, \Throwable $exception): void
    {
        \Log::error(
            'Failed to invalidate room cache after booking',
            [
                'booking_id' => $event->booking->id,
                'room_id' => $event->booking->room_id,
                'error' => $exception->getMessage(),
            ]
        );
    }
}
