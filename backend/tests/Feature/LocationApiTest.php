<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Tests\TestCase;

/**
 * LocationApiTest
 *
 * Tests for the Location API endpoints:
 * - GET /api/v1/locations
 * - GET /api/v1/locations/{slug}
 * - GET /api/v1/locations/{slug}/availability
 * - GET /api/v1/rooms?location_id=X
 */
class LocationApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear seeded locations from migration to ensure clean test state
        Location::query()->delete();
    }

    // ===== GET /api/v1/locations =====

    /** @test */
    public function it_lists_active_locations(): void
    {
        Location::factory()->count(3)->create(['is_active' => true]);
        Location::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_includes_room_counts_in_listing(): void
    {
        $location = Location::factory()->create(['is_active' => true]);
        Room::factory()->count(5)->create(['location_id' => $location->id, 'status' => 'available']);
        Room::factory()->count(2)->create(['location_id' => $location->id, 'status' => 'maintenance']);

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk()
            ->assertJsonPath('data.0.stats.rooms_count', 7)
            ->assertJsonPath('data.0.stats.available_rooms', 5);
    }

    /** @test */
    public function it_returns_locations_ordered_by_name(): void
    {
        Location::factory()->create(['name' => 'Zebra Hostel', 'is_active' => true]);
        Location::factory()->create(['name' => 'Alpha Hostel', 'is_active' => true]);

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha Hostel', 'Zebra Hostel'], $names);
    }

    /** @test */
    public function it_returns_proper_location_structure(): void
    {
        Location::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'address' => ['full', 'street', 'city'],
                        'contact' => ['phone', 'email'],
                        'amenities',
                        'stats' => ['total_rooms'],
                        'is_active',
                    ],
                ],
            ]);
    }

    // ===== GET /api/v1/locations/{slug} =====

    /** @test */
    public function it_shows_location_by_slug(): void
    {
        $location = Location::factory()->create([
            'slug' => 'test-hostel',
            'is_active' => true,
        ]);
        Room::factory()->count(3)->create([
            'location_id' => $location->id,
            'status' => 'available',
        ]);

        $response = $this->getJson('/api/v1/locations/test-hostel');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'test-hostel')
            ->assertJsonCount(3, 'data.rooms');
    }

    /** @test */
    public function it_returns_404_for_inactive_location(): void
    {
        Location::factory()->create([
            'slug' => 'closed-hostel',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/locations/closed-hostel');

        $response->assertNotFound();
    }

    /** @test */
    public function it_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/v1/locations/does-not-exist');

        $response->assertNotFound();
    }

    /** @test */
    public function it_filters_rooms_by_availability_dates(): void
    {
        $location = Location::factory()->create(['slug' => 'test-hostel', 'is_active' => true]);
        $room1 = Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);
        $room2 = Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);

        // Book room1 for specific dates
        Booking::factory()->create([
            'room_id' => $room1->id,
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-05',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson('/api/v1/locations/test-hostel?check_in=2026-06-03&check_out=2026-06-07');

        $response->assertOk()
            ->assertJsonCount(1, 'data.rooms');

        $roomIds = collect($response->json('data.rooms'))->pluck('id')->toArray();
        $this->assertContains($room2->id, $roomIds);
        $this->assertNotContains($room1->id, $roomIds);
    }

    /** @test */
    public function it_filters_rooms_by_guest_capacity(): void
    {
        $location = Location::factory()->create(['slug' => 'test-hostel', 'is_active' => true]);
        Room::factory()->create(['location_id' => $location->id, 'status' => 'available', 'max_guests' => 2]);
        Room::factory()->create(['location_id' => $location->id, 'status' => 'available', 'max_guests' => 4]);
        Room::factory()->create(['location_id' => $location->id, 'status' => 'available', 'max_guests' => 1]);

        $response = $this->getJson('/api/v1/locations/test-hostel?guests=3');

        $response->assertOk()
            ->assertJsonCount(1, 'data.rooms');
    }

    // ===== GET /api/v1/locations/{slug}/availability =====

    /** @test */
    public function it_checks_availability_at_location(): void
    {
        $location = Location::factory()->create(['slug' => 'test-hostel', 'is_active' => true]);
        Room::factory()->count(3)->create(['location_id' => $location->id, 'status' => 'available']);

        $response = $this->getJson('/api/v1/locations/test-hostel/availability?check_in=2026-06-01&check_out=2026-06-05');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'location',
                    'available_rooms',
                    'total_available',
                ],
            ])
            ->assertJsonPath('data.total_available', 3);
    }

    /** @test */
    public function availability_requires_dates(): void
    {
        Location::factory()->create(['slug' => 'test-hostel', 'is_active' => true]);

        $response = $this->getJson('/api/v1/locations/test-hostel/availability');

        $response->assertUnprocessable();
    }

    /** @test */
    public function availability_rejects_past_dates(): void
    {
        Location::factory()->create(['slug' => 'test-hostel', 'is_active' => true]);

        $response = $this->getJson('/api/v1/locations/test-hostel/availability?check_in=2020-01-01&check_out=2020-01-05');

        $response->assertUnprocessable();
    }

    /** @test */
    public function availability_rejects_checkout_before_checkin(): void
    {
        Location::factory()->create(['slug' => 'test-hostel', 'is_active' => true]);

        $response = $this->getJson('/api/v1/locations/test-hostel/availability?check_in=2026-06-10&check_out=2026-06-05');

        $response->assertUnprocessable();
    }

    // ===== GET /api/v1/rooms?location_id=X =====

    /** @test */
    public function it_filters_rooms_by_location_id(): void
    {
        $location1 = Location::factory()->create();
        $location2 = Location::factory()->create();
        Room::factory()->count(3)->create(['location_id' => $location1->id, 'status' => 'available']);
        Room::factory()->count(2)->create(['location_id' => $location2->id, 'status' => 'available']);

        $response = $this->getJson("/api/v1/rooms?location_id={$location1->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_returns_all_rooms_without_location_filter(): void
    {
        $location1 = Location::factory()->create();
        $location2 = Location::factory()->create();
        Room::factory()->count(3)->create(['location_id' => $location1->id, 'status' => 'available']);
        Room::factory()->count(2)->create(['location_id' => $location2->id, 'status' => 'available']);

        $response = $this->getJson('/api/v1/rooms');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function rooms_endpoint_includes_location_info(): void
    {
        $location = Location::factory()->create(['name' => 'Test Hostel', 'slug' => 'test-hostel']);
        Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);

        $response = $this->getJson('/api/v1/rooms');

        $response->assertOk()
            ->assertJsonPath('data.0.location.name', 'Test Hostel')
            ->assertJsonPath('data.0.location.slug', 'test-hostel');
    }

    /** @test */
    public function rooms_endpoint_supports_date_availability_filter(): void
    {
        $location = Location::factory()->create();
        $room1 = Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);
        $room2 = Room::factory()->create(['location_id' => $location->id, 'status' => 'available']);

        Booking::factory()->create([
            'room_id' => $room1->id,
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-05',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson('/api/v1/rooms?check_in=2026-06-03&check_out=2026-06-07');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function room_show_endpoint_includes_active_booking_count(): void
    {
        $location = Location::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        $room = Room::factory()->create([
            'location_id' => $location->id,
            'status' => 'available',
        ]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-05',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson("/api/v1/rooms/{$room->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $room->id)
            ->assertJsonPath('data.active_bookings_count', 1);
    }
}
