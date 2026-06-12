<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F-89: deposit_events.booking_id was ON DELETE CASCADE, so a booking
 * hard-delete (admin force-delete, bookings:prune-deleted) silently destroyed
 * the deposit financial audit trail the table exists to preserve.
 *
 * Decision memo docs/DECISION_LEDGER_IMMUTABILITY_FK.md (D2, accepted
 * 2026-06-12): the ledger outlives the booking. Swap to ON DELETE RESTRICT,
 * matching the service_recovery_cases.booking_id precedent. Hard-deleting a
 * booking with deposit history now fails with SQLSTATE 23503; callers must
 * resolve the ledger first (see memo follow-ups #4/#5 for the prune job and
 * the admin force-delete UX).
 *
 * Postgres-only: SQLite has no ALTER TABLE ... DROP CONSTRAINT (same guard
 * idiom as 2026_04_29_000001). The default test harness runs on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE deposit_events DROP CONSTRAINT IF EXISTS deposit_events_booking_id_foreign');
        DB::statement('
            ALTER TABLE deposit_events
            ADD CONSTRAINT deposit_events_booking_id_foreign
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE deposit_events DROP CONSTRAINT IF EXISTS deposit_events_booking_id_foreign');
        DB::statement('
            ALTER TABLE deposit_events
            ADD CONSTRAINT deposit_events_booking_id_foreign
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
        ');
    }
};
