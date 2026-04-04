<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Tests\TestCase;

/**
 * LocationTest
 *
 * Tests for the Location model, relationships, constraints,
 * and the BookingObserver auto-population of location_id.
 */
class LocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear seeded locations from migration to ensure clean test state
        Location::query()->delete();
    }

    // ===== MODEL TESTS =====

    /** @test */
    public function it_creates_location_with_valid_data(): void
    {
        $location = Location::factory()->create([
            'name' => 'Test Hostel',
            'slug' => 'test-hostel',
            'total_rooms' => 5,
        ]);

        $this->assertDatabaseHas('locations', [
            'name' => 'Test Hostel',
            'slug' => 'test-hostel',
            'total_rooms' => 5,
        ]);
        $this->assertTrue($location->is_active);
    }

    /** @test */
    public function it_casts_amenities_as_array(): void
    {
        $location = Location::factory()->create([
            'amenities' => ['wifi', 'pool', 'parking'],
        ]);

        $location->refresh();
        $this->assertIsArray($location->amenities);
        $this->assertContains('wifi', $location->amenities);
        $this->assertContains('pool', $location->amenities);
    }

    /**
     * PR-3D: lock_version must be cast to integer.
     *
     * Without the cast, PostgreSQL drivers return lock_version as a string,
     * which breaks optimistic-locking CAS integer comparisons silently.
     * Room.php already has this cast; Location.php now matches.
     *
     * @test
     */
    public function it_casts_lock_version_as_integer(): void
    {
        $location = Location::factory()->create();
        $location->refresh();

        $this->assertIsInt($location->lock_version);
    }

    /** @test */
    public function it_returns_full_address_accessor(): void
    {
        $location = Location::factory()->create([
            'address' => '123 Main St',
            'ward' => 'Ward 1',
            'district' => 'District 7',
            'city' => 'Ho Chi Minh City',
            'postal_code' => '700000',
        ]);

        $this->assertStringContainsString('123 Main St', $location->full_address);
        $this->assertStringContainsString('Ho Chi Minh City', $location->full_address);
    }

    /** @test */
    public function it_returns_coordinates_when_both_set(): void
    {
        $location = Location::factory()->create([
            'latitude' => 16.4637,
            'longitude' => 107.5909,
        ]);

        $coords = $location->coordinates;
        $this->assertNotNull($coords);
        $this->assertArrayHasKey('lat', $coords);
        $this->assertArrayHasKey('lng', $coords);
    }

    /** @test */
    public function it_returns_null_coordinates_when_missing(): void
    {
        $location = Location::factory()->withoutCoordinates()->create();

        $this->assertNull($location->coordinates);
    }

    // ===== RELATIONSHIP TESTS =====

    /** @test */
    public function location_has_many_rooms(): void
    {
        $location = Location::factory()->create();
        Room::factory()->count(3)->create(['location_id' => $location->id]);

        $this->assertCount(3, $location->rooms);
    }

    /** @test */
    public function location_has_many_active_rooms(): void
    {
        $location = Location::factory()->create();
        Room::factory()->count(2)->create(['location_id' => $location->id, 'status' => 'available']);
        Room::factory()->create(['location_id' => $location->id, 'status' => 'maintenance']);

        $this->assertCount(2, $location->activeRooms);
    }

    /** @test */
    public function room_belongs_to_location(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id]);

        $this->assertEquals($location->id, $room->location->id);
    }

    // ===== SCOPE TESTS =====

    /** @test */
    public function active_scope_filters_inactive_locations(): void
    {
        Location::factory()->count(3)->create(['is_active' => true]);
        Location::factory()->create(['is_active' => false]);

        $activeLocations = Location::active()->get();
        $this->assertCount(3, $activeLocations);
    }

    /** @test */
    public function with_room_counts_scope_counts_all_rooms_as_available_when_no_bookings(): void
    {
        $location = Location::factory()->create();
        Room::factory()->count(5)->create(['location_id' => $location->id, 'status' => 'available']);
        Room::factory()->count(2)->create(['location_id' => $location->id, 'status' => 'maintenance']);

        $result = Location::withRoomCounts()->find($location->id);
        $this->assertEquals(7, $result->rooms_count);
        // All 7 rooms are available because no active bookings exist
        $this->assertEquals(7, $result->available_rooms_count);
    }

    /** @test */
    public function with_room_counts_scope_excludes_rooms_with_active_bookings_today(): void
    {
        $location = Location::factory()->create();
        $room1 = Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);
        $room2 = Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);
        $room3 = Room::factory()->create(['location_id' => $location->id, 'status' => 'maintenance']);

        $user = User::factory()->create();

        // Book room1 with dates overlapping today
        Booking::factory()->create([
            'room_id' => $room1->id,
            'user_id' => $user->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => 'confirmed',
        ]);

        $result = Location::withRoomCounts()->find($location->id);
        $this->assertEquals(3, $result->rooms_count);
        // room1 is booked today, room2 and room3 are available (regardless of status)
        $this->assertEquals(2, $result->available_rooms_count);
    }

    /** @test */
    public function with_room_counts_scope_ignores_cancelled_bookings(): void
    {
        $location = Location::factory()->create();
        Room::factory()->count(3)->create(['location_id' => $location->id]);

        $user = User::factory()->create();
        $room = $location->rooms->first();

        // Cancelled booking overlapping today should not block availability
        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => 'cancelled',
        ]);

        $result = Location::withRoomCounts()->find($location->id);
        $this->assertEquals(3, $result->rooms_count);
        $this->assertEquals(3, $result->available_rooms_count);
    }

    /** @test */
    public function with_room_counts_scope_ignores_soft_deleted_bookings(): void
    {
        $location = Location::factory()->create();
        Room::factory()->count(2)->create(['location_id' => $location->id]);

        $user = User::factory()->create();
        $room = $location->rooms->first();

        // Soft-deleted booking overlapping today should not block availability
        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => 'confirmed',
        ]);
        $booking->delete(); // soft-delete

        $result = Location::withRoomCounts()->find($location->id);
        $this->assertEquals(2, $result->rooms_count);
        $this->assertEquals(2, $result->available_rooms_count);
    }

    /** @test */
    public function with_room_counts_scope_ignores_future_bookings(): void
    {
        $location = Location::factory()->create();
        Room::factory()->count(2)->create(['location_id' => $location->id]);

        $user = User::factory()->create();
        $room = $location->rooms->first();

        // Future booking should not block today's availability
        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
            'status' => 'confirmed',
        ]);

        $result = Location::withRoomCounts()->find($location->id);
        $this->assertEquals(2, $result->rooms_count);
        $this->assertEquals(2, $result->available_rooms_count);
    }

    // ===== BOOKING OBSERVER TESTS =====

    /** @test */
    public function booking_auto_populates_location_id_on_create(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);
        $user = User::factory()->create();

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            // location_id intentionally not set
        ]);

        $this->assertEquals($location->id, $booking->fresh()->location_id);
    }

    /** @test */
    public function booking_updates_location_id_when_room_changes(): void
    {
        $location1 = Location::factory()->create();
        $location2 = Location::factory()->create();
        $room1 = Room::factory()->create(['location_id' => $location1->id, 'status' => 'available']);
        $room2 = Room::factory()->create(['location_id' => $location2->id, 'status' => 'available']);
        $user = User::factory()->create();

        $booking = Booking::factory()->create([
            'room_id' => $room1->id,
            'user_id' => $user->id,
        ]);

        $this->assertEquals($location1->id, $booking->fresh()->location_id);

        // Change room to a different location
        $booking->room_id = $room2->id;
        $booking->save();

        $this->assertEquals($location2->id, $booking->fresh()->location_id);
    }

    // ===== ROOM SCOPE TESTS =====

    /** @test */
    public function room_at_location_scope_filters_by_location(): void
    {
        $location1 = Location::factory()->create();
        $location2 = Location::factory()->create();

        Room::factory()->count(3)->create(['location_id' => $location1->id]);
        Room::factory()->count(2)->create(['location_id' => $location2->id]);

        $this->assertCount(3, Room::atLocation($location1->id)->get());
        $this->assertCount(2, Room::atLocation($location2->id)->get());
    }

    /** @test */
    public function room_available_between_scope_excludes_booked_rooms(): void
    {
        $location = Location::factory()->create();
        $room1 = Room::factory()->create([
            'location_id' => $location->id,
            'status' => 'available',
        ]);
        $room2 = Room::factory()->create([
            'location_id' => $location->id,
            'status' => 'available',
        ]);

        // Book room1 for March 1-5
        Booking::factory()->create([
            'room_id' => $room1->id,
            'check_in' => '2026-03-01',
            'check_out' => '2026-03-05',
            'status' => 'confirmed',
        ]);

        // Query: Available at location for overlapping dates (March 3-7)
        $available = Room::where('location_id', $location->id)
            ->availableBetween('2026-03-03', '2026-03-07')
            ->get();

        $this->assertCount(1, $available);
        $this->assertEquals($room2->id, $available->first()->id);
    }

    /** @test */
    public function room_available_between_allows_same_day_checkout_checkin(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create([
            'location_id' => $location->id,
            'status' => 'available',
        ]);

        // Book room for March 1-5
        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in' => '2026-03-01',
            'check_out' => '2026-03-05',
            'status' => 'confirmed',
        ]);

        // Query: Available March 5-10 (check-in on check-out day = OK with half-open)
        $available = Room::availableBetween('2026-03-05', '2026-03-10')->get();

        $this->assertCount(1, $available);
        $this->assertEquals($room->id, $available->first()->id);
    }

    /** @test */
    public function room_display_name_includes_room_number(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create([
            'location_id' => $location->id,
            'name' => 'Deluxe Room',
            'room_number' => '101',
        ]);

        $this->assertEquals('Deluxe Room (#101)', $room->display_name);
    }

    /** @test */
    public function room_display_name_without_room_number(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create([
            'location_id' => $location->id,
            'name' => 'Suite Room',
            'room_number' => null,
        ]);

        $this->assertEquals('Suite Room', $room->display_name);
    }
}
