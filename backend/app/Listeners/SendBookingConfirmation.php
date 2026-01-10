<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Notifications\BookingConfirmed;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingConfirmation Listener
 * 
 * Automatically sends booking confirmation email using Laravel Notifications.
 * NOTE: Does NOT implement ShouldQueue because BookingConfirmed notification
 * already implements ShouldQueue - this avoids double-queuing issues.
 */
class SendBookingConfirmation
{
    /**
     * Handle the event.
     */
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        // Send notification to guest email
        // The notification itself is queued via ShouldQueue
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingConfirmed($booking));

        \Log::info('Booking confirmation queued', [
            'booking_id' => $booking->id,
            'guest_email' => $booking->guest_email,
        ]);
    }
}
