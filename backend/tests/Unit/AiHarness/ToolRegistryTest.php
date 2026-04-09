<?php

declare(strict_types=1);

namespace Tests\Unit\AiHarness;

use App\AiHarness\Enums\ToolClassification;
use App\AiHarness\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    private const READ_ONLY_TOOLS = [
        'search_rooms',
        'check_availability',
        'get_location_detail',
        'lookup_policy',
        'get_faq_content',
        'get_booking_status',
        'get_user_bookings',
    ];

    private const APPROVAL_REQUIRED_TOOLS = [
        'draft_booking_suggestion',
        'draft_admin_message',
        'draft_cancellation_summary',
    ];

    private const BLOCKED_TOOLS = [
        'create_booking',
        'cancel_booking',
        'confirm_booking',
        'process_refund',
        'modify_price',
        'restore_booking',
        'force_delete_booking',
        'delete_account',
        'any_direct_db_mutation',
    ];

    public function test_all_read_only_tools_are_classified_correctly(): void
    {
        foreach (self::READ_ONLY_TOOLS as $tool) {
            $this->assertSame(
                ToolClassification::READ_ONLY,
                ToolRegistry::classify($tool),
                "Tool '{$tool}' should be READ_ONLY",
            );
        }
    }

    public function test_all_approval_required_tools_are_classified_correctly(): void
    {
        foreach (self::APPROVAL_REQUIRED_TOOLS as $tool) {
            $this->assertSame(
                ToolClassification::APPROVAL_REQUIRED,
                ToolRegistry::classify($tool),
                "Tool '{$tool}' should be APPROVAL_REQUIRED",
            );
        }
    }

    public function test_all_blocked_tools_are_classified_correctly(): void
    {
        foreach (self::BLOCKED_TOOLS as $tool) {
            $this->assertSame(
                ToolClassification::BLOCKED,
                ToolRegistry::classify($tool),
                "Tool '{$tool}' should be BLOCKED",
            );
        }
    }

    public function test_unknown_tool_defaults_to_blocked(): void
    {
        $this->assertSame(
            ToolClassification::BLOCKED,
            ToolRegistry::classify('nonexistent_tool'),
        );
    }

    public function test_blocked_tools_include_all_mutation_operations(): void
    {
        $blockedTools = ToolRegistry::blockedTools();

        foreach (self::BLOCKED_TOOLS as $tool) {
            $this->assertContains(
                $tool,
                $blockedTools,
                "Mutation tool '{$tool}' must be in blockedTools() list",
            );
        }
    }

    public function test_no_blocked_tool_has_rbac_gate(): void
    {
        foreach (self::BLOCKED_TOOLS as $tool) {
            $this->assertNull(
                ToolRegistry::rbacGate($tool),
                "Blocked tool '{$tool}' must not have an RBAC gate (it never executes)",
            );
        }
    }

    public function test_classify_returns_blocked_for_empty_string(): void
    {
        $this->assertSame(
            ToolClassification::BLOCKED,
            ToolRegistry::classify(''),
        );

        $this->assertTrue(ToolRegistry::isBlocked(''));
    }
}
