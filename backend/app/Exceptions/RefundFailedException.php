<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Booking;
use RuntimeException;

/**
 * Exception thrown when a refund operation fails.
 */
final class RefundFailedException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly Booking $booking,
        private readonly string $errorCode,
        private readonly ?string $stripeErrorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create from Stripe or Cashier exception.
     */
    public static function fromException(Booking $booking, \Throwable $e): self
    {
        $stripeErrorCode = null;

        if ($e instanceof \Stripe\Exception\ApiErrorException) {
            $stripeErrorCode = $e->getStripeCode();
        }

        return new self(
            message: "Refund failed for booking #{$booking->id}: {$e->getMessage()}",
            booking: $booking,
            errorCode: 'refund_failed',
            stripeErrorCode: $stripeErrorCode,
            previous: $e,
        );
    }

    /**
     * Refund already exists for this booking.
     */
    public static function alreadyRefunded(Booking $booking): self
    {
        return new self(
            message: "Booking #{$booking->id} has already been refunded.",
            booking: $booking,
            errorCode: 'already_refunded',
        );
    }

    /**
     * No payment exists to refund.
     */
    public static function noPayment(Booking $booking): self
    {
        return new self(
            message: "Booking #{$booking->id} has no payment to refund.",
            booking: $booking,
            errorCode: 'no_payment',
        );
    }

    /**
     * Refund amount exceeds available balance.
     */
    public static function insufficientBalance(Booking $booking): self
    {
        return new self(
            message: "Insufficient balance to refund booking #{$booking->id}.",
            booking: $booking,
            errorCode: 'insufficient_balance',
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

    public function getStripeErrorCode(): ?string
    {
        return $this->stripeErrorCode;
    }

    /**
     * Check if this error is retryable.
     */
    public function isRetryable(): bool
    {
        // Stripe rate limits and temporary failures are retryable
        $retryableCodes = [
            'rate_limit',
            'api_connection_error',
            'api_error',
            'lock_timeout',
        ];

        return in_array($this->stripeErrorCode, $retryableCodes, true);
    }

    /**
     * Get HTTP status code for this exception.
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'already_refunded', 'no_payment' => 422,
            'insufficient_balance' => 422,
            default => 502, // Bad Gateway for external service failures
        };
    }
}
