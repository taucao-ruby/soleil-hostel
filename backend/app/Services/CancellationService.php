<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\TransactionMetrics;
use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Exceptions\BookingCancellationException;
use App\Exceptions\DepositTransitionException;
use App\Exceptions\RefundFailedException;
use App\Jobs\ProcessDepositRefund;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
 *
 * Transaction Isolation:
 * - Uses pessimistic locking (FOR UPDATE) for cancellation
 * - READ COMMITTED isolation is sufficient with explicit locks
 * - Refund initiation is gated by the refund_pending state transition
 *
 * Data Invariants:
 * - Each refund processed exactly once per booking
 * - Status transitions follow state machine rules
 * - Refund amount matches cancellation policy
 */
final class CancellationService
{
    /**
     * Cancel a booking with optional refund.
     *
     * @param  Booking  $booking  The booking to cancel
     * @param  User  $actor  The user initiating the cancellation
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

        // Phase 3: Transition the deposit lifecycle (CONC-005).
        // Booking has reached its terminal cancellation state by now; the
        // deposit must follow so it cannot linger in 'collected' state.
        $this->transitionDepositForCancellation($booking, $actor);

        Log::info('Booking cancelled successfully', [
            'booking_id' => $booking->id,
            'actor_id' => $actor->id,
            'refund_amount' => $booking->refund_amount,
            'refund_status' => $booking->refund_status,
            'deposit_status' => $booking->fresh()?->deposit_status?->value,
        ]);

        return $booking;
    }

    /**
     * Apply the cancellation policy to the booking's deposit (CONC-005).
     *
     * No-op when there is no held deposit. Otherwise:
     *  1. Compute CancellationPolicy from the booking.
     *  2. Call $booking->deposit->transitionTo() — writes a deposit_events row
     *     and transitions deposit_status to REFUNDED / PARTIAL_REFUND / FORFEITED.
     *  3. If refundPercent > 0, dispatch ProcessDepositRefund to issue the
     *     Stripe refund asynchronously.
     */
    private function transitionDepositForCancellation(Booking $booking, User $actor): void
    {
        $deposit = $booking->fresh()?->deposit;
        if ($deposit === null || ! $deposit->isHeld()) {
            return;
        }

        $policy = $booking->cancellationPolicy();

        try {
            $event = $deposit->transitionTo(
                refundPercent: $policy->refundPercent,
                reason: $policy->reason,
                actor: $actor,
                metadata: [
                    'hours_until_check_in' => $policy->hoursUntilCheckIn,
                    'booking_status' => $booking->status->value,
                ],
            );
        } catch (DepositTransitionException $e) {
            Log::warning('Deposit transition skipped', [
                'booking_id' => $booking->id,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        if ($policy->refundPercent <= 0) {
            return;
        }

        $refundAmount = (int) ($event->refund_amount ?? 0);
        if ($refundAmount <= 0) {
            return;
        }

        ProcessDepositRefund::dispatch(
            bookingId: $booking->id,
            depositEventId: $event->id,
            refundAmount: $refundAmount,
            reason: $policy->reason,
        );
    }

    /**
     * Validate that the booking can be cancelled.
     *
     * Defense-in-depth ownership gate: BookingPolicy::cancel and the
     * controller-level Gate::authorize('cancel', $booking) are the primary
     * authorization barrier. This service-layer check protects code paths
     * that reach cancellation without going through that policy — most
     * notably ProposalConfirmationController::executeCancellation, which
     * passes a confirmed proposer id straight into the service. Without
     * this check, an authenticated user who could craft (or replay) a
     * proposal naming someone else's booking_id would be able to cancel
     * that booking even though the controller layer never invoked the
     * cancel policy. Admins remain exempt — they can cancel any booking,
     * matching BookingPolicy::cancel.
     *
     * @throws BookingCancellationException
     */
    private function validateCancellation(Booking $booking, User $actor): void
    {
        if (! $actor->isAdmin() && (int) $booking->user_id !== (int) $actor->id) {
            throw BookingCancellationException::unauthorized($booking);
        }

        if (! $booking->status->isCancellable()) {
            throw BookingCancellationException::notCancellable($booking);
        }

        // Check if booking has already started (unless config allows or actor is admin)
        if ($booking->isStarted() && ! config('booking.cancellation.allow_after_checkin') && ! $actor->isAdmin()) {
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

            if ($locked === null) {
                throw BookingCancellationException::notCancellable($booking);
            }

            // Re-check status after acquiring lock (another request may have completed)
            if ($locked->status === BookingStatus::CANCELLED) {
                return $locked;
            }

            if (! $locked->status->isCancellable()) {
                throw BookingCancellationException::notCancellable($locked);
            }

            $isRefundable = $this->isRefundable($locked);
            $newStatus = $isRefundable
                ? BookingStatus::REFUND_PENDING
                : BookingStatus::CANCELLED;

            $locked = $locked->transitionTo($newStatus, $actor);
            $locked->update(array_merge([
                'cancelled_at' => now(),
            ], $this->cancellationActorSnapshot($actor)));

            // If not refundable, we can dispatch the event now
            if (! $isRefundable) {
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
     * Idempotency:
     * - The booking is moved to refund_pending under a row lock before this method runs.
     * - Concurrent or replayed cancellation attempts re-check that state and do not call Stripe.
     * - Stripe refund webhook replays are persisted in stripe_refund_events.
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
            $refund = $booking->user->refund(
                $booking->payment_intent_id,
                ['amount' => $refundAmount]
            );

            TransactionMetrics::recordSuccess(
                'process_refund',
                'external_api',
                0,
                0
            );

            return $this->finalizeCancellation(
                $booking,
                $refund->id,
                $refundAmount
            );

            // TODO: Add Cashier exception handling when payment integration is implemented
            // } catch (\Laravel\Cashier\Exceptions\IncompletePayment $e) {
            //     return $this->handleRefundFailure($booking, $e);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            /** @var \Throwable $e */
            return $this->handleRefundFailure($booking, $e);
        }
    }

    /**
     * Calculate refund amount based on cancellation policy.
     *
     * Delegates to the Booking model's calculateRefundAmount() method
     * which is the single source of truth for refund policy.
     *
     * @return int Amount in cents
     */
    private function calculateRefundAmount(Booking $booking): int
    {
        return $booking->calculateRefundAmount();
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
            $booking = $booking->transitionTo(BookingStatus::CANCELLED);
            $booking->update([
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
        $booking = $booking->transitionTo(BookingStatus::REFUND_FAILED);
        $booking->update([
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
        $cancelled = DB::transaction(function () use ($booking, $actor, $reason) {
            $locked = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return $booking;
            }

            if ($locked->status === BookingStatus::CANCELLED) {
                return $locked;
            }

            $locked = $locked->transitionTo(BookingStatus::CANCELLED, $actor);
            $locked->update(array_merge([
                'cancelled_at' => now(),
                'refund_error' => "Force cancelled: {$reason}",
            ], $this->cancellationActorSnapshot($actor)));

            event(new BookingCancelled($locked));

            Log::warning('Booking force cancelled', [
                'booking_id' => $locked->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
            ]);

            return $locked->fresh();
        });

        // Force cancel always forfeits a held deposit — the cancellation
        // policy is intentionally bypassed at the booking layer, but the
        // deposit FSM must still resolve away from 'collected' (CONC-005).
        $this->forfeitHeldDepositOnForceCancel($cancelled, $actor, $reason);

        return $cancelled;
    }

    private function forfeitHeldDepositOnForceCancel(Booking $booking, User $actor, string $reason): void
    {
        $fresh = $booking->fresh();
        if ($fresh === null) {
            return;
        }
        $deposit = $fresh->deposit;
        if (! $deposit->isHeld()) {
            return;
        }

        try {
            $deposit->transitionTo(
                refundPercent: 0,
                reason: 'force_cancelled:'.$reason,
                actor: $actor,
                metadata: ['booking_status' => $fresh->status->value],
            );
        } catch (DepositTransitionException $e) {
            Log::warning('Force-cancel deposit forfeit skipped', [
                'booking_id' => $booking->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{cancelled_by: int, cancelled_by_email: string, cancelled_by_role: string|null, cancelled_by_display: string|null}
     */
    private function cancellationActorSnapshot(User $actor): array
    {
        $cancelledBy = (int) $actor->id;

        $email = $actor->email;
        if ($email === '') {
            throw new \LogicException('Cancellation actor must have a valid email.');
        }

        $role = $actor->role;
        $roleValue = $role->value;

        $name = $actor->name;

        return [
            'cancelled_by' => $cancelledBy,
            'cancelled_by_email' => $email,
            'cancelled_by_role' => $roleValue,
            'cancelled_by_display' => $name,
        ];
    }
}
