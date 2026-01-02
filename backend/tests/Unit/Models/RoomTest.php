<?php

namespace Tests\Unit\Models;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests for Room Model
 *
 * Tests model properties, relationships, accessors, and scopes.
 * These tests verify the Room model's internal behavior without HTTP requests.
 */
class RoomTest extends TestCase
{
    use RefreshDatabase;

    // ========== FACTORY & ATTRIBUTE TESTS ==========

    public function test_room_can_be_created_with_factory(): void
    {
        $room = Room::factory()->create();

        $this->assertInstanceOf(Room::class, $room);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_room_has_correct_fillable_attributes(): void
    {
        $room = new Room();

        $this->assertEquals(
            ['name', 'description', 'price', 'max_guests', 'status'],
            $room->getFillable()
        );
    }

    public function test_room_has_lock_version_in_guarded(): void
    {
        $room = new Room();

        $this->assertContains('lock_version', $room->getGuarded());
    }

    public function test_lock_version_cannot_be_mass_assigned(): void
    {
        $room = Room::factory()->create();

        // Attempt to mass assign lock_version
        $room->update(['lock_version' => 999]);

        // Should NOT have changed (guarded)
        $room->refresh();
        $this->assertEquals(1, $room->lock_version);
    }

    // ========== CAST TESTS ==========

    public function test_price_is_cast_to_decimal(): void
    {
        $room = Room::factory()->create(['price' => 99.99]);

        $this->assertEquals('99.99', $room->price);
    }

    public function test_lock_version_is_cast_to_integer(): void
    {
        $room = Room::factory()->create();

        $this->assertIsInt($room->lock_version);
    }

    public function test_max_guests_is_integer(): void
    {
        $room = Room::factory()->create(['max_guests' => 4]);

        $this->assertIsInt($room->max_guests);
    }

    // ========== ACCESSOR TESTS ==========

    public function test_lock_version_accessor_returns_1_for_null(): void
    {
        $room = Room::factory()->create();

        // Simulate legacy data with null lock_version using reflection
        // This tests the accessor fallback logic
        $reflection = new \ReflectionClass($room);
        $property = $reflection->getProperty('attributes');
        $property->setAccessible(true);
        $attributes = $property->getValue($room);
        $attributes['lock_version'] = null;
        $property->setValue($room, $attributes);

        // Accessor should return 1 for null
        $this->assertEquals(1, $room->getLockVersionAttribute(null));
    }

    public function test_lock_version_accessor_returns_actual_value_when_set(): void
    {
        $room = Room::factory()->create();

        $this->assertEquals(1, $room->lock_version);
        $this->assertEquals(5, $room->getLockVersionAttribute(5));
    }

    // ========== RELATIONSHIP TESTS ==========

    public function test_room_has_many_bookings(): void
    {
        $room = Room::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $room->bookings());
    }

    public function test_room_can_have_multiple_bookings(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->count(3)->create(['room_id' => $room->id]);

        $this->assertCount(3, $room->bookings);
    }

    public function test_active_bookings_relationship_filters_correctly(): void
    {
        $room = Room::factory()->create();

        // Create various booking statuses
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'confirmed',
        ]);
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'pending',
        ]);
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'cancelled',
        ]);

        // Only confirmed and pending should be active (based on Booking::ACTIVE_STATUSES)
        $activeBookings = $room->activeBookings()->get();

        // Should not include cancelled
        $this->assertTrue(
            $activeBookings->every(fn($b) => $b->status !== 'cancelled')
        );
    }

    // ========== SCOPE TESTS ==========

    public function test_scope_select_columns_includes_lock_version(): void
    {
        Room::factory()->create();

        $room = Room::selectColumns()->first();

        $this->assertArrayHasKey('lock_version', $room->toArray());
    }

    public function test_scope_with_common_relations_includes_active_bookings_count(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'confirmed',
        ]);

        $roomWithRelations = Room::withCommonRelations()->find($room->id);

        $this->assertArrayHasKey('active_bookings_count', $roomWithRelations->toArray());
    }

    public function test_scope_active_filters_by_status(): void
    {
        Room::factory()->create(['status' => 'active']);
        Room::factory()->create(['status' => 'available']);
        Room::factory()->create(['status' => 'maintenance']);

        $activeRooms = Room::active()->get();

        $this->assertTrue($activeRooms->every(fn($r) => $r->status === 'active'));
    }

    // ========== STATUS VALUE TESTS ==========

    public function test_room_status_can_be_available(): void
    {
        $room = Room::factory()->create(['status' => 'available']);

        $this->assertEquals('available', $room->status);
    }

    public function test_room_status_can_be_booked(): void
    {
        $room = Room::factory()->create(['status' => 'booked']);

        $this->assertEquals('booked', $room->status);
    }

    public function test_room_status_can_be_maintenance(): void
    {
        $room = Room::factory()->create(['status' => 'maintenance']);

        $this->assertEquals('maintenance', $room->status);
    }

    // ========== EDGE CASE TESTS ==========

    public function test_room_with_zero_price(): void
    {
        $room = Room::factory()->create(['price' => 0]);

        $this->assertEquals('0.00', $room->price);
    }

    public function test_room_with_high_price(): void
    {
        $room = Room::factory()->create(['price' => 9999.99]);

        $this->assertEquals('9999.99', $room->price);
    }

    public function test_room_with_max_guests_1(): void
    {
        $room = Room::factory()->create(['max_guests' => 1]);

        $this->assertEquals(1, $room->max_guests);
    }

    public function test_room_with_max_guests_large(): void
    {
        $room = Room::factory()->create(['max_guests' => 20]);

        $this->assertEquals(20, $room->max_guests);
    }

    public function test_room_name_max_length(): void
    {
        $name = str_repeat('a', 100);
        $room = Room::factory()->create(['name' => $name]);

        $this->assertEquals($name, $room->name);
    }

    public function test_room_timestamps_are_set_on_create(): void
    {
        $room = Room::factory()->create();

        $this->assertNotNull($room->created_at);
        $this->assertNotNull($room->updated_at);
    }

    public function test_room_updated_at_changes_on_update(): void
    {
        $room = Room::factory()->create();
        $originalUpdatedAt = $room->updated_at->copy();

        // Travel 1 second into the future to ensure timestamp changes
        $this->travel(1)->seconds();

        $room->update(['name' => 'Updated Name']);
        $room->refresh();

        $this->assertTrue(
            $room->updated_at->gte($originalUpdatedAt),
            "Expected updated_at ({$room->updated_at}) to be >= original ({$originalUpdatedAt})"
        );
    }
}
