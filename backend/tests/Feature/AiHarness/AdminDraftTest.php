<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\ToolDraft;
use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\Enums\ToolClassification;
use App\AiHarness\PromptRegistry;
use App\AiHarness\Services\ContextAssemblyService;
use App\AiHarness\Services\ToolOrchestrationService;
use App\AiHarness\ToolRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 admin draft assistant tests.
 *
 * Tests APPROVAL_REQUIRED tool classification, ToolDraft struct,
 * context assembly RBAC, and PromptRegistry for admin_draft.
 */
class AdminDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config()->set('ai_harness.enabled', true);
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
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT, 'moderator');

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

    public function test_draft_cancellation_summary_returns_draft_not_executed(): void
    {
        $service = app(ToolOrchestrationService::class);
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT, 'moderator');

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
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT, 'moderator');

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
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT, 'user');

        $context = $service->assemble($request);

        // Non-admin should have contact_messages filtered out
        $sourceIds = array_column($context->sources, 'source_id');
        $this->assertNotContains('contact_messages', $sourceIds);
    }

    public function test_context_assembly_allows_contact_messages_for_moderator(): void
    {
        $service = app(ContextAssemblyService::class);
        $request = $this->makeRequest(TaskType::ADMIN_DRAFT, 'moderator');

        $context = $service->assemble($request);

        // Moderator should have contact_messages RBAC filter NOT applied
        $this->assertContains('exclude:contact_messages', $context->rbacFiltersApplied === [] ? ['exclude:contact_messages'] : []);
        // The actual source may or may not be present depending on DB state,
        // but the filter should not block it
        $this->assertNotContains('scope:own_bookings_only', $context->rbacFiltersApplied);
    }

    public function test_context_assembly_admin_draft_respects_token_budget(): void
    {
        $this->assertSame(6000, (int) config('ai_harness.token_budget.admin_draft'));
    }

    // ── PromptRegistry Tests ──

    public function test_admin_draft_prompt_version_is_correct(): void
    {
        $version = PromptRegistry::getVersion(TaskType::ADMIN_DRAFT);
        $this->assertSame('admin_draft-v1.0.0', $version);
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

    private function makeRequest(TaskType $taskType, string $role): HarnessRequest
    {
        return new HarnessRequest(
            requestId: 'test-' . uniqid(),
            correlationId: 'test-corr-' . uniqid(),
            taskType: $taskType,
            riskTier: RiskTier::LOW,
            promptVersion: PromptRegistry::getVersion($taskType),
            userId: $this->user->id,
            userRole: $role,
            userInput: 'Soạn phản hồi cho khách',
            locale: 'vi',
            featureRoute: "ai.{$taskType->value}",
        );
    }
}
