<?php

declare(strict_types=1);

namespace Tests\Support;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Providers\ModelProviderInterface;
use App\AiHarness\Providers\RawModelResponse;
use Throwable;

final readonly class ThrowingModelProvider implements ModelProviderInterface
{
    public function __construct(private Throwable $exception) {}

    public function complete(HarnessRequest $req, GroundedContext $ctx): RawModelResponse
    {
        throw $this->exception;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return 'ai_eval';
    }
}
