<?php

namespace App\Listeners;

use App\Events\BookingUpdated as BookingUpdatedEvent;
use App\Notifications\BookingUpdated as BookingUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingUpdateNotification Listener
 * 
 * Sends update notification when booking details are modified.
 */
class SendBookingUpdateNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(BookingUpdatedEvent $event): void
    {
        $booking = $event->newBooking;
        $oldBooking = $event->oldBooking;

        // Detect changes
        $changes = [];
        if (isset($oldBooking->check_in) && $oldBooking->check_in !== $booking->check_in) {
            $changes['check_in'] = $booking->check_in;
        }
        if (isset($oldBooking->check_out) && $oldBooking->check_out !== $booking->check_out) {
            $changes['check_out'] = $booking->check_out;
        }

        // Only send notification if there are meaningful changes
        if (!empty($changes)) {
            Notification::route('mail', $booking->guest_email)
                ->notify(new BookingUpdatedNotification($booking, $changes));

            \Log::info('Booking update notification sent', [
                'booking_id' => $booking->id,
                'guest_email' => $booking->guest_email,
                'changes' => $changes,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(BookingUpdatedEvent $event, \Throwable $exception): void
    {
        \Log::error('Failed to send booking update notification', [
            'booking_id' => $event->newBooking->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
