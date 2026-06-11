<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Money-column CHECK constraints (F-81, P0-2).
 *
 * Laravel's PostgreSQL grammar ignores `unsigned`, so every "unsignedBigInteger"
 * money column is a plain BIGINT — negative amounts and over-refunds were
 * insertable at the storage layer with only app-level enforcement.
 *
 * Adds:
 * - `>= 0` checks on all money columns (NULL-tolerant where the column is
 *   nullable: NULL means "not applicable", not zero):
 *     bookings.amount / refund_amount / deposit_amount (nullable)
 *     bookings.amount_capturable / amount_received     (NOT NULL, default 0)
 *     deposit_events.refund_amount                     (nullable)
 *     service_recovery_cases.refund_amount / voucher_amount / cost_delta_absorbed (nullable)
 *     stripe_refund_events.amount_refunded             (NOT NULL)
 * - relational checks on bookings:
 *     refund_amount  <= amount  (chk_bookings_refund_le_amount)
 *     deposit_amount <= amount  (chk_bookings_deposit_le_amount)
 *   Both are NULL-tolerant: the rule only binds when both sides are present.
 *
 * [OUT OF SCOPE — UNPROVEN, needs PaymentStatus review, see FINDINGS_BACKLOG
 * F-81] No cross-column capture-state constraints are added here (e.g.
 * amount_received <= amount_capturable, or amount_capturable <= amount tied to
 * App\Enums\PaymentStatus phases). The authorize-then-capture state machine
 * semantics for partial capture have not been verified; constraining them on
 * inference could reject legitimate writes. Do not add them without a
 * PaymentStatus review.
 *
 * PostgreSQL only — SQLite does not support ALTER TABLE ADD CONSTRAINT.
 * DROP-then-ADD keeps up() idempotent against partially-applied state
 * (same idiom as 2026_05_02_000001). Production note: ADD CONSTRAINT scans the
 * table and fails on historical violators — run the data-quality query for
 * negative/over-refund rows first; if violators exist, clean them as a data
 * task (or stage via NOT VALID + VALIDATE), do not force.
 */
return new class extends Migration
{
    /**
     * name => [table, expression], explicit names per migration-safety rule.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $checks = [
        // bookings — non-negativity
        'chk_bookings_amount_nonneg' => ['bookings', 'amount IS NULL OR amount >= 0'],
        'chk_bookings_refund_amount_nonneg' => ['bookings', 'refund_amount IS NULL OR refund_amount >= 0'],
        'chk_bookings_deposit_amount_nonneg' => ['bookings', 'deposit_amount IS NULL OR deposit_amount >= 0'],
        'chk_bookings_amount_capturable_nonneg' => ['bookings', 'amount_capturable >= 0'],
        'chk_bookings_amount_received_nonneg' => ['bookings', 'amount_received >= 0'],

        // bookings — relational
        'chk_bookings_refund_le_amount' => ['bookings', 'refund_amount IS NULL OR amount IS NULL OR refund_amount <= amount'],
        'chk_bookings_deposit_le_amount' => ['bookings', 'deposit_amount IS NULL OR amount IS NULL OR deposit_amount <= amount'],

        // deposit_events
        'chk_deposit_events_refund_amount_nonneg' => ['deposit_events', 'refund_amount IS NULL OR refund_amount >= 0'],

        // service_recovery_cases (chk_src_* matches the table's existing constraint naming)
        'chk_src_refund_amount_nonneg' => ['service_recovery_cases', 'refund_amount IS NULL OR refund_amount >= 0'],
        'chk_src_voucher_amount_nonneg' => ['service_recovery_cases', 'voucher_amount IS NULL OR voucher_amount >= 0'],
        'chk_src_cost_delta_absorbed_nonneg' => ['service_recovery_cases', 'cost_delta_absorbed IS NULL OR cost_delta_absorbed >= 0'],

        // stripe_refund_events
        'chk_stripe_refund_events_amount_refunded_nonneg' => ['stripe_refund_events', 'amount_refunded >= 0'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->checks as $name => [$table, $expression]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->checks as $name => [$table, $expression]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
        }
    }
};
