<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add FK constraint: reviews.booking_id → bookings.id (ON DELETE RESTRICT).
     *
     * ON DELETE RESTRICT chosen because bookings use soft-delete (deleted_at).
     * CASCADE would silently destroy reviews when a booking is soft-deleted.
     *
     * Preflight orphan query (run manually on prod before deploy):
     *   SELECT r.id, r.booking_id
     *   FROM reviews r
     *   LEFT JOIN bookings b ON b.id = r.booking_id
     *   WHERE b.id IS NULL;
     *
     * SQLite guard follows existing convention from 2026_02_09_000000.
     */
    public function up(): void
    {
        // Only add FK when NOT using SQLite (test environment)
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('booking_id', 'fk_reviews_booking_id')
                  ->references('id')
                  ->on('bookings')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign('fk_reviews_booking_id');
        });
    }
};
