<?php

declare(strict_types=1);

namespace App\Booking;

/**
 * Deterministic cancellation policy snapshot.
 *
 * Computed once at the moment of cancellation (CONC-005) so that the deposit
 * transition recorded in deposit_events is reproducible and not subject to
 * clock drift between the booking transition and the deposit transition.
 */
final class CancellationPolicy
{
    public function __construct(
        public readonly int $refundPercent,
        public readonly string $reason,
        public readonly int $hoursUntilCheckIn,
    ) {
        if ($refundPercent < 0 || $refundPercent > 100) {
            throw new \InvalidArgumentException(
                "refundPercent must be between 0 and 100, got {$refundPercent}",
            );
        }
    }

    public function isFullRefund(): bool
    {
        return $this->refundPercent === 100;
    }

    public function isPartialRefund(): bool
    {
        return $this->refundPercent > 0 && $this->refundPercent < 100;
    }

    public function isForfeit(): bool
    {
        return $this->refundPercent === 0;
    }
}
