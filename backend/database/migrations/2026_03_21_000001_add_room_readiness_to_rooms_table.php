<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('readiness_status')->default('ready')->after('status');
            $table->timestampTz('readiness_changed_at')->nullable()->after('readiness_status');
            $table->unsignedBigInteger('readiness_changed_by')->nullable()->after('readiness_changed_at');
            $table->text('out_of_service_reason')->nullable()->after('readiness_changed_by');

            $table->index(['location_id', 'readiness_status'], 'idx_rooms_location_readiness_status');
            $table->index(['readiness_status', 'readiness_changed_at'], 'idx_rooms_readiness_status_changed_at');
        });

        Schema::create('room_readiness_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('stay_id')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestampTz('changed_at');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('reason')->nullable();

            $table->index(['room_id', 'changed_at'], 'idx_room_readiness_logs_room_changed_at');
        });

        DB::table('rooms')->whereNull('readiness_changed_at')->update([
            'readiness_changed_at' => now(),
        ]);

        DB::table('rooms')->where('status', 'maintenance')->update([
            'readiness_status' => 'out_of_service',
            'readiness_changed_at' => now(),
            'out_of_service_reason' => 'Backfilled from legacy room.status=maintenance during readiness migration.',
        ]);

        if (DB::getDriverName() === 'pgsql') {
            Schema::table('rooms', function (Blueprint $table) {
                $table->foreign('readiness_changed_by', 'fk_rooms_readiness_changed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            Schema::table('room_readiness_logs', function (Blueprint $table) {
                $table->foreign('room_id', 'fk_room_readiness_logs_room_id')
                    ->references('id')
                    ->on('rooms')
                    ->restrictOnDelete();

                $table->foreign('stay_id', 'fk_room_readiness_logs_stay_id')
                    ->references('id')
                    ->on('stays')
                    ->nullOnDelete();

                $table->foreign('changed_by', 'fk_room_readiness_logs_changed_by')
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

            DB::statement("
                ALTER TABLE room_readiness_logs ADD CONSTRAINT chk_room_readiness_logs_from_status
                CHECK (from_status IS NULL OR from_status IN (
                    'ready', 'occupied', 'dirty', 'cleaning', 'inspected', 'out_of_service'
                ))
            ");

            DB::statement("
                ALTER TABLE room_readiness_logs ADD CONSTRAINT chk_room_readiness_logs_to_status
                CHECK (to_status IN (
                    'ready', 'occupied', 'dirty', 'cleaning', 'inspected', 'out_of_service'
                ))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_readiness_status');
            DB::statement('ALTER TABLE room_readiness_logs DROP CONSTRAINT IF EXISTS chk_room_readiness_logs_from_status');
            DB::statement('ALTER TABLE room_readiness_logs DROP CONSTRAINT IF EXISTS chk_room_readiness_logs_to_status');

            Schema::table('rooms', function (Blueprint $table) {
                $table->dropForeign('fk_rooms_readiness_changed_by');
            });

            Schema::table('room_readiness_logs', function (Blueprint $table) {
                $table->dropForeign('fk_room_readiness_logs_room_id');
                $table->dropForeign('fk_room_readiness_logs_stay_id');
                $table->dropForeign('fk_room_readiness_logs_changed_by');
            });
        }

        Schema::dropIfExists('room_readiness_logs');

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_rooms_location_readiness_status');
            $table->dropIndex('idx_rooms_readiness_status_changed_at');
            $table->dropColumn([
                'readiness_status',
                'readiness_changed_at',
                'readiness_changed_by',
                'out_of_service_reason',
            ]);
        });
    }
};
