<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Add location_id to rooms table
 *
 * Phase 1 of multi-location architecture upgrade.
 * Associates rooms with physical locations.
 *
 * Design decisions:
 * - NULLABLE initially: Allows zero-downtime migration (existing rooms have no location)
 * - ON DELETE RESTRICT: Prevent accidental location deletion if rooms exist
 * - Composite indexes for common queries: "available rooms at location X"
 * - Unique constraint on (location_id, room_number): Room 101 can exist at multiple locations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('id');
            $table->string('room_number', 50)->nullable()->after('name');

            $table->foreign('location_id', 'fk_rooms_location')
                ->references('id')
                ->on('locations')
                ->onDelete('restrict');

            // Indexes for location-based queries
            $table->index('location_id', 'idx_rooms_location_id');
            $table->index(['location_id', 'status'], 'idx_rooms_location_status');
            $table->index(['location_id', 'price'], 'idx_rooms_location_price');
        });

        // Partial unique index: room_number unique per location (only when room_number is NOT NULL)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE UNIQUE INDEX idx_rooms_location_room_number 
                ON rooms (location_id, room_number) 
                WHERE room_number IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign('fk_rooms_location');
            $table->dropIndex('idx_rooms_location_id');
            $table->dropIndex('idx_rooms_location_status');
            $table->dropIndex('idx_rooms_location_price');
            $table->dropColumn(['location_id', 'room_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_rooms_location_room_number');
        }
    }
};
