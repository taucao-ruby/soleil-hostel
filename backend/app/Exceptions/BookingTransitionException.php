<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\BookingStatus;
use App\Models\Booking;
use RuntimeException;

final class BookingTransitionException extends RuntimeException
{
    public function __construct(
        private readonly Booking $booking,
        private readonly BookingStatus $from,
        private readonly BookingStatus $to,
    ) {
        parent::__construct(sprintf(
            "Booking #%d cannot transition from '%s' to '%s'.",
            $booking->id,
            $from->value,
            $to->value,
        ));
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function getFrom(): BookingStatus
    {
        return $this->from;
    }

    public function getTo(): BookingStatus
    {
        return $this->to;
    }
}
