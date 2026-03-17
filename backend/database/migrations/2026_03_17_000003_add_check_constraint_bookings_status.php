<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add CHECK constraint: bookings.status must be a known status value.
 *
 * Values locked by App\Enums\BookingStatus backed enum:
 * pending, confirmed, refund_pending, cancelled, refund_failed
 *
 * Adding a new status requires a migration to update this constraint.
 * This is intentional: status changes are schema-level events, not ad-hoc.
 *
 * PostgreSQL only — SQLite does not support ALTER TABLE ADD CONSTRAINT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE bookings ADD CONSTRAINT chk_bookings_status
            CHECK (status IN ('pending', 'confirmed', 'refund_pending', 'cancelled', 'refund_failed'))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS chk_bookings_status');
    }
};
