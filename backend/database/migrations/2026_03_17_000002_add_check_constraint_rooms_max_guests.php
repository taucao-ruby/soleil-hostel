<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add CHECK constraint: rooms.max_guests > 0
 *
 * Business rule enforced at app layer (RoomRequest → 'max_guests' => 'required|integer|min:1')
 * but missing at DB level. This adds defense-in-depth.
 *
 * Data audit: max_guests is NOT NULL integer, factory default is 4.
 * Run preflight on prod before deploy:
 *   SELECT id, max_guests FROM rooms WHERE max_guests <= 0;
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

        DB::statement('ALTER TABLE rooms ADD CONSTRAINT chk_rooms_max_guests CHECK (max_guests > 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_max_guests');
    }
};
