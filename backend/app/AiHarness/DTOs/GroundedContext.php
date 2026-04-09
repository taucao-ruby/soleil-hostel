<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

final readonly class GroundedContext
{
    /**
     * @param  list<array{source_id: string, content: string, retrieved_at: string, freshness_ok: bool}>  $sources
     * @param  list<string>  $rbacFiltersApplied
     */
    public function __construct(
        public array $sources,
        public int $totalTokens,
        public string $provenanceHash,
        public array $rbacFiltersApplied,
    ) {}
}
