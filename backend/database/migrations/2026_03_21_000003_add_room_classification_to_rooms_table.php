<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add room_type_code and room_tier to rooms table for operational comparison.
 *
 * room_type_code: canonical room category for equivalence matching.
 * room_tier: ordinal tier (1=budget, 2=standard, 3=suite) for upgrade comparison.
 *
 * Backfill: existing rooms default to private_single / tier 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type_code')->nullable()->after('status');
            $table->unsignedTinyInteger('room_tier')->nullable()->after('room_type_code');
        });

        // Backfill existing rows with safe defaults
        DB::table('rooms')->whereNull('room_type_code')->update([
            'room_type_code' => 'private_single',
            'room_tier' => 2,
        ]);

        // Now make NOT NULL
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type_code')->nullable(false)->change();
            $table->unsignedTinyInteger('room_tier')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE rooms ADD CONSTRAINT chk_rooms_room_type_code
                CHECK (room_type_code IN (
                    'dormitory', 'private_single', 'private_double',
                    'private_twin', 'private_suite'
                ))
            ");

            DB::statement('
                ALTER TABLE rooms ADD CONSTRAINT chk_rooms_room_tier
                CHECK (room_tier IN (1, 2, 3))
            ');
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->index(
                ['location_id', 'room_type_code', 'room_tier', 'readiness_status'],
                'idx_rooms_classification_availability'
            );
            $table->index(
                ['room_type_code', 'room_tier'],
                'idx_rooms_type_tier'
            );
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_rooms_classification_availability');
            $table->dropIndex('idx_rooms_type_tier');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_room_type_code');
            DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_room_tier');
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['room_type_code', 'room_tier']);
        });
    }
};
