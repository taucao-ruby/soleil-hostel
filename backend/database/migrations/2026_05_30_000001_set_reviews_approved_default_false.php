<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SH-09 / F-44: Align reviews.approved DB default with the Review model default.
 *
 * The original create_reviews_table migration shipped DEFAULT true (auto-approve),
 * while Review::$attributes defaults `approved` to false (safe moderation). A raw
 * INSERT that omits `approved` would therefore be auto-approved, bypassing
 * moderation. This additive migration makes the storage layer agree with the
 * model: new rows default to NOT approved.
 *
 * Additive-only: the shipped create migration is left untouched (migration-safety
 * rule). Existing rows are intentionally NOT backfilled — only the future default
 * changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reviews ALTER COLUMN approved SET DEFAULT false');

            return;
        }

        // Cross-driver fallback (e.g. SQLite local runs): rebuild the column default.
        Schema::table('reviews', function (Blueprint $table): void {
            $table->boolean('approved')->default(false)->comment('Admin-approved flag')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reviews ALTER COLUMN approved SET DEFAULT true');

            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->boolean('approved')->default(true)->comment('Admin-approved flag')->change();
        });
    }
};
