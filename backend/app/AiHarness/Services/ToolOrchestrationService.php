<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Enums\ToolClassification;
use App\AiHarness\Exceptions\BlockedToolException;
use App\AiHarness\ToolRegistry;
use Illuminate\Support\Facades\Log;

/**
 * L5 — Tool Orchestration Service.
 *
 * Classifies tool proposals from the model and either:
 * - READ_ONLY → executes via existing service layer
 * - APPROVAL_REQUIRED → returns draft struct (no execution)
 * - BLOCKED → throws BlockedToolException, logs to 'ai' channel
 *
 * NEVER bypasses existing policies or service layer.
 * NEVER calls raw Eloquent.
 */
class ToolOrchestrationService
{
    /**
     * Execute a tool proposal after policy has authorized it.
     *
     * @param  array{tool: string, input: array}  $proposal
     * @return array{tool: string, classification: string, result: mixed, executed: bool, duration_ms: int}
     *
     * @throws BlockedToolException
     */
    public function execute(array $proposal, HarnessRequest $request): array
    {
        $toolName = $proposal['tool'] ?? 'unknown';
        $input = $proposal['input'] ?? [];
        $classification = ToolRegistry::classify($toolName);

        return match ($classification) {
            ToolClassification::READ_ONLY => $this->executeReadOnly($toolName, $input, $request),
            ToolClassification::APPROVAL_REQUIRED => $this->returnDraft($toolName, $input, $request),
            ToolClassification::BLOCKED => $this->rejectBlocked($toolName, $request),
        };
    }

    private function executeReadOnly(string $toolName, array $input, HarnessRequest $request): array
    {
        $startMs = (int) (microtime(true) * 1000);

        $result = match ($toolName) {
            'search_rooms' => $this->executeSearchRooms($input),
            'check_availability' => $this->executeCheckAvailability($input),
            'get_booking_status' => $this->executeGetBookingStatus($input, $request),
            'get_user_bookings' => $this->executeGetUserBookings($request),
            'get_location_detail' => $this->executeGetLocationDetail($input),
            'lookup_policy' => $this->executeLookupPolicy($input),
            'get_faq_content' => $this->executeGetFaqContent($input),
            default => ['error' => 'Tool not yet wired'],
        };

        $durationMs = (int) (microtime(true) * 1000) - $startMs;

        return [
            'tool' => $toolName,
            'classification' => ToolClassification::READ_ONLY->value,
            'result' => $result,
            'executed' => true,
            'duration_ms' => $durationMs,
        ];
    }

    private function returnDraft(string $toolName, array $input, HarnessRequest $request): array
    {
        return [
            'tool' => $toolName,
            'classification' => ToolClassification::APPROVAL_REQUIRED->value,
            'result' => [
                'draft' => true,
                'tool' => $toolName,
                'input' => $input,
                'message' => 'This action requires human approval before execution.',
            ],
            'executed' => false,
            'duration_ms' => 0,
        ];
    }

    /**
     * @throws BlockedToolException
     */
    private function rejectBlocked(string $toolName, HarnessRequest $request): never
    {
        Log::channel('ai')->error('BLOCKED tool execution attempted', [
            'request_id' => $request->requestId,
            'correlation_id' => $request->correlationId,
            'tool' => $toolName,
            'task_type' => $request->taskType->value,
            'user_id' => $request->userId,
        ]);

        throw new BlockedToolException($toolName);
    }

    // ──────────────────────────────────────────
    // Tool implementations — delegate to existing services
    // ──────────────────────────────────────────

    private function executeSearchRooms(array $input): array
    {
        $service = app(\App\Services\RoomAvailabilityService::class);
        $rooms = $service->getAllRoomsWithAvailability();

        return ['rooms' => $rooms->toArray()];
    }

    private function executeCheckAvailability(array $input): array
    {
        $service = app(\App\Services\RoomAvailabilityService::class);
        $roomId = (int) ($input['room_id'] ?? 0);
        $checkIn = $input['check_in'] ?? '';
        $checkOut = $input['check_out'] ?? '';

        if ($roomId <= 0 || $checkIn === '' || $checkOut === '') {
            return ['available' => false, 'error' => 'Missing required parameters'];
        }

        $available = $service->isRoomAvailable($roomId, $checkIn, $checkOut);

        return ['available' => $available, 'room_id' => $roomId];
    }

    private function executeGetBookingStatus(array $input, HarnessRequest $request): array
    {
        $service = app(\App\Services\BookingService::class);
        $bookingId = (int) ($input['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            return ['error' => 'Missing booking_id'];
        }

        $booking = $service->getBookingById($bookingId);

        if ($booking === null) {
            return ['error' => 'Booking not found'];
        }

        // Ownership check: non-admin users can only see their own bookings
        if (! in_array($request->userRole, ['admin', 'moderator'], true)
            && $booking->user_id !== $request->userId) {
            return ['error' => 'Access denied'];
        }

        return [
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'check_in' => $booking->check_in,
            'check_out' => $booking->check_out,
        ];
    }

    private function executeGetUserBookings(HarnessRequest $request): array
    {
        $service = app(\App\Services\BookingService::class);
        $bookings = $service->getUserBookings($request->userId);

        return ['bookings' => $bookings->toArray()];
    }

    private function executeGetLocationDetail(array $input): array
    {
        $slug = (string) ($input['slug'] ?? '');

        if ($slug === '') {
            return ['error' => 'Missing slug parameter'];
        }

        $location = \App\Models\Location::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['rooms' => fn ($q) => $q->where('status', 'available')->orderBy('price')])
            ->first();

        if ($location === null) {
            return ['found' => false, 'slug' => $slug];
        }

        return [
            'found' => true,
            'slug' => $location->slug,
            'name' => $location->name,
            'address' => $location->address,
            'city' => $location->city,
            'description' => $location->description,
            'amenities' => $location->amenities,
            'phone' => $location->phone,
            'email' => $location->email,
            'total_rooms' => $location->rooms->count(),
            'rooms' => $location->rooms->map(fn ($room) => [
                'id' => $room->id,
                'name' => $room->name,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
            ])->values()->all(),
        ];
    }

    private function executeLookupPolicy(array $input): array
    {
        $service = app(PolicyContentService::class);
        $slug = (string) ($input['slug'] ?? '');

        if ($slug === '') {
            return ['error' => 'Missing slug parameter'];
        }

        $doc = $service->getBySlug($slug);

        if ($doc === null) {
            return ['found' => false, 'slug' => $slug];
        }

        return [
            'found' => true,
            'slug' => $doc->slug,
            'title' => $doc->title,
            'content' => $doc->content,
            'category' => $doc->category,
            'last_verified_at' => $doc->last_verified_at?->toDateString(),
            'version' => $doc->version,
        ];
    }

    private function executeGetFaqContent(array $input): array
    {
        $service = app(PolicyContentService::class);
        $query = (string) ($input['query'] ?? '');
        $language = (string) ($input['language'] ?? 'vi');

        if ($query === '') {
            return ['error' => 'Missing query parameter'];
        }

        $results = $service->findByQuery($query, $language);

        return [
            'count' => $results->count(),
            'documents' => $results->map(fn ($doc) => [
                'slug' => $doc->slug,
                'title' => $doc->title,
                'content' => $doc->content,
                'category' => $doc->category,
                'last_verified_at' => $doc->last_verified_at?->toDateString(),
            ])->values()->all(),
        ];
    }
}
