<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend chk_bookings_deposit_status to include the CONC-005 terminal states.
 *
 * Original (2026_03_23_000003): {none, collected, applied, refunded}
 * Extended:                    {none, collected, applied, refunded, partial_refund, forfeited}
 *
 * partial_refund / forfeited are required by CancellationService so that a
 * cancelled booking's deposit can never linger in 'collected' (held) state.
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

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS chk_bookings_deposit_status');

        DB::statement("
            ALTER TABLE bookings ADD CONSTRAINT chk_bookings_deposit_status
            CHECK (deposit_status IN ('none', 'collected', 'applied', 'refunded', 'partial_refund', 'forfeited'))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS chk_bookings_deposit_status');

        DB::statement("
            ALTER TABLE bookings ADD CONSTRAINT chk_bookings_deposit_status
            CHECK (deposit_status IN ('none', 'collected', 'applied', 'refunded'))
        ");
    }
};
