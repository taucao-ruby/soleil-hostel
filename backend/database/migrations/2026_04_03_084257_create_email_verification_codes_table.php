<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('code_hash', 64);                // SHA-256 hex digest
            $table->timestampTz('expires_at');
            $table->smallInteger('attempts')->default(0);
            $table->smallInteger('max_attempts')->default(5);
            $table->timestampTz('last_sent_at');
            $table->timestampTz('consumed_at')->nullable(); // NULL = not yet used
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id', 'idx_evc_user_id');
            $table->index(['expires_at', 'consumed_at'], 'idx_evc_expires_consumed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
