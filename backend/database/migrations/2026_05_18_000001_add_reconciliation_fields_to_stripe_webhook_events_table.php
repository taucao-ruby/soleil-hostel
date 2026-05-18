<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add reconciliation/failure-context columns to stripe_webhook_events.
 *
 * Background: the live webhook controller INSERTs a row with status='processing'
 * before mutating the booking. If the worker dies between the INSERT and
 * markProcessed/markFailed, the row stays processing forever and the booking
 * stays PENDING — Stripe stops retrying because the UNIQUE constraint on
 * stripe_event_id sees the event recorded. This migration adds the columns
 * required by the webhook:reconcile-stuck-events artisan command to:
 *
 *   - record failure context observably (error, failed_at)
 *   - atomically claim stale rows before doing slow Stripe HTTP calls
 *     (reconcile_started_at, reconcile_finished_at, reconcile_attempts)
 *
 * The status CHECK constraint already permits 'failed' (see
 * 2026_04_28_000001_create_stripe_webhook_events_table.php), so no enum
 * extension is needed.
 *
 * The composite index on (status, created_at) backs the reaper's selection
 * predicate `status='processing' AND created_at < cutoff`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table): void {
            // Failure context (sanitized in StripeWebhookEvent::markFailed)
            $table->text('error')->nullable()->after('processed_at');
            $table->timestamp('failed_at')->nullable()->after('error');

            // Atomic-claim metadata for the reaper. reconcile_started_at is
            // bumped under SELECT ... FOR UPDATE so two concurrent reapers
            // cannot both pick up the same row.
            $table->timestamp('reconcile_started_at')->nullable()->after('failed_at');
            $table->timestamp('reconcile_finished_at')->nullable()->after('reconcile_started_at');
            $table->unsignedInteger('reconcile_attempts')->default(0)->after('reconcile_finished_at');

            // Backs the reaper's stale-processing predicate.
            $table->index(['status', 'created_at'], 'idx_stripe_webhook_events_status_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table): void {
            $table->dropIndex('idx_stripe_webhook_events_status_created_at');
            $table->dropColumn([
                'error',
                'failed_at',
                'reconcile_started_at',
                'reconcile_finished_at',
                'reconcile_attempts',
            ]);
        });
    }
};
