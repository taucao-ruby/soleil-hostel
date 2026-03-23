<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add operational room classification fields for equivalence and upgrade routing.
 *
 * room_type_code is the swap-equivalence key.
 * room_tier is an operational comparability ladder, not pricing logic.
 * Both remain nullable until operators populate them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type_code', 50)
                ->nullable()
                ->after('room_number');
            $table->unsignedTinyInteger('room_tier')
                ->nullable()
                ->default(1)
                ->after('room_type_code');

            $table->index(['room_type_code', 'location_id'], 'idx_rooms_type_location');
            $table->index(['room_tier', 'location_id'], 'idx_rooms_tier_location');
            $table->index(['room_type_code', 'room_tier'], 'idx_rooms_type_tier');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            ALTER TABLE rooms ADD CONSTRAINT chk_rooms_room_tier_positive
            CHECK (room_tier IS NULL OR room_tier > 0)
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_room_tier_positive');
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_rooms_type_location');
            $table->dropIndex('idx_rooms_tier_location');
            $table->dropIndex('idx_rooms_type_tier');
            $table->dropColumn(['room_type_code', 'room_tier']);
        });
    }
};
