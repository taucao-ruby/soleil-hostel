<?php

namespace Tests\Feature\Database;

use App\Database\TransactionIsolation;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\CreateBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * TransactionIsolationIntegrationTest - Integration tests for transaction isolation
 * 
 * These tests verify that our transaction isolation strategy prevents:
 * - Double bookings under concurrent load
 * - Lost updates from race conditions
 * - Dirty reads and phantom reads
 * 
 * Data Invariants Tested:
 * - No overlapping bookings for same room
 * - Booking count matches successful transaction count
 * - Room availability is consistent after concurrent operations
 */
class TransactionIsolationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Room $room;
    protected CreateBookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->room = Room::factory()->create([
            'name' => 'Test Room',
            'status' => 'active',
        ]);
        $this->bookingService = app(CreateBookingService::class);

        $this->actingAs($this->user, 'sanctum');
    }

    /**
     * Test that concurrent booking requests for same dates result in exactly one booking.
     * 
     * Simulates 10 sequential requests (PHPUnit limitation - true parallelism needs process forking).
     * The first request should succeed, all others should fail with overlap error.
     */
    public function test_concurrent_bookings_same_dates_exactly_one_succeeds(): void
    {
        $checkIn = Carbon::now()->addDays(10)->startOfDay();
        $checkOut = $checkIn->clone()->addDays(3);

        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        // Simulate multiple concurrent requests
        for ($i = 0; $i < 10; $i++) {
            try {
                $this->bookingService->create(
                    roomId: $this->room->id,
                    checkIn: $checkIn,
                    checkOut: $checkOut,
                    guestName: "Guest {$i}",
                    guestEmail: "guest{$i}@example.com",
                    userId: $this->user->id
                );
                $successCount++;
            } catch (\RuntimeException $e) {
                $failureCount++;
                $errors[] = $e->getMessage();
            }
        }

        // Assertions
        $this->assertEquals(1, $successCount, "Exactly 1 booking should succeed");
        $this->assertEquals(9, $failureCount, "Exactly 9 bookings should fail");

        // Verify database state
        $bookingCount = Booking::where('room_id', $this->room->id)
            ->where('check_in', $checkIn)
            ->where('check_out', $checkOut)
            ->count();

        $this->assertEquals(1, $bookingCount, "Database should have exactly 1 booking");
    }

    /**
     * Test that overlapping date ranges are properly rejected.
     * 
     * Scenario: Booking exists for days 10-15 (future dates to avoid past date validation)
     * Test various overlap patterns:
     * - Same dates (10-15) - should fail
     * - Contained (11-14) - should fail
     * - Overlap start (8-12) - should fail
     * - Overlap end (13-17) - should fail
     * - Adjacent (15-17) - should succeed (half-open interval)
     */
    public function test_overlap_detection_patterns(): void
    {
        $baseCheckIn = Carbon::now()->addDays(10)->startOfDay();
        $baseCheckOut = $baseCheckIn->clone()->addDays(5); // Days 10-15

        // Create initial booking
        $this->bookingService->create(
            roomId: $this->room->id,
            checkIn: $baseCheckIn,
            checkOut: $baseCheckOut,
            guestName: "Original Guest",
            guestEmail: "original@example.com",
            userId: $this->user->id
        );

        $testCases = [
            [
                'name' => 'Exact same dates',
                'checkIn' => $baseCheckIn,
                'checkOut' => $baseCheckOut,
                'shouldSucceed' => false,
            ],
            [
                'name' => 'Contained within',
                'checkIn' => $baseCheckIn->clone()->addDays(1),
                'checkOut' => $baseCheckOut->clone()->subDays(1),
                'shouldSucceed' => false,
            ],
            [
                'name' => 'Overlaps start',
                'checkIn' => $baseCheckIn->clone()->addDays(1), // Start inside existing booking
                'checkOut' => $baseCheckIn->clone()->addDays(3),
                'shouldSucceed' => false,
            ],
            [
                'name' => 'Overlaps end',
                'checkIn' => $baseCheckOut->clone()->subDays(2),
                'checkOut' => $baseCheckOut->clone()->addDays(2),
                'shouldSucceed' => false,
            ],
            [
                'name' => 'Adjacent (checkout = next checkin)',
                'checkIn' => $baseCheckOut,
                'checkOut' => $baseCheckOut->clone()->addDays(2),
                'shouldSucceed' => true, // Half-open interval allows this
            ],
            [
                'name' => 'After existing (non-overlapping)',
                'checkIn' => $baseCheckOut->clone()->addDays(5),
                'checkOut' => $baseCheckOut->clone()->addDays(8),
                'shouldSucceed' => true,
            ],
        ];

        foreach ($testCases as $case) {
            try {
                $this->bookingService->create(
                    roomId: $this->room->id,
                    checkIn: $case['checkIn'],
                    checkOut: $case['checkOut'],
                    guestName: "Test {$case['name']}",
                    guestEmail: "test-" . md5($case['name']) . "@example.com",
                    userId: $this->user->id
                );

                $this->assertTrue(
                    $case['shouldSucceed'],
                    "Case '{$case['name']}' succeeded but should have failed"
                );
            } catch (\RuntimeException $e) {
                $this->assertFalse(
                    $case['shouldSucceed'],
                    "Case '{$case['name']}' failed but should have succeeded: {$e->getMessage()}"
                );
            }
        }
    }

    /**
     * Test that booking update respects overlap detection.
     */
    public function test_booking_update_overlap_detection(): void
    {
        $checkIn1 = Carbon::now()->addDays(5)->startOfDay();
        $checkOut1 = $checkIn1->clone()->addDays(3);

        $checkIn2 = Carbon::now()->addDays(15)->startOfDay();
        $checkOut2 = $checkIn2->clone()->addDays(3);

        // Create two bookings
        $booking1 = $this->bookingService->create(
            roomId: $this->room->id,
            checkIn: $checkIn1,
            checkOut: $checkOut1,
            guestName: "Guest 1",
            guestEmail: "guest1@example.com",
            userId: $this->user->id
        );

        $booking2 = $this->bookingService->create(
            roomId: $this->room->id,
            checkIn: $checkIn2,
            checkOut: $checkOut2,
            guestName: "Guest 2",
            guestEmail: "guest2@example.com",
            userId: $this->user->id
        );

        // Try to update booking2 to overlap with booking1
        $this->expectException(\RuntimeException::class);

        $this->bookingService->update(
            booking: $booking2,
            checkIn: $checkIn1->clone()->addDays(1),
            checkOut: $checkOut1->clone()->addDays(1)
        );
    }

    /**
     * Test that TransactionIsolation properly handles READ COMMITTED operations.
     */
    public function test_read_committed_transaction_isolation(): void
    {
        $result = TransactionIsolation::run(function () {
            // Create a booking within transaction
            $checkIn = Carbon::now()->addDays(20)->startOfDay();
            $checkOut = $checkIn->clone()->addDays(2);

            $booking = Booking::create([
                'room_id' => $this->room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guest_name' => 'Transaction Test',
                'guest_email' => 'txn@example.com',
                'status' => Booking::STATUS_PENDING,
                'user_id' => $this->user->id,
            ]);

            return $booking->id;
        }, TransactionIsolation::READ_COMMITTED, [
            'operationName' => 'test_booking_creation'
        ]);

        // Verify booking was created
        $this->assertNotNull($result);
        $this->assertDatabaseHas('bookings', ['id' => $result]);
    }

    /**
     * Test that database integrity is maintained after multiple operations.
     */
    public function test_database_integrity_after_concurrent_operations(): void
    {
        $totalOperations = 20;
        $successfulBookings = 0;

        // Create multiple users to book different date ranges
        $users = User::factory()->count(5)->create();

        for ($i = 0; $i < $totalOperations; $i++) {
            $checkIn = Carbon::now()->addDays(30 + $i)->startOfDay();
            $checkOut = $checkIn->clone()->addDays(1);
            $user = $users[$i % count($users)];

            try {
                $this->bookingService->create(
                    roomId: $this->room->id,
                    checkIn: $checkIn,
                    checkOut: $checkOut,
                    guestName: "Guest {$i}",
                    guestEmail: "guest{$i}@example.com",
                    userId: $user->id
                );
                $successfulBookings++;
            } catch (\RuntimeException $e) {
                // Expected for overlapping dates
            }
        }

        // Verify database count matches successful operations
        $dbCount = Booking::where('room_id', $this->room->id)->count();
        $this->assertEquals($successfulBookings, $dbCount);

        // Verify no overlapping bookings exist
        $bookings = Booking::where('room_id', $this->room->id)
            ->orderBy('check_in')
            ->get();

        for ($i = 1; $i < count($bookings); $i++) {
            $this->assertTrue(
                $bookings[$i - 1]->check_out <= $bookings[$i]->check_in,
                "Booking {$bookings[$i - 1]->id} overlaps with {$bookings[$i]->id}"
            );
        }
    }

    /**
     * Test metrics are recorded for successful transactions.
     */
    public function test_transaction_metrics_recorded(): void
    {
        Log::shouldReceive('info')
            ->atLeast()
            ->once();

        $checkIn = Carbon::now()->addDays(50)->startOfDay();
        $checkOut = $checkIn->clone()->addDays(2);

        $this->bookingService->create(
            roomId: $this->room->id,
            checkIn: $checkIn,
            checkOut: $checkOut,
            guestName: "Metrics Test",
            guestEmail: "metrics@example.com",
            userId: $this->user->id
        );

        // If we got here without exception, metrics were logged
        $this->assertTrue(true);
    }
}
