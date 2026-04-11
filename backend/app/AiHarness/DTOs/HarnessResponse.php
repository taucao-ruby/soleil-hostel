<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

use App\AiHarness\Enums\ResponseClass;

final readonly class HarnessResponse
{
    /**
     * @param  list<array{source_slug: string, verified_at: string}>  $citations
     * @param  list<array<string, mixed>>  $proposals  BookingActionProposal arrays (empty if none)
     * @param  array{request_id: string, trace_logged: bool}  $traceRef
     */
    public function __construct(
        public string $requestId,
        public ResponseClass $responseClass,
        public string $content,
        public array $citations,
        public array $proposals,
        public ?string $failureReason,
        public float $estimatedCostUsd,
        public array $traceRef,
    ) {}
}
