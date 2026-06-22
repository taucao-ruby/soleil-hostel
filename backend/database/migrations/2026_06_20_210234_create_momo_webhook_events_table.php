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

        Schema::create('momo_webhook_events', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->string('order_id');           // NOT NULL — dedup key part 1
            $table->string('request_id')->nullable();
            $table->string('trans_id');           // NOT NULL — dedup key part 2
            $table->string('type', 100);
            $table->string('status', 32);         // processing | processed | failed
            $table->integer('result_code')->nullable(); // MoMo resultCode (0 = success)

            if ($driver === 'pgsql') {
                $table->jsonb('payload');
            } else {
                $table->json('payload');
            }

            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // INSERT-first linearization point: a replayed IPN with the same
            // (order_id, trans_id) collides here and raises a
            // UniqueConstraintViolationException, which the controller treats as
            // "already seen ⇒ 204 ack". Both columns are NOT NULL because in
            // PostgreSQL NULLs do not collide in a UNIQUE index.
            $table->unique(['order_id', 'trans_id'], 'uq_momo_webhook_events_order_trans');
        });

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE momo_webhook_events ADD CONSTRAINT chk_momo_webhook_events_status
                CHECK (status IN ('processing', 'processed', 'failed'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('momo_webhook_events');
    }
};
