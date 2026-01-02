<?php

namespace Tests\Feature\Room;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Room Validation Tests
 *
 * Tests input validation for Room API endpoints.
 */
class RoomValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
    }

    // ========== STORE VALIDATION TESTS ==========

    public function test_store_requires_name(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_description(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_store_requires_price(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_store_requires_max_guests(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_guests']);
    }

    public function test_store_requires_status(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_name_max_length_100(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => str_repeat('a', 101),
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_price_must_be_numeric(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 'not-a-number',
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_store_price_cannot_be_negative(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => -50.00,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_store_max_guests_must_be_integer(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2.5,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_guests']);
    }

    public function test_store_max_guests_must_be_at_least_1(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 0,
                'status' => 'available',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_guests']);
    }

    public function test_store_status_must_be_valid(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'invalid-status',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_accepts_valid_status_available(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(201);
    }

    public function test_store_accepts_valid_status_booked(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'booked',
            ]);

        $response->assertStatus(201);
    }

    public function test_store_accepts_valid_status_maintenance(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'maintenance',
            ]);

        $response->assertStatus(201);
    }

    // ========== UPDATE VALIDATION TESTS ==========

    public function test_update_requires_all_fields(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description', 'price', 'max_guests', 'status']);
    }

    public function test_update_lock_version_must_be_integer(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Updated',
                'description' => 'Updated',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
                'lock_version' => 'not-a-number',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lock_version']);
    }

    public function test_update_lock_version_must_be_at_least_1(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Updated',
                'description' => 'Updated',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
                'lock_version' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lock_version']);
    }

    public function test_update_accepts_null_lock_version(): void
    {
        $room = Room::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Updated',
                'description' => 'Updated',
                'price' => 100.00,
                'max_guests' => 2,
                'status' => 'available',
                'lock_version' => null,
            ]);

        $response->assertStatus(200);
    }

    // ========== EDGE CASES ==========

    public function test_store_accepts_zero_price(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Free Room',
                'description' => 'Test description',
                'price' => 0,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(201);
    }

    public function test_store_accepts_high_max_guests(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Dormitory',
                'description' => 'Test description',
                'price' => 50.00,
                'max_guests' => 20,
                'status' => 'available',
            ]);

        $response->assertStatus(201);
    }

    public function test_store_accepts_decimal_price(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/rooms', [
                'name' => 'Test Room',
                'description' => 'Test description',
                'price' => 99.99,
                'max_guests' => 2,
                'status' => 'available',
            ]);

        $response->assertStatus(201);
        
        // Price may be returned as string or float depending on serialization
        $price = $response->json('data.price');
        $this->assertEquals(99.99, (float) $price);
    }
}
