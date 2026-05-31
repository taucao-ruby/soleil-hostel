<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\Enums\RoomReadinessStatus;
use App\Models\AiProposal;
use App\Models\AiProposalEvent;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\CreateBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\Support\EnablesAiHarness;
use Tests\TestCase;

/**
 * AI-005 / AI-006 — proposal lifecycle revalidation.
 *
 * Covers the contract that `decide(confirmed)` enforces at confirm time:
 *  - expiry           → 422 proposal_expired
 *  - shown gate       → 422 proposal_not_shown
 *  - room availability → 422 proposed_room_no_longer_available
 *  - price drift      → 422 proposal_price_changed
 *
 * And the AI-005 shown endpoint:
 *  - idempotent
 *  - 404 on unknown / cross-user hash
 *
 * The fixtures bypass the full orchestration pipeline (which runs the
 * model + tools) by seeding the Cache envelope + AiProposal row directly.
 * The flow under test is: cache hit → proposer binding → durable lookup
 * → revalidation gates → execute (or refuse).
 */
class ProposalLifecycleTest extends TestCase
{
    use EnablesAiHarness, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->enableAiHarness();
    }

    // ── shown endpoint ──────────────────────────────────────────────

    public function test_shown_emits_event_and_marks_proposal(): void
    {
        $hash = $this->seedBookingProposal();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/shown");

        $response->assertOk();
        $response->assertJsonPath('data.shown', true);
        $response->assertJsonPath('data.idempotent', false);

        $this->assertDatabaseHas('ai_proposal_events', [
            'user_id' => $this->user->id,
            'proposal_hash' => $hash,
            'user_decision' => 'shown',
        ]);

        $proposal = AiProposal::where('proposal_hash', $hash)->first();
        $this->assertNotNull($proposal);
        $this->assertNotNull($proposal->shown_at);
    }

    public function test_shown_is_idempotent_on_repeat(): void
    {
        $hash = $this->seedBookingProposal();

        $first = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/shown");

        $second = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/shown");

        $first->assertOk();
        $second->assertOk();
        $second->assertJsonPath('data.idempotent', true);

        // Exactly one shown event row exists for this proposal.
        $count = \DB::table('ai_proposal_events')
            ->where('proposal_hash', $hash)
            ->where('user_decision', 'shown')
            ->count();

        $this->assertSame(1, $count, 'Idempotent shown must not double-insert an event row');
    }

    public function test_shown_404s_unknown_hash(): void
    {
        $hash = hash('sha256', 'never-existed');

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/shown");

        $response->assertNotFound();
    }

    public function test_shown_404s_for_cross_user(): void
    {
        $alice = $this->user;
        $hash = $this->seedBookingProposal($alice);

        $mallory = User::factory()->create();

        $response = $this->actingAs($mallory)
            ->postJson("/api/v1/ai/proposals/{$hash}/shown");

        $response->assertNotFound();

        // No event was emitted, no shown_at set.
        $this->assertDatabaseMissing('ai_proposal_events', ['proposal_hash' => $hash]);
        $proposal = AiProposal::where('proposal_hash', $hash)->first();
        $this->assertNotNull($proposal);
        $this->assertNull($proposal->shown_at);
    }

    public function test_shown_422s_when_proposal_expired(): void
    {
        $hash = $this->seedBookingProposal(expiresAt: now()->subMinute());

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/shown");

        $response->assertStatus(422);
        $response->assertJsonPath('errors.code', 'proposal_expired');
    }

    // ── decide(confirmed): revalidation gates ──────────────────────

    public function test_confirm_returns_422_when_not_yet_shown(): void
    {
        $hash = $this->seedBookingProposal(shownAt: null);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.code', 'proposal_not_shown');

        // No booking was created.
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_confirm_returns_422_when_proposal_expired(): void
    {
        $hash = $this->seedBookingProposal(
            shownAt: now()->subMinutes(10),
            expiresAt: now()->subMinute(),
        );

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.code', 'proposal_expired');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_confirm_returns_422_when_room_no_longer_bookable(): void
    {
        $room = $this->makeBookableRoom(price: 500_000);
        $hash = $this->seedBookingProposal(
            room: $room,
            shownAt: now()->subMinutes(2),
        );

        // Mutate room to non-bookable (room out of service).
        $room->update([
            'readiness_status' => RoomReadinessStatus::OUT_OF_SERVICE->value,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.code', 'proposed_room_no_longer_available');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_confirm_returns_422_when_price_drifted(): void
    {
        $room = $this->makeBookableRoom(price: 500_000);
        $hash = $this->seedBookingProposal(
            room: $room,
            shownAt: now()->subMinutes(2),
        );

        // Acceptance criterion: mutate room price, confirm proposal,
        // assert rejection. The persisted quoted_price_cents was computed
        // at proposal time using the original price; the recheck uses the
        // current price.
        $room->update(['price' => 750_000]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.code', 'proposal_price_changed');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_confirm_succeeds_when_all_gates_pass(): void
    {
        $room = $this->makeBookableRoom(price: 500_000);
        $hash = $this->seedBookingProposal(
            room: $room,
            shownAt: now()->subMinutes(2),
        );
        $user = $this->user;

        $this->mock(CreateBookingService::class, function (MockInterface $mock) use ($room, $user): void {
            $mock->shouldReceive('create')
                ->once()
                ->andReturnUsing(function () use ($room, $user): Booking {
                    return Booking::factory()
                        ->for($user)
                        ->for($room)
                        ->create([
                            'check_in' => now()->addDays(7)->startOfDay(),
                            'check_out' => now()->addDays(9)->startOfDay(),
                        ]);
                });
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.decision', 'confirmed');
        $this->assertStringStartsWith(
            'booking_created:',
            (string) $response->json('data.downstream_result'),
        );

        $this->assertDatabaseCount('bookings', 1);

        $proposal = AiProposal::where('proposal_hash', $hash)->first();
        $this->assertSame('confirmed', $proposal?->decision);
        $this->assertNotNull($proposal?->decided_at);

        $this->assertDatabaseHas('ai_proposal_events', [
            'proposal_hash' => $hash,
            'user_decision' => 'confirmed',
        ]);
    }

    public function test_confirm_downstream_exception_records_errored_not_confirmed(): void
    {
        $room = $this->makeBookableRoom(price: 500_000);
        $hash = $this->seedBookingProposal(
            room: $room,
            shownAt: now()->subMinutes(2),
        );
        $rawExceptionMessage = 'raw downstream secret token should not leak';

        $this->mock(CreateBookingService::class, function (MockInterface $mock) use ($rawExceptionMessage): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException($rawExceptionMessage));
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertStatus(502);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('errors.downstream_result.status', 'failed');
        $response->assertJsonPath('errors.downstream_result.failure_reason', 'downstream_execution_failed');
        $response->assertJsonPath('errors.downstream_result.error_class', 'RuntimeException');
        $response->assertJsonPath('errors.downstream_result.message', 'Downstream execution failed.');
        $this->assertStringNotContainsString($rawExceptionMessage, $response->getContent());
        $this->assertStringNotContainsString('RuntimeException: '.$rawExceptionMessage, $response->getContent());

        $proposal = AiProposal::where('proposal_hash', $hash)->first();
        $this->assertSame('errored', $proposal?->decision);
        $this->assertNotNull($proposal?->decided_at);

        $event = AiProposalEvent::where('proposal_hash', $hash)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('errored', $event->user_decision);
        $this->assertStringNotContainsString($rawExceptionMessage, (string) $event->downstream_result);

        $downstreamResult = json_decode((string) $event->downstream_result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('failed', $downstreamResult['status']);
        $this->assertSame('downstream_execution_failed', $downstreamResult['failure_reason']);
        $this->assertSame('RuntimeException', $downstreamResult['error_class']);
        $this->assertSame('Downstream execution failed.', $downstreamResult['message']);

        $this->assertDatabaseMissing('ai_proposals', [
            'proposal_hash' => $hash,
            'decision' => 'confirmed',
        ]);
        $this->assertDatabaseMissing('ai_proposal_events', [
            'proposal_hash' => $hash,
            'user_decision' => 'confirmed',
        ]);
    }

    // ── decide(confirmed): missing durable record ──────────────────

    public function test_confirm_returns_404_when_durable_record_missing(): void
    {
        // Cache exists but no AiProposal row — a stale-cache attack
        // surface. Must be treated identically to a missing cache entry.
        $hash = hash('sha256', 'cache-only-no-durable-row');
        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_booking',
            'proposed_params' => ['room_id' => 1, 'check_in' => '2026-06-01', 'check_out' => '2026-06-03'],
            'proposer_user_id' => $this->user->id,
        ], 1800);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertNotFound();
    }

    // ── shown → confirm full flow ──────────────────────────────────

    public function test_full_flow_shown_then_confirm(): void
    {
        $room = $this->makeBookableRoom(price: 400_000);
        $hash = $this->seedBookingProposal(room: $room, shownAt: null);

        $shown = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/shown");

        $shown->assertOk();

        $confirm = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $confirm->assertOk();
        $confirm->assertJsonPath('data.decision', 'confirmed');
    }

    // ── Helpers ──

    /**
     * Seed a booking-shape proposal in both the cache (proposer envelope)
     * and the durable AiProposal row, mirroring what the orchestration
     * pipeline persists.
     */
    private function seedBookingProposal(
        ?User $user = null,
        ?Room $room = null,
        ?\Illuminate\Support\Carbon $shownAt = null,
        ?\Illuminate\Support\Carbon $expiresAt = null,
    ): string {
        $user ??= $this->user;
        $room ??= $this->makeBookableRoom();
        $expiresAt ??= now()->addMinutes(30);

        $checkIn = now()->addDays(7)->toDateString();
        $checkOut = now()->addDays(9)->toDateString();
        $hash = hash('sha256', "lifecycle-{$user->id}-{$room->id}-{$checkIn}-{$checkOut}-".bin2hex(random_bytes(4)));

        $quotedPriceCents = $room->currentPriceForDates($checkIn, $checkOut);

        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_booking',
            'proposed_params' => [
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => 2,
                'available' => true,
            ],
            'proposer_user_id' => $user->id,
        ], 1800);

        AiProposal::create([
            'proposal_hash' => $hash,
            'user_id' => $user->id,
            'action_type' => 'suggest_booking',
            'room_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'quoted_price_cents' => $quotedPriceCents,
            'context_version' => hash('sha256', "{$room->id}:{$checkIn}:{$checkOut}:{$quotedPriceCents}:1"),
            'proposed_params' => [
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => 2,
                'available' => true,
            ],
            'risk_assessment' => ['level' => 'low', 'factors' => []],
            'expires_at' => $expiresAt,
            'shown_at' => $shownAt,
        ]);

        return $hash;
    }

    private function makeBookableRoom(int $price = 500_000): Room
    {
        return Room::factory()->create([
            'price' => $price,
            'status' => 'available',
            'readiness_status' => RoomReadinessStatus::READY->value,
        ]);
    }
}
