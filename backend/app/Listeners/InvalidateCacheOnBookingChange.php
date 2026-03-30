<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Events\BookingDeleted;
use App\Events\BookingRestored;
use App\Events\BookingUpdated;
use App\Services\BookingService;
use App\Services\RoomAvailabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class InvalidateCacheOnBookingChange implements ShouldQueue
{
    public function __construct(
        private RoomAvailabilityService $roomAvailabilityService,
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
            } elseif ($event instanceof BookingRestored) {
                $this->handleRestored($event->booking);
            }
        } catch (\Exception $e) {
            Log::error("Cache invalidation failed: {$e->getMessage()}");
        }
    }

    private function handleCreated($booking): void
    {
        // Invalidate room availability (booking created = less availability)
        $this->roomAvailabilityService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
    }

    private function handleUpdated($booking, $oldBooking): void
    {
        // If room changed, invalidate old room availability
        if ($booking->room_id !== $oldBooking->room_id) {
            $this->roomAvailabilityService->invalidateAvailability($oldBooking->room_id);
        }

        // Invalidate new room availability
        $this->roomAvailabilityService->invalidateAvailability($booking->room_id);

        // Invalidate this booking's cache
        $this->bookingService->invalidateBooking($booking->id, $booking->user_id);
    }

    private function handleDeleted($booking): void
    {
        // Invalidate room availability (booking deleted = more availability)
        $this->roomAvailabilityService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
    }

    private function handleCancelled($booking): void
    {
        // Cancelled booking releases room availability
        $this->roomAvailabilityService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
        // Invalidate this booking's cache
        $this->bookingService->invalidateBooking($booking->id, $booking->user_id);
    }

    private function handleRestored($booking): void
    {
        // Restored booking re-blocks room availability
        $this->roomAvailabilityService->invalidateAvailability($booking->room_id);
        // Invalidate user's bookings list
        $this->bookingService->invalidateUserBookings($booking->user_id);
        // Individual booking cache is already invalidated by BookingService::restore()
    }
}
