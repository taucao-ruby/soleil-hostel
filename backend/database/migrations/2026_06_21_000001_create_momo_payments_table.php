<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('momo_payments', function (Blueprint $table): void {
            $table->id();
            // Loose coupling to bookings (no FK), consistent with momo_webhook_events:
            // the minted order_id is the durable handle and booking_id is the resolved
            // target. This keeps a payment-audit row's lifetime independent of a
            // force-deleted booking and matches the established MoMo-table pattern.
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('order_id');                    // the orderId we minted at create()
            $table->string('request_id')->nullable();
            $table->unsignedBigInteger('expected_amount'); // PINNED at create() — drift-proof guard
            $table->string('currency', 8)->default('vnd');
            $table->string('status', 32)->default('pending'); // pending | paid
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // One order id ⇒ one payment row. The IPN handler resolves the booking and
            // the pinned amount through this unique key; a second create mints a new order.
            $table->unique('order_id', 'uq_momo_payments_order_id');
        });

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE momo_payments ADD CONSTRAINT chk_momo_payments_status
                CHECK (status IN ('pending', 'paid'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('momo_payments');
    }
};
