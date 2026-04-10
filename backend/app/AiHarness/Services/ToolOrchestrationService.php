<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\DTOs\ToolDraft;
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
        $startMs = (int) (microtime(true) * 1000);

        $draft = match ($toolName) {
            'draft_admin_message' => $this->buildAdminMessageDraft($input, $request),
            'draft_cancellation_summary' => $this->buildCancellationSummaryDraft($input, $request),
            default => $this->buildGenericDraft($toolName, $input),
        };

        $durationMs = (int) (microtime(true) * 1000) - $startMs;

        return [
            'tool' => $toolName,
            'classification' => ToolClassification::APPROVAL_REQUIRED->value,
            'result' => $draft->toArray(),
            'executed' => false,
            'duration_ms' => $durationMs,
        ];
    }

    private function buildAdminMessageDraft(array $input, HarnessRequest $request): ToolDraft
    {
        $contextSources = [];
        $policyRefs = [];
        $keyFacts = [];

        // Fetch contact message context if provided
        $contactMessageId = (int) ($input['contact_message_id'] ?? 0);
        if ($contactMessageId > 0) {
            $contactMessage = \App\Models\ContactMessage::find($contactMessageId);
            if ($contactMessage !== null) {
                $contextSources[] = "contact_message:{$contactMessageId}";
                $keyFacts['guest_name'] = $contactMessage->name;
                $keyFacts['guest_email'] = $contactMessage->email;
                $keyFacts['subject'] = $contactMessage->subject ?? '';
                $keyFacts['original_message'] = $contactMessage->message;
            }
        }

        // Fetch booking context if provided
        $bookingId = (int) ($input['booking_id'] ?? 0);
        if ($bookingId > 0) {
            $booking = $this->fetchBookingForDraft($bookingId, $request);
            if ($booking !== null) {
                $contextSources[] = "booking:{$bookingId}";
                $keyFacts['booking_status'] = $booking['status'];
                $keyFacts['check_in'] = $booking['check_in'];
                $keyFacts['check_out'] = $booking['check_out'];
            }
        }

        // Fetch relevant policies
        $policySlug = (string) ($input['policy_slug'] ?? '');
        if ($policySlug !== '') {
            $service = app(PolicyContentService::class);
            $doc = $service->getBySlug($policySlug);
            if ($doc !== null) {
                $policyRefs[] = $doc->slug;
                $contextSources[] = "policy:{$doc->slug}";
            }
        }

        $draftText = $input['draft_text'] ?? '';
        $suggestedTone = $input['tone'] ?? 'professional';

        $now = now()->toIso8601String();
        $draftHash = hash('sha256', $draftText.$now.$request->requestId);

        Log::channel('ai')->info('Admin message draft generated', [
            'request_id' => $request->requestId,
            'user_id' => $request->userId,
            'contact_message_id' => $contactMessageId,
            'booking_id' => $bookingId,
            'draft_hash' => $draftHash,
        ]);

        return new ToolDraft(
            toolName: 'draft_admin_message',
            draftText: $draftText,
            suggestedTone: $suggestedTone,
            contextUsed: $contextSources,
            policyRefs: $policyRefs,
            keyFacts: $keyFacts,
            draftHash: $draftHash,
            generatedAt: $now,
        );
    }

    private function buildCancellationSummaryDraft(array $input, HarnessRequest $request): ToolDraft
    {
        $contextSources = [];
        $policyRefs = [];
        $keyFacts = [];

        $bookingId = (int) ($input['booking_id'] ?? 0);
        if ($bookingId <= 0) {
            return new ToolDraft(
                toolName: 'draft_cancellation_summary',
                draftText: 'INSUFFICIENT_CONTEXT: Missing booking_id.',
                suggestedTone: 'professional',
                contextUsed: [],
                policyRefs: [],
                keyFacts: [],
                draftHash: hash('sha256', 'no-booking'.now()->toIso8601String()),
                generatedAt: now()->toIso8601String(),
            );
        }

        $booking = $this->fetchBookingForDraft($bookingId, $request);
        if ($booking !== null) {
            $contextSources[] = "booking:{$bookingId}";
            $keyFacts['booking_id'] = $bookingId;
            $keyFacts['status'] = $booking['status'];
            $keyFacts['check_in'] = $booking['check_in'];
            $keyFacts['check_out'] = $booking['check_out'];
            $keyFacts['guest_name'] = $booking['guest_name'] ?? '';
        } else {
            return new ToolDraft(
                toolName: 'draft_cancellation_summary',
                draftText: 'INSUFFICIENT_CONTEXT: Booking not found or access denied.',
                suggestedTone: 'professional',
                contextUsed: [],
                policyRefs: [],
                keyFacts: ['booking_id' => $bookingId],
                draftHash: hash('sha256', "no-booking-{$bookingId}".now()->toIso8601String()),
                generatedAt: now()->toIso8601String(),
            );
        }

        // Always reference cancellation policy
        $service = app(PolicyContentService::class);
        $cancelPolicy = $service->getBySlug('cancellation-policy');
        if ($cancelPolicy !== null) {
            $policyRefs[] = $cancelPolicy->slug;
            $contextSources[] = "policy:{$cancelPolicy->slug}";
        }

        $draftText = $input['summary_text'] ?? '';
        $now = now()->toIso8601String();
        $draftHash = hash('sha256', $draftText.$now.$request->requestId);

        Log::channel('ai')->info('Cancellation summary draft generated', [
            'request_id' => $request->requestId,
            'user_id' => $request->userId,
            'booking_id' => $bookingId,
            'draft_hash' => $draftHash,
        ]);

        return new ToolDraft(
            toolName: 'draft_cancellation_summary',
            draftText: $draftText,
            suggestedTone: 'empathetic',
            contextUsed: $contextSources,
            policyRefs: $policyRefs,
            keyFacts: $keyFacts,
            draftHash: $draftHash,
            generatedAt: $now,
        );
    }

    private function buildGenericDraft(string $toolName, array $input): ToolDraft
    {
        $now = now()->toIso8601String();

        return new ToolDraft(
            toolName: $toolName,
            draftText: $input['draft_text'] ?? '',
            suggestedTone: 'professional',
            contextUsed: [],
            policyRefs: [],
            keyFacts: [],
            draftHash: hash('sha256', ($input['draft_text'] ?? '').$now),
            generatedAt: $now,
        );
    }

    /**
     * Fetch booking data for draft generation. Enforces RBAC.
     *
     * @return array{booking_id: int, status: string, check_in: string, check_out: string, guest_name?: string}|null
     */
    private function fetchBookingForDraft(int $bookingId, HarnessRequest $request): ?array
    {
        $service = app(\App\Services\BookingService::class);
        $booking = $service->getBookingById($bookingId);

        if ($booking === null) {
            return null;
        }

        // Moderator+ can access any booking; regular users only their own
        if (! in_array($request->userRole, ['admin', 'moderator'], true)
            && $booking->user_id !== $request->userId) {
            return null;
        }

        return [
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'check_in' => (string) $booking->check_in,
            'check_out' => (string) $booking->check_out,
            'guest_name' => $booking->user?->name ?? '',
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
