<?php

declare(strict_types=1);

namespace Tests\Unit\AiHarness;

use App\AiHarness\DTOs\RequestTrace;
use PHPUnit\Framework\TestCase;

class RequestTraceTest extends TestCase
{
    private const REQUIRED_FIELDS = [
        'request_id',
        'correlation_id',
        'user_id',
        'feature_route',
        'prompt_version',
        'model_provider',
        'inference_params',
        'retrieval_summary',
        'tool_proposals',
        'tool_executions',
        'policy_decisions',
        'latency_breakdown',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost_usd',
        'response_class',
        'failure_reason',
    ];

    private function createTrace(): RequestTrace
    {
        return new RequestTrace(
            requestId: 'req-abc-123',
            correlationId: 'sol-1700000000-a1b2c3d4',
            userId: 42,
            featureRoute: 'ai.faq_lookup',
            promptVersion: 'faq_lookup-v1.0.0',
            modelProvider: 'anthropic',
            inferenceParams: ['temperature' => 0.3, 'max_tokens' => 1024],
            retrievalSummary: [
                ['source_id' => 'policy-cancellation', 'freshness_ok' => true, 'token_count' => 350],
            ],
            toolProposals: [
                ['tool' => 'lookup_policy', 'classification' => 'read_only'],
            ],
            toolExecutions: [
                ['tool' => 'lookup_policy', 'result' => 'success', 'duration_ms' => 45],
            ],
            policyDecisions: [
                ['check' => 'input_screening', 'result' => 'pass'],
                ['check' => 'pii_detection', 'result' => 'pass'],
            ],
            latencyBreakdown: ['context_assembly_ms' => 12, 'model_ms' => 890, 'policy_ms' => 5, 'total_ms' => 920],
            promptTokens: 450,
            completionTokens: 180,
            estimatedCostUsd: 0.0023,
            responseClass: 'answer',
            failureReason: null,
        );
    }

    public function test_trace_contains_all_17_required_fields(): void
    {
        $trace = $this->createTrace();
        $array = $trace->toArray();

        $this->assertCount(
            count(self::REQUIRED_FIELDS),
            $array,
            'RequestTrace must have exactly ' . count(self::REQUIRED_FIELDS) . ' fields',
        );

        foreach (self::REQUIRED_FIELDS as $field) {
            $this->assertArrayHasKey(
                $field,
                $array,
                "RequestTrace::toArray() must contain field '{$field}'",
            );
        }
    }

    public function test_to_array_serializes_all_fields(): void
    {
        $trace = $this->createTrace();
        $array = $trace->toArray();

        $this->assertSame('req-abc-123', $array['request_id']);
        $this->assertSame('sol-1700000000-a1b2c3d4', $array['correlation_id']);
        $this->assertSame(42, $array['user_id']);
        $this->assertSame('ai.faq_lookup', $array['feature_route']);
        $this->assertSame('faq_lookup-v1.0.0', $array['prompt_version']);
        $this->assertSame('anthropic', $array['model_provider']);
        $this->assertSame(0.3, $array['inference_params']['temperature']);
        $this->assertCount(1, $array['retrieval_summary']);
        $this->assertCount(1, $array['tool_proposals']);
        $this->assertCount(1, $array['tool_executions']);
        $this->assertCount(2, $array['policy_decisions']);
        $this->assertSame(920, $array['latency_breakdown']['total_ms']);
        $this->assertSame(450, $array['prompt_tokens']);
        $this->assertSame(180, $array['completion_tokens']);
        $this->assertSame(0.0023, $array['estimated_cost_usd']);
        $this->assertSame('answer', $array['response_class']);
        $this->assertNull($array['failure_reason']);
    }

    public function test_to_log_context_masks_sensitive_user_data(): void
    {
        $trace = $this->createTrace();
        $logContext = $trace->toLogContext();

        // user_id must be masked — not the raw integer
        $this->assertIsString($logContext['user_id']);
        $this->assertStringStartsWith('user_', $logContext['user_id']);
        $this->assertNotSame('42', $logContext['user_id']);
        $this->assertNotSame(42, $logContext['user_id']);

        // All other fields must still be present
        $this->assertSame('req-abc-123', $logContext['request_id']);
        $this->assertSame('anthropic', $logContext['model_provider']);
    }
}
