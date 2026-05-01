<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * LockTimeoutException - Lock acquisition timed out
 *
 * Thrown when waiting for a lock exceeds the timeout.
 *
 * Retryable with longer delay to allow lock release.
 */
final class LockTimeoutException extends TransactionException
{
    protected bool $retryable = true;

    protected int $suggestedRetryDelayMs = 500; // Longer delay for lock contention

    public static function create(string $operation, int $timeoutMs, ?\Throwable $previous = null): self
    {
        return new self(
            "Lock timeout ({$timeoutMs}ms) in operation '{$operation}'. ".
            'Resource is currently locked by another transaction.',
            0,
            $previous
        );
    }
}
