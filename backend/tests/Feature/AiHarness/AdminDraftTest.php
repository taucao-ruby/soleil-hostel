<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\ToolDraft;
use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\Enums\ToolClassification;
use App\AiHarness\Exceptions\BlockedToolException;
use App\AiHarness\PromptRegistry;
use App\AiHarness\Providers\ModelProviderInterface;
use App\AiHarness\Providers\RawModelResponse;
use App\AiHarness\Services\ContextAssemblyService;
use App\AiHarness\Services\ToolOrchestrationService;
use App\AiHarness\ToolRegistry;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EnablesAiHarness;
use Tests\TestCase;

/**
 * Phase 3 admin draft assistant tests.
 *
 * Tests APPROVAL_REQUIRED tool classification, ToolDraft struct,
 * context assembly RBAC, and PromptRegistry for admin_draft.
 */
class AdminDraftTest extends TestCase
{
    use EnablesAiHarness, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->moderator()->create();
        $this->enableAiHarness();
        config()->set('ai_harness.canary.admin_draft_percentage', 100);
    }

    // ── Tool Classification Tests ──

    public function test_draft_admin_message_is_approval_required(): void
    {
        $classification = ToolRegistry::classify('draft_admin_message');
        $this->assertSame(ToolClassification::APPROVAL_REQUIRED, $classification);
    }

    public function test_draft_cancellation_summary_is_approval_required(): void
    {
        $classification = ToolRegistry::classify('draft_cancellation_summary');
        $this->assertSame(ToolClassification::APPROVAL_REQUIRED, $classification);
    }

    public function test_draft_tools_have_correct_rbac_gates(): void
    {
        $this->assertSame('moderator', ToolRegistry::rbacGate('draft_admin_message'));
        $this->assertSame('cancel', ToolRegistry::rbacGate('draft_cancellation_summary'));
    }

    // ── ToolDraft Struct Tests ──

    public function test_tool_draft_struct_is_immutable(): void
    {
        $draft = new ToolDraft(
            toolName: 'draft_admin_message',
            draftText: 'Test draft',
            suggestedTone: 'professional',
            contextUsed: ['contact_message:1'],
            policyRefs: ['cancellation-policy'],
            keyFacts: ['guest_name' => 'Test'],
            draftHash: hash('sha256', 'test'),
            generatedAt: now()->toIso8601String(),
        );

        $this->assertSame('draft_admin_message', $draft->toolName);
        $this->assertSame('Test draft', $draft->draftText);
        $this->assertSame('professional', $draft->suggestedTone);
        $this->assertNotEmpty($draft->draftHash);
    }

    public function test_tool_draft_to_array_contains_all_fields(): void
    {
        $draft = new ToolDraft(
            toolName: 'draft_cancellation_summary',
            draftText: 'Summary text',
            suggestedTone: 'empathetic',
            contextUsed: ['booking:42'],
            policyRefs: ['cancellation-policy'],
            keyFacts: ['booking_id' => 42, 'status' => 'cancelled'],
            draftHash: 'abc123',
            generatedAt: '2026-04-09T00:00:00+00:00',
        );

        $array = $draft->toArray();

        $this->assertArrayHasKey('tool_name', $array);
        $this->assertArrayHasKey('draft_text', $array);
        $this->assertArrayHasKey('suggested_tone', $array);
        $this->assertArrayHasKey('context_used', $array);
        $this->assertArrayHasKey('policy_refs', $array);
        $this->assertArrayHasKey('key_facts', $array);
        $this->assertArrayHasKey('draft_hash', $array);
        $this->assertArrayHasKey('generated_at', $array);
    }

    // ── Tool Orchestration Tests ──

    public function test_draft_admin_message_returns_draft_not_executed(): void
    {
        $service = app(ToolOrchestrationService::class);
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT);

        $result = $service->execute([
            'tool' => 'draft_admin_message',
            'input' => ['draft_text' => 'Thank you for your stay', 'tone' => 'professional'],
        ], $request);

        $this->assertSame('draft_admin_message', $result['tool']);
        $this->assertSame(ToolClassification::APPROVAL_REQUIRED->value, $result['classification']);
        $this->assertFalse($result['executed']);
        $this->assertArrayHasKey('draft_text', $result['result']);
        $this->assertArrayHasKey('draft_hash', $result['result']);
    }

    public function test_draft_admin_message_denies_regular_user_even_when_harness_enabled(): void
    {
        $regularUser = User::factory()->user()->create();
        $message = $this->createContactMessage();

        $this->mockProvider(new RawModelResponse(
            providerName: 'anthropic',
            rawContent: 'Private draft that must not be surfaced.',
            promptTokens: 100,
            completionTokens: 30,
            latencyMs: 200,
            toolProposals: [[
                'tool' => 'draft_admin_message',
                'input' => [
                    'contact_message_id' => $message->id,
                    'draft_text' => 'Private draft that must not be surfaced.',
                    'tone' => 'professional',
                ],
            ]],
        ));

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson('/api/v1/ai/admin_draft', [
                'message' => "Soạn phản hồi cho contact message#{$message->id}",
            ]);

        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame('refusal', $data['response_class']);
        $this->assertStringNotContainsString('Private draft', (string) $data['content']);
        $this->assertEmpty($data['proposals']);
    }

    public function test_rbac_gated_draft_admin_message_is_enforced_by_orchestration(): void
    {
        $service = app(ToolOrchestrationService::class);
        $regularUser = User::factory()->user()->create();

        $this->expectException(BlockedToolException::class);

        $service->execute([
            'tool' => 'draft_admin_message',
            'input' => ['draft_text' => 'Hello'],
        ], $this->makeRequest(TaskType::ADMIN_DRAFT, $regularUser));
    }

    public function test_draft_admin_message_denies_moderator_for_contact_message_because_contact_messages_are_admin_only(): void
    {
        $service = app(ToolOrchestrationService::class);
        $message = $this->createContactMessage();

        $this->expectException(BlockedToolException::class);

        $service->execute([
            'tool' => 'draft_admin_message',
            'input' => [
                'contact_message_id' => $message->id,
                'draft_text' => 'Moderator should not see contact message PII.',
            ],
        ], $this->makeRequest(TaskType::ADMIN_DRAFT));
    }

    public function test_draft_admin_message_allows_admin_for_contact_message(): void
    {
        $service = app(ToolOrchestrationService::class);
        $admin = User::factory()->admin()->create();
        $message = $this->createContactMessage([
            'name' => 'Admin Visible Guest',
            'email' => 'admin-visible@example.test',
            'subject' => 'Late arrival',
            'message' => 'I will arrive after midnight.',
        ]);

        $result = $service->execute([
            'tool' => 'draft_admin_message',
            'input' => [
                'contact_message_id' => $message->id,
                'draft_text' => 'We can support your late arrival.',
            ],
        ], $this->makeRequest(TaskType::ADMIN_DRAFT, $admin));

        $this->assertSame('draft_admin_message', $result['tool']);
        $this->assertFalse($result['executed']);
        $this->assertContains("contact_message:{$message->id}", $result['result']['context_used']);
        $this->assertSame('Admin Visible Guest', $result['result']['key_facts']['guest_name']);
        $this->assertSame('Late arrival', $result['result']['key_facts']['subject']);
    }

    public function test_draft_admin_message_path_does_not_use_raw_contact_message_find(): void
    {
        $source = file_get_contents(app_path('AiHarness/Services/ToolOrchestrationService.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('ContactMessage::find(', $source);
        $this->assertStringNotContainsString('ContactMessage::findOrFail(', $source);
        $this->assertStringContainsString('resolveContactMessageForAdminDraft', $source);
    }

    public function test_draft_cancellation_summary_returns_draft_not_executed(): void
    {
        $service = app(ToolOrchestrationService::class);
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT);

        $result = $service->execute([
            'tool' => 'draft_cancellation_summary',
            'input' => ['booking_id' => 999, 'summary_text' => 'Booking cancelled per request'],
        ], $request);

        $this->assertSame('draft_cancellation_summary', $result['tool']);
        $this->assertSame(ToolClassification::APPROVAL_REQUIRED->value, $result['classification']);
        $this->assertFalse($result['executed']);
    }

    public function test_cancellation_summary_without_booking_id_returns_insufficient_context(): void
    {
        $service = app(ToolOrchestrationService::class);
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT);

        $result = $service->execute([
            'tool' => 'draft_cancellation_summary',
            'input' => [],
        ], $request);

        $this->assertStringContainsString('INSUFFICIENT_CONTEXT', $result['result']['draft_text']);
    }

    // ── ContextAssembly Tests ──

    public function test_context_assembly_blocks_contact_messages_for_non_admin(): void
    {
        $service = app(ContextAssemblyService::class);
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT, User::factory()->user()->create());

        $context = $service->assemble($request);

        // Non-admin should have contact_messages filtered out
        $sourceIds = array_column($context->sources, 'source_id');
        $this->assertNotContains('contact_messages', $sourceIds);
    }

    public function test_context_assembly_blocks_contact_messages_for_moderator(): void
    {
        $service = app(ContextAssemblyService::class);
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT);

        $context = $service->assemble($request);

        $sourceIds = array_column($context->sources, 'source_id');
        $this->assertNotContains('contact_messages', $sourceIds);
        $this->assertContains('exclude:contact_messages', $context->rbacFiltersApplied);
        $this->assertNotContains('scope:own_bookings_only', $context->rbacFiltersApplied);
    }

    public function test_context_assembly_allows_contact_messages_for_admin(): void
    {
        $service = app(ContextAssemblyService::class);
        $admin = User::factory()->admin()->create();
        $message = $this->createContactMessage(['subject' => 'Admin context']);

        $context = $service->assemble($this->makeRequest(
            TaskType::ADMIN_DRAFT,
            $admin,
            "Soạn phản hồi cho message#{$message->id}",
        ));

        $sourceIds = array_column($context->sources, 'source_id');
        $this->assertContains('contact_messages', $sourceIds);
        $this->assertNotContains('exclude:contact_messages', $context->rbacFiltersApplied);
    }

    public function test_context_assembly_admin_draft_respects_token_budget(): void
    {
        $this->assertSame(6000, (int) config('ai_harness.token_budget.admin_draft'));
    }

    // ── PromptRegistry Tests ──

    public function test_admin_draft_prompt_version_is_correct(): void
    {
        $version = PromptRegistry::getVersion(TaskType::ADMIN_DRAFT);
        $this->assertSame('admin_draft-v1.1.0', $version);
    }

    public function test_admin_draft_prompt_validates(): void
    {
        $this->assertTrue(PromptRegistry::validate(TaskType::ADMIN_DRAFT));
    }

    public function test_admin_draft_prompt_contains_key_instructions(): void
    {
        $template = PromptRegistry::getTemplate(TaskType::ADMIN_DRAFT);

        $this->assertStringContainsString('drafting assistant', $template['system_instruction']);
        $this->assertStringContainsString('INSUFFICIENT_CONTEXT', $template['system_instruction']);
        $this->assertStringContainsString('reviewed by a human', $template['system_instruction']);
        $this->assertStringContainsString('Never use phrases like', $template['system_instruction']);
    }

    public function test_admin_draft_prompt_prohibits_autonomous_action_claims(): void
    {
        $template = PromptRegistry::getTemplate(TaskType::ADMIN_DRAFT);

        $this->assertStringContainsString('I have cancelled', $template['system_instruction']);
        $this->assertStringContainsString('I have refunded', $template['system_instruction']);
    }

    // ── Config Tests ──

    public function test_admin_draft_canary_percentage_defaults_to_zero(): void
    {
        // Reset to default (from env)
        config()->set('ai_harness.canary.admin_draft_percentage', (int) env('AI_CANARY_ADMIN_DRAFT_PCT', 0));
        $this->assertSame(0, (int) config('ai_harness.canary.admin_draft_percentage'));
    }

    public function test_admin_draft_timeout_is_configured(): void
    {
        $this->assertSame(15, config('ai_harness.timeout_ladder.admin_draft'));
    }

    // ── Helper ──

    private function makeRequest(
        TaskType $taskType,
        ?User $user = null,
        string $input = 'Soạn phản hồi cho khách',
    ): HarnessRequest {
        $user ??= $this->user;
        $role = $user->role instanceof \BackedEnum
            ? (string) $user->role->value
            : (string) $user->role;

        return new HarnessRequest(
            requestId: 'test-'.uniqid(),
            correlationId: 'test-corr-'.uniqid(),
            taskType: $taskType,
            riskTier: RiskTier::LOW,
            promptVersion: PromptRegistry::getVersion($taskType),
            userId: $user->id,
            userRole: $role,
            userInput: $input,
            locale: 'vi',
            featureRoute: "ai.{$taskType->value}",
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createContactMessage(array $overrides = []): ContactMessage
    {
        return ContactMessage::create($overrides + [
            'name' => 'Harness Guest',
            'email' => 'harness-guest@example.test',
            'subject' => 'Question about my stay',
            'message' => 'Please help me with my reservation.',
        ]);
    }

    private function mockProvider(RawModelResponse $response): void
    {
        $mock = $this->createMock(ModelProviderInterface::class);
        $mock->method('complete')->willReturn($response);
        $mock->method('isAvailable')->willReturn(true);
        $mock->method('getProviderName')->willReturn('anthropic');

        $this->app->instance(ModelProviderInterface::class, $mock);
    }
}
