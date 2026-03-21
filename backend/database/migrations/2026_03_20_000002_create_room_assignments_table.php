<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create room_assignments table — actual room allocation history and reassignment audit trail.
 *
 * Each row records a specific room assignment window for a stay:
 * - assigned_from: when the guest started using this room
 * - assigned_until: when they stopped (NULL = currently active)
 *
 * The partial unique index UNIQUE (stay_id) WHERE assigned_until IS NULL
 * ensures at most one active assignment per stay at any time.
 * This index is PostgreSQL-only (SQLite does not support partial indexes in this form).
 *
 * FK policies:
 * - booking_id → RESTRICT: assignment history is part of booking audit trail.
 * - stay_id    → RESTRICT: assignment belongs to stay; cannot orphan.
 * - room_id    → RESTRICT: room deletion blocked if assignments exist (historical integrity).
 * - assigned_by → SET NULL: staff record survives user deletion.
 *
 * CHECK constraints are PostgreSQL only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('stay_id');
            $table->unsignedBigInteger('room_id');

            // Assignment classification
            $table->string('assignment_type');

            // Assignment status
            $table->string('assignment_status')->default('active');

            // Assignment window — NULL assigned_until = currently active
            $table->timestamp('assigned_from');
            $table->timestamp('assigned_until')->nullable();

            // Staff who made the assignment (nullable — SET NULL on user deletion)
            $table->unsignedBigInteger('assigned_by')->nullable();

            // Optional operational metadata
            $table->string('reason_code')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Composite indexes for front-desk and reporting queries
            $table->index(['stay_id', 'assigned_until'], 'idx_room_assignments_stay_active');
            $table->index(['room_id', 'assigned_from', 'assigned_until'], 'idx_room_assignments_room_window');
        });

        // PostgreSQL-only constraints
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('room_assignments', function (Blueprint $table) {
                $table->foreign('booking_id', 'fk_room_assignments_booking_id')
                    ->references('id')
                    ->on('bookings')
                    ->restrictOnDelete();

                $table->foreign('stay_id', 'fk_room_assignments_stay_id')
                    ->references('id')
                    ->on('stays')
                    ->restrictOnDelete();

                $table->foreign('room_id', 'fk_room_assignments_room_id')
                    ->references('id')
                    ->on('rooms')
                    ->restrictOnDelete();

                $table->foreign('assigned_by', 'fk_room_assignments_assigned_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            // CHECK constraint: assignment_type must be a known value
            DB::statement("
                ALTER TABLE room_assignments ADD CONSTRAINT chk_room_assignments_assignment_type
                CHECK (assignment_type IN (
                    'original', 'equivalent_swap', 'complimentary_upgrade',
                    'maintenance_move', 'overflow_relocation'
                ))
            ");

            // CHECK constraint: assignment_status must be a known value
            DB::statement("
                ALTER TABLE room_assignments ADD CONSTRAINT chk_room_assignments_assignment_status
                CHECK (assignment_status IN ('active', 'closed', 'cancelled'))
            ");

            // Partial unique index: at most one active (assigned_until IS NULL) assignment per stay
            DB::statement('
                CREATE UNIQUE INDEX udx_room_assignments_one_active_per_stay
                ON room_assignments (stay_id)
                WHERE assigned_until IS NULL
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS udx_room_assignments_one_active_per_stay');

            Schema::table('room_assignments', function (Blueprint $table) {
                $table->dropForeign('fk_room_assignments_booking_id');
                $table->dropForeign('fk_room_assignments_stay_id');
                $table->dropForeign('fk_room_assignments_room_id');
                $table->dropForeign('fk_room_assignments_assigned_by');
            });
        }

        Schema::dropIfExists('room_assignments');
    }
};
