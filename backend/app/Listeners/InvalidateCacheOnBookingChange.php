<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Events\BookingUpdated;
use App\Events\BookingDeleted;
use App\Services\RoomService;
use App\Services\BookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class InvalidateCacheOnBookingChange implements ShouldQueue
{
    public function __construct(
        private RoomService $roomService,
        private BookingService $bookingService
    ) {}

    public function handle($event): void
    {
        try {
            if ($event instanceof BookingCreated) {
                $this->handleCreated($event->booking);
            } elseif ($event instanceof BookingUpdated) {
                $this->handleUpdated($event->booking, $event->oldBooking);
            } elseif ($event instanceof BookingDeleted) {
                $this->handleDeleted($event->booking);
            } elseif ($event instanceof BookingCancelled) {
                $this->handleCancelled($event->booking);
            }
        } catch (\Exception $e) {
            Log::error("Cache invalidation failed: {$e->getMessage()}");
        }
    }

    private function handleCreated($booking): void
    {
        // Invalidate room availability (booking created = less availability)
        $this->roomService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
    }

    private function handleUpdated($booking, $oldBooking): void
    {
        // If room changed, invalidate old room availability
        if ($booking->room_id !== $oldBooking->room_id) {
            $this->roomService->invalidateAvailability($oldBooking->room_id);
        }
        
        // Invalidate new room availability
        $this->roomService->invalidateAvailability($booking->room_id);
        
        // Invalidate this booking's cache
        $this->bookingService->invalidateBooking($booking->id, $booking->user_id);
    }

    private function handleDeleted($booking): void
    {
        // Invalidate room availability (booking deleted = more availability)
        $this->roomService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
    }

    private function handleCancelled($booking): void
    {
        // Cancelled booking releases room availability
        $this->roomService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
        // Invalidate this booking's cache
        $this->bookingService->invalidateBooking($booking->id, $booking->user_id);
    }
}
