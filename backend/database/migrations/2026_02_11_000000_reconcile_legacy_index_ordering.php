<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile index state from legacy migrations with non-standard chronology.
     *
     * This migration is intentionally idempotent and only adds missing indexes.
     * It avoids renaming historical migration files that may already exist in production.
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder();

        Schema::table('bookings', function (Blueprint $table) use ($schema) {
            if (! $schema->hasIndex('bookings', 'bookings_room_id_index')) {
                $table->index('room_id');
            }

            if (! $schema->hasIndex('bookings', 'bookings_user_id_index')) {
                $table->index('user_id');
            }

            if (! $schema->hasIndex('bookings', 'bookings_status_index')) {
                $table->index('status');
            }

            if (! $schema->hasIndex('bookings', 'bookings_room_id_check_in_check_out_index')) {
                $table->index(['room_id', 'check_in', 'check_out']);
            }

            if (! $schema->hasIndex('bookings', 'bookings_user_id_check_in_index')) {
                $table->index(['user_id', 'check_in']);
            }

            if (! $schema->hasIndex('bookings', 'bookings_status_check_out_index')) {
                $table->index(['status', 'check_out']);
            }
        });

        Schema::table('rooms', function (Blueprint $table) use ($schema) {
            if (! $schema->hasIndex('rooms', 'rooms_status_index')) {
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        // No-op: this migration only reconciles historical index state safely.
    }
};
