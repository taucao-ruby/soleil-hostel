<?php

/**
 * Migration: Add lock_version column for Optimistic Concurrency Control
 *
 * This migration adds a version column to the rooms table to prevent race conditions
 * during concurrent updates. The `lock_version` column is used to detect when another
 * user/process has modified the room between read and write operations.
 *
 * How it works:
 * 1. Client reads room with current lock_version (e.g., 5)
 * 2. Client submits update with lock_version = 5
 * 3. Server checks: UPDATE rooms SET ... WHERE id = ? AND lock_version = 5
 * 4. If rows affected = 0 → version mismatch → reject with 409 Conflict
 * 5. If rows affected = 1 → success → increment lock_version to 6
 *
 * @see App\Services\RoomService::updateWithOptimisticLock()
 * @see App\Exceptions\OptimisticLockException
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Production-safe migration for large tables:
     * 1. Add column as NULLABLE first (fast, no table lock on PostgreSQL 11+)
     * 2. Backfill existing rows with version 1
     * 3. Alter column to NOT NULL with default
     *
     * This approach avoids long-running table locks on large tables.
     */
    public function up(): void
    {
        // Step 1: Add nullable column (fast, minimal locking)
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('lock_version')
                ->nullable()
                ->after('status')
                ->comment('Optimistic locking version - increments on each update');
        });

        // Step 2: Backfill existing rows with version 1
        // Use chunking for very large tables to avoid memory issues
        DB::table('rooms')
            ->whereNull('lock_version')
            ->update(['lock_version' => 1]);

        // Step 3: Make column NOT NULL with default
        // This is safe now because all rows have a value
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('lock_version')
                ->default(1)
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });
    }
};
