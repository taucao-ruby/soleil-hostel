<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Notifications\BookingConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingConfirmation Listener
 * 
 * Automatically sends booking confirmation email using Laravel Notifications.
 * Queued for async delivery to avoid blocking the HTTP request.
 */
class SendBookingConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        // Send notification to guest email
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingConfirmed($booking));

        \Log::info('Booking confirmation sent', [
            'booking_id' => $booking->id,
            'guest_email' => $booking->guest_email,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(BookingCreated $event, \Throwable $exception): void
    {
        \Log::error('Failed to send booking confirmation', [
            'booking_id' => $event->booking->id,
            'guest_email' => $event->booking->guest_email,
            'error' => $exception->getMessage(),
        ]);
    }
}
