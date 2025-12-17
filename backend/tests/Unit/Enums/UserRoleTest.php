<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserRole enum.
 * 
 * Tests enum values, default role, and static helper methods.
 */
class UserRoleTest extends TestCase
{
    /**
     * Test that enum values match expected database values.
     */
    public function test_enum_values_match_database(): void
    {
        $this->assertEquals(['user', 'moderator', 'admin'], UserRole::values());
    }

    /**
     * Test that default role is USER.
     */
    public function test_default_role_is_user(): void
    {
        $this->assertEquals(UserRole::USER, UserRole::default());
        $this->assertEquals('user', UserRole::default()->value);
    }

    /**
     * Test all enum cases exist.
     */
    public function test_all_cases_exist(): void
    {
        $cases = UserRole::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(UserRole::USER, $cases);
        $this->assertContains(UserRole::MODERATOR, $cases);
        $this->assertContains(UserRole::ADMIN, $cases);
    }

    /**
     * Test enum backing values are lowercase strings.
     */
    public function test_backing_values_are_lowercase(): void
    {
        $this->assertEquals('user', UserRole::USER->value);
        $this->assertEquals('moderator', UserRole::MODERATOR->value);
        $this->assertEquals('admin', UserRole::ADMIN->value);
    }

    /**
     * Test tryFrom returns correct enum for valid values.
     */
    public function test_tryFrom_with_valid_values(): void
    {
        $this->assertEquals(UserRole::USER, UserRole::tryFrom('user'));
        $this->assertEquals(UserRole::MODERATOR, UserRole::tryFrom('moderator'));
        $this->assertEquals(UserRole::ADMIN, UserRole::tryFrom('admin'));
    }

    /**
     * Test tryFrom returns null for invalid values.
     */
    public function test_tryFrom_with_invalid_values(): void
    {
        $this->assertNull(UserRole::tryFrom('superadmin'));
        $this->assertNull(UserRole::tryFrom('guest'));
        $this->assertNull(UserRole::tryFrom('ADMIN')); // case-sensitive
        $this->assertNull(UserRole::tryFrom(''));
    }

    /**
     * Test from throws exception for invalid values.
     */
    public function test_from_throws_for_invalid_values(): void
    {
        $this->expectException(\ValueError::class);
        UserRole::from('invalid');
    }

    /**
     * Test enum equality comparison.
     */
    public function test_enum_equality(): void
    {
        $this->assertTrue(UserRole::ADMIN === UserRole::ADMIN);
        $this->assertFalse(UserRole::ADMIN === UserRole::USER);
        $this->assertTrue(UserRole::from('admin') === UserRole::ADMIN);
    }
}
