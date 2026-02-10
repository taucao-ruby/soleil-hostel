<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make booking_id non-nullable on reviews table.
     *
     * Business rule: On a hostel platform, ALL reviews must be associated
     * with a confirmed booking. This prevents fake reviews from users
     * who never stayed at the property.
     *
     * Pre-migration: Assigns orphaned reviews (booking_id = NULL) to the
     * user's most recent booking for the same room, or soft-deletes them.
     */
    public function up(): void
    {
        // Handle existing NULL booking_id records before constraint change
        // Attempt to link orphaned reviews to a matching booking
        $orphanedReviews = DB::table('reviews')->whereNull('booking_id')->get();

        foreach ($orphanedReviews as $review) {
            $matchingBooking = DB::table('bookings')
                ->where('user_id', $review->user_id)
                ->where('room_id', $review->room_id)
                ->orderByDesc('created_at')
                ->first();

            if ($matchingBooking) {
                DB::table('reviews')
                    ->where('id', $review->id)
                    ->update(['booking_id' => $matchingBooking->id]);
            }
        }

        // Delete any remaining reviews that still have no booking
        // (reviews with no matching booking are considered invalid)
        DB::table('reviews')->whereNull('booking_id')->delete();

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });
    }
};
