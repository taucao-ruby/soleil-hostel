<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\DTOs\BookingActionProposal;
use App\AiHarness\Enums\ProposalActionType;
use App\AiHarness\Services\AiOrchestrationService;
use App\Enums\RoomReadinessStatus;
use App\Models\AiProposal;
use App\Models\Room;
use App\Models\User;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * F-86 — DB-write-before-cache ordering for proposal storage.
 *
 * AiOrchestrationService::storeProposal must persist the durable
 * ai_proposals row BEFORE the cache envelope becomes visible. These tests
 * are real regression guards, not tautologies: reintroducing the old
 * cache-first ordering makes test 1 fail (the KeyWritten probe observes
 * the durable row as absent at cache-write time), and dropping the
 * DB::afterCommit wrapper makes test 2 fail (the envelope becomes visible
 * inside an uncommitted transaction).
 *
 * Determinism: the array cache driver (phpunit.xml CACHE_STORE=array) fires
 * KeyWritten synchronously and has no TTL clock dependency.
 */
class ProposalStoreOrderingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_durable_row_is_written_before_cache_envelope(): void
    {
        $room = $this->makeBookableRoom();
        $proposal = $this->makeProposalDto($room);
        $cacheKey = "ai_proposal:{$proposal->proposalHash}";

        // Probe: at the exact moment the envelope is written to the cache,
        // record whether the durable row already exists. With cache-first
        // ordering this captures FALSE; with DB-first ordering, TRUE.
        $durableRowExistedAtCacheWrite = null;
        Event::listen(KeyWritten::class, function (KeyWritten $event) use ($cacheKey, $proposal, &$durableRowExistedAtCacheWrite): void {
            if ($event->key === $cacheKey) {
                $durableRowExistedAtCacheWrite = AiProposal::query()
                    ->where('proposal_hash', $proposal->proposalHash)
                    ->exists();
            }
        });

        app(AiOrchestrationService::class)->storeProposal($proposal, (int) $this->user->id);

        $this->assertNotNull(
            $durableRowExistedAtCacheWrite,
            'Cache envelope was never written — storeProposal must populate the cache',
        );
        $this->assertTrue(
            $durableRowExistedAtCacheWrite,
            'ORDERING REGRESSION: the cache envelope was written before the durable '
            .'ai_proposals row existed (F-86 cache-first race window reintroduced)',
        );
    }

    public function test_cache_envelope_matches_durable_row_read_through(): void
    {
        $room = $this->makeBookableRoom();
        $proposal = $this->makeProposalDto($room);

        app(AiOrchestrationService::class)->storeProposal($proposal, (int) $this->user->id);

        $row = AiProposal::query()->where('proposal_hash', $proposal->proposalHash)->first();
        $envelope = Cache::get("ai_proposal:{$proposal->proposalHash}");

        $this->assertNotNull($row, 'Durable row must exist after storeProposal');
        $this->assertIsArray($envelope, 'Cache envelope must exist after storeProposal');

        // The envelope must agree with the durable row on every field the
        // decision flow consumes (read-through coherence: cache is a copy of
        // DB state, never an independent source of truth).
        $this->assertSame($row->action_type, $envelope['action_type']);
        $this->assertSame($row->proposed_params, $envelope['proposed_params']);
        $this->assertSame((int) $row->user_id, $envelope['proposer_user_id']);
    }

    public function test_cache_envelope_is_deferred_until_transaction_commit(): void
    {
        $room = $this->makeBookableRoom();
        $proposal = $this->makeProposalDto($room);
        $cacheKey = "ai_proposal:{$proposal->proposalHash}";

        $visibleInsideTransaction = null;

        DB::transaction(function () use ($proposal, $cacheKey, &$visibleInsideTransaction): void {
            app(AiOrchestrationService::class)->storeProposal($proposal, (int) $this->user->id);

            // Inside the still-uncommitted transaction the durable row is not
            // yet visible to other connections — the envelope must not be
            // either, or a concurrent decide() could consume a proposal whose
            // durable row never commits.
            $visibleInsideTransaction = Cache::get($cacheKey) !== null;
        });

        $this->assertFalse(
            $visibleInsideTransaction,
            'ORDERING REGRESSION: cache envelope became visible before the wrapping '
            .'DB transaction committed (DB::afterCommit contract broken)',
        );

        $this->assertNotNull(
            Cache::get($cacheKey),
            'Cache envelope must be populated once the transaction has committed',
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function makeProposalDto(Room $room): BookingActionProposal
    {
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(12)->toDateString();

        return new BookingActionProposal(
            actionType: ProposalActionType::SUGGEST_BOOKING,
            proposedParams: [
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_count' => 2,
                'available' => true,
            ],
            humanReadableSummary: 'Đặt phòng thử nghiệm cho kiểm thử thứ tự ghi.',
            policyRefs: [],
            riskAssessment: ['level' => 'low', 'factors' => []],
            requiresConfirmation: true,
            proposalHash: hash('sha256', 'store-ordering-'.bin2hex(random_bytes(8))),
            generatedAt: now()->toIso8601String(),
        );
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
