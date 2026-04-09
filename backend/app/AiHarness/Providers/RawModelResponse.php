<?php

declare(strict_types=1);

namespace App\AiHarness\Providers;

/**
 * Raw response from a model provider.
 *
 * Unvalidated, untrusted — must pass through PolicyEnforcementService post-call.
 */
final readonly class RawModelResponse
{
    public function __construct(
        public string $providerName,
        public string $rawContent,
        public int $promptTokens,
        public int $completionTokens,
        public int $latencyMs,
        public array $toolProposals = [],
    ) {}
}
