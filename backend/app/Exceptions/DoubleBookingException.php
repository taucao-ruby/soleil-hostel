<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * DoubleBookingException - Attempt to double-book a resource
 *
 * Thrown when a booking conflicts with an existing booking.
 *
 * Not retryable - user must select different dates.
 *
 * Data invariant: No overlapping bookings for same room
 */
final class DoubleBookingException extends TransactionException
{
    protected bool $retryable = false;

    public static function create(
        int $roomId,
        string $checkIn,
        string $checkOut,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Room #{$roomId} is already booked for the period {$checkIn} to {$checkOut}. ".
            'Please select different dates.',
            0,
            $previous
        );
    }
}
