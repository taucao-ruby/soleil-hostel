<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * BackfillOperationalStays — create Stay rows for confirmed bookings that pre-date lazy stay creation.
 *
 * PURPOSE:
 * BookingService::confirmBooking() creates Stay rows lazily going forward.
 * Bookings confirmed before that hook was deployed have no Stay row.
 * This command creates expected-state Stay rows for those historical bookings.
 *
 * SELECTION CRITERIA (all conditions must be true):
 * - bookings.status = 'confirmed'
 * - bookings.check_out >= today
 * - no existing stay row for this booking
 *
 * SAFETY:
 * - Idempotent: re-running creates zero additional rows (uses firstOrCreate)
 * - Does NOT fabricate actual_check_in_at or actual_check_out_at timestamps
 * - Does NOT touch cancelled, refund_pending, refunded, refund_failed, or past-checkout bookings
 *
 * USAGE:
 * php artisan stays:backfill-operational            # Persist rows
 * php artisan stays:backfill-operational --dry-run  # Count eligible rows, persist nothing
 *
 * SOURCE-OF-TRUTH BOUNDARIES:
 * bookings.status   = commercial reservation state only
 * stays.stay_status = operational occupancy lifecycle
 * See: docs/DOMAIN_LAYERS.md
 */
class BackfillOperationalStays extends Command
{
    protected $signature = 'stays:backfill-operational
                            {--dry-run : Count eligible rows and print summary without persisting anything}';

    protected $description = 'Create expected-state Stay rows for confirmed bookings that pre-date lazy stay creation';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today = Carbon::today()->toDateString();

        if ($dryRun) {
            $this->warn('DRY RUN MODE — No records will be persisted');
        }

        $this->info("Scanning confirmed bookings with check_out >= {$today}...");

        $scannedBookings = Booking::query()
            ->where('status', BookingStatus::CONFIRMED)
            ->where('check_out', '>=', $today);

        $totalScanned = (clone $scannedBookings)->count();
        $alreadyHaveStay = (clone $scannedBookings)->whereHas('stay')->count();

        $eligibleQuery = (clone $scannedBookings)->whereDoesntHave('stay');
        $eligible = (clone $eligibleQuery)->count();

        $this->info("Total scanned:         {$totalScanned}");
        $this->info("Already have a stay:   {$alreadyHaveStay}");
        $this->info("Rows to create:        {$eligible}");

        if ($eligible === 0) {
            $this->info('No eligible bookings found. Nothing to do.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would create {$eligible} stay row(s).");

            return Command::SUCCESS;
        }

        $created = 0;
        $skipped = $alreadyHaveStay;

        (clone $eligibleQuery)->each(function (Booking $booking) use (&$created, &$skipped) {
            $stay = Stay::firstOrCreate(
                ['booking_id' => $booking->id],
                $this->stayAttributesFor($booking)
            );

            $stay->wasRecentlyCreated ? $created++ : $skipped++;
        });

        $this->info('Backfill complete.');
        $this->info("Rows created:          {$created}");
        $this->info("Rows skipped (exists): {$skipped}");
        $this->info("Total scanned:         {$totalScanned}");

        Log::info('stays:backfill-operational completed', [
            'created' => $created,
            'skipped_exists' => $skipped,
            'total_scanned' => $totalScanned,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Build stay attributes without fabricating actual operational timestamps.
     *
     * @return array<string, mixed>
     */
    private function stayAttributesFor(Booking $booking): array
    {
        return [
            'stay_status' => StayStatus::EXPECTED,
            'scheduled_check_in_at' => $booking->check_in->copy()->setTime(14, 0, 0),
            'scheduled_check_out_at' => $booking->check_out->copy()->setTime(12, 0, 0),
            'actual_check_in_at' => null,
            'actual_check_out_at' => null,
            'late_checkout_minutes' => 0,
            'late_checkout_fee_amount' => null,
            'no_show_at' => null,
            'checked_in_by' => null,
            'checked_out_by' => null,
        ];
    }
}
