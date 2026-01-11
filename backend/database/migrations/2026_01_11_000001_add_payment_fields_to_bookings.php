<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add payment and refund tracking fields to bookings table.
 *
 * Supports Laravel Cashier (Stripe) integration for:
 * - Payment tracking via payment_intent_id
 * - Refund processing with status and amount
 * - Cancellation audit trail
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Booking amount (in cents) - for payment and refund calculations
            $table->unsignedBigInteger('amount')
                ->nullable()
                ->after('status')
                ->comment('Booking amount in cents');

            // Payment tracking (Stripe PaymentIntent)
            $table->string('payment_intent_id')
                ->nullable()
                ->after('amount')
                ->comment('Stripe PaymentIntent ID');

            // Refund tracking
            $table->string('refund_id')
                ->nullable()
                ->after('payment_intent_id')
                ->comment('Stripe Refund ID');

            $table->string('refund_status')
                ->nullable()
                ->after('refund_id')
                ->comment('pending|succeeded|failed');

            $table->unsignedBigInteger('refund_amount')
                ->nullable()
                ->after('refund_status')
                ->comment('Refund amount in cents');

            $table->text('refund_error')
                ->nullable()
                ->after('refund_amount')
                ->comment('Error message if refund failed');

            // Cancellation audit trail
            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('refund_error');

            $table->foreignId('cancelled_by')
                ->nullable()
                ->after('cancelled_at')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who initiated cancellation');

            // Indexes for reconciliation and reporting queries
            $table->index('refund_status', 'idx_bookings_refund_status');
            $table->index('payment_intent_id', 'idx_bookings_payment_intent');
            $table->index(['status', 'cancelled_at'], 'idx_bookings_cancellation');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['cancelled_by']);

            // Drop indexes
            $table->dropIndex('idx_bookings_refund_status');
            $table->dropIndex('idx_bookings_payment_intent');
            $table->dropIndex('idx_bookings_cancellation');

            // Drop columns
            $table->dropColumn([
                'amount',
                'payment_intent_id',
                'refund_id',
                'refund_status',
                'refund_amount',
                'refund_error',
                'cancelled_at',
                'cancelled_by',
            ]);
        });
    }
};
