<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\DepositStatus;
use App\Models\Booking;
use RuntimeException;

final class DepositTransitionException extends RuntimeException
{
    public function __construct(
        private readonly Booking $booking,
        private readonly ?DepositStatus $from,
        private readonly ?DepositStatus $to,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notHeld(Booking $booking): self
    {
        return new self(
            $booking,
            null,
            null,
            sprintf(
                'Booking #%d does not have a held deposit; cannot transition.',
                $booking->id,
            ),
        );
    }

    public static function illegalTransition(
        Booking $booking,
        DepositStatus $from,
        DepositStatus $to,
    ): self {
        return new self(
            $booking,
            $from,
            $to,
            sprintf(
                "Booking #%d deposit cannot transition from '%s' to '%s'.",
                $booking->id,
                $from->value,
                $to->value,
            ),
        );
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function getFrom(): ?DepositStatus
    {
        return $this->from;
    }

    public function getTo(): ?DepositStatus
    {
        return $this->to;
    }
}
