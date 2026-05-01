<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * DeadlockException - PostgreSQL error code 40P01
 *
 * Thrown when the database detects a deadlock between transactions.
 *
 * Always retryable with minimal delay.
 */
final class DeadlockException extends TransactionException
{
    protected bool $retryable = true;

    protected int $suggestedRetryDelayMs = 10; // Quick retry for deadlocks

    public static function create(string $operation, ?\Throwable $previous = null): self
    {
        return new self(
            "Deadlock detected in operation '{$operation}'. ".
            'Transaction was rolled back. Safe to retry immediately.',
            40001, // Using the code as int
            $previous
        );
    }
}
