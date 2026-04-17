<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AiHarness\DTOs\ProposalEvent;
use App\Http\Requests\ProposalDecisionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\AiProposalEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
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
    /**
     * POST /api/v1/ai/proposals/{hash}/decide
     */
    public function decide(ProposalDecisionRequest $request, string $hash): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $decision = $request->validated('decision');

        // Retrieve cached proposal
        $proposalData = Cache::get("ai_proposal:{$hash}");
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

    private function handleDecline(int $userId, string $hash, string $actionType, string $now): JsonResponse
    {
        $this->recordEvent($userId, $hash, $actionType, 'declined', null, $now);

        Cache::forget("ai_proposal:{$hash}");

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
        $proposedParams = $proposalData['proposed_params'] ?? [];
        $downstreamResult = null;

        try {
            $downstreamResult = match ($actionType) {
                'suggest_booking' => $this->executeBooking($userId, $proposedParams),
                'suggest_cancellation' => $this->executeCancellation($userId, $proposedParams),
                default => 'unsupported_action_type',
            };
        } catch (\Throwable $e) {
            $downstreamResult = "error:{$e->getMessage()}";

            Log::channel('ai')->error('Proposal confirmation downstream error', [
                'proposal_hash' => $hash,
                'action_type' => $actionType,
                'error' => $e->getMessage(),
            ]);
        }

        $this->recordEvent($userId, $hash, $actionType, 'confirmed', $downstreamResult, $now);

        Cache::forget("ai_proposal:{$hash}");

        $success = ! str_starts_with((string) $downstreamResult, 'error:');

        if (! $success) {
            return ApiResponse::error(
                'Không thể thực hiện hành động. Vui lòng thử lại.',
                ['downstream_result' => $downstreamResult],
                422,
            );
        }

        return ApiResponse::success([
            'proposal_hash' => $hash,
            'decision' => 'confirmed',
            'downstream_result' => $downstreamResult,
            'message' => 'Hành động đã được thực hiện thành công.',
        ]);
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
     */
    private function executeCancellation(int $userId, array $params): string
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
            $service->cancelBooking($booking, $userId);
        } catch (\App\Exceptions\BookingCancellationException $e) {
            if ($e->getErrorCode() === 'unauthorized') {
                Log::channel('ai')->warning('Proposal cancellation blocked by service-layer ownership check', [
                    'decider_user_id' => $userId,
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
        // Persist to DB
        AiProposalEvent::create([
            'user_id' => $userId,
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
