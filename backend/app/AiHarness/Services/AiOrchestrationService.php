<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\BookingActionProposal;
use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\HarnessResponse;
use App\AiHarness\Enums\ProposalActionType;
use App\AiHarness\Enums\ResponseClass;
use App\AiHarness\Exceptions\BlockedToolException;
use App\AiHarness\Exceptions\ProviderTimeoutException;
use App\AiHarness\Exceptions\ProviderUnavailableException;
use App\AiHarness\Providers\RawModelResponse;
use Illuminate\Support\Facades\Cache;

/**
 * L0 — AI Orchestration Service.
 *
 * Single entry point that coordinates the full L1→L6 pipeline:
 *   1. ContextAssemblyService → GroundedContext
 *   2. PolicyEnforcementService pre-call → PolicyDecision
 *   3. If reject/abstain → return immediately, skip model call
 *   4. ModelExecutionService → RawModelResponse
 *   5. PolicyEnforcementService post-call → final PolicyDecision
 *   6. ToolOrchestrationService if tools proposed
 *   7. Build HarnessResponse
 *   8. AiObservabilityService → write trace
 *
 * Never catches exceptions silently. Surfaces as ResponseClass::ERROR.
 */
class AiOrchestrationService
{
    public function __construct(
        private readonly ContextAssemblyService $contextAssembly,
        private readonly PolicyEnforcementService $policyEnforcement,
        private readonly ModelExecutionService $modelExecution,
        private readonly ToolOrchestrationService $toolOrchestration,
        private readonly AiObservabilityService $observability,
    ) {}

    public function handle(HarnessRequest $request): HarnessResponse
    {
        $latency = ['total_ms' => 0];
        $totalStart = (int) (microtime(true) * 1000);

        // ── L2: Context Assembly ──
        $ctxStart = (int) (microtime(true) * 1000);
        $context = $this->contextAssembly->assemble($request);
        $latency['context_assembly_ms'] = (int) (microtime(true) * 1000) - $ctxStart;

        // ── L4: Pre-call policy screening ──
        $policyStart = (int) (microtime(true) * 1000);
        $preCallDecision = $this->policyEnforcement->screenInput($request);
        $latency['policy_pre_ms'] = (int) (microtime(true) * 1000) - $policyStart;

        // Short-circuit on reject or abstain
        if (in_array($preCallDecision->decision, ['reject', 'abstain'], true)) {
            $responseClass = $preCallDecision->decision === 'reject'
                ? ResponseClass::REFUSAL
                : ResponseClass::ABSTAIN;

            $latency['total_ms'] = (int) (microtime(true) * 1000) - $totalStart;

            $this->observability->recordTrace(
                request: $request,
                context: $context,
                preCallDecision: $preCallDecision,
                modelResponse: null,
                postCallDecision: null,
                toolExecutions: [],
                responseClass: $responseClass->value,
                failureReason: $preCallDecision->reason,
                latencyBreakdown: $latency,
            );

            return new HarnessResponse(
                requestId: $request->requestId,
                responseClass: $responseClass,
                content: $this->buildAbstainContent($preCallDecision),
                citations: [],
                proposals: [],
                failureReason: $preCallDecision->reason,
                estimatedCostUsd: 0.0,
                traceRef: ['request_id' => $request->requestId, 'trace_logged' => true],
            );
        }

        // ── L3: Model execution ──
        $modelResponse = null;
        $responseClass = ResponseClass::ERROR;
        $failureReason = null;

        try {
            $modelStart = (int) (microtime(true) * 1000);
            $modelResponse = $this->modelExecution->execute($request, $context);
            $latency['model_ms'] = (int) (microtime(true) * 1000) - $modelStart;
            $responseClass = ResponseClass::ANSWER;
        } catch (ProviderTimeoutException $e) {
            $latency['model_ms'] = (int) (microtime(true) * 1000) - ($modelStart ?? $totalStart);
            $responseClass = ResponseClass::FALLBACK;
            $failureReason = $e->getMessage();
        } catch (ProviderUnavailableException $e) {
            $latency['model_ms'] = (int) (microtime(true) * 1000) - ($modelStart ?? $totalStart);
            $responseClass = ResponseClass::ERROR;
            $failureReason = $e->getMessage();
        }

        // ── L4: Post-call policy validation (only if model returned) ──
        $postCallDecision = null;
        $toolExecutions = [];

        if ($modelResponse !== null) {
            $postPolicyStart = (int) (microtime(true) * 1000);
            $postCallDecision = $this->policyEnforcement->validateOutput($request, $modelResponse);
            $latency['policy_post_ms'] = (int) (microtime(true) * 1000) - $postPolicyStart;

            if ($postCallDecision->decision === 'reject') {
                $responseClass = ResponseClass::REFUSAL;
                $failureReason = $postCallDecision->reason;
                $modelResponse = null; // Do not surface rejected content
            } elseif ($postCallDecision->decision === 'abstain') {
                $responseClass = ResponseClass::ABSTAIN;
                $failureReason = $postCallDecision->reason;
            }

            // ── L5: Tool orchestration (only if allowed and tools proposed) ──
            if ($modelResponse !== null
                && in_array($postCallDecision->decision, ['allow', 'escalate'], true)
                && ! empty($modelResponse->toolProposals)) {
                try {
                    foreach ($modelResponse->toolProposals as $proposal) {
                        $toolExecutions[] = $this->toolOrchestration->execute($proposal, $request);
                    }
                } catch (BlockedToolException $e) {
                    $responseClass = ResponseClass::REFUSAL;
                    $failureReason = $e->getMessage();
                }
            }
        }

        // ── Phase 4+: Extract and validate BookingActionProposals ──
        $validatedProposals = $this->extractAndValidateProposals(
            $toolExecutions,
            $responseClass,
            $failureReason,
        );

        $latency['total_ms'] = (int) (microtime(true) * 1000) - $totalStart;

        // ── L6: Observability ──
        $trace = $this->observability->recordTrace(
            request: $request,
            context: $context,
            preCallDecision: $preCallDecision,
            modelResponse: $modelResponse,
            postCallDecision: $postCallDecision,
            toolExecutions: $toolExecutions,
            responseClass: $responseClass->value,
            failureReason: $failureReason,
            latencyBreakdown: $latency,
        );

        $content = match ($responseClass) {
            ResponseClass::ANSWER => $modelResponse->rawContent ?? '',
            ResponseClass::REFUSAL => $this->buildRefusalContent($failureReason),
            ResponseClass::ABSTAIN => $this->buildAbstainContent($postCallDecision ?? $preCallDecision),
            ResponseClass::FALLBACK => 'Hệ thống AI tạm thời không khả dụng. Vui lòng thử lại sau.',
            ResponseClass::ERROR => 'Đã xảy ra lỗi. Vui lòng thử lại sau hoặc liên hệ hỗ trợ.',
        };

        return new HarnessResponse(
            requestId: $request->requestId,
            responseClass: $responseClass,
            content: $content,
            citations: [],
            proposals: $validatedProposals,
            failureReason: $failureReason,
            estimatedCostUsd: $trace->estimatedCostUsd,
            traceRef: ['request_id' => $request->requestId, 'trace_logged' => true],
        );
    }

    private function buildAbstainContent(\App\AiHarness\DTOs\PolicyDecision $decision): string
    {
        return 'Tôi không thể xử lý yêu cầu này. Vui lòng liên hệ bộ phận hỗ trợ.';
    }

    private function buildRefusalContent(?string $reason): string
    {
        return 'Tôi không thể thực hiện hành động này trực tiếp. '
            .'Vui lòng sử dụng giao diện đặt phòng hoặc liên hệ lễ tân.';
    }

    /**
     * Extract BookingActionProposals from tool execution results.
     *
     * Identifies proposals by checking for action_type + requires_confirmation
     * in the serialized result. Validates each proposal via PolicyEnforcementService.
     * Stores valid proposals in cache for the confirmation flow.
     *
     * @param  list<array{tool: string, classification: string, result: mixed, executed: bool, duration_ms: int}>  $toolExecutions
     * @return list<array<string, mixed>>
     */
    private function extractAndValidateProposals(
        array $toolExecutions,
        ResponseClass &$responseClass,
        ?string &$failureReason,
    ): array {
        $validatedProposals = [];

        foreach ($toolExecutions as $execution) {
            $result = $execution['result'] ?? null;
            if (! is_array($result)) {
                continue;
            }

            // Detect proposal by its schema signature
            if (! isset($result['action_type'], $result['requires_confirmation'], $result['proposal_hash'])) {
                continue;
            }

            $actionType = ProposalActionType::tryFrom((string) $result['action_type']);
            if ($actionType === null) {
                continue;
            }

            $proposal = new BookingActionProposal(
                actionType: $actionType,
                proposedParams: is_array($result['proposed_params'] ?? null) ? $result['proposed_params'] : [],
                humanReadableSummary: (string) ($result['human_readable_summary'] ?? ''),
                policyRefs: is_array($result['policy_refs'] ?? null) ? $result['policy_refs'] : [],
                riskAssessment: is_array($result['risk_assessment'] ?? null) ? $result['risk_assessment'] : [],
                requiresConfirmation: true,
                proposalHash: (string) $result['proposal_hash'],
                generatedAt: (string) ($result['generated_at'] ?? now()->toIso8601String()),
            );

            $proposalDecision = $this->policyEnforcement->validateProposal($proposal);
            if ($proposalDecision->decision === 'reject') {
                $failureReason = $proposalDecision->reason;
                $responseClass = ResponseClass::REFUSAL;

                continue;
            }

            // Store proposal in cache for confirmation flow (30 min TTL)
            Cache::put(
                "ai_proposal:{$proposal->proposalHash}",
                $proposal->toArray(),
                1800,
            );

            $validatedProposals[] = $proposal->toArray();
        }

        return $validatedProposals;
    }
}
