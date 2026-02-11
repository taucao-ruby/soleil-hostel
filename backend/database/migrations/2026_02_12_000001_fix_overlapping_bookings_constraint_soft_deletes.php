<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix exclusion constraint to exclude soft-deleted bookings.
     *
     * The original constraint only checked status but didn't account for
     * soft-deleted records (deleted_at IS NOT NULL), causing false conflicts
     * when creating bookings for dates previously held by soft-deleted bookings.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS no_overlapping_bookings');

        DB::statement("
            ALTER TABLE bookings
            ADD CONSTRAINT no_overlapping_bookings
            EXCLUDE USING gist (
                room_id WITH =,
                daterange(check_in, check_out, '[)') WITH &&
            )
            WHERE (status IN ('pending', 'confirmed') AND deleted_at IS NULL)
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS no_overlapping_bookings');

        DB::statement("
            ALTER TABLE bookings
            ADD CONSTRAINT no_overlapping_bookings
            EXCLUDE USING gist (
                room_id WITH =,
                daterange(check_in, check_out, '[)') WITH &&
            )
            WHERE (status IN ('pending', 'confirmed'))
        ");
    }
};
