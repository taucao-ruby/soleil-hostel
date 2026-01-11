<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Booking;
use DomainException;

/**
 * Exception thrown when a booking cannot be cancelled.
 */
final class BookingCancellationException extends DomainException
{
    private function __construct(
        string $message,
        private readonly Booking $booking,
        private readonly string $errorCode,
    ) {
        parent::__construct($message);
    }

    /**
     * Booking status does not allow cancellation.
     */
    public static function notCancellable(Booking $booking): self
    {
        return new self(
            message: "Booking #{$booking->id} cannot be cancelled. Current status: {$booking->status->value}",
            booking: $booking,
            errorCode: 'not_cancellable',
        );
    }

    /**
     * Booking has already started (check-in date passed).
     */
    public static function alreadyStarted(Booking $booking): self
    {
        return new self(
            message: "Booking #{$booking->id} cannot be cancelled. Check-in has already started.",
            booking: $booking,
            errorCode: 'already_started',
        );
    }

    /**
     * Booking belongs to a different user.
     */
    public static function unauthorized(Booking $booking): self
    {
        return new self(
            message: "You are not authorized to cancel booking #{$booking->id}.",
            booking: $booking,
            errorCode: 'unauthorized',
        );
    }

    /**
     * Refund window has passed.
     */
    public static function refundWindowPassed(Booking $booking): self
    {
        return new self(
            message: "Refund window has passed for booking #{$booking->id}.",
            booking: $booking,
            errorCode: 'refund_window_passed',
        );
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get HTTP status code for this exception.
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'unauthorized' => 403,
            default => 422,
        };
    }
}
