<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * BookingUpdated Notification
 * 
 * Production-grade queued notification for booking modifications.
 * Uses branded Markdown template with change summary.
 * 
 * Architecture:
 * - Implements ShouldQueue for async delivery via queue workers
 * - Uses afterCommit() to ensure notification only dispatches after DB transaction commits
 * - Exponential backoff retry strategy for transient SMTP failures
 * 
 * @see docs/backend/guides/EMAIL_NOTIFICATIONS.md
 */
class BookingUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum retry attempts before moving to failed_jobs table.
     */
    public int $tries = 3;

    /**
     * Exponential backoff intervals in seconds: 1min, 5min, 15min.
     */
    public array $backoff = [60, 300, 900];

    /**
     * Silently discard job if Booking model is deleted.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly array $changes = []
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     * 
     * Uses branded Markdown template with change summary.
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        // Skip if booking was cancelled (separate notification handles that)
        if ($this->booking->status === BookingStatus::CANCELLED) {
            Log::info('BookingUpdated notification skipped - booking cancelled', [
                'booking_id' => $this->booking->id,
            ]);
            return null;
        }

        return (new MailMessage)
            ->subject('📝 Booking Updated - ' . config('email-branding.name', 'Soleil Hostel'))
            ->markdown('mail.bookings.updated', [
                'guestName' => e($this->booking->guest_name),
                'bookingId' => $this->booking->id,
                'roomName' => e($this->booking->room->name),
                'checkIn' => $this->booking->check_in->format('l, F j, Y'),
                'checkOut' => $this->booking->check_out->format('l, F j, Y'),
                'totalPrice' => $this->booking->amount ?? 0,
                'changes' => $this->changes,
                'viewBookingUrl' => url('/bookings/' . $this->booking->id),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'room_id' => $this->booking->room_id,
            'guest_name' => $this->booking->guest_name,
            'changes' => $this->changes,
        ];
    }

    /**
     * Handle notification failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BookingUpdated notification failed permanently', [
            'booking_id' => $this->booking->id ?? 'unknown',
            'guest_email' => $this->booking->guest_email ?? 'unknown',
            'exception' => $exception->getMessage(),
        ]);
    }
}
