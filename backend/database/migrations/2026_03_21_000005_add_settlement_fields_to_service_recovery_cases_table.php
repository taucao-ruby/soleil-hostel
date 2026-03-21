<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add settlement lifecycle fields to service_recovery_cases.
 *
 * settlement_status: tracks financial discharge of compensation obligation.
 * settled_at: when settlement was completed or waived.
 * settled_amount: amount actually paid out (in cents, BIGINT).
 * settlement_notes: reason for partial/waived settlement.
 *
 * Settlement is distinct from resolved_at (operational resolution).
 * A case can be operationally resolved but financially unsettled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_recovery_cases', function (Blueprint $table) {
            $table->string('settlement_status')
                ->default('unsettled')
                ->after('escalated_at');
            $table->timestamp('settled_at')
                ->nullable()
                ->after('settlement_status');
            $table->unsignedBigInteger('settled_amount')
                ->nullable()
                ->after('settled_at')
                ->comment('Amount settled in cents');
            $table->text('settlement_notes')
                ->nullable()
                ->after('settled_amount');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_settlement_status
                CHECK (settlement_status IN (
                    'unsettled', 'partially_settled', 'settled', 'waived'
                ))
            ");
        }

        // Backfill existing records
        DB::table('service_recovery_cases')
            ->whereNull('settlement_status')
            ->update(['settlement_status' => 'unsettled']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_recovery_cases DROP CONSTRAINT IF EXISTS chk_src_settlement_status');
        }

        Schema::table('service_recovery_cases', function (Blueprint $table) {
            $table->dropColumn(['settlement_status', 'settled_at', 'settled_amount', 'settlement_notes']);
        });
    }
};
