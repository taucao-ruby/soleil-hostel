<?php

namespace Tests\Feature\Stays;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Room assignment tests — active assignment lookups and partial unique index.
 *
 * Covers:
 * - Active assignment lookup via scopeActive / currentRoomAssignment
 * - Second active assignment rejected by partial unique index (PostgreSQL only)
 * - Closing an active assignment allows creating a new one
 * - Assignment type CHECK constraint (PostgreSQL only)
 */
class RoomAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function isPgsql(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    // ===== ACTIVE ASSIGNMENT LOOKUP =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_active_assignment_lookup_via_scope(): void
    {
        $stay = Stay::factory()->inHouse()->create();
        $activeAssignment = RoomAssignment::factory()->forStay($stay)->active()->create();
        RoomAssignment::factory()->forStay($stay)->closed()->create();

        $active = RoomAssignment::active()->where('stay_id', $stay->id)->get();

        $this->assertCount(1, $active);
        $this->assertEquals($activeAssignment->id, $active->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_current_room_assignment_relation_returns_active(): void
    {
        $stay = Stay::factory()->inHouse()->create();
        $activeAssignment = RoomAssignment::factory()->forStay($stay)->active()->create();

        $stay->refresh();
        $current = $stay->currentRoomAssignment;

        $this->assertNotNull($current);
        $this->assertEquals($activeAssignment->id, $current->id);
        $this->assertNull($current->assigned_until);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_current_room_assignment_is_null_when_no_active_assignment(): void
    {
        $stay = Stay::factory()->inHouse()->create();
        RoomAssignment::factory()->forStay($stay)->closed()->create();

        $stay->refresh();

        $this->assertNull($stay->currentRoomAssignment);
    }

    // ===== PARTIAL UNIQUE INDEX (PostgreSQL only) =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_second_active_assignment_for_same_stay_fails(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Partial unique index udx_room_assignments_one_active_per_stay requires PostgreSQL');
        }

        $stay = Stay::factory()->inHouse()->create();
        RoomAssignment::factory()->forStay($stay)->active()->create();

        $this->expectException(QueryException::class);

        // Second active assignment (assigned_until = null) for same stay must be rejected
        RoomAssignment::factory()->forStay($stay)->active()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_closing_active_assignment_allows_new_active(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('Partial unique index udx_room_assignments_one_active_per_stay requires PostgreSQL');
        }

        $stay = Stay::factory()->inHouse()->create();
        $first = RoomAssignment::factory()->forStay($stay)->active()->create();

        // Close the first assignment
        $first->update([
            'assigned_until' => Carbon::now(),
            'assignment_status' => AssignmentStatus::CLOSED,
        ]);

        // Now a new active assignment should succeed
        $second = RoomAssignment::factory()->forStay($stay)->active()->create();

        $this->assertNull($second->assigned_until);
        $this->assertDatabaseHas('room_assignments', ['id' => $second->id, 'assigned_until' => null]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_multiple_closed_assignments_allowed_for_same_stay(): void
    {
        $stay = Stay::factory()->checkedOut()->create();

        // Multiple closed assignments (history) must be allowed — partial index only guards NULL
        RoomAssignment::factory()->forStay($stay)->closed()->create();
        RoomAssignment::factory()->forStay($stay)->closed()->create();
        RoomAssignment::factory()->forStay($stay)->closed()->create();

        $this->assertCount(3, RoomAssignment::where('stay_id', $stay->id)->get());
    }

    // ===== ASSIGNMENT TYPE CONSTRAINT =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_valid_assignment_type_accepted(): void
    {
        $stay = Stay::factory()->inHouse()->create();

        $assignment = RoomAssignment::factory()->forStay($stay)->create([
            'assignment_type' => AssignmentType::COMPLIMENTARY_UPGRADE,
        ]);

        $this->assertDatabaseHas('room_assignments', [
            'id' => $assignment->id,
            'assignment_type' => 'complimentary_upgrade',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invalid_assignment_type_rejected_by_check_constraint(): void
    {
        if (! $this->isPgsql()) {
            $this->markTestSkipped('CHECK constraints require PostgreSQL');
        }

        $stay = Stay::factory()->inHouse()->create();
        $room = Room::factory()->create();

        $this->expectException(QueryException::class);

        // Bypass Eloquent enum cast with raw DB insert to exercise the DB CHECK constraint
        DB::table('room_assignments')->insert([
            'booking_id' => $stay->booking_id,
            'stay_id' => $stay->id,
            'room_id' => $room->id,
            'assignment_type' => 'invalid_type',
            'assignment_status' => 'active',
            'assigned_from' => now()->toDateTimeString(),
            'assigned_until' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    // ===== RELATIONSHIPS =====

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_assignment_belongs_to_stay_booking_and_room(): void
    {
        $room = Room::factory()->create();
        $booking = Booking::factory()->confirmed()->create();
        $stay = Stay::factory()->forBooking($booking)->inHouse()->create();

        $assignment = RoomAssignment::factory()->create([
            'stay_id' => $stay->id,
            'booking_id' => $booking->id,
            'room_id' => $room->id,
        ]);

        $this->assertEquals($stay->id, $assignment->stay->id);
        $this->assertEquals($booking->id, $assignment->booking->id);
        $this->assertEquals($room->id, $assignment->room->id);
    }
}
