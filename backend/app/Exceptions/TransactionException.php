<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * TransactionException - Base exception for transaction-related errors
 *
 * Provides typed exceptions for different transaction failure scenarios,
 * enabling proper error handling and retry decisions.
 */
class TransactionException extends RuntimeException
{
    protected bool $retryable = false;

    protected int $suggestedRetryDelayMs = 100;

    /**
     * Whether this exception indicates a transient failure that should be retried.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /**
     * Suggested delay before retry in milliseconds.
     */
    public function getSuggestedRetryDelayMs(): int
    {
        return $this->suggestedRetryDelayMs;
    }
}
