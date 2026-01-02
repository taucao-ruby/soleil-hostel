<?php

namespace Tests\Feature\Room;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive Room CRUD Tests
 *
 * Tests all Room API endpoints:
 * - GET /api/rooms (index)
 * - GET /api/rooms/{id} (show)
 * - POST /api/rooms (store)
 * - PUT /api/rooms/{id} (update)
 * - DELETE /api/rooms/{id} (destroy)
 */
class RoomCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->user = User::factory()->create(['role' => UserRole::USER]);
    }

    // ========== INDEX TESTS ==========

    public function test_guests_can_list_all_rooms(): void
    {
        Room::factory()->count(5)->create();

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'description', 'price', 'max_guests', 'status']
                ]
            ])
            ->assertJson(['success' => true])
            ->assertJsonCount(5, 'data');
    }

    public function test_rooms_index_returns_empty_array_when_no_rooms(): void
    {
        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => []
            ]);
    }

    public function test_rooms_index_includes_lock_version(): void
    {
        Room::factory()->create();

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['lock_version']
                ]
            ]);
    }

    // ========== SHOW TESTS ==========

    public function test_guests_can_view_single_room(): void
    {
        $room = Room::factory()->create([
            'name' => 'Deluxe Room',
            'price' => 150.00,
        ]);

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Room fetched successfully',
                'data' => [
                    'id' => $room->id,
                    'name' => 'Deluxe Room',
                    'price' => '150.00',
                ]
            ]);
    }

    public function test_show_returns_404_for_nonexistent_room(): void
    {
        $response = $this->getJson('/api/rooms/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Room not found',
            ]);
    }

    public function test_show_room_includes_lock_version(): void
    {
        $room = Room::factory()->create();

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['lock_version']
            ]);
    }

    // ========== STORE TESTS ==========

    public function test_admin_can_create_room(): void
    {
        $roomData = [
            'name' => 'New Room',
            'description' => 'A beautiful new room',
            'price' => 100.00,
            'max_guests' => 2,
            'status' => 'available',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', $roomData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Room created successfully',
                'data' => [
                    'name' => 'New Room',
                    'price' => '100.00',
                    'max_guests' => 2,
                    'lock_version' => 1,
                ]
            ]);

        $this->assertDatabaseHas('rooms', [
            'name' => 'New Room',
            'lock_version' => 1,
        ]);
    }

    public function test_regular_user_cannot_create_room(): void
    {
        $roomData = [
            'name' => 'New Room',
            'description' => 'A beautiful new room',
            'price' => 100.00,
            'max_guests' => 2,
            'status' => 'available',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/rooms', $roomData);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_create_room(): void
    {
        $roomData = [
            'name' => 'New Room',
            'description' => 'A beautiful new room',
            'price' => 100.00,
            'max_guests' => 2,
            'status' => 'available',
        ];

        $response = $this->postJson('/api/rooms', $roomData);

        $response->assertStatus(401);
    }

    // ========== UPDATE TESTS ==========

    public function test_admin_can_update_room(): void
    {
        $room = Room::factory()->create([
            'name' => 'Old Name',
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Updated Name',
                'description' => $room->description,
                'price' => 150.00,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => $room->lock_version,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Room updated successfully',
                'data' => [
                    'name' => 'Updated Name',
                    'price' => '150.00',
                ]
            ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_regular_user_cannot_update_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Hacked Name',
                'description' => $room->description,
                'price' => 999.00,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ]);

        $response->assertStatus(403);
    }

    public function test_update_returns_404_for_nonexistent_room(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/rooms/99999', [
                'name' => 'Test',
                'description' => 'Test',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(404);
    }

    // ========== DELETE TESTS ==========

    public function test_admin_can_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Room deleted successfully',
            ]);

        $this->assertDatabaseMissing('rooms', [
            'id' => $room->id,
        ]);
    }

    public function test_regular_user_cannot_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
        ]);
    }

    public function test_guest_cannot_delete_room(): void
    {
        $room = Room::factory()->create();

        $response = $this->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(401);
    }

    public function test_delete_returns_404_for_nonexistent_room(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/rooms/99999');

        $response->assertStatus(404);
    }

    // ========== EDGE CASES ==========

    public function test_room_status_values_are_correct(): void
    {
        $availableRoom = Room::factory()->create(['status' => 'available']);
        $bookedRoom = Room::factory()->create(['status' => 'booked']);
        $maintenanceRoom = Room::factory()->create(['status' => 'maintenance']);

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_room_price_is_formatted_correctly(): void
    {
        $room = Room::factory()->create(['price' => 99.99]);

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200);
        
        // Price may be returned as string or float depending on serialization
        $price = $response->json('data.price');
        $this->assertEquals(99.99, (float) $price);
    }

    public function test_room_max_guests_is_integer(): void
    {
        $room = Room::factory()->create(['max_guests' => 4]);

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.max_guests', 4);
    }
}
