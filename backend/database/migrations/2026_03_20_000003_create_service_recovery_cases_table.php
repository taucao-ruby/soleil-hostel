<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create service_recovery_cases table — incident and compensation audit trail.
 *
 * Tracks operational incidents (late checkout conflicts, maintenance unavailability,
 * overbooking events, relocations) and the compensation/resolution actions taken.
 *
 * stay_id is nullable because an incident may be recorded before a stay record
 * exists (e.g., discovered during booking review), or a case may be linked
 * directly to a booking without a corresponding stay.
 *
 * Monetary columns use BIGINT (cents) consistent with bookings.amount convention.
 *
 * FK policies:
 * - booking_id → RESTRICT: case is part of booking history; cannot orphan.
 * - stay_id    → SET NULL: case survives stay deletion (incident predate may exist).
 * - handled_by → SET NULL: staff record survives user deletion.
 *
 * CHECK constraints are PostgreSQL only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_recovery_cases', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('stay_id')->nullable();

            // Incident classification
            $table->string('incident_type');

            // Severity and case lifecycle
            $table->string('severity')->default('medium');
            $table->string('case_status')->default('open');

            // Operational notes
            $table->text('action_taken')->nullable();

            // External relocation fields (populated when guest is moved to another hotel)
            $table->string('external_hotel_name')->nullable();
            $table->string('external_booking_reference')->nullable();

            // Compensation tracking
            $table->string('compensation_type')->default('none');
            $table->unsignedBigInteger('refund_amount')->nullable()->comment('Refund component in cents');
            $table->unsignedBigInteger('voucher_amount')->nullable()->comment('Voucher value in cents');
            $table->unsignedBigInteger('cost_delta_absorbed')->nullable()->comment('Extra cost absorbed by hostel in cents');

            // Staff assignment (nullable — SET NULL on user deletion)
            $table->unsignedBigInteger('handled_by')->nullable();

            // Case timeline
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for case management and reporting queries
            $table->index(['case_status', 'severity'], 'idx_src_case_status_severity');
            $table->index('booking_id', 'idx_src_booking_id');
            $table->index('opened_at', 'idx_src_opened_at');
            $table->index('stay_id', 'idx_src_stay_id');
        });

        // PostgreSQL-only constraints
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('service_recovery_cases', function (Blueprint $table) {
                $table->foreign('booking_id', 'fk_src_booking_id')
                    ->references('id')
                    ->on('bookings')
                    ->restrictOnDelete();

                $table->foreign('stay_id', 'fk_src_stay_id')
                    ->references('id')
                    ->on('stays')
                    ->nullOnDelete();

                $table->foreign('handled_by', 'fk_src_handled_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            // CHECK constraint: incident_type
            DB::statement("
                ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_incident_type
                CHECK (incident_type IN (
                    'late_checkout_blocking_arrival', 'room_unavailable_maintenance',
                    'overbooking_no_room', 'internal_relocation', 'external_relocation'
                ))
            ");

            // CHECK constraint: severity
            DB::statement("
                ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_severity
                CHECK (severity IN ('low', 'medium', 'high', 'critical'))
            ");

            // CHECK constraint: case_status
            DB::statement("
                ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_case_status
                CHECK (case_status IN (
                    'open', 'investigating', 'action_in_progress',
                    'compensated', 'resolved', 'closed'
                ))
            ");

            // CHECK constraint: compensation_type
            DB::statement("
                ALTER TABLE service_recovery_cases ADD CONSTRAINT chk_src_compensation_type
                CHECK (compensation_type IN (
                    'none', 'refund_partial', 'refund_full', 'voucher',
                    'complimentary_upgrade', 'refund_plus_voucher'
                ))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('service_recovery_cases', function (Blueprint $table) {
                $table->dropForeign('fk_src_booking_id');
                $table->dropForeign('fk_src_stay_id');
                $table->dropForeign('fk_src_handled_by');
            });
        }

        Schema::dropIfExists('service_recovery_cases');
    }
};
