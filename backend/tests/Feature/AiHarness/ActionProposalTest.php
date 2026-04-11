<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\DTOs\BookingActionProposal;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\ProposalEvent;
use App\AiHarness\Enums\ProposalActionType;
use App\AiHarness\Enums\RiskTier;
use App\AiHarness\Enums\TaskType;
use App\AiHarness\Enums\ToolClassification;
use App\AiHarness\PromptRegistry;
use App\AiHarness\Services\PolicyEnforcementService;
use App\AiHarness\Services\ToolOrchestrationService;
use App\AiHarness\ToolRegistry;
use App\Models\AiProposalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 4+ action proposal tests.
 *
 * Tests BookingActionProposal struct, proposal validation,
 * confirmation flow, audit logging, and safety invariants.
 */
class ActionProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config()->set('ai_harness.enabled', true);
        config()->set('ai_harness.canary.room_discovery_percentage', 100);
    }

    // ── BookingActionProposal Struct Tests ──

    public function test_booking_action_proposal_struct_is_immutable(): void
    {
        $proposal = $this->makeBookingProposal();

        $this->assertSame(ProposalActionType::SUGGEST_BOOKING, $proposal->actionType);
        $this->assertSame('suggest_booking', $proposal->actionType->value);
        $this->assertTrue($proposal->requiresConfirmation);
        $this->assertNotEmpty($proposal->proposalHash);
    }

    public function test_booking_action_proposal_to_array_contains_all_fields(): void
    {
        $proposal = $this->makeBookingProposal();
        $array = $proposal->toArray();

        $this->assertArrayHasKey('action_type', $array);
        $this->assertArrayHasKey('proposed_params', $array);
        $this->assertArrayHasKey('human_readable_summary', $array);
        $this->assertArrayHasKey('policy_refs', $array);
        $this->assertArrayHasKey('risk_assessment', $array);
        $this->assertArrayHasKey('requires_confirmation', $array);
        $this->assertArrayHasKey('proposal_hash', $array);
        $this->assertArrayHasKey('generated_at', $array);
        $this->assertTrue($array['requires_confirmation']);
    }

    public function test_cancellation_proposal_action_type(): void
    {
        $proposal = $this->makeCancellationProposal();

        $this->assertSame(ProposalActionType::SUGGEST_CANCELLATION, $proposal->actionType);
        $this->assertSame('suggest_cancellation', $proposal->actionType->value);
        $this->assertTrue($proposal->requiresConfirmation);
    }

    // ── ProposalActionType Enum Tests ──

    public function test_proposal_action_type_only_has_suggest_variants(): void
    {
        $cases = ProposalActionType::cases();
        $values = array_map(fn ($c) => $c->value, $cases);

        $this->assertContains('suggest_booking', $values);
        $this->assertContains('suggest_cancellation', $values);
        $this->assertCount(2, $cases);
        // No execute_ variants exist
        $this->assertNotContains('execute_booking', $values);
        $this->assertNotContains('execute_cancellation', $values);
    }

    // ── PolicyEnforcementService Proposal Validation Tests ──

    public function test_validate_proposal_accepts_valid_booking_proposal(): void
    {
        $service = app(PolicyEnforcementService::class);
        $proposal = $this->makeBookingProposal();

        $decision = $service->validateProposal($proposal);

        $this->assertSame('allow', $decision->decision);
    }

    public function test_validate_proposal_accepts_valid_cancellation_proposal(): void
    {
        $service = app(PolicyEnforcementService::class);
        $proposal = $this->makeCancellationProposal();

        $decision = $service->validateProposal($proposal);

        $this->assertSame('allow', $decision->decision);
    }

    public function test_validate_proposal_rejects_missing_params_booking(): void
    {
        $service = app(PolicyEnforcementService::class);
        $proposal = new BookingActionProposal(
            actionType: ProposalActionType::SUGGEST_BOOKING,
            proposedParams: [], // Missing required: room_id, check_in, check_out
            humanReadableSummary: 'Test proposal',
            policyRefs: [],
            riskAssessment: ['level' => 'low', 'factors' => []],
            requiresConfirmation: true,
            proposalHash: hash('sha256', 'test'),
            generatedAt: now()->toIso8601String(),
        );

        $decision = $service->validateProposal($proposal);

        $this->assertSame('reject', $decision->decision);
        $this->assertStringContainsString('missing required parameters', $decision->reason);
    }

    public function test_validate_proposal_rejects_missing_risk_assessment(): void
    {
        $service = app(PolicyEnforcementService::class);
        $proposal = new BookingActionProposal(
            actionType: ProposalActionType::SUGGEST_BOOKING,
            proposedParams: ['room_id' => 1, 'check_in' => '2026-05-01', 'check_out' => '2026-05-03'],
            humanReadableSummary: 'Test proposal',
            policyRefs: [],
            riskAssessment: [], // Empty — should be rejected
            requiresConfirmation: true,
            proposalHash: hash('sha256', 'test'),
            generatedAt: now()->toIso8601String(),
        );

        $decision = $service->validateProposal($proposal);

        $this->assertSame('reject', $decision->decision);
        $this->assertStringContainsString('risk_assessment', $decision->reason);
    }

    public function test_validate_proposal_rejects_empty_summary(): void
    {
        $service = app(PolicyEnforcementService::class);
        $proposal = new BookingActionProposal(
            actionType: ProposalActionType::SUGGEST_BOOKING,
            proposedParams: ['room_id' => 1, 'check_in' => '2026-05-01', 'check_out' => '2026-05-03'],
            humanReadableSummary: '',
            policyRefs: [],
            riskAssessment: ['level' => 'low', 'factors' => []],
            requiresConfirmation: true,
            proposalHash: hash('sha256', 'test'),
            generatedAt: now()->toIso8601String(),
        );

        $decision = $service->validateProposal($proposal);

        $this->assertSame('reject', $decision->decision);
        $this->assertStringContainsString('human-readable summary', $decision->reason);
    }

    public function test_validate_proposal_rejects_missing_cancellation_booking_id(): void
    {
        $service = app(PolicyEnforcementService::class);
        $proposal = new BookingActionProposal(
            actionType: ProposalActionType::SUGGEST_CANCELLATION,
            proposedParams: ['reason' => 'guest request'], // Missing: booking_id
            humanReadableSummary: 'Cancel booking',
            policyRefs: [],
            riskAssessment: ['level' => 'medium', 'factors' => []],
            requiresConfirmation: true,
            proposalHash: hash('sha256', 'test'),
            generatedAt: now()->toIso8601String(),
        );

        $decision = $service->validateProposal($proposal);

        $this->assertSame('reject', $decision->decision);
        $this->assertStringContainsString('booking_id', $decision->reason);
    }

    // ── Tool Classification Tests ──

    public function test_draft_booking_suggestion_is_approval_required(): void
    {
        $this->assertSame(
            ToolClassification::APPROVAL_REQUIRED,
            ToolRegistry::classify('draft_booking_suggestion'),
        );
    }

    public function test_blocked_booking_tools_remain_blocked(): void
    {
        $this->assertTrue(ToolRegistry::isBlocked('create_booking'));
        $this->assertTrue(ToolRegistry::isBlocked('cancel_booking'));
        $this->assertTrue(ToolRegistry::isBlocked('confirm_booking'));
        $this->assertTrue(ToolRegistry::isBlocked('process_refund'));
        $this->assertTrue(ToolRegistry::isBlocked('modify_price'));
        $this->assertTrue(ToolRegistry::isBlocked('restore_booking'));
        $this->assertTrue(ToolRegistry::isBlocked('force_delete_booking'));
        $this->assertTrue(ToolRegistry::isBlocked('delete_account'));
    }

    // ── Tool Orchestration Tests ──

    public function test_draft_booking_suggestion_returns_booking_action_proposal(): void
    {
        $service = app(ToolOrchestrationService::class);
        $request = $this->makeRequest(TaskType::ROOM_DISCOVERY);

        $result = $service->execute([
            'tool' => 'draft_booking_suggestion',
            'input' => [
                'room_id' => 1,
                'check_in' => '2026-05-01',
                'check_out' => '2026-05-03',
                'guest_count' => 2,
            ],
        ], $request);

        $this->assertSame('draft_booking_suggestion', $result['tool']);
        $this->assertSame(ToolClassification::APPROVAL_REQUIRED->value, $result['classification']);
        $this->assertFalse($result['executed']);
        $this->assertArrayHasKey('action_type', $result['result']);
        $this->assertSame('suggest_booking', $result['result']['action_type']);
        $this->assertTrue($result['result']['requires_confirmation']);
        $this->assertArrayHasKey('risk_assessment', $result['result']);
        $this->assertArrayHasKey('proposal_hash', $result['result']);
    }

    public function test_suggest_cancellation_returns_booking_action_proposal(): void
    {
        $service = app(ToolOrchestrationService::class);
        $request = $this->makeRequest(TaskType::ROOM_DISCOVERY);

        $result = $service->execute([
            'tool' => 'suggest_cancellation',
            'input' => ['booking_id' => 999],
        ], $request);

        $this->assertSame('suggest_cancellation', $result['tool']);
        $this->assertFalse($result['executed']);
        $this->assertSame('suggest_cancellation', $result['result']['action_type']);
        $this->assertTrue($result['result']['requires_confirmation']);
    }

    public function test_suggest_cancellation_is_approval_required(): void
    {
        $this->assertSame(
            ToolClassification::APPROVAL_REQUIRED,
            ToolRegistry::classify('suggest_cancellation'),
        );
    }

    // ── ProposalEvent DTO Tests ──

    public function test_proposal_event_contains_all_fields(): void
    {
        $event = new ProposalEvent(
            userId: 1,
            proposalHash: 'abc123',
            actionType: 'suggest_booking',
            userDecision: 'confirmed',
            downstreamResult: 'booking_created:42',
            timestamp: '2026-04-11T00:00:00+00:00',
        );

        $array = $event->toArray();

        $this->assertSame(1, $array['user_id']);
        $this->assertSame('abc123', $array['proposal_hash']);
        $this->assertSame('suggest_booking', $array['action_type']);
        $this->assertSame('confirmed', $array['user_decision']);
        $this->assertSame('booking_created:42', $array['downstream_result']);
    }

    public function test_proposal_event_log_context_masks_user_id(): void
    {
        $event = new ProposalEvent(
            userId: 42,
            proposalHash: 'abc123',
            actionType: 'suggest_booking',
            userDecision: 'declined',
            downstreamResult: null,
            timestamp: '2026-04-11T00:00:00+00:00',
        );

        $context = $event->toLogContext();

        $this->assertStringStartsWith('user_', $context['user_id']);
        $this->assertNotSame(42, $context['user_id']);
    }

    // ── Proposal Confirmation Controller Tests ──

    public function test_decline_proposal_returns_success(): void
    {
        $hash = hash('sha256', 'test-decline');
        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_booking',
            'proposed_params' => ['room_id' => 1, 'check_in' => '2026-05-01', 'check_out' => '2026-05-03'],
        ], 1800);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'declined',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.decision', 'declined');

        // Verify audit log exists
        $this->assertDatabaseHas('ai_proposal_events', [
            'user_id' => $this->user->id,
            'proposal_hash' => $hash,
            'user_decision' => 'declined',
        ]);

        // Cache should be cleared
        $this->assertNull(Cache::get("ai_proposal:{$hash}"));
    }

    public function test_decide_expired_proposal_returns_404(): void
    {
        $hash = hash('sha256', 'expired-proposal');

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertNotFound();
    }

    public function test_decide_validates_decision_field(): void
    {
        $hash = hash('sha256', 'test-validation');
        Cache::put("ai_proposal:{$hash}", ['action_type' => 'suggest_booking'], 1800);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'maybe',
            ]);

        $response->assertUnprocessable();
    }

    public function test_decide_requires_authentication(): void
    {
        $hash = hash('sha256', 'test-auth');

        $response = $this->postJson("/api/v1/ai/proposals/{$hash}/decide", [
            'decision' => 'confirmed',
        ]);

        $response->assertUnauthorized();
    }

    public function test_audit_log_records_all_required_fields(): void
    {
        $hash = hash('sha256', 'test-audit');
        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_cancellation',
            'proposed_params' => ['booking_id' => 0],
        ], 1800);

        $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'declined',
            ]);

        $event = AiProposalEvent::where('proposal_hash', $hash)->first();
        $this->assertNotNull($event);
        $this->assertSame($this->user->id, $event->user_id);
        $this->assertSame($hash, $event->proposal_hash);
        $this->assertSame('suggest_cancellation', $event->action_type);
        $this->assertSame('declined', $event->user_decision);
        $this->assertNotNull($event->created_at);
    }

    // ── Safety Invariant Tests ──

    public function test_requires_confirmation_is_always_true(): void
    {
        $proposal = $this->makeBookingProposal();
        $this->assertTrue($proposal->requiresConfirmation);

        $array = $proposal->toArray();
        $this->assertTrue($array['requires_confirmation']);
    }

    public function test_blocked_tools_cannot_be_executed_via_tool_orchestration(): void
    {
        $service = app(ToolOrchestrationService::class);
        $request = $this->makeRequest(TaskType::ROOM_DISCOVERY);

        $this->expectException(\App\AiHarness\Exceptions\BlockedToolException::class);

        $service->execute([
            'tool' => 'create_booking',
            'input' => ['room_id' => 1, 'check_in' => '2026-05-01', 'check_out' => '2026-05-03'],
        ], $request);
    }

    // ── Helpers ──

    private function makeBookingProposal(): BookingActionProposal
    {
        return new BookingActionProposal(
            actionType: ProposalActionType::SUGGEST_BOOKING,
            proposedParams: [
                'room_id' => 5,
                'check_in' => '2026-05-01',
                'check_out' => '2026-05-03',
                'guest_count' => 2,
                'available' => true,
            ],
            humanReadableSummary: 'Đề xuất đặt phòng #5 từ 2026-05-01 đến 2026-05-03 cho 2 khách.',
            policyRefs: ['booking-policy'],
            riskAssessment: ['level' => 'low', 'factors' => []],
            requiresConfirmation: true,
            proposalHash: hash('sha256', 'test-booking-proposal'),
            generatedAt: now()->toIso8601String(),
        );
    }

    private function makeCancellationProposal(): BookingActionProposal
    {
        return new BookingActionProposal(
            actionType: ProposalActionType::SUGGEST_CANCELLATION,
            proposedParams: [
                'booking_id' => 42,
                'reason' => 'Guest request',
                'current_status' => 'confirmed',
            ],
            humanReadableSummary: 'Đề xuất hủy booking #42.',
            policyRefs: ['cancellation-policy'],
            riskAssessment: ['level' => 'medium', 'factors' => []],
            requiresConfirmation: true,
            proposalHash: hash('sha256', 'test-cancellation-proposal'),
            generatedAt: now()->toIso8601String(),
        );
    }

    private function makeRequest(TaskType $taskType, string $role = 'moderator'): HarnessRequest
    {
        return new HarnessRequest(
            requestId: 'test-'.uniqid(),
            correlationId: 'test-corr-'.uniqid(),
            taskType: $taskType,
            riskTier: RiskTier::LOW,
            promptVersion: PromptRegistry::getVersion($taskType),
            userId: $this->user->id,
            userRole: $role,
            userInput: 'Đề xuất đặt phòng',
            locale: 'vi',
            featureRoute: "ai.{$taskType->value}",
        );
    }
}
