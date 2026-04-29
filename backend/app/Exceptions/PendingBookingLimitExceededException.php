<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class PendingBookingLimitExceededException extends RuntimeException
{
    public const ERROR_CODE = 'PENDING_LIMIT_EXCEEDED';

    public function __construct()
    {
        parent::__construct('You have reached the maximum number of pending bookings.');
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
