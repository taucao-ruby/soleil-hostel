<?php

declare(strict_types=1);

namespace App\AiHarness\DTOs;

use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;

final readonly class HarnessRequest
{
    public function __construct(
        public string $requestId,
        public string $correlationId,
        public TaskType $taskType,
        public RiskTier $riskTier,
        public string $promptVersion,
        public int $userId,
        public string $userRole,
        public string $userInput,
        public string $locale,
        public string $featureRoute,
    ) {}
}
