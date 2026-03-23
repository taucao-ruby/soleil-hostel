<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix reviews.room_id delete policy to match the non-nullable column definition.
 *
 * Source defect before this migration:
 * - reviews.room_id column is NOT NULL
 * - reviews.room_id FK was changed to SET NULL in 2026_03_17_000001
 *
 * RESTRICT is the safe policy here: room deletion must be blocked instead of
 * attempting a NULL write that the column definition cannot accept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->nullOnDelete();
        });
    }
};
