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
        $currency = strtolower((string) config('cashier.currency', 'vnd'));

        Schema::table('bookings', function (Blueprint $table) use ($currency): void {
            if (! Schema::hasColumn('bookings', 'payment_policy')) {
                $table->string('payment_policy', 32)
                    ->default('prepaid')
                    ->after('amount');
            }

            if (! Schema::hasColumn('bookings', 'payment_status')) {
                $table->string('payment_status', 32)
                    ->default('requires_confirmation')
                    ->after('payment_policy');
            }

            if (! Schema::hasColumn('bookings', 'payment_currency')) {
                $table->string('payment_currency', 3)
                    ->default($currency)
                    ->after('payment_status');
            }

            if (! Schema::hasColumn('bookings', 'amount_capturable')) {
                $table->unsignedBigInteger('amount_capturable')
                    ->default(0)
                    ->after('payment_currency');
            }

            if (! Schema::hasColumn('bookings', 'amount_received')) {
                $table->unsignedBigInteger('amount_received')
                    ->default(0)
                    ->after('amount_capturable');
            }

            if (! Schema::hasColumn('bookings', 'authorized_at')) {
                $table->timestamp('authorized_at')
                    ->nullable()
                    ->after('amount_received');
            }

            if (! Schema::hasColumn('bookings', 'paid_at')) {
                $table->timestamp('paid_at')
                    ->nullable()
                    ->after('authorized_at');
            }

            if (! Schema::hasColumn('bookings', 'capture_due_at')) {
                $table->timestamp('capture_due_at')
                    ->nullable()
                    ->after('paid_at');
            }

            if (! Schema::hasColumn('bookings', 'payment_failed_reason')) {
                $table->text('payment_failed_reason')
                    ->nullable()
                    ->after('capture_due_at');
            }
        });

        DB::table('bookings')
            ->whereNull('payment_intent_id')
            ->update([
                'payment_policy' => 'pay_at_property',
                'payment_status' => 'offline_due',
                'payment_currency' => $currency,
            ]);

        DB::table('bookings')
            ->whereNotNull('payment_intent_id')
            ->where('status', 'confirmed')
            ->update([
                'payment_policy' => 'prepaid',
                'payment_status' => 'paid',
                'payment_currency' => $currency,
                'paid_at' => DB::raw('COALESCE(paid_at, updated_at)'),
            ]);

        DB::table('bookings')
            ->whereNotNull('payment_intent_id')
            ->where('status', 'pending')
            ->update([
                'payment_policy' => 'prepaid',
                'payment_status' => 'requires_payment_method',
                'payment_currency' => $currency,
            ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE bookings ADD CONSTRAINT chk_bookings_payment_policy
                CHECK (payment_policy IN ('prepaid', 'authorize_then_capture', 'pay_at_property', 'not_required'))
            ");

            DB::statement("
                ALTER TABLE bookings ADD CONSTRAINT chk_bookings_payment_status
                CHECK (payment_status IN (
                    'not_required',
                    'offline_due',
                    'requires_confirmation',
                    'requires_payment_method',
                    'requires_action',
                    'processing',
                    'authorized',
                    'paid',
                    'failed',
                    'cancelled',
                    'capture_failed',
                    'refunded',
                    'partially_refunded'
                ))
            ");
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->index('payment_status', 'idx_bookings_payment_status');
            $table->index(['payment_policy', 'payment_status'], 'idx_bookings_payment_policy_status');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS chk_bookings_payment_status');
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS chk_bookings_payment_policy');
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('idx_bookings_payment_policy_status');
            $table->dropIndex('idx_bookings_payment_status');

            $table->dropColumn([
                'payment_policy',
                'payment_status',
                'payment_currency',
                'amount_capturable',
                'amount_received',
                'authorized_at',
                'paid_at',
                'capture_due_at',
                'payment_failed_reason',
            ]);
        });
    }
};
