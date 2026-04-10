<?php

declare(strict_types=1);

namespace App\AiHarness\Exceptions;

use RuntimeException;

/**
 * Thrown when a model provider's circuit breaker is open
 * or the provider cannot accept requests.
 */
class ProviderUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $providerName,
        string $message = '',
    ) {
        parent::__construct($message ?: "AI provider '{$providerName}' is unavailable (circuit breaker open).");
    }
}
