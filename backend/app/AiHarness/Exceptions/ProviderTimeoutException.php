<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

use RuntimeException;

/**
 * Thrown when a model provider request times out.
 */
class ProviderTimeoutException extends RuntimeException
{
    public function __construct(
        public readonly string $providerName,
        public readonly int $timeoutSeconds,
        string $message = '',
    ) {
        parent::__construct($message ?: "AI provider '{$providerName}' timed out after {$timeoutSeconds}s.");
    }
}
