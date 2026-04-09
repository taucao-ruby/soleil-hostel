<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

/**
 * Append-only request trace. Never mutate after creation.
 *
 * Captures the complete lifecycle of a single AI harness request
 * for observability, replay, and incident investigation.
 */
final readonly class RequestTrace
{
    /**
     * @param  array{temperature?: float, top_p?: float, max_tokens?: int, seed?: int}  $inferenceParams
     * @param  list<array{source_id: string, freshness_ok: bool, token_count: int}>  $retrievalSummary
     * @param  list<array{tool: string, classification: string}>  $toolProposals
     * @param  list<array{tool: string, result: string, duration_ms: int}>  $toolExecutions
     * @param  list<array{check: string, result: string}>  $policyDecisions
     * @param  array{ttft_ms?: int, context_assembly_ms?: int, model_ms?: int, policy_ms?: int, total_ms: int}  $latencyBreakdown
     */
    public function __construct(
        public string $requestId,
        public string $correlationId,
        public int $userId,
        public string $featureRoute,
        public string $promptVersion,
        public string $modelProvider,
        public array $inferenceParams,
        public array $retrievalSummary,
        public array $toolProposals,
        public array $toolExecutions,
        public array $policyDecisions,
        public array $latencyBreakdown,
        public int $promptTokens,
        public int $completionTokens,
        public float $estimatedCostUsd,
        public string $responseClass,
        public ?string $failureReason,
    ) {}

    /**
     * Serialize all fields for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'user_id' => $this->userId,
            'feature_route' => $this->featureRoute,
            'prompt_version' => $this->promptVersion,
            'model_provider' => $this->modelProvider,
            'inference_params' => $this->inferenceParams,
            'retrieval_summary' => $this->retrievalSummary,
            'tool_proposals' => $this->toolProposals,
            'tool_executions' => $this->toolExecutions,
            'policy_decisions' => $this->policyDecisions,
            'latency_breakdown' => $this->latencyBreakdown,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
            'response_class' => $this->responseClass,
            'failure_reason' => $this->failureReason,
        ];
    }

    /**
     * Produce a log-safe context array (masks user_id for privacy).
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        $data = $this->toArray();
        $data['user_id'] = $this->userId > 0 ? 'user_' . substr(md5((string) $this->userId), 0, 8) : 'anonymous';

        return $data;
    }
}
