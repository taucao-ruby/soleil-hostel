<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Booking;
use DomainException;

/**
 * Thrown when a trashed booking cannot be restored because its date range
 * overlaps with an existing active booking.
 *
 * Raised by BookingService::restore() inside the DB transaction, after the
 * lock-aware overlap check confirms a conflict. The controller maps this to
 * a 422 Unprocessable Entity response.
 *
 * Distinct from QueryException(23P01), which is the PostgreSQL exclusion-
 * constraint backstop that fires only when a concurrent restore races through
 * the lock-aware check window. That case is mapped to 409 Conflict.
 */
final class BookingRestoreConflictException extends DomainException
{
    public function __construct(private readonly Booking $booking)
    {
        parent::__construct(
            "Booking #{$booking->id} cannot be restored: date range conflicts with existing bookings."
        );
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }
}
