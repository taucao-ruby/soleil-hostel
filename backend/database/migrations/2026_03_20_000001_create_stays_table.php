<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create stays table — operational occupancy lifecycle per booking.
 *
 * Separation of concerns:
 * - bookings.status = commercial reservation state (pending/confirmed/cancelled/...)
 * - stays.stay_status = operational occupancy lifecycle (expected/in_house/checked_out/...)
 *
 * One stay per booking (UNIQUE booking_id). A booking without a stay record
 * is a pre-arrival booking; a stay is created when the guest is operationally
 * tracked (e.g., day of arrival).
 *
 * FK policy:
 * - stays.booking_id → RESTRICT: stay is part of booking history; cannot orphan.
 * - stays.checked_in_by / checked_out_by → SET NULL: staff record survives user deletion.
 *
 * CHECK constraints are PostgreSQL only (SQLite does not support ALTER TABLE ADD CONSTRAINT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stays', function (Blueprint $table) {
            $table->id();

            // booking_id: NOT NULL UNIQUE — one stay per booking
            $table->unsignedBigInteger('booking_id');
            $table->unique('booking_id', 'stays_booking_id_unique');

            // Operational status — varchar for portability (same pattern as bookings.status)
            $table->string('stay_status')->default('expected');

            // Scheduled times (from booking dates — may be null if stay pre-dates this column)
            $table->timestamp('scheduled_check_in_at')->nullable();
            $table->timestamp('scheduled_check_out_at')->nullable();

            // Actual times recorded at front desk
            $table->timestamp('actual_check_in_at')->nullable();
            $table->timestamp('actual_check_out_at')->nullable();

            // Late checkout tracking
            $table->integer('late_checkout_minutes')->default(0);
            $table->unsignedBigInteger('late_checkout_fee_amount')->nullable()->comment('Late checkout fee in cents');

            // No-show timestamp
            $table->timestamp('no_show_at')->nullable();

            // Staff who performed the actions (nullable — SET NULL on user deletion)
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->unsignedBigInteger('checked_out_by')->nullable();

            $table->timestamps();

            // Indexes for front-desk queries
            $table->index('stay_status', 'idx_stays_stay_status');
            $table->index('scheduled_check_in_at', 'idx_stays_scheduled_check_in_at');
            $table->index('scheduled_check_out_at', 'idx_stays_scheduled_check_out_at');
        });

        // FK constraints — PostgreSQL only (SQLite test isolation; parallel migrations)
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('stays', function (Blueprint $table) {
                $table->foreign('booking_id', 'fk_stays_booking_id')
                    ->references('id')
                    ->on('bookings')
                    ->restrictOnDelete();

                $table->foreign('checked_in_by', 'fk_stays_checked_in_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign('checked_out_by', 'fk_stays_checked_out_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            // CHECK constraint: stay_status must be a known value
            DB::statement("
                ALTER TABLE stays ADD CONSTRAINT chk_stays_stay_status
                CHECK (stay_status IN (
                    'expected', 'in_house', 'late_checkout', 'checked_out',
                    'no_show', 'relocated_internal', 'relocated_external'
                ))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('stays', function (Blueprint $table) {
                $table->dropForeign('fk_stays_booking_id');
                $table->dropForeign('fk_stays_checked_in_by');
                $table->dropForeign('fk_stays_checked_out_by');
            });
        }

        Schema::dropIfExists('stays');
    }
};
