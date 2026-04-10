<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

/**
 * Immutable draft struct for APPROVAL_REQUIRED tools.
 *
 * A ToolDraft is NEVER written to DB directly.
 * Human must explicitly confirm before any downstream action.
 */
final readonly class ToolDraft
{
    /**
     * @param  list<string>  $contextUsed  Source IDs used to generate this draft
     * @param  list<string>  $policyRefs  Policy document slugs referenced
     * @param  array<string, mixed>  $keyFacts  Structured facts extracted from context
     */
    public function __construct(
        public string $toolName,
        public string $draftText,
        public string $suggestedTone,
        public array $contextUsed,
        public array $policyRefs,
        public array $keyFacts,
        public string $draftHash,
        public string $generatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'tool_name' => $this->toolName,
            'draft_text' => $this->draftText,
            'suggested_tone' => $this->suggestedTone,
            'context_used' => $this->contextUsed,
            'policy_refs' => $this->policyRefs,
            'key_facts' => $this->keyFacts,
            'draft_hash' => $this->draftHash,
            'generated_at' => $this->generatedAt,
        ];
    }
}
