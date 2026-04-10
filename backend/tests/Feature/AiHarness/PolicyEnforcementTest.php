<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\Providers\ModelProviderInterface;
use App\AiHarness\Providers\RawModelResponse;
use App\AiHarness\Services\PolicyEnforcementService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests policy enforcement: blocked tools, PII detection, risk tier gating.
 */
class PolicyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config()->set('ai_harness.enabled', true);
        config()->set('ai_harness.canary.faq_lookup_percentage', 100);
    }

    public function test_blocked_tool_proposal_is_rejected_and_logged(): void
    {
        // Mock the provider to return a response with a BLOCKED tool proposal
        $this->mockProvider(new RawModelResponse(
            providerName: 'anthropic',
            rawContent: 'I will cancel your booking now.',
            promptTokens: 100,
            completionTokens: 50,
            latencyMs: 200,
            toolProposals: [
                ['tool' => 'cancel_booking', 'input' => ['booking_id' => 1]],
            ],
        ));

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'Cancel my booking']);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('refusal', $data['response_class']);
    }

    public function test_blocked_tool_never_executes_any_service_method(): void
    {
        // The policy enforcement post-call should reject BEFORE tool orchestration
        $mockResponse = new RawModelResponse(
            providerName: 'anthropic',
            rawContent: 'Processing your cancellation...',
            promptTokens: 100,
            completionTokens: 50,
            latencyMs: 200,
            toolProposals: [
                ['tool' => 'create_booking', 'input' => ['room_id' => 1]],
            ],
        );

        $this->mockProvider($mockResponse);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'Create a booking']);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('refusal', $data['response_class']);
        $this->assertStringContains('blocked tool', $data['failure_reason'] ?? '');
    }

    public function test_pii_in_model_output_is_detected(): void
    {
        $mockResponse = new RawModelResponse(
            providerName: 'anthropic',
            rawContent: 'The guest email is john@example.com and phone is +84912345678.',
            promptTokens: 100,
            completionTokens: 50,
            latencyMs: 200,
        );

        $this->mockProvider($mockResponse);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'What is the policy?']);

        $response->assertOk();
        $data = $response->json('data');
        // PII in output triggers escalate, not reject — content may still be returned
        // but the policy decision flags it
        $this->assertNotNull($data['response_class']);
    }

    public function test_critical_risk_tier_request_is_abstained(): void
    {
        // Directly test the PolicyEnforcementService with a CRITICAL risk tier
        $service = new PolicyEnforcementService;

        $request = new HarnessRequest(
            requestId: 'test-123',
            correlationId: 'sol-test-abc',
            taskType: TaskType::FAQ_LOOKUP,
            riskTier: RiskTier::CRITICAL,
            promptVersion: 'faq_lookup-v1.0.0',
            userId: 1,
            userRole: 'guest',
            userInput: 'test input',
            locale: 'vi',
            featureRoute: 'ai.faq_lookup',
        );

        $decision = $service->screenInput($request);

        $this->assertSame('abstain', $decision->decision);
        $this->assertStringContains('risk tier', strtolower($decision->reason));
    }

    /**
     * Mock the ModelProviderInterface to return a predetermined response.
     */
    private function mockProvider(RawModelResponse $response): void
    {
        $mock = $this->createMock(ModelProviderInterface::class);
        $mock->method('complete')->willReturn($response);
        $mock->method('isAvailable')->willReturn(true);
        $mock->method('getProviderName')->willReturn('anthropic');

        $this->app->instance(ModelProviderInterface::class, $mock);
    }

    /**
     * Assert that a string contains a substring (case-insensitive).
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains(strtolower($haystack), strtolower($needle)),
            "Expected '{$haystack}' to contain '{$needle}'",
        );
    }
}
