<?php

namespace Tests\Feature\Queue;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueMonitoringAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_horizon_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->get('/horizon');

        $response->assertStatus(200);
    }

    public function test_moderator_cannot_access_horizon_dashboard(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);

        $response = $this->actingAs($moderator)->get('/horizon');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_horizon_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $response = $this->actingAs($user)->get('/horizon');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_horizon_dashboard(): void
    {
        $response = $this->get('/horizon');

        $response->assertStatus(403);
    }

    public function test_admin_has_view_queue_monitoring_gate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin);

        $this->assertTrue($admin->can('view-queue-monitoring'));
    }

    public function test_non_admin_does_not_have_view_queue_monitoring_gate(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->assertFalse($moderator->can('view-queue-monitoring'));
        $this->assertFalse($user->can('view-queue-monitoring'));
    }
}
