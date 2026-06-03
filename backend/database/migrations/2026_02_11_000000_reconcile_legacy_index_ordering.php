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
        $schema = Schema::connection($this->getConnection())->getConnection()->getSchemaBuilder();

        // Reverse only the indexes this migration actually creates on the
        // canonical migration chain. up() guards every add with hasIndex(); the
        // single-column room_id/user_id/status indexes, the [user_id, check_in]
        // composite, and rooms.status already exist by the time this migration
        // runs — created by create_bookings_table, add_user_id_to_bookings, and
        // add_nplusone_fix_indexes — so up() skips them and they are owned (and
        // torn down) by those migrations. The only indexes up() recreates here
        // are the two that optimize_booking_indexes had dropped. Dropping the
        // pre-existing ones would clobber indexes this migration never created.
        // Each drop is hasIndex-guarded so down() stays idempotent.
        Schema::table('bookings', function (Blueprint $table) use ($schema) {
            if ($schema->hasIndex('bookings', 'bookings_status_check_out_index')) {
                $table->dropIndex('bookings_status_check_out_index');
            }

            if ($schema->hasIndex('bookings', 'bookings_room_id_check_in_check_out_index')) {
                $table->dropIndex('bookings_room_id_check_in_check_out_index');
            }
        });
    }
};
