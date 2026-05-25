<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\Enums\BookingStatus;
use App\Models\AiProposal;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\EnablesAiHarness;
use Tests\TestCase;

/**
 * BL-7 — ProposalConfirmationController::executeCancellation passes the
 * authenticated proposer/actor id (not the booking owner id) into
 * BookingService::cancelBooking. CancellationService::validateCancellation
 * then enforces booking ownership against that actor.
 *
 * This file is the dedicated regression net for the BL-7 invariant. Without
 * it, a future "cleanup" that swaps the actor id for $booking->user_id at
 * the call site would silently convert the proposal cancellation path into
 * a confused-deputy authorization bypass: any caller who could name a
 * booking_id in a confirmed proposal would be able to cancel that booking.
 *
 * Adjacent coverage at other layers (kept here for traceability):
 *   - Tests\Feature\AiHarness\ActionProposalTest
 *       ::test_decide_rejects_cross_user_confirmation
 *       — F-06 proposer-binding gate: someone else's hash cannot be decided.
 *   - Tests\Feature\AiHarness\ActionProposalTest
 *       ::test_confirm_cannot_cancel_other_users_booking_via_proposal
 *       — service-layer ownership gate at the route boundary.
 *   - Tests\Feature\BookingCancellationTest
 *       ::test_cancellation_service_rejects_non_owner_non_admin_actor
 *       — direct service-layer invocation with a non-owner actor.
 *
 * What this file adds:
 *   1. The missing positive case — owner-proposer CAN cancel their own
 *      booking via the proposal path. This locks the "owner allowed" half
 *      of BL-7 so a future refactor cannot accidentally over-deny.
 *   2. A BL-7-named anchor for the cross-owner denial so the invariant is
 *      discoverable by grep.
 *   3. The auth-required gate at this specific route.
 */
class ProposalCancellationOwnershipTest extends TestCase
{
    use EnablesAiHarness, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->enableAiHarness();
    }

    /**
     * BL-7 positive case: when the authenticated proposer IS the booking
     * owner, the proposal confirmation path successfully cancels the
     * booking. This proves the service-layer ownership gate is not
     * over-broad — owners retain their ability to cancel their own
     * bookings through the AI proposal surface.
     */
    public function test_bl7_owner_proposer_can_cancel_own_booking_via_proposal_path(): void
    {
        $alice = $this->owner;
        $room = Room::factory()->create();

        $aliceBooking = Booking::factory()
            ->for($alice)
            ->for($room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(10),
                'check_out' => now()->addDays(12),
            ]);

        $hash = $this->seedCancellationProposal(
            proposer: $alice,
            bookingId: $aliceBooking->id,
        );

        $response = $this->actingAs($alice)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.decision', 'confirmed');
        $this->assertSame(
            "booking_cancelled:{$aliceBooking->id}",
            $response->json('data.downstream_result'),
        );

        $aliceBooking->refresh();
        $this->assertSame(BookingStatus::CANCELLED, $aliceBooking->status);

        $this->assertDatabaseHas('ai_proposal_events', [
            'user_id' => $alice->id,
            'proposal_hash' => $hash,
            'action_type' => 'suggest_cancellation',
            'user_decision' => 'confirmed',
            'downstream_result' => "booking_cancelled:{$aliceBooking->id}",
        ]);
    }

    /**
     * BL-7 denial case (anchor): a non-owner proposer who successfully
     * passes the F-06 binding check (because they ARE the proposer of
     * record in the cache) is still blocked by the service-layer ownership
     * gate when the booking_id in proposed_params points at someone else's
     * booking. The booking must not transition, the audit row must record
     * the refusal under the proposer's identity, and the controller must
     * surface the stable downstream code rather than the raw exception
     * message.
     */
    public function test_bl7_non_owner_proposer_cannot_cancel_other_users_booking(): void
    {
        $alice = $this->owner;
        $bob = User::factory()->create();
        $room = Room::factory()->create();

        $bobsBooking = Booking::factory()
            ->for($bob)
            ->for($room)
            ->confirmed()
            ->create([
                'check_in' => now()->addDays(15),
                'check_out' => now()->addDays(17),
            ]);

        // Alice is the proposer of record (binding check will pass).
        // proposed_params names Bob's booking_id (service-layer check must reject).
        $hash = $this->seedCancellationProposal(
            proposer: $alice,
            bookingId: $bobsBooking->id,
        );

        $response = $this->actingAs($alice)
            ->postJson("/api/v1/ai/proposals/{$hash}/decide", [
                'decision' => 'confirmed',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('errors.downstream_result.status', 'failed');
        $response->assertJsonPath('errors.downstream_result.failure_reason', 'unauthorized_booking_owner');

        // Bob's booking is untouched — no status change, no cancellation columns set.
        $bobsBooking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $bobsBooking->status);
        $this->assertNull($bobsBooking->cancelled_at);
        $this->assertNull($bobsBooking->cancelled_by);

        // No "booking_cancelled:" audit row was emitted under any actor —
        // the only event for this proposal is the refused execution.
        $this->assertDatabaseMissing('ai_proposal_events', [
            'proposal_hash' => $hash,
            'downstream_result' => "booking_cancelled:{$bobsBooking->id}",
        ]);

        $event = \App\Models\AiProposalEvent::where('proposal_hash', $hash)->first();
        $this->assertNotNull($event);
        $this->assertSame($alice->id, $event->user_id);
        $this->assertSame('suggest_cancellation', $event->action_type);
        $this->assertSame('errored', $event->user_decision);

        $downstreamResult = json_decode((string) $event->downstream_result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('failed', $downstreamResult['status']);
        $this->assertSame('unauthorized_booking_owner', $downstreamResult['failure_reason']);

        $proposal = AiProposal::where('proposal_hash', $hash)->first();
        $this->assertSame('errored', $proposal?->decision);
        $this->assertDatabaseMissing('ai_proposal_events', [
            'proposal_hash' => $hash,
            'user_decision' => 'confirmed',
        ]);
    }

    /**
     * BL-7 auth gate: the decide route is behind check_token_valid +
     * verified middleware. An unauthenticated caller cannot reach
     * executeCancellation regardless of what cache state exists, so the
     * actor id passed into cancelBooking is always server-derived from a
     * real authenticated session — never request-body input.
     */
    public function test_bl7_unauthenticated_caller_cannot_invoke_cancellation_proposal_path(): void
    {
        $hash = hash('sha256', 'bl7-unauth-probe');

        // Seed a syntactically valid envelope so the only thing standing
        // between the request and executeCancellation is the auth middleware.
        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_cancellation',
            'proposed_params' => ['booking_id' => 1],
            'proposer_user_id' => $this->owner->id,
        ], 1800);

        $response = $this->postJson("/api/v1/ai/proposals/{$hash}/decide", [
            'decision' => 'confirmed',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('ai_proposal_events', [
            'proposal_hash' => $hash,
        ]);
    }

    /**
     * Seed a suggest_cancellation proposal in both the cache envelope and
     * the durable AiProposal row, in the shape ProposalConfirmationController
     * expects after a successful orchestration turn (already shown,
     * unexpired, proposer-bound).
     */
    private function seedCancellationProposal(User $proposer, int $bookingId): string
    {
        $hash = hash('sha256', "bl7-cancel-{$proposer->id}-{$bookingId}-".bin2hex(random_bytes(4)));

        Cache::put("ai_proposal:{$hash}", [
            'action_type' => 'suggest_cancellation',
            'proposed_params' => ['booking_id' => $bookingId],
            'proposer_user_id' => $proposer->id,
        ], 1800);

        AiProposal::create([
            'proposal_hash' => $hash,
            'user_id' => $proposer->id,
            'action_type' => 'suggest_cancellation',
            'context_version' => hash('sha256', "cancel:{$bookingId}"),
            'proposed_params' => ['booking_id' => $bookingId],
            'risk_assessment' => ['level' => 'medium', 'factors' => []],
            'expires_at' => now()->addMinutes(30),
            'shown_at' => now(),
        ]);

        return $hash;
    }
}
