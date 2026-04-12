<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

/**
 * Audit event for a BookingActionProposal lifecycle.
 *
 * Captures: proposal shown → user decision → downstream result.
 * Every proposal event is logged — no silent discards.
 */
final readonly class ProposalEvent
{
    public function __construct(
        public int $userId,
        public string $proposalHash,
        public string $actionType,
        public string $userDecision,
        public ?string $downstreamResult,
        public string $timestamp,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'proposal_hash' => $this->proposalHash,
            'action_type' => $this->actionType,
            'user_decision' => $this->userDecision,
            'downstream_result' => $this->downstreamResult,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Log-safe context (masks user_id).
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        $data = $this->toArray();
        $data['user_id'] = $this->userId > 0
            ? 'user_'.substr(md5((string) $this->userId), 0, 8)
            : 'anonymous';

        return $data;
    }
}
