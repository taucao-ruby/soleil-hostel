<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * SerializationFailureException - PostgreSQL error code 40001
 *
 * Thrown when a SERIALIZABLE transaction cannot be completed
 * because the database detected a potential serialization anomaly.
 *
 * Always retryable with exponential backoff.
 */
final class SerializationFailureException extends TransactionException
{
    protected bool $retryable = true;

    protected int $suggestedRetryDelayMs = 100;

    public static function create(string $operation, ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Serialization failure in operation '{$operation}'. ".
            'Concurrent transaction conflict detected. Safe to retry.',
            40001,
            $previous
        );

        return $exception;
    }
}
