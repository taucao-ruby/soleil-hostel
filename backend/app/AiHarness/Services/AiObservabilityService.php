<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\PolicyDecision;
use App\AiHarness\DTOs\RequestTrace;
use App\AiHarness\Providers\RawModelResponse;
use Illuminate\Support\Facades\Log;

/**
 * L6 — AI Observability Service.
 *
 * Builds a RequestTrace and writes it to the 'ai' log channel.
 *
 * NEVER logs: raw user PII (beyond masked form), full prompt text.
 * ALWAYS logs: all 17 RequestTrace fields.
 *
 * @psalm-type ToolClassificationEntry = array{
 *   tool: non-empty-string,
 *   classification: 'approval_required'|'blocked'|'read_only'
 * }
 * @psalm-type ToolExecutionEntry = array{
 *   tool: non-empty-string,
 *   result: string,
 *   duration_ms: int
 * }
 */
class AiObservabilityService
{
    /**
     * Build and persist the request trace.
     */
    public function recordTrace(
        HarnessRequest $request,
        GroundedContext $context,
        PolicyDecision $preCallDecision,
        ?RawModelResponse $modelResponse,
        ?PolicyDecision $postCallDecision,
        array $toolExecutions,
        string $responseClass,
        ?string $failureReason,
        array $latencyBreakdown,
    ): RequestTrace {
        $trace = new RequestTrace(
            requestId: $request->requestId,
            correlationId: $request->correlationId,
            userId: $request->userId,
            featureRoute: $request->featureRoute,
            promptVersion: $request->promptVersion,
            modelProvider: $modelResponse?->providerName ?? 'none',
            inferenceParams: [
                'max_tokens' => config("ai_harness.providers.{$request->taskType->value}.max_tokens", 1024),
            ],
            retrievalSummary: array_map(fn (array $s) => [
                'source_id' => $s['source_id'],
                'freshness_ok' => $s['freshness_ok'],
                'token_count' => $this->estimateTokens($s['content'] ?? ''),
            ], $context->sources),
            toolProposals: $this->normalizeToolClassifications($modelResponse?->toolProposals ?? []),
            toolExecutions: $this->normalizeToolExecutions($toolExecutions),
            policyDecisions: $this->collectPolicyDecisions($preCallDecision, $postCallDecision),
            latencyBreakdown: $latencyBreakdown,
            promptTokens: $modelResponse?->promptTokens ?? 0,
            completionTokens: $modelResponse?->completionTokens ?? 0,
            estimatedCostUsd: $this->estimateCost(
                $modelResponse?->promptTokens ?? 0,
                $modelResponse?->completionTokens ?? 0,
                $modelResponse?->providerName ?? 'none',
            ),
            responseClass: $responseClass,
            failureReason: $failureReason,
        );

        $this->writeTrace($trace);

        return $trace;
    }

    private function writeTrace(RequestTrace $trace): void
    {
        Log::channel('ai')->info('AI harness request trace', $trace->toLogContext());
    }

    /**
     * @param  array<array-key, mixed>  $proposals
     * @return list<array{tool: non-empty-string, classification: 'approval_required'|'blocked'|'read_only'}>
     */
    private function normalizeToolClassifications(array $proposals): array
    {
        $result = [];
        foreach ($proposals as $p) {
            if (! is_array($p)) {
                continue;
            }
            $rawTool = $p['tool'] ?? null;
            $tool = is_string($rawTool) && $rawTool !== '' ? $rawTool : 'unknown';
            $result[] = [
                'tool' => $tool,
                'classification' => \App\AiHarness\ToolRegistry::classify($tool)->value,
            ];
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $executions
     * @return list<array{tool: non-empty-string, result: string, duration_ms: int}>
     */
    private function normalizeToolExecutions(array $executions): array
    {
        $result = [];
        foreach ($executions as $e) {
            if (! is_array($e)) {
                continue;
            }
            $rawTool = $e['tool'] ?? null;
            $tool = is_string($rawTool) && $rawTool !== '' ? $rawTool : 'unknown';
            $rawDuration = $e['duration_ms'] ?? 0;
            $durationMs = is_int($rawDuration) ? $rawDuration : 0;
            $rawResult = $e['result'] ?? null;
            $resultStr = is_string($rawResult) ? $rawResult : (string) json_encode($rawResult);
            $result[] = [
                'tool' => $tool,
                'result' => $resultStr,
                'duration_ms' => $durationMs,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{check: string, result: string}>
     */
    private function collectPolicyDecisions(
        PolicyDecision $preCall,
        ?PolicyDecision $postCall,
    ): array {
        $decisions = [];

        foreach ($preCall->checksPerformed as $check) {
            $decisions[] = ['check' => "pre:{$check}", 'result' => $preCall->decision];
        }

        if ($postCall !== null) {
            foreach ($postCall->checksPerformed as $check) {
                $decisions[] = ['check' => "post:{$check}", 'result' => $postCall->decision];
            }
        }

        return $decisions;
    }

    private function estimateTokens(string $content): int
    {
        return max(0, (int) ceil(mb_strlen($content) / 4));
    }

    /**
     * Estimate cost based on provider pricing.
     * Conservative estimates — will need calibration.
     */
    private function estimateCost(int $promptTokens, int $completionTokens, string $provider): float
    {
        // Pricing per 1M tokens (as of 2025)
        $pricing = match ($provider) {
            'anthropic' => ['input' => 3.0, 'output' => 15.0],
            'openai' => ['input' => 2.5, 'output' => 10.0],
            default => ['input' => 0.0, 'output' => 0.0],
        };

        return ($promptTokens * $pricing['input'] + $completionTokens * $pricing['output']) / 1_000_000;
    }
}
