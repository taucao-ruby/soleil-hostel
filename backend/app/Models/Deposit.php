<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use App\Exceptions\DepositTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Deposit value object bound to a single Booking row (CONC-005).
 *
 * Deposit data is denormalised onto bookings (deposit_amount, deposit_status,
 * deposit_collected_at). This class is the only writer for those columns once
 * a deposit is held — it enforces the deposit FSM and writes an append-only
 * row to deposit_events for every transition.
 *
 * Construction:
 *   $deposit = $booking->deposit;            // accessor on Booking
 *
 * Transitioning:
 *   $deposit->transitionTo(
 *       refundPercent: 100,                  // 0..100, derived from CancellationPolicy
 *       reason: 'cancelled_within_full_refund_window',
 *       actor: $user,
 *   );
 *
 * After a successful call the booking's deposit_status reflects the BUSINESS
 * decision (REFUNDED / PARTIAL_REFUND / FORFEITED). The actual Stripe call,
 * if any, is performed asynchronously by ProcessDepositRefund — the row in
 * deposit_events is the durable record of the decision.
 */
final class Deposit
{
    public function __construct(
        public readonly Booking $booking,
    ) {}

    public function status(): DepositStatus
    {
        $status = $this->booking->deposit_status;
        if ($status instanceof DepositStatus) {
            return $status;
        }

        // Defensive: factory paths or raw arrays may yield a string.
        return DepositStatus::from((string) $status);
    }

    public function amount(): int
    {
        return (int) ($this->booking->deposit_amount ?? 0);
    }

    public function isHeld(): bool
    {
        return $this->status() === DepositStatus::COLLECTED;
    }

    public function exists(): bool
    {
        return $this->status() !== DepositStatus::NONE && $this->amount() > 0;
    }

    /**
     * Apply a deposit transition driven by a cancellation policy.
     *
     * Steps (single DB transaction):
     *  1. Validate that the booking has a held deposit.
     *  2. Derive the target status from refundPercent.
     *  3. Validate the FSM transition.
     *  4. Persist the new status + cancellation reason on the booking row.
     *  5. Append a deposit_events row capturing from_status, to_status,
     *     refund_percent, refund_amount, reason, and actor snapshot.
     *
     * Idempotency: calling transitionTo twice with the same policy on a
     * booking that is already in the resolved terminal status is a no-op
     * and does NOT write a duplicate event.
     *
     * @param  int  $refundPercent  0..100
     * @param  string  $reason  short policy reason ('cancelled_within_full_refund_window' etc.)
     * @param  User  $actor  the user initiating the transition
     * @param  array<string, mixed>  $metadata  optional extra audit data
     */
    public function transitionTo(
        int $refundPercent,
        string $reason,
        User $actor,
        array $metadata = [],
    ): DepositEvent {
        if ($refundPercent < 0 || $refundPercent > 100) {
            throw new \InvalidArgumentException(
                "refundPercent must be between 0 and 100, got {$refundPercent}",
            );
        }

        if (! $this->exists()) {
            throw DepositTransitionException::notHeld($this->booking);
        }

        $target = self::resolveTargetStatus($refundPercent);
        $current = $this->status();

        if ($current === $target) {
            // Idempotent no-op: do not write a duplicate event.
            return $this->lastEventForCurrentStatus();
        }

        if (! $current->canTransitionTo($target)) {
            throw DepositTransitionException::illegalTransition(
                $this->booking,
                $current,
                $target,
            );
        }

        $refundAmount = self::computeRefundAmount($this->amount(), $refundPercent);

        return DB::transaction(function () use (
            $current,
            $target,
            $refundPercent,
            $refundAmount,
            $reason,
            $actor,
            $metadata,
        ): DepositEvent {
            // Lock the booking row so deposit_status updates are serialised
            // with any other booking writers (e.g. CancellationService).
            $locked = Booking::query()
                ->whereKey($this->booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check status under the lock to defeat lost-update races.
            $lockedStatus = $locked->deposit_status instanceof DepositStatus
                ? $locked->deposit_status
                : DepositStatus::from((string) $locked->deposit_status);

            if ($lockedStatus === $target) {
                return $this->lastEventForCurrentStatus();
            }

            if (! $lockedStatus->canTransitionTo($target)) {
                throw DepositTransitionException::illegalTransition(
                    $locked,
                    $lockedStatus,
                    $target,
                );
            }

            $locked->forceFill([
                'deposit_status' => $target,
                'cancellation_reason' => $locked->cancellation_reason ?? $reason,
            ])->save();

            $event = DepositEvent::create([
                'booking_id' => $locked->id,
                'from_status' => $current,
                'to_status' => $target,
                'refund_percent' => $refundPercent,
                'refund_amount' => $refundAmount,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role->value,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            // Refresh the in-memory booking attached to this value object so
            // callers see the new status without re-querying.
            $this->booking->setRawAttributes($locked->getRawOriginal(), true);

            return $event;
        });
    }

    /**
     * Map a refund percentage to its terminal deposit state.
     */
    public static function resolveTargetStatus(int $refundPercent): DepositStatus
    {
        return match (true) {
            $refundPercent === 100 => DepositStatus::REFUNDED,
            $refundPercent === 0 => DepositStatus::FORFEITED,
            default => DepositStatus::PARTIAL_REFUND,
        };
    }

    /**
     * Compute the refund amount in cents for a given deposit + percent.
     *
     * Uses integer division on the cents value to avoid float rounding.
     */
    public static function computeRefundAmount(int $depositAmount, int $refundPercent): int
    {
        if ($depositAmount <= 0 || $refundPercent <= 0) {
            return 0;
        }

        return intdiv($depositAmount * $refundPercent, 100);
    }

    private function lastEventForCurrentStatus(): DepositEvent
    {
        return DepositEvent::query()
            ->where('booking_id', $this->booking->id)
            ->where('to_status', $this->status())
            ->latest('id')
            ->firstOrFail();
    }
}
