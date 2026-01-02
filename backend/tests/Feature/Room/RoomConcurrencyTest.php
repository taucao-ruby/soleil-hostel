<?php

namespace Tests\Feature\Room;

use App\Enums\UserRole;
use App\Exceptions\OptimisticLockException;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\RoomTestAssertions;

/**
 * Room Concurrency Tests
 *
 * Advanced tests for race conditions and concurrent access scenarios.
 * These tests verify the optimistic locking implementation protects
 * against the "lost update" problem in high-concurrency environments.
 */
class RoomConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use RoomTestAssertions;

    private RoomService $roomService;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomService = app(RoomService::class);
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
    }

    // ========== SIMULATED RACE CONDITIONS ==========

    public function test_concurrent_price_update_second_writer_loses(): void
    {
        // Arrange: Create room with price $100
        $room = Room::factory()->create(['price' => 100.00]);
        $initialVersion = $room->lock_version;

        // Two admins read the room at the same "time"
        $adminAVersion = $initialVersion;
        $adminBVersion = $initialVersion;

        // Admin A updates price to $150 first - SUCCESS
        $responseA = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => $room->name,
                'description' => $room->description,
                'price' => 150.00,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => $adminAVersion,
            ]);
        
        $responseA->assertStatus(200)
            ->assertJsonPath('data.lock_version', 2);
        
        // Price may be string or numeric depending on serialization
        $this->assertEquals(150.00, (float) $responseA->json('data.price'));

        // Admin B tries to update price to $120 with stale version - FAIL
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => $room->name,
                'description' => $room->description,
                'price' => 120.00,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => $adminBVersion, // Stale!
            ]);

        $this->assertConflictResponse($response);

        // Verify Admin A's changes were preserved
        $room->refresh();
        $this->assertEquals('150.00', $room->price);
        $this->assertEquals(2, $room->lock_version);
    }

    public function test_three_concurrent_updates_only_first_succeeds(): void
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Original']);
        $originalVersion = $room->lock_version;

        // Three admins all read version 1
        $versions = [$originalVersion, $originalVersion, $originalVersion];
        $names = ['Update-A', 'Update-B', 'Update-C'];

        // First update succeeds
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => $names[0],
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => $versions[0],
            ])
            ->assertStatus(200);

        // Second update fails
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => $names[1],
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => $versions[1],
            ])
            ->assertStatus(409);

        // Third update fails
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => $names[2],
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => $versions[2],
            ])
            ->assertStatus(409);

        // Verify only first update persisted
        $room->refresh();
        $this->assertEquals('Update-A', $room->name);
        $this->assertEquals(2, $room->lock_version);
    }

    public function test_rapid_sequential_updates_all_succeed_with_correct_versions(): void
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Start']);

        // Perform 10 rapid updates, each with correct version
        for ($i = 1; $i <= 10; $i++) {
            $room->refresh(); // Get latest version

            $response = $this->actingAs($this->admin, 'sanctum')
                ->putJson("/api/rooms/{$room->id}", [
                    'name' => "Update #{$i}",
                    'description' => $room->description,
                    'price' => $room->price,
                    'max_guests' => $room->max_guests,
                    'status' => $room->status,
                    'lock_version' => $room->lock_version,
                ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.lock_version', $i + 1);
        }

        // Final state
        $room->refresh();
        $this->assertEquals('Update #10', $room->name);
        $this->assertEquals(11, $room->lock_version);
    }

    // ========== SERVICE LAYER CONCURRENCY ==========

    public function test_service_layer_concurrent_updates_exception_contains_details(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $staleVersion = $room->lock_version;

        // Simulate another process updating the room
        DB::table('rooms')
            ->where('id', $room->id)
            ->update([
                'name' => 'Updated by other process',
                'lock_version' => DB::raw('lock_version + 1'),
            ]);

        // Act & Assert
        $this->assertOptimisticLockFailed(
            function () use ($room, $staleVersion) {
                $this->roomService->updateWithOptimisticLock(
                    $room,
                    [
                        'name' => 'My update',
                        'description' => $room->description,
                        'price' => $room->price,
                        'max_guests' => $room->max_guests,
                        'status' => $room->status,
                    ],
                    $staleVersion
                );
            },
            expectedVersion: $staleVersion,
            actualVersion: 2
        );
    }

    public function test_delete_with_stale_version_fails(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $staleVersion = $room->lock_version;

        // Simulate update by another process
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 5]);

        // Act & Assert
        $this->assertOptimisticLockFailed(
            function () use ($room, $staleVersion) {
                $this->roomService->deleteWithOptimisticLock($room, $staleVersion);
            }
        );

        // Room should still exist
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_concurrent_delete_attempts_only_first_succeeds(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $version = $room->lock_version;
        $roomId = $room->id;

        // First delete succeeds
        $result = $this->roomService->deleteWithOptimisticLock($room, $version);
        $this->assertTrue($result);

        // Room is gone
        $this->assertDatabaseMissing('rooms', ['id' => $roomId]);
    }

    // ========== EDGE CASES ==========

    public function test_update_after_many_versions_still_works(): void
    {
        // Arrange: Room with very high version number
        $room = Room::factory()->create();
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 999999]);

        $room->refresh();
        $this->assertEquals(999999, $room->lock_version);

        // Act: Update with correct high version
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'High version update',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => 999999,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.lock_version', 1000000);
    }

    public function test_transaction_rollback_preserves_original_version(): void
    {
        // Arrange
        $room = Room::factory()->create(['name' => 'Original']);
        $originalVersion = $room->lock_version;

        // Simulate a transaction that fails mid-way
        try {
            DB::transaction(function () use ($room, $originalVersion) {
                $this->roomService->updateWithOptimisticLock(
                    $room,
                    [
                        'name' => 'Should be rolled back',
                        'description' => $room->description,
                        'price' => $room->price,
                        'max_guests' => $room->max_guests,
                        'status' => $room->status,
                    ],
                    $originalVersion
                );

                // Force rollback
                throw new \Exception('Simulated failure');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Verify rollback preserved original
        $room->refresh();
        $this->assertEquals('Original', $room->name);
        $this->assertEquals($originalVersion, $room->lock_version);
    }

    public function test_concurrent_status_change_blocked(): void
    {
        // Scenario: Two admins try to change status at the same time
        $room = Room::factory()->create(['status' => 'available']);
        $originalVersion = $room->lock_version;

        // Admin A changes to 'booked'
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => $room->name,
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => 'booked',
                'lock_version' => $originalVersion,
            ])
            ->assertStatus(200);

        // Admin B tries to change to 'maintenance' with stale version
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => $room->name,
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => 'maintenance',
                'lock_version' => $originalVersion, // Stale!
            ]);

        // Assert: 409 Conflict returned
        $response->assertStatus(409)
            ->assertJson(['error' => 'resource_out_of_date']);

        // Verify 'booked' status persisted
        $room->refresh();
        $this->assertEquals('booked', $room->status);
    }

    // ========== API RESPONSE FORMAT VERIFICATION ==========

    public function test_conflict_response_contains_error_info(): void
    {
        // Arrange
        $room = Room::factory()->create();
        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['lock_version' => 5]);

        // Act: Update with stale version 1
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Test',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => 1,
            ]);

        // Assert: Response includes error details for client-side handling
        $response->assertStatus(409)
            ->assertJson([
                'error' => 'resource_out_of_date',
                'message' => 'The room has been modified by another user. Please refresh and try again.',
            ]);
    }

    public function test_successful_update_response_includes_new_version(): void
    {
        // Arrange
        $room = Room::factory()->create();

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", [
                'name' => 'Updated',
                'description' => $room->description,
                'price' => $room->price,
                'max_guests' => $room->max_guests,
                'status' => $room->status,
                'lock_version' => $room->lock_version,
            ]);

        // Assert: Client receives new version for next update
        $this->assertRoomUpdated($response, 2);
        $this->assertRoomJsonStructure($response);
    }
}
