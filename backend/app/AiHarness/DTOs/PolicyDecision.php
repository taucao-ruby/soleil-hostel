<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

final readonly class PolicyDecision
{
    /**
     * @param  'allow'|'reject'|'abstain'|'escalate'  $decision
     * @param  list<string>  $checksPerformed
     * @param  list<string>  $sanitizedFields
     */
    public function __construct(
        public string $decision,
        public string $reason,
        public array $checksPerformed,
        public bool $piiDetected,
        public ?string $blockedTool,
        public array $sanitizedFields,
    ) {}
}
