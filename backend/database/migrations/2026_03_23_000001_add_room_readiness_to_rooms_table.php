<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add canonical physical room readiness tracking to rooms.
 *
 * This is the physical room state, distinct from:
 * - rooms.status (legacy sellable/admin availability semantics)
 * - bookings.status (commercial reservation state)
 * - stays.stay_status (guest operational lifecycle)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('readiness_status')
                ->default('ready')
                ->after('status');
            $table->timestamp('readiness_updated_at')
                ->nullable()
                ->after('readiness_status');
            $table->unsignedBigInteger('readiness_updated_by')
                ->nullable()
                ->after('readiness_updated_at');

            $table->index(['location_id', 'readiness_status'], 'idx_rooms_location_readiness');
            $table->index('readiness_status', 'idx_rooms_readiness_status');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->foreign('readiness_updated_by', 'fk_rooms_readiness_updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::statement("
            ALTER TABLE rooms ADD CONSTRAINT chk_rooms_readiness_status
            CHECK (readiness_status IN (
                'ready', 'occupied', 'dirty', 'cleaning', 'inspected', 'out_of_service'
            ))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_readiness_status');

            Schema::table('rooms', function (Blueprint $table) {
                $table->dropForeign('fk_rooms_readiness_updated_by');
            });
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_rooms_location_readiness');
            $table->dropIndex('idx_rooms_readiness_status');
            $table->dropColumn([
                'readiness_status',
                'readiness_updated_at',
                'readiness_updated_by',
            ]);
        });
    }
};
