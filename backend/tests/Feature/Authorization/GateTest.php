<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for RBAC Gates defined in AuthServiceProvider.
 * 
 * Tests: admin, moderator, manage-users, moderate-content, view-all-bookings, manage-rooms
 */
class GateTest extends TestCase
{
    use RefreshDatabase;

    // ========== 'admin' Gate Tests ==========

    public function test_admin_gate_allows_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('admin'));
    }

    public function test_admin_gate_denies_moderator(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator);
        $this->assertFalse(Gate::allows('admin'));
    }

    public function test_admin_gate_denies_user(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('admin'));
    }

    // ========== 'moderator' Gate Tests ==========

    public function test_moderator_gate_allows_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('moderator'));
    }

    public function test_moderator_gate_allows_moderator(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator);
        $this->assertTrue(Gate::allows('moderator'));
    }

    public function test_moderator_gate_denies_user(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('moderator'));
    }

    // ========== 'manage-users' Gate Tests ==========

    public function test_manage_users_gate_allows_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('manage-users'));

        $this->actingAs($moderator);
        $this->assertFalse(Gate::allows('manage-users'));

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('manage-users'));
    }

    // ========== 'moderate-content' Gate Tests ==========

    public function test_moderate_content_gate_allows_moderator_and_above(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('moderate-content'));

        $this->actingAs($moderator);
        $this->assertTrue(Gate::allows('moderate-content'));

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('moderate-content'));
    }

    // ========== 'view-all-bookings' Gate Tests ==========

    public function test_view_all_bookings_gate_allows_moderator_and_above(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('view-all-bookings'));

        $this->actingAs($moderator);
        $this->assertTrue(Gate::allows('view-all-bookings'));

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('view-all-bookings'));
    }

    // ========== 'manage-rooms' Gate Tests ==========

    public function test_manage_rooms_gate_allows_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('manage-rooms'));

        $this->actingAs($moderator);
        $this->assertFalse(Gate::allows('manage-rooms'));

        $this->actingAs($user);
        $this->assertFalse(Gate::allows('manage-rooms'));
    }

    // ========== Gate::authorize() Tests ==========

    public function test_gate_authorize_throws_for_unauthorized(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        Gate::authorize('admin');
    }

    public function test_gate_authorize_passes_for_authorized(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        // Should not throw
        Gate::authorize('admin');
        $this->assertTrue(true);
    }

    // ========== Gate::check() vs Gate::allows() ==========

    public function test_gate_check_is_equivalent_to_allows(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->assertEquals(Gate::allows('admin'), Gate::check('admin'));
        $this->assertEquals(Gate::allows('manage-rooms'), Gate::check('manage-rooms'));
    }

    // ========== Guest User Tests ==========

    public function test_gates_deny_unauthenticated_users(): void
    {
        // No user authenticated
        $this->assertFalse(Gate::allows('admin'));
        $this->assertFalse(Gate::allows('moderator'));
        $this->assertFalse(Gate::allows('manage-users'));
    }
}
