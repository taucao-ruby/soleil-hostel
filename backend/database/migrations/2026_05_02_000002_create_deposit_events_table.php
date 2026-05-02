<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for deposit lifecycle transitions (CONC-005).
 *
 * Every Deposit::transitionTo() write is captured here. There is intentionally
 * no updated_at column and no UPDATE/DELETE path; the row is the durable
 * record that the deposit was moved from $from_status to $to_status by
 * $actor for $reason at $created_at.
 *
 * Columns:
 * - booking_id     : owner booking (FK with cascade delete to keep history aligned with the booking)
 * - from_status    : deposit status BEFORE the transition
 * - to_status      : deposit status AFTER the transition
 * - refund_percent : 0..100, the policy-derived refund percentage at transition time
 * - refund_amount  : cents, the resolved refund amount (null when transition does not imply a refund movement)
 * - reason         : short human/policy reason string
 * - actor_id       : user who initiated the transition (nullable — system jobs may transition)
 * - actor_email    : snapshot to survive user deletion
 * - actor_role     : snapshot to survive role changes
 * - metadata       : free-form jsonb for downstream tooling
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->unsignedSmallInteger('refund_percent');
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_email', 255)->nullable();
            $table->string('actor_role', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('booking_id')
                ->references('id')->on('bookings')
                ->cascadeOnDelete();

            $table->foreign('actor_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('booking_id', 'idx_deposit_events_booking_id');
            $table->index('created_at', 'idx_deposit_events_created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_events_from_status
                CHECK (from_status IN ('none', 'collected', 'applied', 'refunded', 'partial_refund', 'forfeited'))
            ");
            DB::statement("
                ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_events_to_status
                CHECK (to_status IN ('none', 'collected', 'applied', 'refunded', 'partial_refund', 'forfeited'))
            ");
            DB::statement('
                ALTER TABLE deposit_events ADD CONSTRAINT chk_deposit_events_refund_percent
                CHECK (refund_percent BETWEEN 0 AND 100)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_events');
    }
};
