<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Booking deposit lifecycle.
 *
 * deposit_amount is an unearned-revenue/liability signal at collection time.
 * It is operational tracking, not ledger-based revenue recognition.
 *
 * State machine (post CONC-005):
 * - NONE: no deposit was ever collected (no transitions possible)
 * - COLLECTED ("held"): deposit captured, awaiting fulfilment or cancellation
 *     -> APPLIED        (deposit consumed against the stay)
 *     -> REFUNDED       (full refund issued)
 *     -> PARTIAL_REFUND (partial refund issued per cancellation policy)
 *     -> FORFEITED      (no refund — late cancel / policy violation)
 * - APPLIED, REFUNDED, PARTIAL_REFUND, FORFEITED: terminal
 */
enum DepositStatus: string
{
    case NONE = 'none';
    case COLLECTED = 'collected';
    case APPLIED = 'applied';
    case REFUNDED = 'refunded';
    case PARTIAL_REFUND = 'partial_refund';
    case FORFEITED = 'forfeited';

    /**
     * Whether this state is terminal (no transitions out).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::APPLIED, self::REFUNDED, self::PARTIAL_REFUND, self::FORFEITED => true,
            default => false,
        };
    }

    /**
     * Whether the deposit is currently held against the booking.
     *
     * COLLECTED is the canonical "held" state in this state machine.
     */
    public function isHeld(): bool
    {
        return $this === self::COLLECTED;
    }

    /**
     * Whether transitioning from $this to $target is permitted by the deposit FSM.
     *
     * Only COLLECTED can transition to a refund/forfeit terminal state.
     * APPLIED is reached from COLLECTED via the stay fulfilment path, not
     * through cancellation, so it is not modelled here.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::COLLECTED => in_array(
                $target,
                [self::REFUNDED, self::PARTIAL_REFUND, self::FORFEITED, self::APPLIED],
                true,
            ),
            default => false,
        };
    }
}
