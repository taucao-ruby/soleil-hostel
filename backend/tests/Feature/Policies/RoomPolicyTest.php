<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;
use App\Policies\RoomPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Room Policy Tests
 *
 * Direct unit tests for the RoomPolicy class.
 * Tests policy methods in isolation without HTTP layer.
 */
class RoomPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RoomPolicy $policy;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RoomPolicy();
        $this->room = Room::factory()->create();
    }

    // ========== VIEW ANY (LIST) ==========

    public function test_viewAny_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->assertTrue($this->policy->viewAny($admin));
    }

    public function test_viewAny_returns_true_for_moderator(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $this->assertTrue($this->policy->viewAny($moderator));
    }

    public function test_viewAny_returns_true_for_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->assertTrue($this->policy->viewAny($user));
    }

    // ========== VIEW (SHOW) ==========

    public function test_view_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->assertTrue($this->policy->view($admin, $this->room));
    }

    public function test_view_returns_true_for_moderator(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $this->assertTrue($this->policy->view($moderator, $this->room));
    }

    public function test_view_returns_true_for_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->assertTrue($this->policy->view($user, $this->room));
    }

    // ========== CREATE ==========

    public function test_create_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->assertTrue($this->policy->create($admin));
    }

    public function test_create_returns_false_for_moderator(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $this->assertFalse($this->policy->create($moderator));
    }

    public function test_create_returns_false_for_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->assertFalse($this->policy->create($user));
    }

    // ========== UPDATE ==========

    public function test_update_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->assertTrue($this->policy->update($admin, $this->room));
    }

    public function test_update_returns_false_for_moderator(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $this->assertFalse($this->policy->update($moderator, $this->room));
    }

    public function test_update_returns_false_for_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->assertFalse($this->policy->update($user, $this->room));
    }

    // ========== DELETE ==========

    public function test_delete_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->assertTrue($this->policy->delete($admin, $this->room));
    }

    public function test_delete_returns_false_for_moderator(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $this->assertFalse($this->policy->delete($moderator, $this->room));
    }

    public function test_delete_returns_false_for_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->assertFalse($this->policy->delete($user, $this->room));
    }

    // ========== EDGE CASES ==========

    public function test_admin_can_manage_any_room(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $rooms = Room::factory()->count(3)->create();

        foreach ($rooms as $room) {
            $this->assertTrue($this->policy->update($admin, $room));
            $this->assertTrue($this->policy->delete($admin, $room));
        }
    }

    public function test_different_admins_have_same_permissions(): void
    {
        $admin1 = User::factory()->create(['role' => UserRole::ADMIN]);
        $admin2 = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->assertTrue($this->policy->create($admin1));
        $this->assertTrue($this->policy->create($admin2));
        $this->assertTrue($this->policy->update($admin1, $this->room));
        $this->assertTrue($this->policy->update($admin2, $this->room));
        $this->assertTrue($this->policy->delete($admin1, $this->room));
        $this->assertTrue($this->policy->delete($admin2, $this->room));
    }

    public function test_user_role_is_checked_correctly(): void
    {
        // Verify isAdmin() method is being used
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());

        // Verify policy uses isAdmin()
        $this->assertTrue($this->policy->create($admin));
        $this->assertFalse($this->policy->create($user));
    }
}
