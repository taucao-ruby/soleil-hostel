<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_refund_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_refund_id');
            $table->string('stripe_event_id');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->integer('amount_refunded');
            $table->string('currency', 3);
            $table->timestampTz('processed_at')->useCurrent();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('stripe_refund_id', 'idx_stripe_refund_events_refund_id');
            $table->foreign('booking_id', 'fk_stripe_refund_events_booking_id')
                ->references('id')
                ->on('bookings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_refund_events');
    }
};
