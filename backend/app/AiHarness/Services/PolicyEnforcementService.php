<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\PolicyDecision;
use App\AiHarness\Enums\ToolClassification;
use App\AiHarness\Providers\RawModelResponse;
use App\AiHarness\ToolRegistry;
use Illuminate\Support\Facades\Log;

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
     */
    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions?|prompts?|rules?)/i',
        '/you\s+are\s+now\s+/i',
        '/system\s*:\s*/i',
        '/\bDAN\b/',
        '/do\s+anything\s+now/i',
        '/jailbreak/i',
        '/pretend\s+you\s+are/i',
    ];

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
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $request->userInput)) {
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
            decision: $piiDetected ? 'escalate' : 'allow',
            reason: $piiDetected
                ? 'Output contains potential PII — requires review.'
                : 'All post-call checks passed.',
            checksPerformed: $checks,
            piiDetected: $piiDetected,
            blockedTool: null,
            sanitizedFields: $sanitizedFields,
        );
    }
}
