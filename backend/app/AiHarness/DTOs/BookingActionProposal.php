<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

use App\AiHarness\Enums\ProposalActionType;

/**
 * Immutable booking action proposal struct.
 *
 * The model produces this. The harness validates schema. The human decides.
 * The existing service executes. The database constrains.
 *
 * A BookingActionProposal is NEVER a service call.
 * requires_confirmation is ALWAYS true — no auto-confirm path exists.
 */
final readonly class BookingActionProposal
{
    /**
     * @param  array<string, mixed>  $proposedParams  Structured booking/cancellation parameters
     * @param  list<mixed>  $policyRefs  Policy document slugs referenced
     * @param  array<string, mixed>  $riskAssessment  Risk evaluation (structure validated by PolicyEnforcementService)
     */
    public function __construct(
        public ProposalActionType $actionType,
        public array $proposedParams,
        public string $humanReadableSummary,
        public array $policyRefs,
        public array $riskAssessment,
        public bool $requiresConfirmation,
        public string $proposalHash,
        public string $generatedAt,
    ) {
        // Enforce: requires_confirmation is ALWAYS true.
        // This assertion exists to catch construction errors at dev time.
        assert($this->requiresConfirmation === true, 'BookingActionProposal must require confirmation');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action_type' => $this->actionType->value,
            'proposed_params' => $this->proposedParams,
            'human_readable_summary' => $this->humanReadableSummary,
            'policy_refs' => $this->policyRefs,
            'risk_assessment' => $this->riskAssessment,
            'requires_confirmation' => $this->requiresConfirmation,
            'proposal_hash' => $this->proposalHash,
            'generated_at' => $this->generatedAt,
        ];
    }
}
