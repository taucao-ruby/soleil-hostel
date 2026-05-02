<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\BookingActionProposal;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\PolicyDecision;
use App\AiHarness\Enums\ProposalActionType;
use App\AiHarness\Enums\ToolClassification;
use App\AiHarness\Providers\RawModelResponse;
use App\AiHarness\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Normalizer;
use RuntimeException;

/**
 * L4 — Policy Enforcement Service.
 *
 * AUTHORITATIVE safety boundary. This is NOT advisory — it decides.
 *
 * PRE-CALL: screens input before model invocation.
 * POST-CALL: validates model output before returning to caller.
 */
class PolicyEnforcementService
{
    /**
     * Patterns that indicate PII in text.
     * Deliberately kept conservative to avoid false positives.
     */
    private const PII_PATTERNS = [
        'email' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
        'phone_vn' => '/(?:\+84|0)\d{9,10}/',
        'phone_intl' => '/\+\d{10,15}/',
        'passport' => '/[A-Z]{1,2}\d{6,9}/',
    ];

    /**
     * Heuristic patterns for prompt injection attempts.
     *
     * These run against normalizeForDetection(), not raw user input.
     *
     * @var list<string>
     */
    private const INJECTION_PATTERNS = [
        '/\bignore\s+(all\s+)?(previous|prior|above)\s+(instructions?|prompts?|rules?)\b/',
        '/\bignore\s+instructions?\b/',
        '/\bignore\b/',
        '/\byou\s+are\s+now\s+(?:(?:a|an|the)\s+)?\w+\b/',
        '/\bsystem\s*:\s*/',
        '/\bdan\b/',
        '/\bdo\s+anything\s+now\b/',
        '/\bjailbreak\b/',
        '/\bpretend\s+(you\s+are|to\s+be)\b/',
        '/\bnew\s+(persona|role|identity)\b/',
        '/\bdisregard\s+(your\s+)?(guidelines|constraints|rules)\b/',
        '/\bin\s+(this|your)\s+(new\s+)?role\b/',
        '/\bact\s+as\s+(if\s+you\s+are|a)\b/',
    ];

    private const PII_SAFE_RESPONSE = 'I cannot share personal information. Please contact the front desk directly.';

    /**
     * Pre-call screening. Runs BEFORE model invocation.
     */
    public function screenInput(HarnessRequest $request): PolicyDecision
    {
        $checks = [];
        $piiDetected = false;
        $sanitizedFields = [];

        // Check 1: Risk tier gate
        if ($request->riskTier->isBlockedForModel()) {
            $checks[] = 'risk_tier_gate:blocked';

            return new PolicyDecision(
                decision: 'abstain',
                reason: 'Request risk tier is too high for model processing.',
                checksPerformed: $checks,
                piiDetected: false,
                blockedTool: null,
                sanitizedFields: [],
            );
        }
        $checks[] = 'risk_tier_gate:pass';

        // Check 2: PII in user input
        foreach (self::PII_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $request->userInput)) {
                $piiDetected = true;
                $sanitizedFields[] = "input:{$type}";
            }
        }
        $checks[] = $piiDetected ? 'pii_input_scan:detected' : 'pii_input_scan:clean';

        // Check 3: Prompt injection heuristics
        $normalizedInput = $this->normalizeForDetection($request->userInput);

        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalizedInput)) {
                $checks[] = 'injection_scan:blocked';

                Log::channel('ai')->warning('Prompt injection attempt detected', [
                    'request_id' => $request->requestId,
                    'correlation_id' => $request->correlationId,
                    'pattern' => 'redacted',
                ]);

                return new PolicyDecision(
                    decision: 'reject',
                    reason: 'Input contains suspicious patterns.',
                    checksPerformed: $checks,
                    piiDetected: $piiDetected,
                    blockedTool: null,
                    sanitizedFields: $sanitizedFields,
                );
            }
        }
        $checks[] = 'injection_scan:pass';

        // Check 4: Input length sanity
        if (mb_strlen($request->userInput) > 10000) {
            $checks[] = 'input_length:exceeded';

            return new PolicyDecision(
                decision: 'reject',
                reason: 'Input exceeds maximum allowed length.',
                checksPerformed: $checks,
                piiDetected: $piiDetected,
                blockedTool: null,
                sanitizedFields: $sanitizedFields,
            );
        }
        $checks[] = 'input_length:pass';

        return new PolicyDecision(
            decision: 'allow',
            reason: 'All pre-call checks passed.',
            checksPerformed: $checks,
            piiDetected: $piiDetected,
            blockedTool: null,
            sanitizedFields: $sanitizedFields,
        );
    }

    /**
     * Post-call validation. Runs AFTER model response is received.
     */
    public function validateOutput(
        HarnessRequest $request,
        RawModelResponse $response,
    ): PolicyDecision {
        $checks = [];
        $piiDetected = false;
        $sanitizedFields = [];

        // Check 1: PII in model output
        foreach (self::PII_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $response->rawContent)) {
                $piiDetected = true;
                $sanitizedFields[] = "output:{$type}";
            }
        }
        $checks[] = $piiDetected ? 'pii_output_scan:detected' : 'pii_output_scan:clean';

        if ($piiDetected) {
            $auditPayload = [
                'output_hmac' => $this->hashForAudit($response->rawContent),
            ];

            Log::channel('ai')->warning('PII detected in model output; response blocked', [
                'request_id' => $request->requestId,
                'correlation_id' => $request->correlationId,
                'sanitized_fields' => $sanitizedFields,
                ...$auditPayload,
            ]);

            return new PolicyDecision(
                decision: 'reject',
                reason: 'PII detected in model output',
                checksPerformed: $checks,
                piiDetected: true,
                blockedTool: null,
                sanitizedFields: $sanitizedFields,
                safeResponse: self::PII_SAFE_RESPONSE,
                auditPayload: $auditPayload,
            );
        }

        // Check 2: Tool proposals — classify and gate each one
        $blockedTool = null;
        foreach ($response->toolProposals as $proposal) {
            $toolName = $proposal['tool'] ?? 'unknown';
            $classification = ToolRegistry::classify($toolName);

            if ($classification === ToolClassification::BLOCKED) {
                $blockedTool = $toolName;
                $checks[] = "tool_gate:{$toolName}:blocked";

                Log::channel('ai')->error('BLOCKED tool proposed by model', [
                    'request_id' => $request->requestId,
                    'correlation_id' => $request->correlationId,
                    'tool' => $toolName,
                    'task_type' => $request->taskType->value,
                ]);

                return new PolicyDecision(
                    decision: 'reject',
                    reason: "Model proposed blocked tool: {$toolName}.",
                    checksPerformed: $checks,
                    piiDetected: $piiDetected,
                    blockedTool: $blockedTool,
                    sanitizedFields: $sanitizedFields,
                );
            }

            $checks[] = "tool_gate:{$toolName}:{$classification->value}";
        }

        // Check 3: Response not empty
        if (trim($response->rawContent) === '' && empty($response->toolProposals)) {
            $checks[] = 'content_check:empty';

            return new PolicyDecision(
                decision: 'abstain',
                reason: 'Model returned empty response.',
                checksPerformed: $checks,
                piiDetected: $piiDetected,
                blockedTool: null,
                sanitizedFields: $sanitizedFields,
            );
        }
        $checks[] = 'content_check:pass';

        return new PolicyDecision(
            decision: 'allow',
            reason: 'All post-call checks passed.',
            checksPerformed: $checks,
            piiDetected: false,
            blockedTool: null,
            sanitizedFields: $sanitizedFields,
        );
    }

    /**
     * Validate a BookingActionProposal schema before presenting to user.
     *
     * Reject if:
     * - action_type not in allowed list
     * - proposed_params missing required fields
     * - risk_assessment missing
     *
     * On rejection → proposal is discarded, REFUSAL returned.
     */
    public function validateProposal(BookingActionProposal $proposal): PolicyDecision
    {
        $checks = [];

        // Check 1: action_type is a valid ProposalActionType.
        // $proposal->actionType is typed as ProposalActionType — PHP's type system guarantees
        // validity at construction. An in_array comparison against ProposalActionType::cases()
        // values is structurally unreachable for any typed enum instance and creates a dead
        // branch that Psalm correctly flags as impossible.
        $checks[] = 'proposal_action_type:valid';

        // Check 2: proposed_params has required fields per action_type
        $requiredParams = match ($proposal->actionType) {
            ProposalActionType::SUGGEST_BOOKING => ['room_id', 'check_in', 'check_out'],
            ProposalActionType::SUGGEST_CANCELLATION => ['booking_id'],
        };

        $missingParams = [];
        foreach ($requiredParams as $param) {
            if (! array_key_exists($param, $proposal->proposedParams)
                || $proposal->proposedParams[$param] === null
                || $proposal->proposedParams[$param] === '') {
                $missingParams[] = $param;
            }
        }

        if (! empty($missingParams)) {
            $checks[] = 'proposal_params:missing';
            $missing = implode(', ', $missingParams);

            Log::channel('ai')->error('Proposal missing required params', [
                'missing' => $missingParams,
                'proposal_hash' => $proposal->proposalHash,
            ]);

            return new PolicyDecision(
                decision: 'reject',
                reason: "Proposal missing required parameters: {$missing}.",
                checksPerformed: $checks,
                piiDetected: false,
                blockedTool: null,
                sanitizedFields: [],
            );
        }
        $checks[] = 'proposal_params:valid';

        // Check 3: risk_assessment present with required structure
        if (empty($proposal->riskAssessment)
            || ! isset($proposal->riskAssessment['level'])
            || ! isset($proposal->riskAssessment['factors'])) {
            $checks[] = 'proposal_risk_assessment:missing';

            Log::channel('ai')->error('Proposal missing risk_assessment', [
                'proposal_hash' => $proposal->proposalHash,
            ]);

            return new PolicyDecision(
                decision: 'reject',
                reason: 'Proposal missing required risk_assessment.',
                checksPerformed: $checks,
                piiDetected: false,
                blockedTool: null,
                sanitizedFields: [],
            );
        }
        $checks[] = 'proposal_risk_assessment:valid';

        // Check 4: requires_confirmation must be true (defensive — struct enforces this,
        // but validation layer must reject if ever bypassed via deserialization)
        /** @phpstan-ignore booleanNot.alwaysFalse (defense-in-depth: guard against deserialization bypass) */
        if ($proposal->requiresConfirmation !== true) {
            $checks[] = 'proposal_confirmation:bypass_attempt';

            Log::channel('ai')->error('Proposal attempted to bypass confirmation', [
                'proposal_hash' => $proposal->proposalHash,
            ]);

            return new PolicyDecision(
                decision: 'reject',
                reason: 'Proposal must require confirmation.',
                checksPerformed: $checks,
                piiDetected: false,
                blockedTool: null,
                sanitizedFields: [],
            );
        }
        $checks[] = 'proposal_confirmation:required';

        // Check 5: human_readable_summary not empty
        if (trim($proposal->humanReadableSummary) === '') {
            $checks[] = 'proposal_summary:empty';

            return new PolicyDecision(
                decision: 'reject',
                reason: 'Proposal must include a human-readable summary.',
                checksPerformed: $checks,
                piiDetected: false,
                blockedTool: null,
                sanitizedFields: [],
            );
        }
        $checks[] = 'proposal_summary:present';

        return new PolicyDecision(
            decision: 'allow',
            reason: 'Proposal schema validation passed.',
            checksPerformed: $checks,
            piiDetected: false,
            blockedTool: null,
            sanitizedFields: [],
        );
    }

    private function normalizeForDetection(string $text): string
    {
        $normalized = $text;

        if (class_exists(Normalizer::class)) {
            $nfc = Normalizer::normalize($normalized, Normalizer::FORM_C);
            $normalized = $nfc === false ? $normalized : $nfc;
        }

        $stripped = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $normalized);
        $normalized = $stripped ?? $normalized;

        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $normalized);
            $normalized = $transliterated === false ? $normalized : $transliterated;
        }

        return mb_strtolower($normalized);
    }

    /**
     * Generate a forensic verifier without storing blocked model content.
     */
    private function hashForAudit(string $content): string
    {
        $key = (string) config('app.key', '');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? $key : $decoded;
        }

        if ($key === '') {
            throw new RuntimeException('AI audit HMAC key is not configured.');
        }

        return hash_hmac('sha256', $content, $key);
    }
}
