<?php

namespace App\Listeners;

use App\Events\BookingDeleted;
use App\Notifications\BookingCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingCancellation Listener
 * 
 * Sends cancellation notification when a booking is deleted.
 */
class SendBookingCancellation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(BookingDeleted $event): void
    {
        $booking = $event->booking;

        // Send notification to guest email
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingCancelled($booking));

        \Log::info('Booking cancellation sent', [
            'booking_id' => $booking->id,
            'guest_email' => $booking->guest_email,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(BookingDeleted $event, \Throwable $exception): void
    {
        \Log::error('Failed to send booking cancellation', [
            'booking_id' => $event->booking->id,
            'guest_email' => $event->booking->guest_email,
            'error' => $exception->getMessage(),
        ]);
    }
}
