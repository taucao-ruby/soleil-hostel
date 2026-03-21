<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add deposit tracking fields to bookings table.
 *
 * deposit_amount: advance/deposit collected at booking time, in cents (BIGINT).
 * deposit_collected_at: when the deposit was recorded.
 *
 * IMPORTANT: deposit_amount is operational tracking only — NOT recognized revenue.
 * Revenue recognition model is intentionally deferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('deposit_amount')
                ->default(0)
                ->after('amount')
                ->comment('Deposit/advance collected in cents — NOT recognized revenue');
            $table->timestamp('deposit_collected_at')
                ->nullable()
                ->after('deposit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'deposit_collected_at']);
        });
    }
};
