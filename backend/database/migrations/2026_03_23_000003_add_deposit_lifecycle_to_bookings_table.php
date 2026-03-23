<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add booking deposit lifecycle tracking.
 *
 * deposit_amount is an advance/deposit collected at booking time and remains
 * unearned revenue / liability until the stay is fulfilled.
 * This migration adds operational visibility only; it does not implement GL logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('deposit_amount')
                ->nullable()
                ->after('amount')
                ->comment('Advance/deposit in cents; operational liability tracking only');
            $table->timestamp('deposit_collected_at')
                ->nullable()
                ->after('deposit_amount');
            $table->string('deposit_status')
                ->default('none')
                ->after('deposit_collected_at');

            $table->index(['deposit_status', 'check_in'], 'idx_bookings_deposit_status_check_in');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE bookings ADD CONSTRAINT chk_bookings_deposit_status
            CHECK (deposit_status IN ('none', 'collected', 'applied', 'refunded'))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS chk_bookings_deposit_status');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_deposit_status_check_in');
            $table->dropColumn([
                'deposit_amount',
                'deposit_collected_at',
                'deposit_status',
            ]);
        });
    }
};
