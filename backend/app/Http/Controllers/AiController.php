<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Services\AiOrchestrationService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single entry point for all AI harness requests.
 *
 * Route: POST /api/v1/ai/{task_type}
 *
 * The AiRequestNormalizer middleware builds the HarnessRequest DTO
 * and attaches it to $request->attributes before this controller runs.
 */
class AiController extends Controller
{
    public function __construct(
        private readonly AiOrchestrationService $orchestration,
    ) {}

    public function handle(Request $request, string $taskType): JsonResponse
    {
        /** @var HarnessRequest $harnessRequest */
        $harnessRequest = $request->attributes->get('harness_request');

        $response = $this->orchestration->handle($harnessRequest);

        return ApiResponse::success([
            'request_id' => $response->requestId,
            'response_class' => $response->responseClass->value,
            'content' => $response->content,
            'citations' => $response->citations,
            'proposals' => $response->proposals,
            'failure_reason' => $response->failureReason,
            'estimated_cost_usd' => $response->estimatedCostUsd,
            'trace_ref' => $response->traceRef,
        ]);
    }
}
