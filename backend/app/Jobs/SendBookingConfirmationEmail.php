<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\BookingConfirmed;
use App\Queue\Middleware\ThrottlesPerRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * SendBookingConfirmationEmail
 *
 * Recipient-scoped queued delivery for the booking-confirmation email.
 *
 * Why this job exists (BL-4):
 *   Previously, BookingService::confirmBooking() rate-limited at the dispatch site
 *   and silently swallowed notifications when the limit was hit. A bulk admin
 *   confirm of N>5 bookings for the same guest would suppress (N-5) emails with
 *   no retry and no user-visible signal.
 *
 *   The recipient-level throttle is real and intentional (guest mailbox protection).
 *   The bug was *erasing* notifications instead of *delaying* them. This job
 *   reshapes the delivery: dispatch-on-confirm is unconditional, and recipient
 *   throttling lives in queue middleware that releases the job back to the queue
 *   when the limit is hit. Nothing is dropped.
 *
 * Holds only scalar identifiers — the Booking is re-hydrated in handle()
 * so the worker sees current DB state (including post-dispatch status changes).
 */
class SendBookingConfirmationEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Total attempts before the job moves to failed_jobs. The recipient throttle
     * releases via middleware do not consume an attempt.
     */
    public int $tries = 3;

    /**
     * Exponential backoff for transient SMTP/mail provider failures, matching
     * the existing BookingConfirmed notification's backoff.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $bookingId,
        public readonly ?int $actorId = null,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Stable recipient identity for the throttle key. The booking's user_id is
     * authoritative: the throttle protects that mailbox, not whoever happened to
     * trigger this particular send.
     *
     * Resolved lazily from the DB so the limiter sees the recipient even if the
     * job is released before handle() runs. Returns 0 only if the booking row is
     * gone, in which case handle() will short-circuit anyway.
     */
    public function recipientUserId(): int
    {
        $userId = Booking::withTrashed()
            ->whereKey($this->bookingId)
            ->value('user_id');

        return (int) ($userId ?? 0);
    }

    /**
     * Queue middleware. Recipient throttling lives here so that hitting the limit
     * RELEASES the job (re-queued with backoff) rather than silently consuming it.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ThrottlesPerRecipient('booking-confirmation-email-recipient')];
    }

    public function handle(): void
    {
        $booking = Booking::with('user')->find($this->bookingId);

        if ($booking === null) {
            // Booking force-deleted between dispatch and execution. Treat as
            // idempotent no-op — there is nothing left to confirm to anyone.
            Log::info('booking_confirmation_email.skipped_missing_booking', [
                'booking_id' => $this->bookingId,
                'actor_id' => $this->actorId,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        if ($booking->status !== BookingStatus::CONFIRMED) {
            // Booking was cancelled/refunded after dispatch. Idempotency guard.
            Log::info('booking_confirmation_email.skipped_not_confirmed', [
                'booking_id' => $booking->id,
                'current_status' => $booking->status->value,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        if ($booking->user === null) {
            // Account was deleted after the booking was created. Logged loudly:
            // an orphaned-user confirmed booking is a data-integrity signal, not
            // a routine skip.
            Log::warning('booking_confirmation_email.skipped_missing_user', [
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        // sendNow — we are already in a queue worker. Re-queuing the underlying
        // ShouldQueue notification would double-dispatch onto the queue.
        Notification::sendNow($booking->user, new BookingConfirmed($booking));

        Log::info('booking_confirmation_email.sent', [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'actor_id' => $this->actorId,
            'attempt' => $this->attempts(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('booking_confirmation_email.failed_permanently', [
            'booking_id' => $this->bookingId,
            'actor_id' => $this->actorId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
