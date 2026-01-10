<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * BookingCancelled Notification
 * 
 * Production-grade queued notification for booking cancellations.
 * 
 * Architecture:
 * - Implements ShouldQueue for async delivery via queue workers
 * - Uses afterCommit() to ensure notification only dispatches after DB transaction commits
 * - Includes idempotency guard to prevent duplicate sends
 * - Exponential backoff retry strategy for transient SMTP failures
 * 
 * @see docs/backend/BOOKING_CONFIRMATION_NOTIFICATION_ARCHITECTURE.md
 */
class BookingCancelled extends Notification implements ShouldQueue
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
        public readonly Booking $booking
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
     * Includes idempotency guard: returns null if booking status is not cancelled.
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        // Idempotency guard: only send if booking is actually cancelled
        if ($this->booking->status !== Booking::STATUS_CANCELLED) {
            Log::info('BookingCancelled notification skipped - booking not cancelled', [
                'booking_id' => $this->booking->id,
                'current_status' => $this->booking->status,
            ]);
            return null;
        }

        return (new MailMessage)
            ->subject('Booking Cancelled - Soleil Hostel')
            ->greeting('Hello ' . $this->booking->guest_name . ',')
            ->line('Your booking has been cancelled.')
            ->line('**Cancelled Booking Details:**')
            ->line('Room: ' . $this->booking->room->name)
            ->line('Check-in: ' . $this->booking->check_in->format('M j, Y'))
            ->line('Check-out: ' . $this->booking->check_out->format('M j, Y'))
            ->line('If this was a mistake or you have any questions, please contact us.')
            ->action('Contact Support', url('/contact'))
            ->line('We hope to serve you again in the future.')
            ->salutation('Best regards, Soleil Hostel Team');
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
            'status' => 'cancelled',
        ];
    }

    /**
     * Handle notification failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BookingCancelled notification failed permanently', [
            'booking_id' => $this->booking->id ?? 'unknown',
            'guest_email' => $this->booking->guest_email ?? 'unknown',
            'exception' => $exception->getMessage(),
        ]);
    }
}
