<?php

use Tests\TestCase;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;

class NPlusOneQueriesTest extends TestCase
{
    /**
     * Assert that no N+1 queries occur during booking index
     */
    public function test_booking_index_no_nplusone_queries(): void
    {
        // Create test data
        $user = User::factory()->create();
        $rooms = Room::factory(3)->create();

        // Create bookings for all rooms by same user
        foreach ($rooms as $room) {
            Booking::factory(2)->create([
                'room_id' => $room->id,
                'user_id' => $user->id,
            ]);
        }

        // Login and count queries
        $token = $user->createToken('test-token');
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);

        $this->assertQueryCount(function () {
            $this->getJson('/api/bookings')->assertOk();
        }, expectedCount: 3, tolerance: 1); // 1 for bookings + 1 for rooms + 1 for users
    }

    /**
     * Assert that no N+1 queries occur during room index
     */
    public function test_room_index_no_nplusone_queries(): void
    {
        // Create test data
        $rooms = Room::factory(5)->create();

        // Create bookings for all rooms
        foreach ($rooms as $room) {
            Booking::factory(3)->create(['room_id' => $room->id]);
        }

        $this->assertQueryCount(function () {
            $this->getJson('/api/rooms')->assertOk();
        }, expectedCount: 2, tolerance: 1); // 1 for rooms + 1 for active_bookings count
    }

    /**
     * Assert that room show doesn't trigger N+1
     */
    public function test_room_show_no_nplusone_queries(): void
    {
        $room = Room::factory()->create();
        Booking::factory(5)->create(['room_id' => $room->id]);

        $roomId = $room->id;
        $this->assertQueryCount(function () use ($roomId) {
            $this->getJson("/api/rooms/{$roomId}")->assertOk();
        }, expectedCount: 2, tolerance: 1); // 1 for room + 1 for active_bookings count
    }

    /**
     * Assert that booking show doesn't trigger N+1
     */
    public function test_booking_show_no_nplusone_queries(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token');
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);

        $bookingId = $booking->id;
        $this->assertQueryCount(function () use ($bookingId) {
            $this->getJson("/api/bookings/{$bookingId}")->assertOk();
        }, expectedCount: 3, tolerance: 1); // 1 for booking + 1 for room + 1 for user
    }

    /**
     * Assert that creating booking triggers only necessary queries
     */
    public function test_create_booking_optimal_queries(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $token = $user->createToken('test-token');
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);

        $roomId = $room->id;
        $this->assertQueryCount(function () use ($roomId) {
            $this->postJson('/api/bookings', [
                'room_id' => $roomId,
                'check_in' => now()->addDay()->format('Y-m-d'),
                'check_out' => now()->addDays(3)->format('Y-m-d'),
                'guest_name' => 'Test Guest',
                'guest_email' => 'test@example.com',
            ])->assertCreated();
        }, expectedCount: 10, tolerance: 3); // Service transaction + overlap check + insert
    }

    /**
     * Assert that updating booking triggers only necessary queries
     */
    public function test_update_booking_optimal_queries(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token');
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);

        $bookingId = $booking->id;
        $this->assertQueryCount(function () use ($bookingId) {
            $this->putJson("/api/bookings/{$bookingId}", [
                'check_in' => now()->addDays(5)->format('Y-m-d'),
                'check_out' => now()->addDays(7)->format('Y-m-d'),
            ])->assertOk();
        }, expectedCount: 10, tolerance: 3); // Service transaction + overlap check + update
    }

    /**
     * Assert that deleting booking triggers only necessary queries
     */
    public function test_delete_booking_optimal_queries(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token');
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);

        $bookingId = $booking->id;
        $this->assertQueryCount(function () use ($bookingId) {
            $this->deleteJson("/api/bookings/{$bookingId}")->assertNoContent();
        }, expectedCount: 3, tolerance: 1); // Find + Delete + Event dispatch
    }

    /**
     * Helper: Assert total query count stays within expected range
     */
    private function assertQueryCount(callable $callback, int $expectedCount, int $tolerance = 0): void
    {
        $queryCount = 0;
        $listener = function ($query) use (&$queryCount) {
            $queryCount++;
        };

        \Illuminate\Support\Facades\DB::listen($listener);

        $callback();

        \Illuminate\Support\Facades\DB::flushQueryListeners();

        $min = $expectedCount - $tolerance;
        $max = $expectedCount + $tolerance;

        $this->assertGreaterThanOrEqual(
            $min,
            $queryCount,
            "Expected query count {$expectedCount} but got {$queryCount} (too few)"
        );

        $this->assertLessThanOrEqual(
            $max,
            $queryCount,
            "Expected query count {$expectedCount} but got {$queryCount} (too many - possible N+1)"
        );
    }
}
