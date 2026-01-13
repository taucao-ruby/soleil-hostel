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
 * BookingConfirmed Notification
 * 
 * Production-grade queued notification for booking confirmations.
 * Uses branded Markdown template for professional email presentation.
 * 
 * Architecture:
 * - Implements ShouldQueue for async delivery via queue workers
 * - Uses afterCommit() to ensure notification only dispatches after DB transaction commits
 * - Includes idempotency guard to prevent duplicate sends on status change
 * - Exponential backoff retry strategy for transient SMTP failures
 * 
 * Usage:
 * ```php
 * $user->notify(new BookingConfirmed($booking));
 * // or for guest email:
 * Notification::route('mail', $booking->guest_email)
 *     ->notify(new BookingConfirmed($booking));
 * ```
 * 
 * @see docs/backend/guides/EMAIL_NOTIFICATIONS.md
 */
class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum retry attempts before moving to failed_jobs table.
     */
    public int $tries = 3;

    /**
     * Exponential backoff intervals in seconds: 1min, 5min, 15min.
     * Gives mail provider time to recover from transient failures.
     */
    public array $backoff = [60, 300, 900];

    /**
     * Silently discard job if Booking model is deleted (soft or hard).
     * Trade-off: Loses observability unless explicitly logged elsewhere.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly Booking $booking
    ) {
        $this->onQueue('notifications');
        
        // Critical: Only dispatch after DB transaction commits
        // Prevents ghost notifications when transaction rolls back
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
     * Uses branded Markdown template with idempotency guard:
     * returns null if booking status changed between queue dispatch and worker execution.
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        // Idempotency guard: booking may have been cancelled between queue and execution
        if ($this->booking->status !== BookingStatus::CONFIRMED) {
            Log::info('BookingConfirmed notification skipped - booking not confirmed', [
                'booking_id' => $this->booking->id,
                'current_status' => $this->booking->status->value,
            ]);
            return null; // Returning null skips the mail channel silently
        }

        return (new MailMessage)
            ->subject('🎉 Booking Confirmed - ' . config('email-branding.name', 'Soleil Hostel'))
            ->markdown('mail.bookings.confirmed', [
                'guestName' => e($this->booking->guest_name),
                'bookingId' => $this->booking->id,
                'roomName' => e($this->booking->room->name),
                'checkIn' => $this->booking->check_in->format('l, F j, Y'),
                'checkOut' => $this->booking->check_out->format('l, F j, Y'),
                'totalPrice' => $this->booking->amount ?? 0,
                'viewBookingUrl' => url('/bookings/' . $this->booking->id),
            ]);
    }

    /**
     * Get the array representation of the notification.
     * Used for database notification channel and logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'room_id' => $this->booking->room_id,
            'guest_name' => $this->booking->guest_name,
            'check_in' => $this->booking->check_in,
            'check_out' => $this->booking->check_out,
            'status' => $this->booking->status,
        ];
    }

    /**
     * Handle notification failure after all retries exhausted.
     * 
     * Called when job moves to failed_jobs table.
     * Log for alerting but do NOT re-queue - that creates infinite loops.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BookingConfirmed notification failed permanently', [
            'booking_id' => $this->booking->id ?? 'unknown',
            'guest_email' => $this->booking->guest_email ?? 'unknown',
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optional: Notify ops team via Slack/PagerDuty
        // Notification::route('slack', config('services.slack.ops_webhook'))
        //     ->notify(new OpsAlertNotification('Booking confirmation email failed', $exception));
    }
}
