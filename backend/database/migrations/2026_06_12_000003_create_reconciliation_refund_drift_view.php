<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F-85 — reconciliation_refund_drift view.
 *
 * Cross-table money invariants ("Σ Stripe refunds ≤ booking amount",
 * "deposit lifecycle state matches the deposit_events trail") cannot be
 * expressed as CHECK constraints. This view is the read-only detection
 * surface the reconciliation:check-drift command queries daily; it never
 * blocks a write path.
 *
 * Drift facets (drift_type column):
 *
 *   refund_sum
 *     Booking says its refund succeeded, but the Stripe-side refund ledger
 *     (stripe_refund_events) sums to a different total than
 *     bookings.refund_amount.
 *
 *   refund_exceeds_amount
 *     Total Stripe refunds for a booking exceed bookings.amount — the F-85
 *     headline invariant. Detected regardless of refund_status.
 *
 *   deposit_lifecycle
 *     bookings.deposit_status disagrees with the latest deposit_events
 *     to_status, or a non-'none' deposit status has no event trail at all.
 *
 *   deposit_refund_exceeds_deposit
 *     Σ deposit_events.refund_amount exceeds bookings.deposit_amount.
 *
 * Columns: booking_id, drift_type, expected (text), actual (text),
 * drift_cents (bigint, NULL for status-shaped drift).
 *
 * Tolerance: config('booking.reconciliation.drift_tolerance_cents') is baked
 * in at migrate time (a view cannot read runtime config). Status comparisons
 * take no tolerance.
 *
 * Scope notes:
 *  - Soft-deleted bookings are included: financial drift on a deleted
 *    booking still matters.
 *  - stripe_refund_events rows whose booking_id was NULLed by a booking
 *    hard-delete cannot be attributed and are out of scope here (the FK is
 *    ON DELETE SET NULL; orphan attribution needs Stripe metadata, not SQL).
 *  - refund_status='succeeded' bookings with NO ledger rows are not flagged:
 *    a refund recorded outside Stripe (manual/cash) has no ledger to compare.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tolerance = max(0, (int) config('booking.reconciliation.drift_tolerance_cents', 1));

        DB::statement(<<<SQL
            CREATE OR REPLACE VIEW reconciliation_refund_drift AS
            WITH stripe_refund_sums AS (
                SELECT booking_id, SUM(amount_refunded)::bigint AS refunded_cents
                FROM stripe_refund_events
                WHERE booking_id IS NOT NULL
                GROUP BY booking_id
            ),
            deposit_latest AS (
                SELECT DISTINCT ON (booking_id)
                       booking_id,
                       to_status AS latest_to_status
                FROM deposit_events
                ORDER BY booking_id, created_at DESC, id DESC
            ),
            deposit_refund_sums AS (
                SELECT booking_id, SUM(refund_amount)::bigint AS deposit_refunded_cents
                FROM deposit_events
                WHERE refund_amount IS NOT NULL
                GROUP BY booking_id
            )

            SELECT b.id AS booking_id,
                   'refund_sum'::text AS drift_type,
                   COALESCE(b.refund_amount, 0)::text AS expected,
                   srs.refunded_cents::text AS actual,
                   ABS(srs.refunded_cents - COALESCE(b.refund_amount, 0))::bigint AS drift_cents
            FROM bookings b
            JOIN stripe_refund_sums srs ON srs.booking_id = b.id
            WHERE b.refund_status = 'succeeded'
              AND ABS(srs.refunded_cents - COALESCE(b.refund_amount, 0)) > {$tolerance}

            UNION ALL

            SELECT b.id,
                   'refund_exceeds_amount'::text,
                   COALESCE(b.amount, 0)::text,
                   srs.refunded_cents::text,
                   (srs.refunded_cents - COALESCE(b.amount, 0))::bigint
            FROM bookings b
            JOIN stripe_refund_sums srs ON srs.booking_id = b.id
            WHERE srs.refunded_cents > COALESCE(b.amount, 0) + {$tolerance}

            UNION ALL

            SELECT b.id,
                   'deposit_lifecycle'::text,
                   dl.latest_to_status,
                   b.deposit_status,
                   NULL::bigint
            FROM bookings b
            JOIN deposit_latest dl ON dl.booking_id = b.id
            WHERE b.deposit_status IS DISTINCT FROM dl.latest_to_status

            UNION ALL

            SELECT b.id,
                   'deposit_lifecycle'::text,
                   'deposit_events trail present'::text,
                   'no deposit_events rows'::text,
                   NULL::bigint
            FROM bookings b
            LEFT JOIN deposit_latest dl ON dl.booking_id = b.id
            WHERE b.deposit_status <> 'none'
              AND dl.booking_id IS NULL

            UNION ALL

            SELECT b.id,
                   'deposit_refund_exceeds_deposit'::text,
                   COALESCE(b.deposit_amount, 0)::text,
                   drs.deposit_refunded_cents::text,
                   (drs.deposit_refunded_cents - COALESCE(b.deposit_amount, 0))::bigint
            FROM bookings b
            JOIN deposit_refund_sums drs ON drs.booking_id = b.id
            WHERE drs.deposit_refunded_cents > COALESCE(b.deposit_amount, 0) + {$tolerance}
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS reconciliation_refund_drift');
    }
};
