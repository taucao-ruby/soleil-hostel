<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harden FK delete policies to prevent accidental data destruction.
 *
 * Changes:
 * - bookings.user_id:  CASCADE → SET NULL  (booking history survives user deletion)
 * - bookings.room_id:  CASCADE → RESTRICT  (room deletion blocked if bookings exist)
 * - reviews.user_id:   CASCADE → SET NULL  (review survives user deletion)
 * - reviews.room_id:   CASCADE → SET NULL  (review survives room deletion)
 *
 * Rationale:
 * - CASCADE on bookings.room_id would destroy booking + review history when a room is deleted
 * - CASCADE on bookings.user_id would destroy booking records needed for financial audit
 * - CASCADE on reviews.user_id/room_id would silently destroy guest reviews
 * - reviews.booking_id → RESTRICT is already correct (set in 2026_02_22_000002)
 *
 * Prerequisite: bookings.user_id must be nullable for SET NULL.
 * The column was added as unsignedBigInteger (nullable) in 2025_11_18_000000.
 *
 * Guard: FKs from 2026_02_09_000000 were created on PG only.
 * This migration must also skip non-PG drivers (FKs don't exist to alter).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // --- bookings.user_id: CASCADE → SET NULL ---
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // --- bookings.room_id: CASCADE → RESTRICT ---
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->restrictOnDelete();
        });

        // --- reviews.user_id: CASCADE → SET NULL ---
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // --- reviews.room_id: CASCADE → SET NULL ---
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Restore original CASCADE policies from 2026_02_09_000000
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->cascadeOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->cascadeOnDelete();
        });
    }
};
