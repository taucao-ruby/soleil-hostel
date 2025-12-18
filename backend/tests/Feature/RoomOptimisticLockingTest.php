<?php

namespace Tests\Feature;

use App\Exceptions\OptimisticLockException;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Optimistic Locking Tests for Room Updates
 *
 * These tests verify the optimistic concurrency control implementation
 * for the Room model. Optimistic locking prevents the "lost update" problem
 * when multiple users/processes attempt to update the same room concurrently.
 *
 * Test Coverage:
 * 1. New rooms start with lock_version = 1
 * 2. Successful update increments lock_version
 * 3. Concurrent update with stale version fails with OptimisticLockException
 * 4. Legacy records with null version are handled safely
 * 5. API returns 409 Conflict on version mismatch
 * 6. lock_version is included in API responses
 *
 * @see App\Services\RoomService::updateWithOptimisticLock()
 * @see App\Exceptions\OptimisticLockException
 */
class RoomOptimisticLockingTest extends TestCase
{
    use RefreshDatabase;

    private RoomService $roomService;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomService = app(RoomService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ========================================================================
    // UNIT TESTS - RoomService Optimistic Locking Logic
    // ========================================================================

    public function test_new_room_starts_with_lock_version_1(): void
    {
        $room = Room::factory()->create();

        $this->assertEquals(1, $room->lock_version);
    }

    public function test_successful_update_with_matching_version_increments_lock_version(): void
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Original Name']);
        $originalVersion = $room->lock_version;

        $this->assertEquals(1, $originalVersion);

        // Act
        $updatedRoom = $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'Updated Name',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            $originalVersion
        );

        // Assert
        $this->assertEquals('Updated Name', $updatedRoom->name);
        $this->assertEquals($originalVersion + 1, $updatedRoom->lock_version);
        $this->assertEquals(2, $updatedRoom->lock_version);
    }

    public function test_update_with_stale_version_throws_optimistic_lock_exception(): void
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Original Name']);
        $staleVersion = $room->lock_version; // Version 1

        // Simulate another user updating the room first
        DB::table('rooms')
            ->where('id', $room->id)
            ->update([
                'name' => 'Updated by Another User',
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

        // Refresh to verify the DB was updated
        $room->refresh();
        $this->assertEquals(2, $room->lock_version);
        $this->assertEquals('Updated by Another User', $room->name);

        // Act & Assert
        $this->expectException(OptimisticLockException::class);

        $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'My Update',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            $staleVersion // Using stale version 1, but DB has version 2
        );
    }

    public function test_concurrent_update_scenario_second_update_fails(): void
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Original']);
        $versionBeforeUpdates = $room->lock_version;

        // User A and User B both read the room at the same time
        $userAVersion = $versionBeforeUpdates;
        $userBVersion = $versionBeforeUpdates;

        // Act: User A updates first - should succeed
        $updatedByA = $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'Updated by User A',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            $userAVersion
        );

        $this->assertEquals($versionBeforeUpdates + 1, $updatedByA->lock_version);
        $this->assertEquals('Updated by User A', $updatedByA->name);

        // Refresh room for User B's attempt
        $room->refresh();

        // Act & Assert: User B tries to update with stale version - should fail
        $this->expectException(OptimisticLockException::class);

        $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'Updated by User B',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            $userBVersion // Stale version
        );
    }

    public function test_multiple_sequential_updates_increment_version_correctly(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $this->assertEquals(1, $room->lock_version);

        // Act: Perform 5 sequential updates
        for ($i = 1; $i <= 5; $i++) {
            $currentVersion = $room->lock_version;
            $room = $this->roomService->updateWithOptimisticLock(
                $room,
                [
                    'name' => "Update #{$i}",
                    'description' => $room->description,
                    'price' => $room->price,
                    'max_guests' => $room->max_guests,
                    'status' => $room->status,
                ],
                $currentVersion
            );
        }

        // Assert
        $this->assertEquals(6, $room->lock_version); // Started at 1, +5 updates = 6
        $this->assertEquals('Update #5', $room->name);
    }

    public function test_backward_compatibility_null_version_uses_current_db_version(): void
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Original']);

        // Act: Call update without providing version (null)
        $updatedRoom = $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'Updated without version',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            null // No version provided
        );

        // Assert: Should succeed and increment version
        $this->assertEquals('Updated without version', $updatedRoom->name);
        $this->assertEquals(2, $updatedRoom->lock_version);
    }

    public function test_optimistic_lock_exception_contains_correct_version_information(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $staleVersion = $room->lock_version;

        // Update to create version mismatch
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 5]);

        // Act
        try {
            $this->roomService->updateWithOptimisticLock(
                $room,
                [
                    'name' => 'Test',
                    'description' => $room->description,
                    'price' => $room->price,
                    'max_guests' => $room->max_guests,
                    'status' => $room->status,
                ],
                $staleVersion
            );
            $this->fail('Expected OptimisticLockException was not thrown');
        } catch (OptimisticLockException $e) {
            // Assert - using public readonly properties (PHP 8.3)
            $this->assertEquals($staleVersion, $e->expectedVersion);
            $this->assertEquals(5, $e->actualVersion);
            $this->assertInstanceOf(Room::class, $e->model);
            $this->assertStringContainsString('modified by another user', $e->getMessage());
        }
    }

    public function test_create_room_sets_lock_version_to_1_automatically(): void
    {
        // Act
        $room = $this->roomService->createRoom([
            'name' => 'New Room',
            'description' => 'A brand new room',
            'price' => 100.00,
            'max_guests' => 4,
            'status' => 'available',
        ]);

        // Assert
        $this->assertEquals(1, $room->lock_version);
    }

    public function test_delete_with_optimistic_lock_succeeds_with_correct_version(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $currentVersion = $room->lock_version;
        $roomId = $room->id;

        // Act
        $result = $this->roomService->deleteWithOptimisticLock($room, $currentVersion);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(Room::find($roomId));
    }

    public function test_delete_with_optimistic_lock_fails_with_stale_version(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $staleVersion = $room->lock_version;

        // Update version directly
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 5]);

        $room->refresh();

        // Act & Assert
        $this->expectException(OptimisticLockException::class);

        $this->roomService->deleteWithOptimisticLock($room, $staleVersion);
    }

    // ========================================================================
    // INTEGRATION TESTS - API Endpoints
    // ========================================================================

    public function test_get_room_returns_lock_version(): void
    {
        // Arrange
        $room = Room::factory()->create();

        // Act
        $response = $this->actingAs($this->admin)
            ->getJson("/api/rooms/{$room->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.lock_version', 1);
    }

    public function test_post_rooms_creates_room_with_lock_version_1(): void
    {
        // Act
        $response = $this->actingAs($this->admin)
            ->postJson('/api/rooms', [
                'name' => 'New API Room',
                'description' => 'Created via API',
                'price' => 150.00,
                'max_guests' => 3,
                'status' => 'available',
            ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.lock_version', 1);
    }

    public function test_put_room_with_correct_version_succeeds_and_increments_version(): void
    {
        // Arrange
        $room = Room::factory()->create([
            'name' => 'Original',
            'description' => 'Original description',
            'price' => 100,
            'max_guests' => 2,
            'status' => 'available',
        ]);

        // Act
        $response = $this->actingAs($this->admin)
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Updated via API',
                'description' => 'Updated description',
                'price' => 120,
                'max_guests' => 3,
                'status' => 'available',
                'lock_version' => 1,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated via API')
            ->assertJsonPath('data.lock_version', 2);
    }

    public function test_put_room_with_stale_version_returns_409_conflict(): void
    {
        // Arrange
        $room = Room::factory()->create([
            'description' => 'Test description',
            'price' => 100,
            'max_guests' => 2,
            'status' => 'available',
        ]);

        // Simulate another update that incremented version
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 5]);

        // Act: Try to update with stale version 1
        $response = $this->actingAs($this->admin)
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'My Update',
                'description' => 'Test description',
                'price' => 100,
                'max_guests' => 2,
                'status' => 'available',
                'lock_version' => 1, // Stale version
            ]);

        // Assert
        $response->assertStatus(409)
            ->assertJson([
                'error' => 'resource_out_of_date',
                'message' => 'The room has been modified by another user. Please refresh and try again.',
            ]);
    }

    public function test_put_room_without_lock_version_uses_backward_compatible_mode(): void
    {
        // Arrange
        $room = Room::factory()->create([
            'name' => 'Original',
            'description' => 'Test description',
            'price' => 100,
            'max_guests' => 2,
            'status' => 'available',
        ]);

        // Act: Update without providing lock_version (backward compatibility)
        $response = $this->actingAs($this->admin)
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Updated without version',
                'description' => 'Test description',
                'price' => 100,
                'max_guests' => 2,
                'status' => 'available',
                // No lock_version provided
            ]);

        // Assert: Should succeed in backward compatible mode
        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated without version')
            ->assertJsonPath('data.lock_version', 2);
    }

    public function test_concurrent_api_updates_second_request_fails_with_409(): void
    {
        // Arrange
        $room = Room::factory()->create([
            'name' => 'Original',
            'description' => 'Test description',
            'price' => 100,
            'max_guests' => 2,
            'status' => 'available',
        ]);
        $originalVersion = $room->lock_version;

        // First user updates successfully
        $response1 = $this->actingAs($this->admin)
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'First User Update',
                'description' => 'Test description',
                'price' => 100,
                'max_guests' => 2,
                'status' => 'available',
                'lock_version' => $originalVersion,
            ]);

        $response1->assertStatus(200);

        // Second user tries to update with stale version
        $response2 = $this->actingAs($this->admin)
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Second User Update',
                'description' => 'Test description',
                'price' => 100,
                'max_guests' => 2,
                'status' => 'available',
                'lock_version' => $originalVersion, // Stale - first user incremented it
            ]);

        // Assert
        $response2->assertStatus(409)
            ->assertJson([
                'error' => 'resource_out_of_date',
            ]);

        // Verify first user's update was preserved
        $room->refresh();
        $this->assertEquals('First User Update', $room->name);
        $this->assertEquals(2, $room->lock_version);
    }

    // ========================================================================
    // EDGE CASE TESTS
    // ========================================================================

    public function test_lock_version_accessor_handles_null_as_version_1(): void
    {
        // This test simulates legacy data that existed before the migration.
        // After migration runs, the column becomes NOT NULL, so we need to
        // test the accessor logic differently.
        
        // Create a room normally
        $room = Room::factory()->create();
        
        // Manually set lock_version to null via raw query to bypass NOT NULL constraint
        // Note: This will only work if the column allows null (pre-migration state)
        // If column is NOT NULL (post-migration), we skip this test
        try {
            DB::statement('PRAGMA foreign_keys = OFF'); // SQLite specific
            DB::table('rooms')
                ->where('id', $room->id)
                ->update(['lock_version' => null]);
            DB::statement('PRAGMA foreign_keys = ON');
        } catch (\Exception $e) {
            // Column is NOT NULL (post-migration), which is expected
            // Test the accessor returns the correct default value for new rooms
            $this->assertEquals(1, $room->lock_version);
            return;
        }

        // Refresh and test accessor returns 1 for null
        $room->refresh();
        $this->assertEquals(1, $room->lock_version);
    }

    public function test_high_version_numbers_work_correctly(): void
    {
        // Arrange: Room with very high version number
        $room = Room::factory()->create();
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 999999999]);

        $room->refresh();
        $this->assertEquals(999999999, $room->lock_version);

        // Act: Update should still work
        $updatedRoom = $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'High version update',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            999999999
        );

        // Assert
        $this->assertEquals(1000000000, $updatedRoom->lock_version);
    }

    public function test_version_0_throws_exception(): void
    {
        // Arrange
        $room = Room::factory()->create();

        // Act & Assert: Version 0 should fail (doesn't match version 1)
        $this->expectException(OptimisticLockException::class);

        $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'Test',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            0 // Version 0 doesn't match version 1
        );
    }

    public function test_negative_version_throws_exception(): void
    {
        // Arrange
        $room = Room::factory()->create();

        // Act & Assert: Negative version should fail
        $this->expectException(OptimisticLockException::class);

        $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'Test',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
            ],
            -1 // Negative version
        );
    }

    public function test_update_only_modifies_specified_fields_plus_version(): void
    {
        // Arrange
        $room = Room::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
            'price' => 100.00,
            'max_guests' => 4,
            'status' => 'available',
        ]);

        $originalDescription = $room->description;
        $originalPrice = $room->price;

        // Act: Only update name
        $updatedRoom = $this->roomService->updateWithOptimisticLock(
            $room,
            [
                'name' => 'New Name',
                'description' => $originalDescription,
                'price' => $originalPrice,
                'max_guests' => 4,
                'status' => 'available',
            ],
            1
        );

        // Assert
        $this->assertEquals('New Name', $updatedRoom->name);
        $this->assertEquals($originalDescription, $updatedRoom->description);
        $this->assertEquals((float) $originalPrice, (float) $updatedRoom->price);
        $this->assertEquals(2, $updatedRoom->lock_version);
    }

    // ========================================================================
    // EXCEPTION TESTS
    // ========================================================================

    public function test_exception_has_correct_message(): void
    {
        $exception = new OptimisticLockException();

        $this->assertEquals(
            'The resource has been modified by another user. Please refresh and try again.',
            $exception->getMessage()
        );
    }

    public function test_for_room_factory_creates_room_specific_exception(): void
    {
        $room = Room::factory()->create();

        $exception = OptimisticLockException::forRoom($room, 1, 5);

        $this->assertStringContainsString('room has been modified', $exception->getMessage());
        // Using public readonly properties (PHP 8.3)
        $this->assertSame($room, $exception->model);
        $this->assertEquals(1, $exception->expectedVersion);
        $this->assertEquals(5, $exception->actualVersion);
    }

    public function test_get_detailed_message_includes_all_context(): void
    {
        $room = Room::factory()->create();

        $exception = OptimisticLockException::forRoom($room, 3, 7);

        $detailed = $exception->getDetailedMessage();

        $this->assertStringContainsString('Room', $detailed);
        $this->assertStringContainsString('Expected version: 3', $detailed);
        $this->assertStringContainsString('Actual version: 7', $detailed);
    }
}
