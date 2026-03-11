<?php

namespace Tests\Feature\Room;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LegacyRoomAuthorizationTest — Validates role enforcement on legacy room CUD routes.
 *
 * Legacy routes (/api/rooms) must enforce the same RBAC as v1 routes (/api/v1/rooms).
 * Covers: G-01 (RBAC audit Phase 1 fix — added role:admin to legacy.php)
 */
class LegacyRoomAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    private User $user;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $this->user = User::factory()->create(['role' => UserRole::USER]);
        $this->location = Location::factory()->create();
    }

    private function getValidRoomData(): array
    {
        return [
            'location_id' => $this->location->id,
            'name' => 'Legacy Test Room',
            'description' => 'Test description',
            'price' => 100.00,
            'max_guests' => 2,
            'status' => 'available',
        ];
    }

    // ========== CREATE via legacy /api/rooms ==========

    public function test_user_cannot_create_room_via_legacy_endpoint(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(403);
    }

    public function test_moderator_cannot_create_room_via_legacy_endpoint(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(403);
    }

    public function test_guest_cannot_create_room_via_legacy_endpoint(): void
    {
        $response = $this->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(401);
    }

    public function test_admin_can_create_room_via_legacy_endpoint(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(201);
    }

    // ========== UPDATE via legacy /api/rooms/{id} ==========

    public function test_user_cannot_update_room_via_legacy_endpoint(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", $this->getValidRoomData());

        $response->assertStatus(403);
    }

    public function test_moderator_cannot_update_room_via_legacy_endpoint(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", $this->getValidRoomData());

        $response->assertStatus(403);
    }

    // ========== DELETE via legacy /api/rooms/{id} ==========

    public function test_user_cannot_delete_room_via_legacy_endpoint(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_moderator_cannot_delete_room_via_legacy_endpoint(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_admin_can_delete_room_via_legacy_endpoint(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }
}
