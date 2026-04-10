<?php

declare(strict_types=1);

namespace App\AiHarness;

use App\AiHarness\Enums\ToolClassification;

/**
 * Static tool classification registry for AI harness.
 *
 * Every tool the model can propose is classified here.
 * Unknown tools default to BLOCKED — this is a safety invariant.
 * BLOCKED tools are permanent — never promote them to READ_ONLY or APPROVAL_REQUIRED.
 */
final class ToolRegistry
{
    /** @var array<string, ToolClassification> */
    private const TOOLS = [
        // READ_ONLY — execute freely within context auth
        'search_rooms' => ToolClassification::READ_ONLY,
        'check_availability' => ToolClassification::READ_ONLY,
        'get_location_detail' => ToolClassification::READ_ONLY,
        'lookup_policy' => ToolClassification::READ_ONLY,
        'get_faq_content' => ToolClassification::READ_ONLY,
        'get_booking_status' => ToolClassification::READ_ONLY,
        'get_user_bookings' => ToolClassification::READ_ONLY,

        // APPROVAL_REQUIRED — draft + present for human confirmation
        'draft_booking_suggestion' => ToolClassification::APPROVAL_REQUIRED,
        'draft_admin_message' => ToolClassification::APPROVAL_REQUIRED,
        'draft_cancellation_summary' => ToolClassification::APPROVAL_REQUIRED,

        // BLOCKED — model never executes these directly (PERMANENT)
        'create_booking' => ToolClassification::BLOCKED,
        'cancel_booking' => ToolClassification::BLOCKED,
        'confirm_booking' => ToolClassification::BLOCKED,
        'process_refund' => ToolClassification::BLOCKED,
        'modify_price' => ToolClassification::BLOCKED,
        'restore_booking' => ToolClassification::BLOCKED,
        'force_delete_booking' => ToolClassification::BLOCKED,
        'delete_account' => ToolClassification::BLOCKED,
        'any_direct_db_mutation' => ToolClassification::BLOCKED,
    ];

    /**
     * RBAC gate required for tool execution.
     * null = authenticated user only (no specific gate).
     * Only READ_ONLY and APPROVAL_REQUIRED tools have gates.
     *
     * @var array<string, ?string>
     */
    private const RBAC_GATES = [
        'get_booking_status' => 'view',           // BookingPolicy::view (ownership + moderator+)
        'get_user_bookings' => null,               // Authenticated user, own bookings only
        'draft_admin_message' => 'moderator',      // role:moderator
        'draft_cancellation_summary' => 'cancel',  // BookingPolicy::cancel
    ];

    private function __construct()
    {
        // Static-only class — no instantiation.
    }

    /**
     * Classify a tool. Unknown tools default to BLOCKED.
     */
    public static function classify(string $tool): ToolClassification
    {
        return self::TOOLS[$tool] ?? ToolClassification::BLOCKED;
    }

    /**
     * Get the RBAC gate for a tool, or null if only authentication is required.
     */
    public static function rbacGate(string $tool): ?string
    {
        return self::RBAC_GATES[$tool] ?? null;
    }

    /**
     * Check if a tool is blocked for model execution.
     */
    public static function isBlocked(string $tool): bool
    {
        return self::classify($tool) === ToolClassification::BLOCKED;
    }

    /**
     * Get all tool names classified as BLOCKED.
     *
     * @return list<string>
     */
    public static function blockedTools(): array
    {
        return array_keys(array_filter(
            self::TOOLS,
            fn (ToolClassification $c) => $c === ToolClassification::BLOCKED,
        ));
    }

    /**
     * Get the full tool classification map.
     *
     * @return array<string, ToolClassification>
     */
    public static function all(): array
    {
        return self::TOOLS;
    }
}
