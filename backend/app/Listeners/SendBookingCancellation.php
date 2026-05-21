<?php

namespace App\Listeners;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingDeleted;
use App\Notifications\BookingCancelled as BookingCancelledNotification;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingCancellation Listener
 *
 * Sends cancellation notification when a booking is cancelled or deleted.
 * Handles both:
 * - BookingDeleted: Soft delete flow
 * - BookingCancelled: Cancellation with refund flow
 *
 * NOTE: Does NOT implement ShouldQueue because the notification
 * already implements ShouldQueue - this avoids double-queuing issues.
 */
class SendBookingCancellation
{
    /**
     * Handle the event.
     */
    public function handle(BookingDeleted|BookingCancelled $event): void
    {
        $booking = $event->booking;

        // Skip notification if disabled in config
        if (! config('booking.notifications.send_cancellation_email', true)) {
            \Log::info('Booking cancellation notification skipped (disabled)', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        // Soft delete (BookingDeleted) is NOT a cancellation — the booking keeps
        // its current status. BookingCancelled::toMail() returns null unless the
        // booking is actually CANCELLED, and routing a null mail message through
        // an on-demand notifiable crashes MailChannel ("Attempt to read property
        // 'view' on null") → 500 on DELETE /bookings/{id} under the sync queue.
        // Only notify genuinely cancelled bookings (F-72).
        if ($booking->status !== BookingStatus::CANCELLED) {
            \Log::info('Booking cancellation notification skipped — booking not cancelled', [
                'booking_id' => $booking->id,
                'status' => $booking->status->value,
                'event_type' => class_basename($event),
            ]);

            return;
        }

        // Send notification to guest email
        // The notification itself is queued via ShouldQueue
        Notification::route('mail', $booking->guest_email)
            ->notify(new BookingCancelledNotification($booking));

        \Log::info('Booking cancellation notification queued', [
            'booking_id' => $booking->id,
            'guest_email' => $booking->guest_email,
            'refund_amount' => $booking->refund_amount,
            'event_type' => class_basename($event),
        ]);
    }
}
