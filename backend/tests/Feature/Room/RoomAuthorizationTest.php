<?php

namespace Tests\Feature\Room;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Room Authorization Tests
 *
 * Tests role-based access control for Room API endpoints.
 * - Guests: Can only read (index, show)
 * - Users: Can only read (index, show)
 * - Moderators: Can only read (index, show)
 * - Admins: Full CRUD access
 */
class RoomAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $moderator;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
        $this->user = User::factory()->create(['role' => UserRole::USER]);
    }

    private function getValidRoomData(): array
    {
        return [
            'name' => 'Test Room',
            'description' => 'Test description',
            'price' => 100.00,
            'max_guests' => 2,
            'status' => 'available',
        ];
    }

    // ========== READ ACCESS (ALL ROLES) ==========

    public function test_guest_can_access_rooms_index(): void
    {
        Room::factory()->create();

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200);
    }

    public function test_user_can_access_rooms_index(): void
    {
        Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/rooms');

        $response->assertStatus(200);
    }

    public function test_moderator_can_access_rooms_index(): void
    {
        Room::factory()->create();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/rooms');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_rooms_index(): void
    {
        Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/rooms');

        $response->assertStatus(200);
    }

    public function test_guest_can_access_rooms_show(): void
    {
        $room = Room::factory()->create();

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200);
    }

    public function test_user_can_access_rooms_show(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200);
    }

    // ========== CREATE ACCESS (ADMIN ONLY) ==========

    public function test_guest_cannot_create_room(): void
    {
        $response = $this->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(401);
    }

    public function test_user_cannot_create_room(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(403);
    }

    public function test_moderator_cannot_create_room(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(403);
    }

    public function test_admin_can_create_room(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(201);
    }

    // ========== UPDATE ACCESS (ADMIN ONLY) ==========

    public function test_guest_cannot_update_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->putJson("/api/rooms/{$room->id}", $this->getValidRoomData());

        $response->assertStatus(401);
    }

    public function test_user_cannot_update_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", $this->getValidRoomData());

        $response->assertStatus(403);
    }

    public function test_moderator_cannot_update_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", $this->getValidRoomData());

        $response->assertStatus(403);
    }

    public function test_admin_can_update_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", array_merge(
                $this->getValidRoomData(),
                ['lock_version' => $room->lock_version]
            ));

        $response->assertStatus(200);
    }

    // ========== DELETE ACCESS (ADMIN ONLY) ==========

    public function test_guest_cannot_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_user_cannot_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_moderator_cannot_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->moderator, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_admin_can_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    // ========== MULTIPLE ADMINS ==========

    public function test_any_admin_can_manage_any_room(): void
    {
        $admin1 = User::factory()->create(['role' => UserRole::ADMIN]);
        $admin2 = User::factory()->create(['role' => UserRole::ADMIN]);

        // Admin1 creates room
        $response = $this->actingAs($admin1, 'sanctum')
            ->postJson('/api/rooms', $this->getValidRoomData());

        $response->assertStatus(201);
        $roomId = $response->json('data.id');

        // Admin2 can update it
        $response = $this->actingAs($admin2, 'sanctum')
            ->putJson("/api/rooms/{$roomId}", array_merge(
                $this->getValidRoomData(),
                ['name' => 'Updated by Admin2', 'lock_version' => 1]
            ));

        $response->assertStatus(200);

        // Admin1 can delete it
        $response = $this->actingAs($admin1, 'sanctum')
            ->deleteJson("/api/rooms/{$roomId}");

        $response->assertStatus(200);
    }
}
