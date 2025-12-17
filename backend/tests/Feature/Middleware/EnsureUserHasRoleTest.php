<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for EnsureUserHasRole middleware.
 * 
 * Tests route protection based on role hierarchy.
 */
class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register test routes with role middleware
        Route::middleware(['auth:sanctum', 'role:admin'])->get('/test/admin-only', function () {
            return response()->json(['message' => 'admin access granted']);
        });

        Route::middleware(['auth:sanctum', 'role:moderator'])->get('/test/moderator-only', function () {
            return response()->json(['message' => 'moderator access granted']);
        });

        Route::middleware(['auth:sanctum', 'role:user'])->get('/test/user-only', function () {
            return response()->json(['message' => 'user access granted']);
        });
    }

    // ========== Admin Route Tests ==========

    public function test_admin_can_access_admin_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/test/admin-only')
            ->assertStatus(200)
            ->assertJson(['message' => 'admin access granted']);
    }

    public function test_moderator_cannot_access_admin_route(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/test/admin-only')
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden. Insufficient permissions.']);
    }

    public function test_user_cannot_access_admin_route(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/test/admin-only')
            ->assertStatus(403);
    }

    // ========== Moderator Route Tests ==========

    public function test_admin_can_access_moderator_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/test/moderator-only')
            ->assertStatus(200)
            ->assertJson(['message' => 'moderator access granted']);
    }

    public function test_moderator_can_access_moderator_route(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/test/moderator-only')
            ->assertStatus(200);
    }

    public function test_user_cannot_access_moderator_route(): void
    {
        $user = User::factory()->user()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/test/moderator-only')
            ->assertStatus(403);
    }

    // ========== User Route Tests ==========

    public function test_all_authenticated_users_can_access_user_route(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/test/user-only')
            ->assertStatus(200);

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/test/user-only')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->getJson('/test/user-only')
            ->assertStatus(200);
    }

    // ========== Unauthenticated Tests ==========

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/test/admin-only')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated. Please log in.']);
    }

    public function test_unauthenticated_user_cannot_access_any_protected_route(): void
    {
        $this->getJson('/test/moderator-only')->assertStatus(401);
        $this->getJson('/test/user-only')->assertStatus(401);
    }
}
