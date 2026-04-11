<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_proposal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('proposal_hash', 64)->index();
            $table->string('action_type', 30);
            $table->string('user_decision', 20); // confirmed | declined | shown
            $table->text('downstream_result')->nullable();
            $table->timestamps();

            $table->index(['proposal_hash', 'user_decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_proposal_events');
    }
};
