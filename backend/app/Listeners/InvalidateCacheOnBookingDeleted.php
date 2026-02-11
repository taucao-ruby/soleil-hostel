<?php

namespace App\Listeners;

use App\Events\BookingDeleted;
use App\Services\RoomAvailabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class InvalidateCacheOnBookingDeleted implements ShouldQueue
{
    use InteractsWithQueue;

    public int $timeout = 15;

    public int $delay = 0;

    public function __construct(private RoomAvailabilityService $availabilityService)
    {
        //
    }

    /**
     * Handle the event - invalidate cache for deleted booking's room
     */
    public function handle(BookingDeleted $event): void
    {
        $roomId = $event->booking->room_id;

        // Invalidate cache for this room
        $this->availabilityService->invalidateRoomCache($roomId);

        \Log::info("Cache invalidated for room {$roomId} after booking deleted");
    }

    public function failed(BookingDeleted $event, \Throwable $exception): void
    {
        \Log::error('Cache invalidation failed on booking delete: '.$exception->getMessage());
    }
}
