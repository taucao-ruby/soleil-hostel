<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

use RuntimeException;

/**
 * Thrown when a BLOCKED tool is proposed by the model.
 */
class BlockedToolException extends RuntimeException
{
    public function __construct(
        public readonly string $toolName,
        string $message = '',
    ) {
        parent::__construct($message ?: "Blocked tool '{$toolName}' was proposed by model and rejected.");
    }
}
