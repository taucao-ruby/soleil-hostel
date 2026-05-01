<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Batch 4 / 3B — rooms.status deprecation, phase 3 of 3.
 *
 * Goals:
 *   1. Backfill readiness_status from the legacy status column for any rows
 *      where readiness_status was left null (defensive — the 2026-03-23
 *      migration already defaulted to 'ready', but we cannot assume every
 *      production row was created via the same path).
 *   2. Narrow the rooms.status valid-value set to {'available','unavailable'}
 *      via a DB CHECK constraint so future writes cannot reintroduce ad-hoc
 *      string values during the transition window.
 *
 * scopeBookable() (Batch 1) remains the canonical bookability predicate. It
 * still consults rooms.status='available'; this constraint just guarantees
 * the column carries one of the two values it was ever supposed to.
 *
 * Postgres-only — driver guards keep the SQLite/MySQL test paths working.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: data backfill (idempotent — runs only on rows still null).
        DB::statement("
            UPDATE rooms
            SET readiness_status = 'ready'
            WHERE status = 'available' AND readiness_status IS NULL
        ");

        DB::statement("
            UPDATE rooms
            SET readiness_status = 'out_of_service'
            WHERE (status IS NULL OR status <> 'available') AND readiness_status IS NULL
        ");

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Step 2: narrow rooms.status to {'available','unavailable'} during transition.
        // Drop first if a previous attempt failed mid-flight (idempotent).
        DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS rooms_status_deprecated');
        DB::statement("
            ALTER TABLE rooms
            ADD CONSTRAINT rooms_status_deprecated
            CHECK (status IN ('available', 'unavailable'))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS rooms_status_deprecated');
    }
};
