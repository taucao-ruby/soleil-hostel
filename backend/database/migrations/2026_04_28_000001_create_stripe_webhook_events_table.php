<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('stripe_webhook_events', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type', 100);
            $table->string('status', 32);

            if ($driver === 'pgsql') {
                $table->jsonb('payload');
            } else {
                $table->json('payload');
            }

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE stripe_webhook_events ADD CONSTRAINT chk_stripe_webhook_events_status
                CHECK (status IN ('processing', 'processed', 'failed'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
