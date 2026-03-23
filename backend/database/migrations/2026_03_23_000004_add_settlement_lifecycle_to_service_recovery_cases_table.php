<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add operational settlement tracking to service recovery cases.
 *
 * settlement_status is not authoritative accounting/GL state.
 * It exists so PM/BM operators can track open financial exposure and closure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_recovery_cases', function (Blueprint $table) {
            $table->string('settlement_status')
                ->default('unsettled')
                ->after('cost_delta_absorbed');
            $table->unsignedBigInteger('settled_amount')
                ->nullable()
                ->after('settlement_status')
                ->comment('Settled amount in cents; operational tracking only');
            $table->timestamp('settled_at')
                ->nullable()
                ->after('settled_amount');
            $table->text('settlement_notes')
                ->nullable()
                ->after('settled_at');

            $table->index(['settlement_status', 'settled_at'], 'idx_src_settlement_status_settled_at');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_settlement_status
            CHECK (settlement_status IN (
                'unsettled', 'partially_settled', 'settled', 'written_off'
            ))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_recovery_cases DROP CONSTRAINT IF EXISTS chk_src_settlement_status');
        }

        Schema::table('service_recovery_cases', function (Blueprint $table) {
            $table->dropIndex('idx_src_settlement_status_settled_at');
            $table->dropColumn([
                'settlement_status',
                'settled_amount',
                'settled_at',
                'settlement_notes',
            ]);
        });
    }
};
