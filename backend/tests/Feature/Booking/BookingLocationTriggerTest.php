<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BookingLocationTriggerTest — BL-5 regression harness.
 *
 * Proves the three-layer defense for the `bookings.location_id` denormalization
 * invariant, with explicit raw-SQL coverage that bypasses the Eloquent
 * BookingObserver and exercises the PostgreSQL trigger directly.
 *
 * Layers under test:
 *   1. App-set       — CreateBookingService writes location_id explicitly.
 *   2. Observer      — BookingObserver fills/restamps via Eloquent events.
 *   3. PG trigger    — trg_booking_set_location auto-repairs on INSERT/UPDATE OF room_id.
 *
 * Note on parity: SQLite has no equivalent trigger. The default test harness
 * (phpunit.xml) runs on PostgreSQL, so the PG-only tests below execute in CI.
 * The observer-level tests are driver-independent.
 */
#[\PHPUnit\Framework\Attributes\Group('booking')]
class BookingLocationTriggerTest extends TestCase
{
    private function pgsqlOnly(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'trg_booking_set_location is a PostgreSQL-only trigger '.
                '(migration 2026_02_09_000006 is guarded by DB::getDriverName() === "pgsql"). '.
                'Run against PostgreSQL — the default test driver per phpunit.xml.'
            );
        }
    }

    /** @test */
    public function pg_trigger_restamps_location_id_on_raw_insert_bypassing_eloquent(): void
    {
        $this->pgsqlOnly();

        $correctLocation = Location::factory()->create();
        $wrongLocation = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $correctLocation->id]);

        $now = Carbon::now();

        // Bypass Eloquent → bypasses BookingObserver. Only the PG trigger can save us here.
        $bookingId = DB::table('bookings')->insertGetId([
            'room_id' => $room->id,
            'location_id' => $wrongLocation->id, // deliberately wrong
            'check_in' => $now->copy()->addDays(1)->toDateString(),
            'check_out' => $now->copy()->addDays(3)->toDateString(),
            'guest_name' => 'Raw Insert Guest',
            'guest_email' => 'raw@example.test',
            'status' => BookingStatus::PENDING->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $persistedLocationId = DB::table('bookings')->where('id', $bookingId)->value('location_id');

        $this->assertSame(
            $correctLocation->id,
            (int) $persistedLocationId,
            'trg_booking_set_location must overwrite the app-supplied location_id '.
            'with rooms.location_id on raw INSERT (the trigger is auto-repair, not validation).'
        );
        $this->assertNotSame(
            $wrongLocation->id,
            (int) $persistedLocationId,
            'Drift detected: trigger did not restamp the wrong location_id.'
        );
    }

    /** @test */
    public function pg_trigger_restamps_location_id_on_raw_update_of_room_id(): void
    {
        $this->pgsqlOnly();

        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();
        $roomA = Room::factory()->create(['location_id' => $locationA->id]);
        $roomB = Room::factory()->create(['location_id' => $locationB->id]);

        // Seed via the normal Eloquent path so the row starts consistent.
        $booking = Booking::factory()->create(['room_id' => $roomA->id]);
        $this->assertSame($locationA->id, $booking->fresh()->location_id);

        // Raw UPDATE bypasses observer; only the PG trigger restamps because
        // the trigger is defined BEFORE UPDATE OF room_id.
        DB::table('bookings')
            ->where('id', $booking->id)
            ->update(['room_id' => $roomB->id]);

        $persistedLocationId = DB::table('bookings')->where('id', $booking->id)->value('location_id');

        $this->assertSame(
            $locationB->id,
            (int) $persistedLocationId,
            'trg_booking_set_location must restamp location_id when room_id changes '.
            'via raw UPDATE (Observer is bypassed; trigger is the root of trust).'
        );
    }

    /** @test */
    public function pg_trigger_fills_location_id_when_raw_insert_omits_it(): void
    {
        $this->pgsqlOnly();

        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id]);

        $now = Carbon::now();

        // Omit location_id entirely; trigger must fill from rooms.location_id.
        $bookingId = DB::table('bookings')->insertGetId([
            'room_id' => $room->id,
            'check_in' => $now->copy()->addDays(5)->toDateString(),
            'check_out' => $now->copy()->addDays(7)->toDateString(),
            'guest_name' => 'No Location Guest',
            'guest_email' => 'nolocation@example.test',
            'status' => BookingStatus::PENDING->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $persistedLocationId = DB::table('bookings')->where('id', $bookingId)->value('location_id');

        $this->assertSame(
            $location->id,
            (int) $persistedLocationId,
            'trg_booking_set_location must populate location_id from rooms.location_id '.
            'when the raw INSERT omits it.'
        );
    }

    /**
     * Observer-layer compensating control — driver-independent.
     *
     * Runs on any driver. On SQLite (no DB trigger) this is the only line of
     * defense for Eloquent writes that omit location_id; documenting that role.
     *
     * @test
     */
    public function observer_fills_location_id_when_eloquent_create_omits_it(): void
    {
        $location = Location::factory()->create();
        $room = Room::factory()->create(['location_id' => $location->id]);

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            // location_id intentionally not set — Observer (and PG trigger if available) must fill.
        ]);

        $this->assertSame(
            $location->id,
            $booking->fresh()->location_id,
            'BookingObserver::creating must populate location_id from room->location_id when missing.'
        );
    }

    /**
     * Observer-layer compensating control — driver-independent.
     *
     * @test
     */
    public function observer_restamps_location_id_on_eloquent_room_change(): void
    {
        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();
        $roomA = Room::factory()->create(['location_id' => $locationA->id]);
        $roomB = Room::factory()->create(['location_id' => $locationB->id]);

        $booking = Booking::factory()->create(['room_id' => $roomA->id]);
        $this->assertSame($locationA->id, $booking->fresh()->location_id);

        $booking->room_id = $roomB->id;
        $booking->save();

        $this->assertSame(
            $locationB->id,
            $booking->fresh()->location_id,
            'BookingObserver::updating must restamp location_id when room_id is dirty.'
        );
    }
}
