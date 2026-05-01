<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * ConcurrencyException - General concurrency conflict
 *
 * Thrown when optimistic locking or other concurrency checks fail.
 *
 * May or may not be retryable depending on the scenario.
 */
final class ConcurrencyException extends TransactionException
{
    protected bool $retryable = false;

    public static function staleData(string $resource, int $expectedVersion, int $actualVersion): self
    {
        $exception = new self(
            "Concurrency conflict for {$resource}: ".
            "expected version {$expectedVersion}, but found version {$actualVersion}. ".
            'The resource was modified by another user. Please refresh and try again.'
        );
        $exception->retryable = false; // Requires user intervention

        return $exception;
    }

    public static function resourceNotAvailable(string $resource, string $reason): self
    {
        return new self(
            "Resource '{$resource}' is not available: {$reason}"
        );
    }
}
