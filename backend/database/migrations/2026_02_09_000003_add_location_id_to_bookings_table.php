<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add location_id to bookings table
 *
 * Phase 1 of multi-location architecture upgrade.
 * Denormalized location_id on bookings for:
 * - Analytics performance: "Revenue per location" without JOINs
 * - Historical accuracy: If room moves to different location, booking keeps original
 * - Query simplification: WHERE location_id = X vs JOIN rooms
 *
 * Tradeoff: 8 bytes per booking row, consistency maintained via observer/trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('room_id');

            $table->foreign('location_id', 'fk_bookings_location')
                ->references('id')
                ->on('locations')
                ->onDelete('set null');

            // Indexes for location-based analytics
            $table->index('location_id', 'idx_bookings_location_id');
            $table->index(['location_id', 'check_in', 'check_out'], 'idx_bookings_location_dates');
            $table->index(['location_id', 'status'], 'idx_bookings_location_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign('fk_bookings_location');
            $table->dropIndex('idx_bookings_location_id');
            $table->dropIndex('idx_bookings_location_dates');
            $table->dropIndex('idx_bookings_location_status');
            $table->dropColumn('location_id');
        });
    }
};
