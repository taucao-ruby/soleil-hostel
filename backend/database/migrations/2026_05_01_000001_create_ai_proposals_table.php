<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-005 / AI-006 — durable AiProposal record.
 *
 * The Cache-backed proposal envelope is fast but offers no revalidation
 * surface at confirm time: a guest could see a proposal, wait, and confirm
 * a booking against a now-unavailable or re-priced room. This table is the
 * durable contract that lets ProposalConfirmationController re-check
 * room availability, price drift, expiry, and shown-before-confirm before
 * executing the underlying booking action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_hash', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_type', 30);

            // Booking-shape fields (nullable because cancellation proposals
            // do not carry a room/dates/price triplet).
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedBigInteger('quoted_price_cents')->nullable();

            // Drift detection: hash of room price + availability state at
            // proposal generation time. Recomputed at confirm time and
            // compared for fast equality.
            $table->string('context_version', 64);

            // Mirror of the cached envelope so the audit trail survives
            // even after the cache TTL evicts the original.
            $table->json('proposed_params');
            $table->json('risk_assessment');

            $table->timestamp('expires_at');
            $table->timestamp('shown_at')->nullable();
            $table->string('decision', 20)->nullable(); // confirmed | declined | errored
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
            $table->index(['proposal_hash', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_proposals');
    }
};
