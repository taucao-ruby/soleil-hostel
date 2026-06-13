<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F-85 — daily money-drift detector (alert, don't block).
 *
 * Queries the reconciliation_refund_drift view (see migration
 * 2026_06_12_000003) and emits one structured warning per drifted booking
 * plus a run-level gauge, following the same log-metric alerting pattern as
 * webhook:reconcile-stuck-events (`metric` keys consumed by log-based
 * alerting/SIEM — no new external integration).
 *
 * Contract:
 *  - Read-only: no state is mutated anywhere; safe to run any number of
 *    times (idempotent by construction).
 *  - ALWAYS exits 0 — drift found, view missing, or even a thrown
 *    exception must never fail CI or break the scheduler chain. Failures
 *    are themselves surfaced as error-level log metrics.
 */
final class CheckReconciliationDrift extends Command
{
    protected $signature = 'reconciliation:check-drift
        {--limit=500 : Maximum drift rows logged per run (the run-level gauge always reports the full count)}';

    protected $description = 'Detect drift between the Stripe refund ledger / deposit lifecycle trail and booking money fields. Alert-only: always exits 0.';

    public function handle(): int
    {
        try {
            $this->runCheck();
        } catch (Throwable $e) {
            // Alert-don't-block: a broken drift checker must never take the
            // scheduler down with it. The error metric is the alert.
            Log::error('reconciliation_drift.check_failed', [
                'metric' => 'reconciliation_drift.check_failed',
                'error_class' => class_basename($e),
                'error' => $e->getMessage(),
            ]);

            $this->error('Drift check failed: '.$e->getMessage());
        }

        return self::SUCCESS;
    }

    private function runCheck(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->info('reconciliation_refund_drift view is PostgreSQL-only; skipping.');

            return;
        }

        $limit = max(1, (int) $this->option('limit'));
        $toleranceCents = (int) config('booking.reconciliation.drift_tolerance_cents', 1);

        $totalDrift = DB::table('reconciliation_refund_drift')->count();

        $rows = DB::table('reconciliation_refund_drift')
            ->orderBy('booking_id')
            ->orderBy('drift_type')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            Log::warning('reconciliation_drift.row_detected', [
                'metric' => 'reconciliation_drift.row_detected',
                'booking_id' => (int) $row->booking_id,
                'drift_type' => (string) $row->drift_type,
                'expected' => $row->expected,
                'actual' => $row->actual,
                'drift_cents' => $row->drift_cents === null ? null : (int) $row->drift_cents,
            ]);

            $this->warn(sprintf(
                'DRIFT booking=%d type=%s expected=%s actual=%s',
                (int) $row->booking_id,
                (string) $row->drift_type,
                (string) $row->expected,
                (string) $row->actual,
            ));
        }

        $context = [
            'metric' => 'reconciliation_drift.drift_count',
            'value' => $totalDrift,
            'logged_rows' => $rows->count(),
            'truncated' => $totalDrift > $rows->count(),
            'tolerance_cents' => $toleranceCents,
        ];

        if ($totalDrift > 0) {
            // The run-level alert key for SIEM rules; per-row warnings above
            // carry the forensic detail.
            Log::warning('reconciliation_drift.drift_detected', $context);

            $this->warn(sprintf(
                'Reconciliation drift detected: %d row(s) (%d logged). See reconciliation_drift.* log metrics.',
                $totalDrift,
                $rows->count(),
            ));

            return;
        }

        Log::info('reconciliation_drift.run_clean', $context);

        $this->info('No reconciliation drift detected.');
    }
}
