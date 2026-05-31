<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox for Stripe PaymentIntent cancellations (PAY-03).
 *
 * Problem this solves: ExpireStaleBookings used to call
 * StripeService::cancelPaymentIntent() *inside* the per-booking
 * SELECT ... FOR UPDATE transaction. A Stripe hang therefore held the
 * booking row lock for the full HTTP timeout, and a concurrent
 * CreateBookingService overlap-lock (SELECT ... FOR UPDATE on active
 * bookings for the same room) could block behind it.
 *
 * Fix: the expiry transaction now only mutates local DB state and INSERTs
 * a row here (durable intent, atomic with the CANCELLED transition — no
 * network I/O). ProcessPaymentCancellationOutbox drains these rows OUTSIDE
 * any booking/room lock, calling Stripe with a stable idempotency key and
 * bounded retry/backoff. The row is the recoverable record: if a worker
 * crashes mid-flight the next sweep re-claims it.
 *
 * Columns:
 * - booking_id        : owner booking (FK cascade — drop the task if the booking is hard-deleted)
 * - payment_intent_id : the Stripe PaymentIntent to cancel (snapshot at expiry time)
 * - action            : task verb; only 'payment_intent.cancel' today
 * - status            : pending | processing | retrying | succeeded | failed_permanent
 * - attempts          : bumped on each claim; gates the max-attempt circuit breaker
 * - available_at      : visibility timestamp; backoff pushes this into the future
 * - claimed_at        : set when a worker claims the row (stale-claim crash recovery)
 * - processed_at      : set when the row reaches a terminal status
 * - last_error_code   : short machine code (e.g. Stripe exception class basename)
 * - last_error        : sanitized last-failure context (secrets/PII stripped)
 *
 * The UNIQUE (booking_id, payment_intent_id, action) guarantees a single
 * active task per cancellation, so duplicate/idempotent expiry runs cannot
 * enqueue duplicate Stripe work. The (status, available_at) index backs the
 * drainer's claimable predicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('payment_cancellation_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('payment_intent_id');
            $table->string('action', 32)->default('payment_intent.cancel');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')
                ->references('id')->on('bookings')
                ->cascadeOnDelete();

            // One active cancellation task per (booking, intent, action). The
            // expiry path uses firstOrCreate against this key so repeated runs
            // are idempotent at the storage layer, not just the application layer.
            $table->unique(
                ['booking_id', 'payment_intent_id', 'action'],
                'uq_payment_cancellation_tasks_booking_intent_action',
            );

            // Backs the drainer's claimable predicate
            // (status IN (...) AND available_at <= now).
            $table->index(['status', 'available_at'], 'idx_payment_cancellation_tasks_status_available');
        });

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE payment_cancellation_tasks ADD CONSTRAINT chk_payment_cancellation_tasks_status
                CHECK (status IN ('pending', 'processing', 'retrying', 'succeeded', 'failed_permanent'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_cancellation_tasks');
    }
};
