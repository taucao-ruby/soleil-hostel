<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Assign existing rooms to default location + backfill bookings
 *
 * Phase 2 data migration:
 * 1. Assign all existing rooms to "Soleil Hostel" (location_id = 1)
 * 2. Backfill bookings.location_id from rooms.location_id
 * 3. Make rooms.location_id NOT NULL (enforce constraint)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Assign existing rooms to Soleil Hostel (default location)
        DB::table('rooms')
            ->whereNull('location_id')
            ->update(['location_id' => 1]);

        // Step 2: Backfill bookings.location_id from rooms
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE bookings b
                SET location_id = r.location_id
                FROM rooms r
                WHERE b.room_id = r.id
                AND b.location_id IS NULL
            ');
        } else {
            // SQLite/MySQL compatible syntax
            DB::statement('
                UPDATE bookings
                SET location_id = (
                    SELECT rooms.location_id 
                    FROM rooms 
                    WHERE rooms.id = bookings.room_id
                )
                WHERE bookings.location_id IS NULL
                AND bookings.room_id IS NOT NULL
            ');
        }

        // Step 3: Make rooms.location_id NOT NULL.
        //
        // F-68 fix (2026-04-19): Laravel's Schema->change() routes through
        // doctrine/dbal, which opens a secondary PostgreSQL connection to
        // introspect the column's current type before emitting ALTER TABLE.
        // That secondary connection races the primary connection — which
        // still holds RowExclusiveLock on rooms/bookings from the UPDATE
        // bookings FROM rooms statement above — for AccessExclusiveLock on
        // rooms, producing intermittent SQLSTATE[40P01]: Deadlock detected
        // during migrate:fresh and RefreshDatabase in tests.
        //
        // The column is already unsignedBigInteger from migration
        // 2026_02_09_000002:25, so no type coercion is required; only the
        // NOT NULL transition. A single-statement ALTER on the primary
        // connection eliminates the race without changing the end-state
        // schema. Non-pgsql drivers retain ->change() since the race is
        // PostgreSQL-lock-specific.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rooms ALTER COLUMN location_id SET NOT NULL');
        } else {
            Schema::table('rooms', function (Blueprint $table) {
                $table->unsignedBigInteger('location_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        // Make location_id nullable again. See F-68 note in up() for why
        // pgsql uses raw DDL instead of Schema->change().
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rooms ALTER COLUMN location_id DROP NOT NULL');
        } else {
            Schema::table('rooms', function (Blueprint $table) {
                $table->unsignedBigInteger('location_id')->nullable()->change();
            });
        }

        // Clear location assignments
        DB::table('rooms')->update(['location_id' => null]);
        DB::table('bookings')->update(['location_id' => null]);
    }
};
