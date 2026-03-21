<?php

namespace App\Console\Commands;

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
 * - bookings.check_out >= today  (skip past-checkout bookings)
 * - no existing stays row for this booking
 *
 * SAFETY:
 * - Idempotent: re-running creates zero additional rows (uses firstOrCreate)
 * - Does NOT fabricate actual_check_in_at or actual_check_out_at timestamps
 * - Does NOT touch cancelled, refund_pending, refund_failed, or past-checkout bookings
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
        $today = Carbon::today();

        if ($dryRun) {
            $this->warn('DRY RUN MODE — No records will be persisted');
        }

        $this->info("Scanning confirmed bookings with check_out >= {$today->toDateString()}...");

        // Total confirmed bookings with future/today checkout (denominator for summary)
        $totalScanned = Booking::query()
            ->where('status', 'confirmed')
            ->where('check_out', '>=', $today)
            ->count();

        // Subset that already have a stay (will be skipped)
        $alreadyHaveStay = Booking::query()
            ->where('status', 'confirmed')
            ->where('check_out', '>=', $today)
            ->whereHas('stay')
            ->count();

        // Eligible: confirmed + future checkout + no existing stay row
        $eligibleQuery = Booking::query()
            ->where('status', 'confirmed')
            ->where('check_out', '>=', $today)
            ->whereDoesntHave('stay');

        $eligible = $eligibleQuery->count();

        $this->info("Total scanned:         {$totalScanned}");
        $this->info("Already have a stay:   {$alreadyHaveStay}");
        $this->info("Eligible for backfill: {$eligible}");

        if ($eligible === 0) {
            $this->info('No eligible bookings found. Nothing to do.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would create {$eligible} stay row(s). Run without --dry-run to persist.");

            return Command::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        $eligibleQuery->each(function (Booking $booking) use (&$created, &$skipped) {
            $stay = Stay::firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'stay_status' => StayStatus::EXPECTED,
                    'scheduled_check_in_at' => $booking->check_in->copy()->setTime(14, 0, 0),
                    'scheduled_check_out_at' => $booking->check_out->copy()->setTime(12, 0, 0),
                    'actual_check_in_at' => null,
                    'actual_check_out_at' => null,
                ]
            );

            $stay->wasRecentlyCreated ? $created++ : $skipped++;
        });

        $this->info('Backfill complete.');
        $this->info("Rows created:          {$created}");
        $this->info("Rows skipped (exists): {$skipped}");
        $this->info("Total scanned:         {$totalScanned}");

        Log::info('stays:backfill-operational completed', [
            'created' => $created,
            'skipped' => $skipped,
            'total_scanned' => $totalScanned,
        ]);

        return Command::SUCCESS;
    }
}
