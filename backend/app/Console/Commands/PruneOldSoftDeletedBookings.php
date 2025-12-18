<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * PruneOldSoftDeletedBookings - Clean up old soft-deleted bookings
 * 
 * PURPOSE:
 * Permanently removes soft-deleted bookings older than retention period.
 * This is for compliance (storage management) AFTER the audit retention period.
 * 
 * COMPLIANCE NOTES:
 * - Default: 7 years retention (SOX, general accounting)
 * - Can be configured via --days option
 * - Always logs what is pruned for audit trail
 * - Runs as scheduled job (e.g., weekly)
 * 
 * USAGE:
 * php artisan bookings:prune-deleted           # Default 7 years
 * php artisan bookings:prune-deleted --days=365 # 1 year
 * php artisan bookings:prune-deleted --dry-run  # Preview without deleting
 * 
 * SCHEDULING (in app/Console/Kernel.php):
 * $schedule->command('bookings:prune-deleted')->weekly();
 */
class PruneOldSoftDeletedBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:prune-deleted 
                            {--days=2555 : Number of days after which to prune (default 7 years)}
                            {--dry-run : Preview what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete soft-deleted bookings older than retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Pruning soft-deleted bookings older than {$days} days (before {$cutoffDate->toDateString()})");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No records will be deleted');
        }

        // Find all soft-deleted bookings older than cutoff
        $query = Booking::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No soft-deleted bookings found matching criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found {$count} soft-deleted booking(s) to prune.");

        if ($dryRun) {
            // Show what would be deleted
            $bookings = $query->get(['id', 'room_id', 'guest_name', 'check_in', 'check_out', 'deleted_at']);
            
            $this->table(
                ['ID', 'Room ID', 'Guest', 'Check-In', 'Check-Out', 'Deleted At'],
                $bookings->map(fn($b) => [
                    $b->id,
                    $b->room_id,
                    $b->guest_name,
                    $b->check_in->format('Y-m-d'),
                    $b->check_out->format('Y-m-d'),
                    $b->deleted_at->format('Y-m-d H:i:s'),
                ])->toArray()
            );

            $this->info('Run without --dry-run to permanently delete these records.');
            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (!$this->confirm("Are you sure you want to permanently delete {$count} booking(s)? This action is IRREVERSIBLE.")) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Log before deletion for audit
        $bookingIds = $query->pluck('id')->toArray();
        Log::warning('Pruning old soft-deleted bookings', [
            'count' => $count,
            'cutoff_date' => $cutoffDate->toDateTimeString(),
            'booking_ids' => $bookingIds,
            'executed_by' => 'console:bookings:prune-deleted',
        ]);

        // Force delete all matching records
        $deleted = $query->forceDelete();

        $this->info("Successfully pruned {$deleted} booking(s).");

        Log::info('Completed pruning old soft-deleted bookings', [
            'deleted_count' => $deleted,
        ]);

        return Command::SUCCESS;
    }
}
