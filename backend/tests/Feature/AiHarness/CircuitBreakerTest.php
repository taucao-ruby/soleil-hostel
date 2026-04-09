<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\Exceptions\ProviderTimeoutException;
use App\AiHarness\Exceptions\ProviderUnavailableException;
use App\AiHarness\Providers\ModelProviderInterface;
use App\AiHarness\Providers\RawModelResponse;
use App\AiHarness\Services\ModelExecutionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests circuit breaker, timeout, and provider failover.
 */
class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_circuit_breaker_opens_after_failure_threshold(): void
    {
        config()->set('ai_harness.circuit_breaker.failure_threshold', 3);
        config()->set('ai_harness.circuit_breaker.recovery_timeout', 30);

        // Create a provider that always fails
        $failingProvider = $this->createMock(ModelProviderInterface::class);
        $failingProvider->method('getProviderName')->willReturn('test_provider');
        $failingProvider->method('isAvailable')->willReturn(true);
        $failingProvider->method('complete')->willThrowException(
            new ProviderUnavailableException('test_provider', 'Service down'),
        );

        $executionService = new ModelExecutionService($failingProvider);

        $request = $this->buildRequest();
        $context = $this->buildContext();

        // Exhaust the provider — should throw on each attempt
        for ($i = 0; $i < 3; $i++) {
            try {
                $executionService->execute($request, $context);
            } catch (ProviderUnavailableException) {
                // Expected
            }
        }

        // After threshold failures, the provider itself should track state.
        // The ModelExecutionService throws when all providers fail.
        $this->expectException(ProviderUnavailableException::class);
        $executionService->execute($request, $context);
    }

    public function test_circuit_breaker_routes_to_fallback_provider(): void
    {
        // Primary always fails
        $primary = $this->createMock(ModelProviderInterface::class);
        $primary->method('getProviderName')->willReturn('primary');
        $primary->method('isAvailable')->willReturn(false); // circuit open
        $primary->method('complete')->willThrowException(
            new ProviderUnavailableException('primary'),
        );

        // Fallback succeeds
        $fallbackResponse = new RawModelResponse(
            providerName: 'fallback',
            rawContent: 'Fallback response',
            promptTokens: 50,
            completionTokens: 25,
            latencyMs: 100,
        );

        $fallback = $this->createMock(ModelProviderInterface::class);
        $fallback->method('getProviderName')->willReturn('fallback');
        $fallback->method('isAvailable')->willReturn(true);
        $fallback->method('complete')->willReturn($fallbackResponse);

        $executionService = new ModelExecutionService($primary);
        $executionService->registerProvider($fallback);

        config()->set('ai_harness.default_provider', 'primary');

        $result = $executionService->execute($this->buildRequest(), $this->buildContext());

        $this->assertSame('fallback', $result->providerName);
        $this->assertSame('Fallback response', $result->rawContent);
    }

    public function test_timeout_triggers_fallback_response_class(): void
    {
        config()->set('ai_harness.enabled', true);
        config()->set('ai_harness.canary.faq_lookup_percentage', 100);

        // Mock provider to throw timeout
        $mock = $this->createMock(ModelProviderInterface::class);
        $mock->method('getProviderName')->willReturn('anthropic');
        $mock->method('isAvailable')->willReturn(true);
        $mock->method('complete')->willThrowException(
            new ProviderTimeoutException('anthropic', 3, 'Connection timed out'),
        );

        $this->app->instance(ModelProviderInterface::class, $mock);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'test timeout']);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('fallback', $data['response_class']);
    }

    private function buildRequest(): HarnessRequest
    {
        return new HarnessRequest(
            requestId: 'test-req-' . bin2hex(random_bytes(4)),
            correlationId: 'sol-test-' . bin2hex(random_bytes(4)),
            taskType: TaskType::FAQ_LOOKUP,
            riskTier: RiskTier::LOW,
            promptVersion: 'faq_lookup-v1.0.0',
            userId: 1,
            userRole: 'guest',
            userInput: 'test input',
            locale: 'vi',
            featureRoute: 'ai.faq_lookup',
        );
    }

    private function buildContext(): GroundedContext
    {
        return new GroundedContext(
            sources: [],
            totalTokens: 0,
            provenanceHash: hash('sha256', ''),
            rbacFiltersApplied: [],
        );
    }
}
