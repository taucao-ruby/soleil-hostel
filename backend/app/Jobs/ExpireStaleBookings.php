<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expire stale pending bookings.
 *
 * A pending booking reserves its room (Booking::ACTIVE_STATUSES includes
 * PENDING). Without a TTL, an abandoned pending booking would block other
 * guests from booking the room indefinitely and would silently corrupt
 * availability reporting.
 *
 * Rules:
 * - Only bookings whose status is PENDING are considered.
 * - Age is measured against created_at, not updated_at. A pending booking
 *   that was updated (dates shifted) still counts from its original creation
 *   time; the TTL is a commitment window, not a heartbeat.
 * - Transitions via the state-machine-legal route: PENDING -> CANCELLED.
 *   cancellation_reason is set to 'expired' so downstream analytics can
 *   distinguish system expiry from user cancellation.
 * - cancelled_by is null (no human actor).
 * - BookingCancelled event is dispatched per row so cache invalidation and
 *   notification listeners run (same contract as CancellationService).
 *
 * Concurrency safety:
 * - Each row is updated inside a DB transaction with SELECT ... FOR UPDATE.
 * - The transaction re-reads the status under lock; if a concurrent confirm()
 *   promoted the booking to CONFIRMED between chunk fetch and lock acquire,
 *   the expire is skipped. This avoids racing with BookingController::confirm.
 *
 * Scheduled via routes/console.php every 5 minutes.
 */
final class ExpireStaleBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public bool $deleteWhenMissingModels = true;

    /**
     * The cancellation_reason marker written for system-expired bookings.
     * Downstream reports can filter on this to separate TTL expiry from
     * user-initiated cancellations.
     */
    public const EXPIRED_REASON = 'expired';

    public function handle(): void
    {
        $ttlMinutes = (int) config('booking.pending_ttl_minutes', 30);
        $batchSize = (int) config('booking.pending_expiry_batch_size', 100);

        if ($ttlMinutes <= 0) {
            Log::warning('ExpireStaleBookings skipped: non-positive TTL configured', [
                'pending_ttl_minutes' => $ttlMinutes,
            ]);

            return;
        }

        $threshold = now()->subMinutes($ttlMinutes);
        $expiredCount = 0;

        Booking::query()
            ->where('status', BookingStatus::PENDING)
            ->where('created_at', '<', $threshold)
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id')
            ->each(function (int $bookingId) use (&$expiredCount): void {
                if ($this->expireOne($bookingId)) {
                    $expiredCount++;
                }
            });

        if ($expiredCount > 0) {
            Log::info('ExpireStaleBookings completed', [
                'expired_count' => $expiredCount,
                'ttl_minutes' => $ttlMinutes,
            ]);
        }
    }

    /**
     * Expire a single booking inside its own transaction with row-level lock.
     *
     * Returns true if the booking was transitioned to CANCELLED, false if it
     * was no longer eligible (concurrent confirm/cancel raced ahead).
     */
    private function expireOne(int $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId): bool {
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                return false;
            }

            // Re-check under lock: a concurrent confirm() may have promoted
            // the booking to CONFIRMED between the chunk fetch and this lock.
            if ($booking->status !== BookingStatus::PENDING) {
                return false;
            }

            $booking->update([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => null,
                'cancellation_reason' => self::EXPIRED_REASON,
            ]);

            event(new BookingCancelled($booking));

            return true;
        });
    }
}
