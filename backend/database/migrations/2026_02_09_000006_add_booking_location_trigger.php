<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Add PostgreSQL trigger to auto-populate bookings.location_id
 *
 * Phase 3 cleanup: Database-level consistency guarantee.
 * When a booking is created or its room_id changes, automatically set
 * location_id from the room's location.
 *
 * This is a safety net complementing the application-level BookingObserver.
 * Only applies to PostgreSQL (SQLite used in tests doesn't support triggers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            CREATE OR REPLACE FUNCTION set_booking_location()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.location_id := (SELECT location_id FROM rooms WHERE id = NEW.room_id);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER trg_booking_set_location
            BEFORE INSERT OR UPDATE OF room_id ON bookings
            FOR EACH ROW EXECUTE FUNCTION set_booking_location();
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS trg_booking_set_location ON bookings');
        DB::statement('DROP FUNCTION IF EXISTS set_booking_location()');
    }
};
