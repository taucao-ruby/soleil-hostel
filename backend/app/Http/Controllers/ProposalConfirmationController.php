<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AiHarness\DTOs\ProposalEvent;
use App\AiHarness\Enums\ProposalActionType;
use App\AiHarness\Exceptions\ProposalExpiredException;
use App\AiHarness\Exceptions\ProposalLifecycleException;
use App\AiHarness\Exceptions\ProposalNotShownException;
use App\AiHarness\Exceptions\ProposalPriceChangedException;
use App\AiHarness\Exceptions\ProposedRoomNoLongerAvailableException;
use App\Http\Requests\ProposalDecisionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\AiProposal;
use App\Models\AiProposalEvent;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles user confirmation/decline of BookingActionProposals.
 *
 * Flow (R7):
 *   user sees proposal → user explicitly confirms → harness calls service → service validates
 *
 * On confirm: dispatches to existing service layer (NOT the model).
 * On decline: logs rejection, returns acknowledgement.
 *
 * Never short-circuits the user confirmation step (R8).
 */
class ProposalConfirmationController extends Controller
{
    private const DECISION_CONFIRMED = 'confirmed';

    private const DECISION_ERRORED = 'errored';

    private const DOWNSTREAM_FAILURE_REASON = 'downstream_execution_failed';

    /**
     * POST /api/v1/ai/proposals/{hash}/decide
     */
    public function decide(ProposalDecisionRequest $request, string $hash): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $decision = $request->validated('decision');

        // Retrieve cached proposal (fast path). F-86: the durable
        // ai_proposals row — not the cache — is authoritative for whether a
        // proposal exists. On a cache miss (eviction, Redis restart, TTL
        // race) rebuild the envelope from the durable row before concluding
        // the proposal is gone.
        $proposalData = Cache::get("ai_proposal:{$hash}");
        if ($proposalData === null) {
            $proposalData = $this->envelopeFromDurableRow($hash, $userId);
        }

        if ($proposalData === null) {
            return ApiResponse::notFound('Proposal not found or expired.');
        }

        // F-06: proposer-binding check.
        //
        // The cached proposal was produced for a specific authenticated user
        // during their harness turn. Another authenticated user who somehow
        // learned the hash (log leak, IDOR, response interception) must not
        // be able to confirm/decline it — doing so would execute Alice's
        // proposed booking/cancellation under Mallory's identity (or worse,
        // cancel Alice's booking while the audit trail records Mallory).
        //
        // A bound proposal without a matching proposer id returns 404 (NOT
        // 403) to avoid leaking hash existence to an attacker probing for
        // valid hashes. Legacy cache entries written before this change
        // lack the field; those are also treated as unbound → 404.
        $proposerUserId = $proposalData['proposer_user_id'] ?? null;
        if (! is_int($proposerUserId) || $proposerUserId !== $userId) {
            Log::channel('ai')->warning('Proposal decide blocked by proposer-binding check', [
                'proposal_hash' => $hash,
                'decider_user_id' => $userId,
                'proposer_user_id_present' => is_int($proposerUserId),
            ]);

            return ApiResponse::notFound('Proposal not found or expired.');
        }

        $actionType = $proposalData['action_type'] ?? 'unknown';
        $now = now()->toIso8601String();

        if ($decision === 'declined') {
            return $this->handleDecline($userId, $hash, $actionType, $now);
        }

        return $this->handleConfirm($userId, $hash, $actionType, $proposalData, $now);
    }

    /**
     * F-86: rebuild the decision envelope from the durable ai_proposals row
     * when the cache misses. The WHERE clauses reproduce every guarantee the
     * cache fast path provided implicitly:
     *
     *  - user_id = decider   → F-06 proposer binding (404, not 403, on any
     *                          mismatch — same anti-probing shape as the
     *                          cache path)
     *  - decision IS NULL    → decided proposals stay consumed; no replay
     *  - expires_at > now()  → equivalent of the cache TTL (PROPOSAL_TTL_SECONDS
     *                          and expires_at are written from the same clock)
     *
     * No cache repopulation happens here: every decide() outcome forgets the
     * key, so a put would be dead within the same request. The durable row IS
     * the read-through source.
     *
     * @return array{action_type: string, proposed_params: array<string, mixed>, proposer_user_id: int}|null
     */
    private function envelopeFromDurableRow(string $hash, int $userId): ?array
    {
        $proposal = AiProposal::query()
            ->where('proposal_hash', $hash)
            ->where('user_id', $userId)
            ->whereNull('decision')
            ->where('expires_at', '>', now())
            ->first();

        if ($proposal === null) {
            return null;
        }

        return [
            'action_type' => $proposal->action_type,
            'proposed_params' => $proposal->proposed_params,
            'proposer_user_id' => (int) $proposal->user_id,
        ];
    }

    private function handleDecline(int $userId, string $hash, string $actionType, string $now): JsonResponse
    {
        $this->recordEvent($userId, $hash, $actionType, 'declined', null, $now);

        Cache::forget("ai_proposal:{$hash}");

        // F-86: keep the durable row in lockstep with the decision. Without
        // this, a declined proposal whose cache entry is gone would remain
        // decidable through the DB-authoritative fallback (replay window).
        // Cache-only proposals (no durable row) make this a no-op 0-row
        // update, preserving the legacy decline path.
        AiProposal::query()
            ->where('proposal_hash', $hash)
            ->where('user_id', $userId)
            ->whereNull('decision')
            ->update(['decision' => 'declined', 'decided_at' => now()]);

        return ApiResponse::success([
            'proposal_hash' => $hash,
            'decision' => 'declined',
            'message' => 'Đề xuất đã bị từ chối.',
        ]);
    }

    private function handleConfirm(
        int $userId,
        string $hash,
        string $actionType,
        array $proposalData,
        string $now,
    ): JsonResponse {
        // AI-005 / AI-006: durable record carries expiry, shown-state, and the
        // room/price snapshot used for drift detection. Cache alone is not a
        // sufficient gate at confirm time — an attacker could replay a hash
        // that was generated minutes ago against a now-unavailable or
        // re-priced room. The cache lookup above proved the proposer
        // binding; this lookup proves the proposal is still safe to commit.
        $proposal = AiProposal::query()
            ->where('proposal_hash', $hash)
            ->where('user_id', $userId)
            ->first();

        if ($proposal === null) {
            // Mirror the cache-miss 404 so we don't leak whether the cache
            // entry exists in isolation from the durable record.
            return ApiResponse::notFound('Proposal not found or expired.');
        }

        try {
            $this->revalidateProposal($proposal);
        } catch (ProposalLifecycleException $e) {
            $this->recordEvent($userId, $hash, $actionType, 'confirmed', "error:{$e->errorCode()}", $now);

            // Cache and the durable row are both forgotten — this proposal
            // is dead, no replay path remains.
            Cache::forget("ai_proposal:{$hash}");
            $proposal->update(['decision' => 'rejected', 'decided_at' => now()]);

            return ApiResponse::error(
                $e->getMessage(),
                ['code' => $e->errorCode()],
                422,
            );
        }

        $proposedParams = $proposalData['proposed_params'] ?? [];
        $downstreamResult = null;
        $failurePayload = null;

        try {
            $downstreamResult = match ($actionType) {
                'suggest_booking' => $this->executeBooking($userId, $proposedParams),
                'suggest_cancellation' => $this->executeCancellation($userId, $proposedParams),
                default => 'error:unsupported_action_type',
            };
        } catch (\Throwable $e) {
            $failurePayload = $this->downstreamFailurePayload(self::DOWNSTREAM_FAILURE_REASON, $e);
            $downstreamResult = $this->encodeDownstreamResult($failurePayload);

            Log::channel('ai')->error('Proposal confirmation downstream error', [
                'proposal_hash' => $hash,
                'action_type' => $actionType,
                'failure_reason' => self::DOWNSTREAM_FAILURE_REASON,
                'error_class' => class_basename($e),
            ]);
        }

        $statusCode = 502;
        if ($failurePayload === null && $this->isFailedDownstreamResult((string) $downstreamResult)) {
            $failurePayload = $this->downstreamFailurePayload(
                $this->failureReasonFromDownstreamResult((string) $downstreamResult),
            );
            $downstreamResult = $this->encodeDownstreamResult($failurePayload);
            $statusCode = 422;
        }

        if ($failurePayload !== null) {
            $this->recordEvent($userId, $hash, $actionType, self::DECISION_ERRORED, $downstreamResult, $now);

            Cache::forget("ai_proposal:{$hash}");
            $proposal->update([
                'decision' => self::DECISION_ERRORED,
                'decided_at' => now(),
            ]);

            return ApiResponse::error(
                'Không thể thực hiện hành động. Vui lòng thử lại.',
                ['downstream_result' => $failurePayload],
                $statusCode,
            );
        }

        $this->recordEvent($userId, $hash, $actionType, self::DECISION_CONFIRMED, $downstreamResult, $now);

        Cache::forget("ai_proposal:{$hash}");
        $proposal->update([
            'decision' => self::DECISION_CONFIRMED,
            'decided_at' => now(),
        ]);

        return ApiResponse::success([
            'proposal_hash' => $hash,
            'decision' => self::DECISION_CONFIRMED,
            'downstream_result' => $downstreamResult,
            'message' => 'Hành động đã được thực hiện thành công.',
        ]);
    }

    /**
     * @return array{status: string, failure_reason: string, error_class: string|null, message: string}
     */
    private function downstreamFailurePayload(string $failureReason, ?\Throwable $e = null): array
    {
        return [
            'status' => 'failed',
            'failure_reason' => $this->sanitizeFailureReason($failureReason),
            'error_class' => $e === null ? null : class_basename($e),
            'message' => 'Downstream execution failed.',
        ];
    }

    /**
     * @param  array{status: string, failure_reason: string, error_class: string|null, message: string}  $payload
     */
    private function encodeDownstreamResult(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function isFailedDownstreamResult(string $downstreamResult): bool
    {
        return str_starts_with($downstreamResult, 'error:');
    }

    private function failureReasonFromDownstreamResult(string $downstreamResult): string
    {
        $reason = str_starts_with($downstreamResult, 'error:')
            ? substr($downstreamResult, strlen('error:'))
            : $downstreamResult;

        return $this->sanitizeFailureReason($reason);
    }

    private function sanitizeFailureReason(string $reason): string
    {
        return preg_match('/\A[a-z0-9_]{1,80}\z/', $reason) === 1
            ? $reason
            : self::DOWNSTREAM_FAILURE_REASON;
    }

    /**
     * Revalidate the proposal against current room / price / lifecycle
     * state. Each precondition maps to a stable error code surfaced on the
     * 422 response so the frontend can render the right recovery affordance.
     *
     * Order matters: expiry first (cheapest), then shown-state (state
     * machine), then room availability, then price (most expensive — needs
     * the Room model). The first failed gate short-circuits.
     *
     * @throws ProposalLifecycleException
     */
    private function revalidateProposal(AiProposal $proposal): void
    {
        if ($proposal->isExpired()) {
            throw new ProposalExpiredException;
        }

        if (! $proposal->isShown()) {
            throw new ProposalNotShownException;
        }

        // Booking-shape proposals carry a room/dates/price triple; drift
        // checks only apply to those. Cancellation proposals reference an
        // existing booking — the service layer owns availability there.
        if ($proposal->action_type !== ProposalActionType::SUGGEST_BOOKING->value) {
            return;
        }

        $room = Room::query()->bookable()->find($proposal->room_id);
        if ($room === null) {
            throw new ProposedRoomNoLongerAvailableException;
        }

        if ($proposal->check_in === null || $proposal->check_out === null) {
            return;
        }

        $currentPrice = $room->currentPriceForDates(
            $proposal->check_in->toDateString(),
            $proposal->check_out->toDateString(),
        );

        if ($proposal->quoted_price_cents !== $currentPrice) {
            throw new ProposalPriceChangedException(
                quotedPriceCents: (int) $proposal->quoted_price_cents,
                currentPriceCents: $currentPrice,
            );
        }
    }

    /**
     * Execute booking via existing CreateBookingService.
     * The service owns validation — room availability, date constraints, RBAC.
     */
    private function executeBooking(int $userId, array $params): string
    {
        $roomId = (int) ($params['room_id'] ?? 0);
        $checkIn = (string) ($params['check_in'] ?? '');
        $checkOut = (string) ($params['check_out'] ?? '');
        $available = (bool) ($params['available'] ?? false);

        if (! $available) {
            return 'error:room_not_available';
        }

        if ($roomId <= 0 || $checkIn === '' || $checkOut === '') {
            return 'error:missing_required_params';
        }

        $user = \App\Models\User::find($userId);
        if ($user === null) {
            return 'error:user_not_found';
        }

        // Delegate to existing CreateBookingService — it owns all business rules
        $service = app(\App\Services\CreateBookingService::class);

        $booking = $service->create(
            roomId: $roomId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            guestName: (string) $user->name,
            guestEmail: (string) $user->email,
            userId: $userId,
        );

        return "booking_created:{$booking->id}";
    }

    /**
     * Execute cancellation via existing BookingService.
     * The service owns validation — status checks, policy enforcement.
     *
     * F-06 follow-up (Lane 3 Batch 3.1): the proposal flow names a
     * booking_id from the cached proposed_params. The proposer-binding
     * check in decide() proves the *decider* equals the *proposer*, but
     * does NOT prove the proposer owns the booking they are trying to
     * cancel. CancellationService::validateCancellation now enforces
     * ownership/admin defense-in-depth and raises
     * BookingCancellationException::unauthorized() — caught here and
     * mapped to a stable downstream code so the audit log records the
     * refusal without leaking the internal exception message to the
     * client.
     *
     * BL-7 invariant: $actorUserId is the AUTHENTICATED proposer/decider
     * derived from $request->user()->id in decide(). It is NOT
     * $booking->user_id. Substituting the booking owner here would silently
     * satisfy the service-layer ownership check and convert this method
     * into a confused-deputy authorization bypass — any caller who can
     * name a booking_id would be able to cancel it. See
     * ProposalCancellationOwnershipTest for the regression net.
     */
    private function executeCancellation(int $actorUserId, array $params): string
    {
        $bookingId = (int) ($params['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            return 'error:missing_booking_id';
        }

        $service = app(\App\Services\BookingService::class);
        $booking = $service->getBookingById($bookingId);

        if ($booking === null) {
            return 'error:booking_not_found';
        }

        try {
            // Intentionally pass the authenticated proposer/actor id.
            // CancellationService::validateCancellation enforces booking
            // ownership against this actor. Do not replace with
            // $booking->user_id — that would make the ownership check
            // tautologically true and bypass BL-7.
            $service->cancelBooking($booking, $actorUserId);
        } catch (\App\Exceptions\BookingCancellationException $e) {
            if ($e->getErrorCode() === 'unauthorized') {
                Log::channel('ai')->warning('Proposal cancellation blocked by service-layer ownership check', [
                    'decider_user_id' => $actorUserId,
                    'booking_id' => $bookingId,
                    'booking_owner_id' => $booking->user_id,
                ]);

                return 'error:unauthorized_booking_owner';
            }

            return "error:{$e->getErrorCode()}";
        }

        return "booking_cancelled:{$bookingId}";
    }

    /**
     * POST /api/v1/ai/proposals/{hash}/shown
     *
     * AI-005: emits a `shown` event when the frontend mounts the proposal
     * UI. Required precondition for `confirmed` — without a prior shown,
     * the decide() handler raises ProposalNotShown.
     *
     * Idempotent: a second call for an already-shown proposal returns 200
     * without inserting a duplicate event row. The check + write pair is
     * wrapped in a DB transaction so concurrent calls cannot both win.
     */
    public function shown(Request $request, string $hash): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $proposal = AiProposal::query()
            ->where('proposal_hash', $hash)
            ->where('user_id', $userId)
            ->first();

        if ($proposal === null) {
            // Same shape as decide(): 404 hides whether the hash exists at
            // all or just isn't bound to the requester.
            return ApiResponse::notFound('Proposal not found or expired.');
        }

        if ($proposal->isExpired()) {
            return ApiResponse::error(
                (new ProposalExpiredException)->getMessage(),
                ['code' => (new ProposalExpiredException)->errorCode()],
                422,
            );
        }

        $alreadyShown = false;

        DB::transaction(function () use ($proposal, $userId, $hash, &$alreadyShown): void {
            // Re-fetch under a row lock so concurrent /shown calls cannot
            // double-insert the audit event when the row started unshown.
            $locked = AiProposal::query()
                ->whereKey($proposal->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            if ($locked->shown_at !== null) {
                $alreadyShown = true;

                return;
            }

            $locked->update(['shown_at' => now()]);

            $actorRole = $locked->user?->role;

            AiProposalEvent::create([
                'user_id' => $userId,
                'actor_email' => $locked->user?->email,
                'actor_role' => $actorRole instanceof \BackedEnum ? $actorRole->value : $actorRole,
                'actor_display_name' => $locked->user?->name,
                'proposal_hash' => $hash,
                'action_type' => $locked->action_type,
                'user_decision' => 'shown',
                'downstream_result' => null,
            ]);
        });

        return ApiResponse::success([
            'proposal_hash' => $hash,
            'shown' => true,
            'idempotent' => $alreadyShown,
        ]);
    }

    /**
     * Record a ProposalEvent to both DB and log channel.
     */
    private function recordEvent(
        int $userId,
        string $hash,
        string $actionType,
        string $decision,
        ?string $downstreamResult,
        string $now,
    ): void {
        // Resolve denormalised actor identity. Fields survive user deletion
        // (Batch 4 / 3F: FK is now ON DELETE SET NULL) so the audit trail
        // remains attributable even when the user record is gone.
        $user = \App\Models\User::find($userId);
        $actorRole = $user?->role;

        // Persist to DB
        AiProposalEvent::create([
            'user_id' => $userId,
            'actor_email' => $user?->email,
            'actor_role' => $actorRole instanceof \BackedEnum ? $actorRole->value : $actorRole,
            'actor_display_name' => $user?->name,
            'proposal_hash' => $hash,
            'action_type' => $actionType,
            'user_decision' => $decision,
            'downstream_result' => $downstreamResult,
        ]);

        // Log to 'ai' channel
        $event = new ProposalEvent(
            userId: $userId,
            proposalHash: $hash,
            actionType: $actionType,
            userDecision: $decision,
            downstreamResult: $downstreamResult,
            timestamp: $now,
        );

        Log::channel('ai')->info('Proposal event recorded', $event->toLogContext());
    }
}
