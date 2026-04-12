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

        $service->cancelBooking($booking, $userId);

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
