<?php

namespace App\Notifications;

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
 * 
 * Architecture:
 * - Implements ShouldQueue for async delivery via queue workers
 * - Uses afterCommit() to ensure notification only dispatches after DB transaction commits
 * - Exponential backoff retry strategy for transient SMTP failures
 * 
 * @see docs/backend/BOOKING_CONFIRMATION_NOTIFICATION_ARCHITECTURE.md
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
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        // Skip if booking was cancelled (separate notification handles that)
        if ($this->booking->status === Booking::STATUS_CANCELLED) {
            Log::info('BookingUpdated notification skipped - booking cancelled', [
                'booking_id' => $this->booking->id,
            ]);
            return null;
        }

        $message = (new MailMessage)
            ->subject('Booking Updated - Soleil Hostel')
            ->greeting('Hello ' . $this->booking->guest_name . ',')
            ->line('Your booking has been updated.');

        // Add changes information if available
        if (!empty($this->changes)) {
            $message->line('**Changes Made:**');
            foreach ($this->changes as $field => $value) {
                $displayField = ucfirst(str_replace('_', ' ', $field));
                $displayValue = $value instanceof \DateTimeInterface 
                    ? $value->format('M j, Y') 
                    : $value;
                $message->line($displayField . ': ' . $displayValue);
            }
        }

        return $message
            ->line('**Current Booking Details:**')
            ->line('Room: ' . $this->booking->room->name)
            ->line('Check-in: ' . $this->booking->check_in->format('M j, Y'))
            ->line('Check-out: ' . $this->booking->check_out->format('M j, Y'))
            ->action('View Booking', url('/bookings/' . $this->booking->id))
            ->line('Thank you for choosing Soleil Hostel!')
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
