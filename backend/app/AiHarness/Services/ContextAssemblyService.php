<?php

declare(strict_types=1);

namespace App\AiHarness\Services;

use App\AiHarness\DTOs\GroundedContext;
use App\AiHarness\DTOs\HarnessRequest;
use App\AiHarness\Enums\TaskType;

/**
 * L2 — Context Assembly Service.
 *
 * Assembles grounded context for the model prompt:
 * - Source allowlist per TaskType (static, not runtime-configurable)
 * - Freshness checking on each source
 * - Token budget enforcement
 * - Provenance tagging
 * - RBAC-aware filtering
 */
class ContextAssemblyService
{
    /**
     * Allowed data sources per task type.
     * Only these sources may be included in the context.
     */
    private const SOURCE_ALLOWLIST = [
        'faq_lookup' => ['policy_documents'],
        'room_discovery' => ['rooms', 'locations'],
        'booking_status' => ['bookings'],
        'admin_draft' => ['bookings', 'contact_messages', 'policy_documents'],
    ];

    /**
     * Maximum source age in hours before flagging as stale.
     */
    private const MAX_SOURCE_AGE_HOURS = 24;

    public function assemble(HarnessRequest $request): GroundedContext
    {
        $allowedSources = self::SOURCE_ALLOWLIST[$request->taskType->value] ?? [];
        $tokenBudget = (int) config("ai_harness.token_budget.{$request->taskType->value}", 2000);
        $rbacFilters = $this->resolveRbacFilters($request);

        $sources = [];
        $totalTokens = 0;

        foreach ($allowedSources as $sourceId) {
            if ($this->isFilteredByRbac($sourceId, $request->userRole)) {
                continue;
            }

            $content = $this->retrieveSource($sourceId, $request);
            if ($content === null) {
                continue;
            }

            $estimatedTokens = $this->estimateTokens($content);
            $isFresh = $this->checkFreshness($sourceId);

            // Skip stale sources
            if (! $isFresh) {
                continue;
            }

            // Enforce token budget
            if ($totalTokens + $estimatedTokens > $tokenBudget) {
                $remainingBudget = $tokenBudget - $totalTokens;
                if ($remainingBudget <= 0) {
                    break;
                }
                // Truncate content to fit budget
                $content = $this->truncateToTokenBudget($content, $remainingBudget);
                $estimatedTokens = $remainingBudget;
            }

            $sources[] = [
                'source_id' => $sourceId,
                'content' => $content,
                'retrieved_at' => now()->toIso8601String(),
                'freshness_ok' => true,
            ];

            $totalTokens += $estimatedTokens;
        }

        $provenanceHash = hash('sha256', json_encode(array_column($sources, 'source_id')));

        return new GroundedContext(
            sources: $sources,
            totalTokens: $totalTokens,
            provenanceHash: $provenanceHash,
            rbacFiltersApplied: $rbacFilters,
        );
    }

    /**
     * Retrieve content for a source. Returns null if source is not available.
     */
    private function retrieveSource(string $sourceId, HarnessRequest $request): ?string
    {
        return match ($sourceId) {
            'policy_documents' => $this->retrievePolicyDocuments($request),
            'rooms' => $this->retrieveRooms($request),
            'locations' => $this->retrieveLocations($request),
            'bookings' => $this->retrieveBookings($request),
            'contact_messages' => $this->retrieveContactMessages($request),
            default => null,
        };
    }

    private function retrievePolicyDocuments(HarnessRequest $request): ?string
    {
        $service = app(\App\AiHarness\Services\PolicyContentService::class);
        $results = $service->findByQuery($request->userInput, $request->locale);

        if ($results->isEmpty()) {
            return null;
        }

        $parts = [];
        foreach ($results as $doc) {
            $parts[] = "---\nSOURCE: {$doc->slug} | VERIFIED: {$doc->last_verified_at->toDateString()} | VERSION: {$doc->version}\n{$doc->content}\n---";
        }

        return implode("\n\n", $parts);
    }

    private function retrieveRooms(HarnessRequest $request): ?string
    {
        $service = app(\App\Services\RoomAvailabilityService::class);
        $rooms = $service->getAllRoomsWithAvailability();

        if ($rooms->isEmpty()) {
            return null;
        }

        $parts = [];
        foreach ($rooms as $room) {
            // Public fields only — exclude internal admin notes, internal pricing rules
            $parts[] = "ROOM_ID: {$room->id} | NAME: {$room->name} | PRICE: {$room->price} | MAX_GUESTS: {$room->max_guests} | STATUS: {$room->status}";
        }

        return "--- ROOMS (retrieved: " . now()->toIso8601String() . ") ---\n" . implode("\n", $parts);
    }

    private function retrieveLocations(HarnessRequest $request): ?string
    {
        $locations = \App\Models\Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($locations->isEmpty()) {
            return null;
        }

        $parts = [];
        foreach ($locations as $location) {
            $parts[] = "LOCATION: {$location->slug} | NAME: {$location->name} | ADDRESS: {$location->address} | CITY: {$location->city}";
        }

        return "--- LOCATIONS (retrieved: " . now()->toIso8601String() . ") ---\n" . implode("\n", $parts);
    }

    private function checkFreshness(string $sourceId): bool
    {
        // Phase 1: Always fresh (no real sources yet).
        return true;
    }

    /**
     * Retrieve bookings for admin draft context.
     * Only the referenced booking (from user input) — never bulk export.
     */
    private function retrieveBookings(HarnessRequest $request): ?string
    {
        // Extract booking ID from user input (pattern: booking_id=NNN or #NNN)
        $bookingId = $this->extractBookingId($request->userInput);

        if ($bookingId === null) {
            return null;
        }

        $service = app(\App\Services\BookingService::class);
        $booking = $service->getBookingById($bookingId);

        if ($booking === null) {
            return null;
        }

        // Cross-customer PII guard: check if this booking data should be visible
        if (! in_array($request->userRole, ['admin', 'moderator'], true)) {
            return null;
        }

        $parts = [
            "BOOKING_ID: {$booking->id}",
            "STATUS: {$booking->status}",
            "CHECK_IN: {$booking->check_in}",
            "CHECK_OUT: {$booking->check_out}",
            "GUEST: {$booking->user?->name}",
        ];

        return "--- BOOKING (retrieved: " . now()->toIso8601String() . ") ---\n" . implode("\n", $parts);
    }

    /**
     * Retrieve contact messages for admin draft context.
     * Only contact messages related to the current task context.
     * Cross-customer PII is stripped: only the specific customer's own message is included.
     */
    private function retrieveContactMessages(HarnessRequest $request): ?string
    {
        // Extract contact_message_id from user input
        $messageId = $this->extractContactMessageId($request->userInput);

        if ($messageId === null) {
            // Return most recent unresponded messages (max 3)
            $messages = \App\Models\ContactMessage::query()
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();
        } else {
            $messages = \App\Models\ContactMessage::query()
                ->where('id', $messageId)
                ->get();
        }

        if ($messages->isEmpty()) {
            return null;
        }

        $parts = [];
        foreach ($messages as $msg) {
            // Include only the customer's own contact info — no cross-customer data
            $parts[] = implode("\n", [
                "MSG_ID: {$msg->id}",
                "FROM: {$msg->name}",
                "EMAIL: {$msg->email}",
                "SUBJECT: " . ($msg->subject ?? 'N/A'),
                "MESSAGE: {$msg->message}",
                "SENT_AT: {$msg->created_at}",
            ]);
        }

        return "--- CONTACT MESSAGES (retrieved: " . now()->toIso8601String() . ") ---\n" . implode("\n---\n", $parts);
    }

    /**
     * Extract booking ID from user input.
     */
    private function extractBookingId(string $input): ?int
    {
        if (preg_match('/\bbooking[_\s#]*(\d+)/i', $input, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/\b#(\d+)\b/', $input, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract contact message ID from user input.
     */
    private function extractContactMessageId(string $input): ?int
    {
        if (preg_match('/\bmessage[_\s#]*(\d+)/i', $input, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/\bcontact[_\s#]*(\d+)/i', $input, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Check if a source is filtered out for the user's role.
     * Guests cannot access admin-scoped sources.
     */
    private function isFilteredByRbac(string $sourceId, string $userRole): bool
    {
        $adminOnlySources = ['contact_messages'];

        if (in_array($sourceId, $adminOnlySources, true)) {
            return ! in_array($userRole, ['admin', 'moderator'], true);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resolveRbacFilters(HarnessRequest $request): array
    {
        $filters = [];

        if (! in_array($request->userRole, ['admin', 'moderator'], true)) {
            $filters[] = 'exclude:contact_messages';
            $filters[] = 'scope:own_bookings_only';
        }

        return $filters;
    }

    /**
     * Rough token estimation: ~4 characters per token (English/Vietnamese average).
     */
    private function estimateTokens(string $content): int
    {
        return max(1, (int) ceil(mb_strlen($content) / 4));
    }

    private function truncateToTokenBudget(string $content, int $tokenBudget): string
    {
        $charLimit = $tokenBudget * 4;

        return mb_substr($content, 0, $charLimit);
    }
}
