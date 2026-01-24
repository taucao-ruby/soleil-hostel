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
            "Serialization failure in operation '{$operation}'. " .
            "Concurrent transaction conflict detected. Safe to retry.",
            40001,
            $previous
        );
        
        return $exception;
    }
}

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
            "Deadlock detected in operation '{$operation}'. " .
            "Transaction was rolled back. Safe to retry immediately.",
            40001, // Using the code as int
            $previous
        );
    }
}

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
            "Lock timeout ({$timeoutMs}ms) in operation '{$operation}'. " .
            "Resource is currently locked by another transaction.",
            0,
            $previous
        );
    }
}

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
            "Concurrency conflict for {$resource}: " .
            "expected version {$expectedVersion}, but found version {$actualVersion}. " .
            "The resource was modified by another user. Please refresh and try again."
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

/**
 * InsufficientInventoryException - Stock/availability check failed
 * 
 * Thrown when attempting to book/reserve more items than available.
 * 
 * Not retryable - requires user to change their request.
 * 
 * Data invariant: inventory.available >= 0
 */
final class InsufficientInventoryException extends TransactionException
{
    protected bool $retryable = false;

    private int $requested;
    private int $available;
    private int $resourceId;

    public static function create(
        int $resourceId,
        int $requested,
        int $available,
        string $resourceType = 'room'
    ): self {
        $exception = new self(
            "Insufficient availability for {$resourceType} #{$resourceId}: " .
            "requested {$requested}, but only {$available} available."
        );
        
        $exception->resourceId = $resourceId;
        $exception->requested = $requested;
        $exception->available = $available;
        
        return $exception;
    }

    public function getRequested(): int
    {
        return $this->requested;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }

    public function getResourceId(): int
    {
        return $this->resourceId;
    }
}

/**
 * DoubleBookingException - Attempt to double-book a resource
 * 
 * Thrown when a booking conflicts with an existing booking.
 * 
 * Not retryable - user must select different dates.
 * 
 * Data invariant: No overlapping bookings for same room
 */
final class DoubleBookingException extends TransactionException
{
    protected bool $retryable = false;

    public static function create(
        int $roomId,
        string $checkIn,
        string $checkOut,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Room #{$roomId} is already booked for the period {$checkIn} to {$checkOut}. " .
            "Please select different dates.",
            0,
            $previous
        );
    }
}

/**
 * DuplicateOperationException - Idempotency violation
 * 
 * Thrown when attempting to perform an operation that has already been completed.
 * 
 * Not retryable - the operation was already successful.
 * 
 * Data invariant: Each payment/refund processed exactly once
 */
final class DuplicateOperationException extends TransactionException
{
    protected bool $retryable = false;

    private mixed $existingResult;

    public static function create(string $operation, string $key, mixed $existingResult = null): self
    {
        $exception = new self(
            "Operation '{$operation}' with key '{$key}' has already been completed. " .
            "Returning cached result."
        );
        
        $exception->existingResult = $existingResult;
        
        return $exception;
    }

    public function getExistingResult(): mixed
    {
        return $this->existingResult;
    }
}
