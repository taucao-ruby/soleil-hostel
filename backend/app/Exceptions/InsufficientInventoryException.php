<?php

declare(strict_types=1);

namespace App\Exceptions;

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
            "Insufficient availability for {$resourceType} #{$resourceId}: ".
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
