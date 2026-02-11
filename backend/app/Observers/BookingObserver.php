<?php

namespace App\Observers;

use App\Models\Booking;

/**
 * BookingObserver
 *
 * Auto-populates location_id from the associated room when creating/updating bookings.
 * This is the application-level complement to the PostgreSQL trigger
 * (trg_booking_set_location) and ensures consistency in both testing (SQLite)
 * and production (PostgreSQL) environments.
 */
class BookingObserver
{
    /**
     * Handle the Booking "creating" event.
     *
     * Auto-populate location_id from room when not explicitly set.
     */
    public function creating(Booking $booking): void
    {
        if (! $booking->location_id && $booking->room_id) {
            $booking->location_id = $booking->room?->location_id;
        }
    }

    /**
     * Handle the Booking "updating" event.
     *
     * Update location_id if room_id changed (room transferred to different location).
     */
    public function updating(Booking $booking): void
    {
        if ($booking->isDirty('room_id') && $booking->room_id) {
            // Unload cached relationship to force fresh query with new room_id
            $booking->unsetRelation('room');
            $booking->location_id = $booking->room?->location_id;
        }
    }
}
