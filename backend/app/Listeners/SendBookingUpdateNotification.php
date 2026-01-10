<?php

namespace App\Listeners;

use App\Events\BookingUpdated as BookingUpdatedEvent;
use App\Notifications\BookingUpdated as BookingUpdatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingUpdateNotification Listener
 * 
 * Sends update notification when booking details are modified.
 * NOTE: Does NOT implement ShouldQueue because BookingUpdated notification
 * already implements ShouldQueue - this avoids double-queuing issues.
 */
class SendBookingUpdateNotification
{
    /**
     * Handle the event.
     */
    public function handle(BookingUpdatedEvent $event): void
    {
        $booking = $event->booking;
        $oldBooking = $event->originalBooking;

        // Detect changes - normalize dates to string for comparison
        $changes = [];
        
        $oldCheckIn = $this->normalizeDate($oldBooking->check_in ?? null);
        $newCheckIn = $this->normalizeDate($booking->check_in ?? null);
        if ($oldCheckIn !== null && $oldCheckIn !== $newCheckIn) {
            $changes['check_in'] = $booking->check_in;
        }
        
        $oldCheckOut = $this->normalizeDate($oldBooking->check_out ?? null);
        $newCheckOut = $this->normalizeDate($booking->check_out ?? null);
        if ($oldCheckOut !== null && $oldCheckOut !== $newCheckOut) {
            $changes['check_out'] = $booking->check_out;
        }

        // Only send notification if there are meaningful changes
        if (!empty($changes)) {
            Notification::route('mail', $booking->guest_email)
                ->notify(new BookingUpdatedNotification($booking, $changes));

            \Log::info('Booking update notification queued', [
                'booking_id' => $booking->id,
                'guest_email' => $booking->guest_email,
                'changes' => $changes,
            ]);
        }
    }

    /**
     * Normalize date to Y-m-d string format for comparison.
     */
    private function normalizeDate(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }
        
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        
        if (is_string($date)) {
            return Carbon::parse($date)->format('Y-m-d');
        }
        
        return (string) $date;
    }
}
