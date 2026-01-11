<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Booking status state machine.
 *
 * State transitions:
 * - PENDING → CONFIRMED, REFUND_PENDING, CANCELLED
 * - CONFIRMED → REFUND_PENDING, CANCELLED
 * - REFUND_PENDING → CANCELLED, REFUND_FAILED
 * - CANCELLED → (terminal)
 * - REFUND_FAILED → REFUND_PENDING, CANCELLED (retry allowed)
 */
enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REFUND_PENDING = 'refund_pending';
    case CANCELLED = 'cancelled';
    case REFUND_FAILED = 'refund_failed';

    /**
     * Check if this status allows cancellation.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
            self::REFUND_FAILED, // Retry after failure
        ], true);
    }

    /**
     * Check if this is a terminal state (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return $this === self::CANCELLED;
    }

    /**
     * Check if refund is in progress.
     */
    public function isRefundInProgress(): bool
    {
        return $this === self::REFUND_PENDING;
    }

    /**
     * Check if this status indicates an active booking.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
        ], true);
    }

    /**
     * Validate if transition to target status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PENDING => in_array($target, [
                self::CONFIRMED,
                self::REFUND_PENDING,
                self::CANCELLED,
            ], true),
            self::CONFIRMED => in_array($target, [
                self::REFUND_PENDING,
                self::CANCELLED,
            ], true),
            self::REFUND_PENDING => in_array($target, [
                self::CANCELLED,
                self::REFUND_FAILED,
            ], true),
            self::CANCELLED => false, // Terminal state
            self::REFUND_FAILED => in_array($target, [
                self::REFUND_PENDING, // Retry
                self::CANCELLED, // Force cancel without refund
            ], true),
        };
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::REFUND_PENDING => 'Refund Processing',
            self::CANCELLED => 'Cancelled',
            self::REFUND_FAILED => 'Refund Failed',
        };
    }

    /**
     * Get CSS color class for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'green',
            self::REFUND_PENDING => 'blue',
            self::CANCELLED => 'gray',
            self::REFUND_FAILED => 'red',
        };
    }
}
