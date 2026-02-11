<?php

namespace Tests\Traits;

use App\Exceptions\OptimisticLockException;
use Illuminate\Testing\TestResponse;

/**
 * Custom Assertion Helpers for Room Tests
 *
 * Provides reusable assertions for testing Room-related functionality,
 * especially optimistic locking scenarios.
 */
trait RoomTestAssertions
{
    /**
     * Assert that an OptimisticLockException was thrown.
     *
     * @param  callable  $callback  The code that should throw the exception
     * @param  int|null  $expectedVersion  The expected version (optional)
     * @param  int|null  $actualVersion  The actual version in DB (optional)
     */
    protected function assertOptimisticLockFailed(
        callable $callback,
        ?int $expectedVersion = null,
        ?int $actualVersion = null
    ): void {
        $exceptionThrown = false;

        try {
            $callback();
        } catch (OptimisticLockException $e) {
            $exceptionThrown = true;

            if ($expectedVersion !== null) {
                $this->assertEquals(
                    $expectedVersion,
                    $e->expectedVersion,
                    "Expected version mismatch: expected {$expectedVersion}, got {$e->expectedVersion}"
                );
            }

            if ($actualVersion !== null) {
                $this->assertEquals(
                    $actualVersion,
                    $e->actualVersion,
                    "Actual version mismatch: expected {$actualVersion}, got {$e->actualVersion}"
                );
            }
        }

        $this->assertTrue(
            $exceptionThrown,
            'Expected OptimisticLockException was not thrown'
        );
    }

    /**
     * Assert that a response is a 409 Conflict with optimistic lock error.
     */
    protected function assertConflictResponse(TestResponse $response): void
    {
        $response->assertStatus(409)
            ->assertJson([
                'error' => 'resource_out_of_date',
            ])
            ->assertJsonStructure([
                'message',
                'error',
            ]);
    }

    /**
     * Assert that the room was successfully created with lock_version = 1.
     */
    protected function assertRoomCreated(TestResponse $response): void
    {
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Room created successfully',
            ])
            ->assertJsonPath('data.lock_version', 1);
    }

    /**
     * Assert that the room was successfully updated with incremented lock_version.
     */
    protected function assertRoomUpdated(TestResponse $response, int $expectedNewVersion): void
    {
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Room updated successfully',
            ])
            ->assertJsonPath('data.lock_version', $expectedNewVersion);
    }

    /**
     * Assert that the room was successfully deleted.
     */
    protected function assertRoomDeleted(TestResponse $response): void
    {
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Room deleted successfully',
            ]);
    }

    /**
     * Assert proper room JSON structure in response.
     */
    protected function assertRoomJsonStructure(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'price',
                'max_guests',
                'status',
                'lock_version',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    /**
     * Assert room list response structure.
     */
    protected function assertRoomListStructure(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'max_guests',
                    'status',
                    'lock_version',
                ],
            ],
        ]);
    }

    /**
     * Assert validation error response.
     *
     * @param  array  $fields  Fields that should have validation errors
     */
    protected function assertValidationFailed(TestResponse $response, array $fields): void
    {
        $response->assertStatus(422)
            ->assertJsonValidationErrors($fields);
    }

    /**
     * Assert unauthorized response (guest access).
     */
    protected function assertUnauthorized(TestResponse $response): void
    {
        $response->assertStatus(401);
    }

    /**
     * Assert forbidden response (authenticated but not authorized).
     */
    protected function assertForbidden(TestResponse $response): void
    {
        $response->assertStatus(403);
    }

    /**
     * Assert room not found response.
     */
    protected function assertRoomNotFound(TestResponse $response): void
    {
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Room not found',
            ]);
    }
}
