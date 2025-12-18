<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Illuminate\Database\Eloquent\Model;

/**
 * Exception thrown when an optimistic lock conflict is detected.
 *
 * This exception is raised when attempting to update a resource that has been
 * modified by another process/user since it was last read. This prevents the
 * "lost update" problem in concurrent systems.
 *
 * Scenario:
 * 1. User A reads Room (version 5)
 * 2. User B reads Room (version 5)
 * 3. User B updates Room → version becomes 6
 * 4. User A tries to update with version 5 → OptimisticLockException
 *
 * The client should:
 * 1. Inform the user that the resource was modified
 * 2. Refresh the data from the server
 * 3. Allow the user to re-apply their changes
 *
 * HTTP Status Code: 409 Conflict
 *
 * @see App\Services\RoomService::updateWithOptimisticLock()
 */
class OptimisticLockException extends RuntimeException
{
    /**
     * HTTP status code for this exception.
     */
    public const HTTP_STATUS_CODE = 409;

    /**
     * Create a new OptimisticLockException.
     *
     * Uses PHP 8.3 constructor property promotion for cleaner code.
     *
     * @param string     $message          The exception message
     * @param Model|null $model            The model that failed the lock check
     * @param int|null   $expectedVersion  The version the client expected
     * @param int|null   $actualVersion    The actual version in the database
     */
    public function __construct(
        string $message = 'The resource has been modified by another user. Please refresh and try again.',
        public readonly ?Model $model = null,
        public readonly ?int $expectedVersion = null,
        public readonly ?int $actualVersion = null,
    ) {
        parent::__construct($message, self::HTTP_STATUS_CODE);
    }

    /**
     * Get a detailed message for logging purposes.
     *
     * Includes model class, ID, expected version, and actual version.
     */
    public function getDetailedMessage(): string
    {
        $details = [];

        if ($this->model) {
            $details[] = sprintf(
                'Model: %s (ID: %s)',
                get_class($this->model),
                $this->model->getKey()
            );
        }

        if ($this->expectedVersion !== null) {
            $details[] = "Expected version: {$this->expectedVersion}";
        }

        if ($this->actualVersion !== null) {
            $details[] = "Actual version: {$this->actualVersion}";
        }

        return $this->getMessage() . ' | ' . implode(' | ', $details);
    }

    /**
     * Create an exception for a specific Room model.
     *
     * Factory method for creating room-specific lock exceptions.
     *
     * @param Model    $room             The Room model instance
     * @param int|null $expectedVersion  The version the client expected
     * @param int|null $actualVersion    The actual version in the database
     */
    public static function forRoom(
        Model $room,
        ?int $expectedVersion = null,
        ?int $actualVersion = null
    ): self {
        return new self(
            'The room has been modified by another user. Please refresh and try again.',
            $room,
            $expectedVersion,
            $actualVersion
        );
    }
}
