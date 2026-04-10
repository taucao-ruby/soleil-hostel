<?php

declare(strict_types=1);

namespace App\AiHarness\Providers;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;

/**
 * Contract for AI model providers.
 *
 * Each provider wraps an external LLM API (Anthropic, OpenAI, etc.)
 * behind a uniform interface for the harness to call.
 */
interface ModelProviderInterface
{
    /**
     * Send a completion request to the model provider.
     *
     * @throws \App\AiHarness\Exceptions\ProviderUnavailableException
     * @throws \App\AiHarness\Exceptions\ProviderTimeoutException
     */
    public function complete(HarnessRequest $req, GroundedContext $ctx): RawModelResponse;

    /**
     * Check if the provider is currently available (circuit breaker is closed).
     */
    public function isAvailable(): bool;

    /**
     * Get the provider's identifier name.
     */
    public function getProviderName(): string;
}
