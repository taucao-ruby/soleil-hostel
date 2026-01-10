<?php

namespace App\Listeners;

use App\Events\BookingDeleted;
use App\Notifications\BookingCancelled;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingCancellation Listener
 * 
 * Sends cancellation notification when a booking is deleted.
 * NOTE: Does NOT implement ShouldQueue because BookingCancelled notification
 * already implements ShouldQueue - this avoids double-queuing issues.
 */
class SendBookingCancellation
{
    /**
     * Handle the event.
     */
    public function handle(BookingDeleted $event): void
    {
        $booking = $event->booking;

        // Send notification to guest email
        // The notification itself is queued via ShouldQueue
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingCancelled($booking));

        \Log::info('Booking cancellation queued', [
            'booking_id' => $booking->id,
            'guest_email' => $booking->guest_email,
        ]);
    }
}
