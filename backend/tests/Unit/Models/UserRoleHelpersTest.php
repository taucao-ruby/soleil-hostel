<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

/**
 * Unit tests for User model RBAC helper methods.
 * 
 * Tests: isAdmin(), isModerator(), isUser(), hasRole(), hasAnyRole(), isAtLeast()
 */
class UserRoleHelpersTest extends TestCase
{
    // ========== isAdmin() Tests ==========

    public function test_isAdmin_returns_true_only_for_admin(): void
    {
        $admin = User::factory()->admin()->make();
        $moderator = User::factory()->moderator()->make();
        $user = User::factory()->user()->make();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($moderator->isAdmin());
        $this->assertFalse($user->isAdmin());
    }

    // ========== isModerator() Tests ==========

    public function test_isModerator_returns_true_for_moderator_and_admin(): void
    {
        $admin = User::factory()->admin()->make();
        $moderator = User::factory()->moderator()->make();
        $user = User::factory()->user()->make();

        $this->assertTrue($admin->isModerator(), 'Admin should pass isModerator check');
        $this->assertTrue($moderator->isModerator(), 'Moderator should pass isModerator check');
        $this->assertFalse($user->isModerator(), 'Regular user should not pass isModerator check');
    }

    // ========== isUser() Tests ==========

    public function test_isUser_returns_true_only_for_user_role(): void
    {
        $admin = User::factory()->admin()->make();
        $moderator = User::factory()->moderator()->make();
        $user = User::factory()->user()->make();

        $this->assertFalse($admin->isUser());
        $this->assertFalse($moderator->isUser());
        $this->assertTrue($user->isUser());
    }

    // ========== hasRole() Tests ==========

    public function test_hasRole_exact_match(): void
    {
        $admin = User::factory()->admin()->make();
        $moderator = User::factory()->moderator()->make();

        $this->assertTrue($admin->hasRole(UserRole::ADMIN));
        $this->assertFalse($admin->hasRole(UserRole::MODERATOR));
        $this->assertFalse($admin->hasRole(UserRole::USER));

        $this->assertTrue($moderator->hasRole(UserRole::MODERATOR));
        $this->assertFalse($moderator->hasRole(UserRole::ADMIN));
    }

    // ========== hasAnyRole() Tests ==========

    public function test_hasAnyRole_with_matching_role(): void
    {
        $moderator = User::factory()->moderator()->make();

        $this->assertTrue($moderator->hasAnyRole([UserRole::ADMIN, UserRole::MODERATOR]));
        $this->assertTrue($moderator->hasAnyRole([UserRole::MODERATOR]));
    }

    public function test_hasAnyRole_with_no_matching_role(): void
    {
        $user = User::factory()->user()->make();

        $this->assertFalse($user->hasAnyRole([UserRole::ADMIN, UserRole::MODERATOR]));
    }

    public function test_hasAnyRole_with_empty_array(): void
    {
        $admin = User::factory()->admin()->make();

        $this->assertFalse($admin->hasAnyRole([]));
    }

    // ========== isAtLeast() Tests ==========

    public function test_isAtLeast_user_level(): void
    {
        $admin = User::factory()->admin()->make();
        $moderator = User::factory()->moderator()->make();
        $user = User::factory()->user()->make();

        // All roles are at least USER
        $this->assertTrue($admin->isAtLeast(UserRole::USER));
        $this->assertTrue($moderator->isAtLeast(UserRole::USER));
        $this->assertTrue($user->isAtLeast(UserRole::USER));
    }

    public function test_isAtLeast_moderator_level(): void
    {
        $admin = User::factory()->admin()->make();
        $moderator = User::factory()->moderator()->make();
        $user = User::factory()->user()->make();

        $this->assertTrue($admin->isAtLeast(UserRole::MODERATOR));
        $this->assertTrue($moderator->isAtLeast(UserRole::MODERATOR));
        $this->assertFalse($user->isAtLeast(UserRole::MODERATOR));
    }

    public function test_isAtLeast_admin_level(): void
    {
        $admin = User::factory()->admin()->make();
        $moderator = User::factory()->moderator()->make();
        $user = User::factory()->user()->make();

        $this->assertTrue($admin->isAtLeast(UserRole::ADMIN));
        $this->assertFalse($moderator->isAtLeast(UserRole::ADMIN));
        $this->assertFalse($user->isAtLeast(UserRole::ADMIN));
    }

    // ========== Role Casting Tests ==========

    public function test_role_is_cast_to_enum(): void
    {
        $user = User::factory()->admin()->make();

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertEquals(UserRole::ADMIN, $user->role);
    }

    public function test_role_can_be_set_with_enum(): void
    {
        $user = User::factory()->make();
        $user->role = UserRole::MODERATOR;

        $this->assertEquals(UserRole::MODERATOR, $user->role);
        $this->assertTrue($user->isModerator());
    }

    // ========== Factory State Tests ==========

    public function test_factory_admin_state(): void
    {
        $user = User::factory()->admin()->make();
        $this->assertEquals(UserRole::ADMIN, $user->role);
    }

    public function test_factory_moderator_state(): void
    {
        $user = User::factory()->moderator()->make();
        $this->assertEquals(UserRole::MODERATOR, $user->role);
    }

    public function test_factory_user_state(): void
    {
        $user = User::factory()->user()->make();
        $this->assertEquals(UserRole::USER, $user->role);
    }

    public function test_factory_default_is_user(): void
    {
        $user = User::factory()->make();
        $this->assertEquals(UserRole::USER, $user->role);
    }
}
