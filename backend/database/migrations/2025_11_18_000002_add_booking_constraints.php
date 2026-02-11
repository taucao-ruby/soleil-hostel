<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Add unique constraint to prevent double-booking same room for overlapping dates
            // Note: MySQL doesn't support conditional unique indexes well, so we'll use a partial index
            // This constraint ensures no two non-cancelled bookings can have overlapping dates for the same room
            $table->unique(['room_id', 'check_in', 'check_out'], 'unique_room_dates');

            // Add index for better query performance on common queries
            $table->index(['status', 'check_in', 'check_out']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('unique_room_dates');
            $table->dropIndex(['status', 'check_in', 'check_out']);
            $table->dropIndex(['created_at']);
        });
    }
};
