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
            // ========== FOREIGN KEY INDEXES ==========
            // For with('room') eager loading relationship
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('bookings', 'bookings_room_id_index')) {
                $table->index('room_id');
            }

            // For where('user_id', auth()->id()) filter
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('bookings', 'bookings_user_id_index')) {
                $table->index('user_id');
            }

            // ========== FILTER INDEXES ==========
            // For whereIn('status', STATUS_LIST) queries
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('bookings', 'bookings_status_index')) {
                $table->index('status');
            }

            // ========== COMPOSITE INDEXES ==========
            // For overlappingBookings(room_id, check_in, check_out) scope - CRITICAL for availability check
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('bookings', 'bookings_room_id_check_in_check_out_index')) {
                $table->index(['room_id', 'check_in', 'check_out']);
            }

            // For date range queries on user bookings
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('bookings', 'bookings_user_id_check_in_index')) {
                $table->index(['user_id', 'check_in']);
            }

            // For active bookings with date range (overlapping with current date)
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('bookings', 'bookings_status_check_out_index')) {
                $table->index(['status', 'check_out']);
            }
        });

        Schema::table('rooms', function (Blueprint $table) {
            // For filtering by room status
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('rooms', 'rooms_status_index')) {
                $table->index('status');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // For with('bookings') eager loading
            if (!Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder()->hasIndex('users', 'users_email_index')) {
                // Email index already exists for login, but ensures it's present
                $table->index('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndexIfExists('bookings_room_id_index');
            $table->dropIndexIfExists('bookings_user_id_index');
            $table->dropIndexIfExists('bookings_status_index');
            $table->dropIndexIfExists('bookings_room_id_check_in_check_out_index');
            $table->dropIndexIfExists('bookings_user_id_check_in_index');
            $table->dropIndexIfExists('bookings_status_check_out_index');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndexIfExists('rooms_status_index');
        });

        Schema::table('users', function (Blueprint $table) {
            // Keep email index as it's needed for auth
        });
    }
};
