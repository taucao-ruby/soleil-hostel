<?php

declare(strict_types=1);

namespace App\AiHarness\Providers;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Exceptions\ProviderTimeoutException;
use App\AiHarness\Exceptions\ProviderUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic API provider (Claude) for the AI harness.
 *
 * Uses Laravel's HTTP client — no external SDK dependency.
 * Includes circuit breaker (cache-based) and retry with exponential backoff.
 */
class AnthropicProvider implements ModelProviderInterface
{
    private const CIRCUIT_BREAKER_KEY = 'ai_harness:circuit:anthropic';
    private const FAILURE_COUNT_KEY = 'ai_harness:failures:anthropic';
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function complete(HarnessRequest $req, GroundedContext $ctx): RawModelResponse
    {
        if (! $this->isAvailable()) {
            throw new ProviderUnavailableException($this->getProviderName());
        }

        $config = config('ai_harness.providers.anthropic');
        $timeout = config("ai_harness.timeout_ladder.{$req->taskType->value}", 8);
        $retryConfig = config('ai_harness.retry');
        $maxAttempts = $retryConfig['max_attempts'] ?? 2;
        $backoffMs = $retryConfig['backoff_ms'] ?? [500, 2000];

        $prompt = $this->buildPrompt($req, $ctx);

        $lastException = null;

        for ($attempt = 0; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                $delayMs = $backoffMs[min($attempt - 1, count($backoffMs) - 1)] ?? 1000;
                // Add jitter: ±25%
                $jitter = (int) ($delayMs * 0.25);
                $delayMs += random_int(-$jitter, $jitter);
                usleep($delayMs * 1000);
            }

            $startMs = (int) (microtime(true) * 1000);

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'x-api-key' => $config['api_key'],
                        'anthropic-version' => self::API_VERSION,
                        'content-type' => 'application/json',
                        'anthropic-idempotency-key' => $req->correlationId,
                    ])
                    ->post(self::API_URL, [
                        'model' => $config['model'],
                        'max_tokens' => $config['max_tokens'] ?? 1024,
                        'system' => $prompt['system'],
                        'messages' => $prompt['messages'],
                    ]);

                $latencyMs = (int) (microtime(true) * 1000) - $startMs;

                if ($response->successful()) {
                    $this->resetFailureCount();
                    $body = $response->json();

                    return new RawModelResponse(
                        providerName: $this->getProviderName(),
                        rawContent: $this->extractContent($body),
                        promptTokens: $body['usage']['input_tokens'] ?? 0,
                        completionTokens: $body['usage']['output_tokens'] ?? 0,
                        latencyMs: $latencyMs,
                        toolProposals: $this->extractToolProposals($body),
                    );
                }

                // Non-retryable status codes
                if (in_array($response->status(), [400, 401, 403], true)) {
                    $this->recordFailure();

                    throw new ProviderUnavailableException(
                        $this->getProviderName(),
                        "Anthropic API returned {$response->status()}: " . $response->body(),
                    );
                }

                // Retryable error — continue loop
                $lastException = new ProviderUnavailableException(
                    $this->getProviderName(),
                    "Anthropic API returned {$response->status()}",
                );
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $latencyMs = (int) (microtime(true) * 1000) - $startMs;
                $lastException = new ProviderTimeoutException(
                    $this->getProviderName(),
                    $timeout,
                    $e->getMessage(),
                );
            }
        }

        // All retries exhausted
        $this->recordFailure();

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new ProviderUnavailableException($this->getProviderName(), 'All retries exhausted.');
    }

    public function isAvailable(): bool
    {
        return ! Cache::has(self::CIRCUIT_BREAKER_KEY);
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    /**
     * Record a failure and potentially open the circuit breaker.
     */
    private function recordFailure(): void
    {
        $threshold = config('ai_harness.circuit_breaker.failure_threshold', 5);
        $recoveryTimeout = config('ai_harness.circuit_breaker.recovery_timeout', 30);

        $failures = (int) Cache::get(self::FAILURE_COUNT_KEY, 0) + 1;
        Cache::put(self::FAILURE_COUNT_KEY, $failures, now()->addMinutes(5));

        if ($failures >= $threshold) {
            Cache::put(self::CIRCUIT_BREAKER_KEY, true, now()->addSeconds($recoveryTimeout));

            Log::channel('ai')->warning('Circuit breaker opened for Anthropic provider', [
                'failures' => $failures,
                'threshold' => $threshold,
                'recovery_timeout' => $recoveryTimeout,
            ]);
        }
    }

    private function resetFailureCount(): void
    {
        Cache::forget(self::FAILURE_COUNT_KEY);
    }

    /**
     * Build the prompt payload from harness request and grounded context.
     */
    private function buildPrompt(HarnessRequest $req, GroundedContext $ctx): array
    {
        $template = \App\AiHarness\PromptRegistry::getTemplate($req->taskType);

        $contextText = '';
        foreach ($ctx->sources as $source) {
            $contextText .= "--- Source: {$source['source_id']} (retrieved: {$source['retrieved_at']}) ---\n";
            $contextText .= $source['content'] . "\n\n";
        }

        $systemInstruction = $template['system_instruction']
            . "\n\n" . $template['abstain_instruction']
            . "\n\n" . $template['citation_requirement'];

        if ($contextText !== '') {
            $systemInstruction .= "\n\n--- VERIFIED CONTEXT ---\n" . $contextText;
        }

        return [
            'system' => $systemInstruction,
            'messages' => [
                ['role' => 'user', 'content' => $req->userInput],
            ],
        ];
    }

    private function extractContent(array $body): string
    {
        $blocks = $body['content'] ?? [];

        $text = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return $text;
    }

    /**
     * @return list<array{tool: string, input: array}>
     */
    private function extractToolProposals(array $body): array
    {
        $proposals = [];
        foreach (($body['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $proposals[] = [
                    'tool' => $block['name'] ?? 'unknown',
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return $proposals;
    }
}
