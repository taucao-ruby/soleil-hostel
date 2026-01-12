<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add unique constraint on booking_id to enforce one-review-per-booking.
     * 
     * This is the authoritative guard for uniqueness:
     * - Policy checks are advisory (pre-INSERT validation)
     * - DB constraint is transactional (catches race conditions)
     * 
     * If two concurrent requests pass the policy check, only one INSERT succeeds.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Add booking_id column if not exists (for booking-linked reviews)
            if (!Schema::hasColumn('reviews', 'booking_id')) {
                $table->unsignedBigInteger('booking_id')->nullable()->after('user_id');
                $table->index('booking_id');
            }

            // Unique constraint: one review per booking (nullable unique allows multiple NULLs)
            // This catches race conditions that policy cannot prevent
            $table->unique('booking_id', 'reviews_booking_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_booking_id_unique');
        });
    }
};
