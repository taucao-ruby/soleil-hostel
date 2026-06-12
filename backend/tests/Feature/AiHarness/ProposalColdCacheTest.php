<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\Enums\RoomReadinessStatus;
use App\Models\AiProposal;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\EnablesAiHarness;
use Tests\TestCase;

/**
 * F-86 — decide() must be DB-authoritative on a cold cache.
 *
 * The cache envelope is a fast path only. When it misses (Redis restart,
 * eviction, TTL race), the durable ai_proposals row decides whether the
 * proposal exists — with the same proposer-binding (F-06), consumed-state,
 * and expiry guarantees the cache gave implicitly.
 *
 * Cold cache here means: the ai_proposal:{hash} key was never written (or
 * was evicted). The whole cache is deliberately NOT flushed — the AI-harness
 * kill switch also lives in the cache (see EnablesAiHarness).
 */
class ProposalColdCacheTest extends TestCase
{
    use EnablesAiHarness, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->enableAiHarness();
    }

    // ── cold cache: cache-miss path is exercised, DB state served ──

    public function test_decline_succeeds_from_cold_cache_via_durable_row(): void
    {
        $hash = $this->seedDurableProposalOnly();

        $this->assertNull(
            Cache::get("ai_proposal:{$hash}"),
            'Precondition: the proposal cache key must be cold so the DB fallback path is exercised',
        );

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'declined',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.decision', 'declined');

        // The decision came from (and returned to) durable state: the event
        // trail records the DB row's action_type, and the row is consumed.
        $this->assertDatabaseHas('ai_proposal_events', [
            'proposal_hash' => $hash,
            'user_decision' => 'declined',
            'action_type' => 'suggest_booking',
        ]);

        $proposal = AiProposal::where('proposal_hash', $hash)->first();
        $this->assertSame('declined', $proposal?->decision);
        $this->assertNotNull($proposal?->decided_at);
    }

    public function test_confirm_succeeds_from_cold_cache_via_durable_row(): void
    {
        $room = $this->makeBookableRoom();
        $hash = $this->seedDurableProposalOnly(room: $room, shownAt: now());

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.decision', 'confirmed');

        // Confirm executed against DB-sourced proposed_params: a real
        // booking exists for the room/dates only the durable row knew.
        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertSame(
            'confirmed',
            AiProposal::where('proposal_hash', $hash)->value('decision'),
        );
    }

    // ── cold cache: every cache-path guarantee must survive the fallback ──

    public function test_cold_cache_cross_user_hash_is_404(): void
    {
        $hash = $this->seedDurableProposalOnly();
        $mallory = User::factory()->create();

        $response = $this->actingAs($mallory)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'declined',
            ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('ai_proposal_events', ['proposal_hash' => $hash]);
        $this->assertNull(
            AiProposal::where('proposal_hash', $hash)->value('decision'),
            'A cross-user probe must not consume the proposal',
        );
    }

    public function test_cold_cache_expired_durable_row_is_404(): void
    {
        $hash = $this->seedDurableProposalOnly(expiresAt: now()->subMinute());

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'declined',
            ]);

        $response->assertNotFound();
    }

    public function test_cold_cache_already_decided_row_is_404_no_replay(): void
    {
        $hash = $this->seedDurableProposalOnly(decision: 'declined');

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertNotFound();
    }

    public function test_decided_proposal_is_not_replayable_after_cache_eviction(): void
    {
        // Full replay scenario: decline with a warm cache, then simulate
        // eviction and try to decide again through the cold-cache fallback.
        $hash = $this->seedDurableProposalOnly();
        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_booking',
            'proposed_params' => ['room_id' => 1, 'available' => true],
            'proposer_user_id' => $this->user->id,
        ], 1800);

        $first = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", ['decision' => 'declined']);
        $first->assertOk();

        // decide() already forgets the key; assert it and decide again cold.
        $this->assertNull(Cache::get("ai_proposal:{$hash}"));

        $replay = $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", ['decision' => 'confirmed']);

        $replay->assertNotFound();
    }

    // ── warm cache: durable row stays in lockstep on decline ──

    public function test_warm_cache_decline_marks_durable_row_declined(): void
    {
        $hash = $this->seedDurableProposalOnly();
        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_booking',
            'proposed_params' => ['room_id' => 1, 'available' => true],
            'proposer_user_id' => $this->user->id,
        ], 1800);

        $this->actingAs($this->user)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", ['decision' => 'declined'])
            ->assertOk();

        $proposal = AiProposal::where('proposal_hash', $hash)->first();
        $this->assertSame('declined', $proposal?->decision);
        $this->assertNotNull($proposal?->decided_at);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Seed ONLY the durable ai_proposals row — never the cache envelope.
     * Mirrors ProposalLifecycleTest::seedBookingProposal minus Cache::put.
     */
    private function seedDurableProposalOnly(
        ?Room $room = null,
        ?\Illuminate\Support\Carbon $shownAt = null,
        ?\Illuminate\Support\Carbon $expiresAt = null,
        ?string $decision = null,
    ): string {
        $room ??= $this->makeBookableRoom();
        $expiresAt ??= now()->addMinutes(30);

        $checkIn = now()->addDays(7)->toDateString();
        $checkOut = now()->addDays(9)->toDateString();
        $hash = hash('sha256', "cold-cache-{$this->user->id}-{$room->id}-".bin2hex(random_bytes(4)));

        $quotedPriceCents = $room->currentPriceForDates($checkIn, $checkOut);

        AiProposal::create([
            'proposal_hash' => $hash,
            'user_id' => $this->user->id,
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
            'decision' => $decision,
            'decided_at' => $decision === null ? null : now(),
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
