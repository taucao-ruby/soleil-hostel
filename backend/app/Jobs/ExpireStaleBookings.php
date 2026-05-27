<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\PaymentCancellationTask;
use App\Services\FeatureFlag;
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
 * - PAY-03: NO external I/O happens inside this transaction. Cancelling the
 *   Stripe PaymentIntent used to run under the booking row lock, so a Stripe
 *   hang could hold the lock for the full HTTP timeout and stall a concurrent
 *   CreateBookingService overlap-lock for the same room. The cancellation is
 *   now recorded as a durable payment_cancellation_tasks row (atomic with the
 *   CANCELLED transition) and performed later, off the lock, by
 *   ProcessPaymentCancellationOutbox.
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
        // Batch 4 / 3E: Redis-backed kill switch. Defaults to ON so existing
        // schedulers continue to run; flip OFF via `feature:toggle booking.expire_pending off`
        // during incident response without touching env / restarting workers.
        if (! FeatureFlag::get('booking.expire_pending', true)) {
            Log::info('ExpireStaleBookings skipped: feature flag booking.expire_pending is off');

            return;
        }

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
            // Expire pending holds whose payment never reached a committed
            // state. This INCLUDES the offline policies (NOT_REQUIRED /
            // OFFLINE_DUE): an abandoned pay-at-property or no-payment-required
            // hold still blocks the room via ACTIVE_STATUSES and must TTL-expire
            // exactly like an online hold. AUTHORIZED and PAID are deliberately
            // excluded — those bookings are mid-confirmation (the payment/verify
            // endpoint or the succeeded webhook will promote them to CONFIRMED)
            // and must never be auto-cancelled out from under a settled payment.
            ->whereIn('payment_status', [
                PaymentStatus::NOT_REQUIRED->value,
                PaymentStatus::OFFLINE_DUE->value,
                PaymentStatus::REQUIRES_CONFIRMATION->value,
                PaymentStatus::REQUIRES_PAYMENT_METHOD->value,
                PaymentStatus::REQUIRES_ACTION->value,
                PaymentStatus::PROCESSING->value,
                PaymentStatus::FAILED->value,
            ])
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
     *
     * PAY-03: this transaction performs ONLY local DB work — lock, re-validate,
     * transition, audit, and (if a PaymentIntent exists) enqueue a durable
     * payment_cancellation_tasks row. No Stripe/network call runs here, so the
     * booking row lock is held for microseconds and can never be stalled by
     * Stripe latency. The actual PaymentIntent cancel happens off the lock in
     * ProcessPaymentCancellationOutbox.
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

            $booking = $booking->transitionTo(BookingStatus::CANCELLED);
            $booking->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => null,
                'cancellation_reason' => self::EXPIRED_REASON,
            ])->save();

            $this->enqueuePaymentIntentCancellation($booking);

            event(new BookingCancelled($booking));

            return true;
        });
    }

    /**
     * Record a durable intent to cancel the booking's Stripe PaymentIntent.
     *
     * Runs inside the expiry transaction so the task and the CANCELLED
     * transition commit atomically — there is no window where the booking is
     * cancelled but the cancellation intent is lost. firstOrCreate keys on the
     * table's UNIQUE (booking_id, payment_intent_id, action), so repeated
     * expiry runs (or a re-fired job) never enqueue duplicate Stripe work.
     */
    private function enqueuePaymentIntentCancellation(Booking $booking): void
    {
        if ($booking->payment_intent_id === null) {
            return;
        }

        PaymentCancellationTask::query()->firstOrCreate(
            [
                'booking_id' => $booking->id,
                'payment_intent_id' => $booking->payment_intent_id,
                'action' => PaymentCancellationTask::ACTION_CANCEL,
            ],
            [
                'status' => PaymentCancellationTask::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );
    }
}
