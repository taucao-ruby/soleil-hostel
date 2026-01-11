<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Exceptions\BookingCancellationException;
use App\Exceptions\RefundFailedException;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

/**
 * Handles booking cancellation with optional refund processing.
 *
 * Flow:
 * 1. Validate cancellation eligibility
 * 2. Lock booking and transition to refund_pending (if refundable)
 * 3. Process refund via Stripe (outside transaction)
 * 4. Finalize cancellation with refund result
 * 5. Dispatch notification
 *
 * Design decisions:
 * - Synchronous refund: User receives immediate confirmation
 * - Two-phase commit: DB lock → Stripe → DB update
 * - Stripe call outside transaction to avoid holding locks during I/O
 * - Intermediate states for crash recovery (refund_pending, refund_failed)
 */
final class CancellationService
{
    /**
     * Cancel a booking with optional refund.
     *
     * @param Booking $booking The booking to cancel
     * @param User $actor The user initiating the cancellation
     * @return Booking The updated booking
     *
     * @throws BookingCancellationException If booking cannot be cancelled
     * @throws RefundFailedException If refund processing fails
     */
    public function cancel(Booking $booking, User $actor): Booking
    {
        // Idempotency: already cancelled bookings return immediately
        if ($booking->status === BookingStatus::CANCELLED) {
            Log::info('Cancellation skipped: already cancelled', [
                'booking_id' => $booking->id,
                'actor_id' => $actor->id,
            ]);

            return $booking->fresh();
        }

        // Validate cancellation is allowed
        $this->validateCancellation($booking, $actor);

        // Phase 1: Lock and mark as refund_pending (or cancelled if no payment)
        $booking = $this->transitionToRefundPending($booking, $actor);

        // Phase 2: Process refund if applicable (outside transaction)
        if ($booking->status === BookingStatus::REFUND_PENDING) {
            $booking = $this->processRefund($booking);
        }

        Log::info('Booking cancelled successfully', [
            'booking_id' => $booking->id,
            'actor_id' => $actor->id,
            'refund_amount' => $booking->refund_amount,
            'refund_status' => $booking->refund_status,
        ]);

        return $booking;
    }

    /**
     * Validate that the booking can be cancelled.
     *
     * @throws BookingCancellationException
     */
    private function validateCancellation(Booking $booking, User $actor): void
    {
        if (!$booking->status->isCancellable()) {
            throw BookingCancellationException::notCancellable($booking);
        }

        // Check if booking has already started (unless config allows or actor is admin)
        if ($booking->isStarted() && !config('booking.cancellation.allow_after_checkin') && !$actor->isAdmin()) {
            throw BookingCancellationException::alreadyStarted($booking);
        }
    }

    /**
     * Phase 1: Acquire lock and transition to intermediate state.
     *
     * Uses pessimistic locking to prevent concurrent cancellation attempts.
     * Status becomes refund_pending if refundable, otherwise cancelled.
     */
    private function transitionToRefundPending(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            // Acquire exclusive lock
            $locked = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            // Re-check status after acquiring lock (another request may have completed)
            if ($locked->status === BookingStatus::CANCELLED) {
                return $locked;
            }

            if (!$locked->status->isCancellable()) {
                throw BookingCancellationException::notCancellable($locked);
            }

            $isRefundable = $this->isRefundable($locked);
            $newStatus = $isRefundable
                ? BookingStatus::REFUND_PENDING
                : BookingStatus::CANCELLED;

            $locked->update([
                'status' => $newStatus,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);

            // If not refundable, we can dispatch the event now
            if (!$isRefundable) {
                event(new BookingCancelled($locked));
            }

            return $locked->fresh();
        });
    }

    /**
     * Check if booking has a refundable payment.
     */
    private function isRefundable(Booking $booking): bool
    {
        return $booking->payment_intent_id !== null
            && $booking->refund_id === null
            && $booking->status->isCancellable();
    }

    /**
     * Phase 2: Process refund via Stripe.
     *
     * This method is intentionally NOT wrapped in a transaction because:
     * 1. Stripe calls are network I/O - holding locks during I/O is bad practice
     * 2. If process crashes after Stripe success but before DB update,
     *    ReconcileRefundsJob will recover the state
     *
     * @throws RefundFailedException If Stripe refund fails
     */
    private function processRefund(Booking $booking): Booking
    {
        $refundAmount = $this->calculateRefundAmount($booking);

        // No refund amount = cancel without refund
        if ($refundAmount === 0) {
            return $this->finalizeCancellation($booking, null, 0);
        }

        try {
            // Call Stripe via Laravel Cashier
            $refund = $booking->user->refund(
                $booking->payment_intent_id,
                ['amount' => $refundAmount]
            );

            return $this->finalizeCancellation($booking, $refund->id, $refundAmount);

        } catch (IncompletePayment $e) {
            return $this->handleRefundFailure($booking, $e);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->handleRefundFailure($booking, $e);
        }
    }

    /**
     * Calculate refund amount based on cancellation policy.
     *
     * @return int Amount in cents
     */
    private function calculateRefundAmount(Booking $booking): int
    {
        // Use model method if available, otherwise calculate here
        if (method_exists($booking, 'calculateRefundAmount')) {
            return $booking->calculateRefundAmount();
        }

        $hoursUntilCheckIn = now()->diffInHours($booking->check_in, false);
        $config = config('booking.cancellation');

        // Before check-in date
        if ($hoursUntilCheckIn < 0) {
            return 0;
        }

        // Full refund window
        if ($hoursUntilCheckIn >= $config['full_refund_hours']) {
            $refundPct = 100;
        }
        // Partial refund window
        elseif ($hoursUntilCheckIn >= $config['partial_refund_hours']) {
            $refundPct = $config['partial_refund_pct'];
        }
        // No refund
        else {
            return 0;
        }

        // Apply cancellation fee if enabled
        if ($config['allow_fee']) {
            $refundPct -= $config['fee_pct'];
        }

        // Assuming 'amount' field exists on booking (in cents)
        $bookingAmount = $booking->amount ?? 0;

        return (int) ($bookingAmount * $refundPct / 100);
    }

    /**
     * Finalize cancellation after successful refund (or no refund needed).
     */
    private function finalizeCancellation(
        Booking $booking,
        ?string $refundId,
        int $refundAmount
    ): Booking {
        return DB::transaction(function () use ($booking, $refundId, $refundAmount) {
            $booking->update([
                'status' => BookingStatus::CANCELLED,
                'refund_id' => $refundId,
                'refund_status' => $refundId ? 'succeeded' : null,
                'refund_amount' => $refundAmount ?: null,
                'refund_error' => null,
            ]);

            // Dispatch event (notification listener will pick this up)
            event(new BookingCancelled($booking));

            return $booking->fresh();
        });
    }

    /**
     * Handle refund failure: update booking state and re-throw.
     *
     * @throws RefundFailedException
     */
    private function handleRefundFailure(Booking $booking, \Throwable $e): Booking
    {
        Log::error('Refund failed', [
            'booking_id' => $booking->id,
            'payment_intent_id' => $booking->payment_intent_id,
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
        ]);

        // Update to failed state (allows retry)
        $booking->update([
            'status' => BookingStatus::REFUND_FAILED,
            'refund_status' => 'failed',
            'refund_error' => substr($e->getMessage(), 0, 1000),
        ]);

        throw RefundFailedException::fromException($booking, $e);
    }

    /**
     * Force cancel a booking without refund.
     *
     * For admin use or when refund is explicitly waived.
     */
    public function forceCancel(Booking $booking, User $actor, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $locked = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status === BookingStatus::CANCELLED) {
                return $locked;
            }

            $locked->update([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'refund_error' => "Force cancelled: {$reason}",
            ]);

            event(new BookingCancelled($locked));

            Log::warning('Booking force cancelled', [
                'booking_id' => $locked->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }
}
