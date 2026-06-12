<?php

declare(strict_types=1);

namespace Tests\Feature\Reconciliation;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F-85 — reconciliation_refund_drift view + reconciliation:check-drift.
 *
 * The view is the read-only detection surface for cross-table money
 * invariants that cannot be CHECK constraints. These tests seed real drift
 * shapes through the public schema (ledger inserts + booking money fields)
 * and assert the view classifies them; the command contract is
 * alert-don't-block (exit 0 even when drift exists).
 */
class ReconciliationDriftTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_exists_and_is_empty_without_drift(): void
    {
        $this->assertSame(0, DB::table('reconciliation_refund_drift')->count());
    }

    public function test_clean_booking_produces_no_drift_rows(): void
    {
        $booking = $this->makeBooking([
            'amount' => 150_000,
            'refund_status' => 'succeeded',
            'refund_amount' => 40_000,
        ]);

        $this->insertStripeRefund($booking->id, 40_000);

        $this->assertSame(
            0,
            DB::table('reconciliation_refund_drift')->where('booking_id', $booking->id)->count(),
            'A booking whose ledger matches its recorded refund must not be flagged',
        );
    }

    public function test_refund_sum_drift_is_detected(): void
    {
        $booking = $this->makeBooking([
            'amount' => 200_000,
            'refund_status' => 'succeeded',
            'refund_amount' => 50_000,
        ]);

        // Stripe-side ledger says 70 000 was refunded; booking says 50 000.
        $this->insertStripeRefund($booking->id, 70_000);

        $row = DB::table('reconciliation_refund_drift')
            ->where('booking_id', $booking->id)
            ->where('drift_type', 'refund_sum')
            ->first();

        $this->assertNotNull($row, 'refund_sum drift row expected');
        $this->assertSame('50000', $row->expected);
        $this->assertSame('70000', $row->actual);
        $this->assertSame(20_000, (int) $row->drift_cents);
    }

    public function test_over_refund_against_booking_amount_is_detected(): void
    {
        $booking = $this->makeBooking(['amount' => 100_000]);

        // Two partial refunds summing past the booking amount — the F-85
        // headline invariant (Σ refunds ≤ amount) violated at the ledger.
        $this->insertStripeRefund($booking->id, 60_000);
        $this->insertStripeRefund($booking->id, 70_000);

        $row = DB::table('reconciliation_refund_drift')
            ->where('booking_id', $booking->id)
            ->where('drift_type', 'refund_exceeds_amount')
            ->first();

        $this->assertNotNull($row, 'refund_exceeds_amount drift row expected');
        $this->assertSame('100000', $row->expected);
        $this->assertSame('130000', $row->actual);
        $this->assertSame(30_000, (int) $row->drift_cents);
    }

    public function test_deposit_lifecycle_status_mismatch_is_detected(): void
    {
        $booking = $this->makeBooking([
            'amount' => 200_000,
            'deposit_amount' => 50_000,
            'deposit_status' => 'refunded',
        ]);

        // Trail says the deposit only ever reached 'collected'.
        $this->insertDepositEvent($booking->id, from: 'none', to: 'collected');

        $row = DB::table('reconciliation_refund_drift')
            ->where('booking_id', $booking->id)
            ->where('drift_type', 'deposit_lifecycle')
            ->first();

        $this->assertNotNull($row, 'deposit_lifecycle drift row expected');
        $this->assertSame('collected', $row->expected);
        $this->assertSame('refunded', $row->actual);
        $this->assertNull($row->drift_cents);
    }

    public function test_deposit_status_without_event_trail_is_detected(): void
    {
        $booking = $this->makeBooking([
            'amount' => 200_000,
            'deposit_amount' => 30_000,
            'deposit_status' => 'collected',
        ]);

        $row = DB::table('reconciliation_refund_drift')
            ->where('booking_id', $booking->id)
            ->where('drift_type', 'deposit_lifecycle')
            ->first();

        $this->assertNotNull($row, 'missing-trail deposit_lifecycle drift row expected');
        $this->assertSame('no deposit_events rows', $row->actual);
    }

    public function test_deposit_refund_exceeding_deposit_is_detected(): void
    {
        $booking = $this->makeBooking([
            'amount' => 200_000,
            'deposit_amount' => 40_000,
            'deposit_status' => 'partial_refund',
        ]);

        $this->insertDepositEvent($booking->id, from: 'none', to: 'collected');
        $this->insertDepositEvent(
            $booking->id,
            from: 'collected',
            to: 'partial_refund',
            refundPercent: 100,
            refundAmount: 65_000,
        );

        $row = DB::table('reconciliation_refund_drift')
            ->where('booking_id', $booking->id)
            ->where('drift_type', 'deposit_refund_exceeds_deposit')
            ->first();

        $this->assertNotNull($row, 'deposit_refund_exceeds_deposit drift row expected');
        $this->assertSame('40000', $row->expected);
        $this->assertSame('65000', $row->actual);
        $this->assertSame(25_000, (int) $row->drift_cents);
    }

    public function test_command_exits_zero_with_drift_present(): void
    {
        $booking = $this->makeBooking(['amount' => 100_000]);
        $this->insertStripeRefund($booking->id, 130_000);

        $this->assertGreaterThan(
            0,
            DB::table('reconciliation_refund_drift')->count(),
            'Precondition: at least one drifted row must exist for this test to prove anything',
        );

        $this->artisan('reconciliation:check-drift')
            ->expectsOutputToContain('Reconciliation drift detected')
            ->assertExitCode(0);
    }

    public function test_command_exits_zero_when_clean(): void
    {
        $this->artisan('reconciliation:check-drift')
            ->expectsOutputToContain('No reconciliation drift detected')
            ->assertExitCode(0);
    }

    public function test_command_is_idempotent_read_only(): void
    {
        $booking = $this->makeBooking(['amount' => 100_000]);
        $this->insertStripeRefund($booking->id, 130_000);

        $before = DB::table('reconciliation_refund_drift')->count();

        $this->artisan('reconciliation:check-drift')->assertExitCode(0);
        $this->artisan('reconciliation:check-drift')->assertExitCode(0);

        $this->assertSame(
            $before,
            DB::table('reconciliation_refund_drift')->count(),
            'Repeated runs must not mutate any state the view reads',
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Build a booking, then set money/reconciliation columns with a direct
     * UPDATE so the fixture is independent of model fillable/cast/factory
     * configure() side effects. All values respect the chk_bookings_* CHECK
     * constraints (refund ≤ amount, deposit ≤ amount, valid statuses).
     *
     * @param  array<string, int|string|null>  $moneyFields
     */
    private function makeBooking(array $moneyFields = []): Booking
    {
        $booking = Booking::factory()->create();

        if ($moneyFields !== []) {
            DB::table('bookings')->where('id', $booking->id)->update($moneyFields);
        }

        return $booking->refresh();
    }

    private function insertStripeRefund(int $bookingId, int $amountRefunded): void
    {
        DB::table('stripe_refund_events')->insert([
            'stripe_refund_id' => 're_test_'.bin2hex(random_bytes(8)),
            'stripe_event_id' => 'evt_test_'.bin2hex(random_bytes(8)),
            'booking_id' => $bookingId,
            'amount_refunded' => $amountRefunded,
            'currency' => 'vnd',
            'processed_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function insertDepositEvent(
        int $bookingId,
        string $from,
        string $to,
        int $refundPercent = 0,
        ?int $refundAmount = null,
    ): void {
        DB::table('deposit_events')->insert([
            'booking_id' => $bookingId,
            'from_status' => $from,
            'to_status' => $to,
            'refund_percent' => $refundPercent,
            'refund_amount' => $refundAmount,
            'reason' => 'reconciliation-drift-test',
            'created_at' => now(),
        ]);
    }
}
