<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\PaymentCancellationTask;
use App\Services\Payment\PaymentIntentCancellationOutcome;
use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\PermissionException;
use Stripe\Exception\RateLimitException;
use Throwable;

/**
 * Drain the payment_cancellation_tasks outbox (PAY-03).
 *
 * ExpireStaleBookings records a durable cancellation task inside its expiry
 * transaction (no network I/O under the booking lock). This job performs the
 * actual Stripe PaymentIntent cancellation OUTSIDE any booking/room lock, so
 * Stripe latency can never stall a live createBooking overlap-lock.
 *
 * Per-task lifecycle:
 *   pending/retrying --claim--> processing --Stripe-->
 *       succeeded            (canceled / already canceled)
 *       retrying             (transient Stripe error; backoff via available_at)
 *       failed_permanent     (non-cancellable PI, config error, or budget burned)
 *
 * Concurrency:
 * - Rows are claimed under SELECT ... FOR UPDATE inside a short transaction
 *   that makes NO Stripe call (mirrors ReconcileStuckStripeWebhookEvents).
 *   The claim bumps status->processing and attempts so a second drainer (or a
 *   re-fired job) cannot pick the same row mid-flight.
 * - A worker that crashes mid-flight leaves the row 'processing'; the next run
 *   re-claims it once claimed_at is older than the stale cutoff.
 * - attempts >= max_attempts is the circuit breaker: such rows are failed
 *   permanently and surfaced to an operator instead of looping forever.
 *
 * Scheduled via routes/console.php every 5 minutes.
 */
final class ProcessPaymentCancellationOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Per-task durability lives in the outbox row, so a whole-job replay would
     * only re-drain rows the next scheduled tick handles anyway. Keep job-level
     * retries off and lean on the schedule + stale-claim recovery instead.
     */
    public int $tries = 1;

    /**
     * Bound the worker so a pathological run of slow Stripe calls cannot pin a
     * queue worker indefinitely. Rows left 'processing' on timeout are re-claimed
     * by the stale-claim path on the next run.
     */
    public int $timeout = 120;

    public function handle(StripeService $stripe): void
    {
        $batchSize = max(1, (int) config('booking.reconciliation.payment_cancellation.batch_size', 50));
        $maxAttempts = max(1, (int) config('booking.reconciliation.payment_cancellation.max_attempts', 10));
        $staleMinutes = max(1, (int) config('booking.reconciliation.payment_cancellation.stale_processing_minutes', 5));

        $exhausted = $this->failExhaustedTasks($maxAttempts, $batchSize);

        $claimed = $this->claimTasks($batchSize, $maxAttempts, $staleMinutes);

        if ($claimed->isEmpty()) {
            if ($exhausted > 0) {
                Log::info('payment_cancellation_outbox.run_complete', [
                    'claimed' => 0,
                    'succeeded' => 0,
                    'retried' => 0,
                    'failed_permanent' => 0,
                    'exhausted' => $exhausted,
                ]);
            }

            return;
        }

        $succeeded = 0;
        $retried = 0;
        $failedPermanent = 0;

        foreach ($claimed as $task) {
            match ($this->processOne($task, $stripe, $maxAttempts)) {
                'succeeded' => $succeeded++,
                'retrying' => $retried++,
                'failed_permanent' => $failedPermanent++,
            };
        }

        Log::info('payment_cancellation_outbox.run_complete', [
            'claimed' => $claimed->count(),
            'succeeded' => $succeeded,
            'retried' => $retried,
            'failed_permanent' => $failedPermanent,
            'exhausted' => $exhausted,
        ]);
    }

    /**
     * Atomically claim a batch of due tasks. The SELECT ... FOR UPDATE
     * serializes the claim against concurrent drainers; bumping status and
     * attempts inside the same transaction means a claimed row will not be
     * re-selected mid-flight. NO Stripe call happens in this transaction.
     *
     * @return Collection<int, PaymentCancellationTask>
     */
    private function claimTasks(int $batchSize, int $maxAttempts, int $staleMinutes): Collection
    {
        return DB::transaction(function () use ($batchSize, $maxAttempts, $staleMinutes): Collection {
            $now = now();
            $staleCutoff = $now->copy()->subMinutes($staleMinutes);

            /** @var Collection<int, PaymentCancellationTask> $rows */
            $rows = PaymentCancellationTask::query()
                ->claimable($now, $staleCutoff, $maxAttempts)
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $task) {
                $task->markProcessing();
            }

            return $rows;
        });
    }

    /**
     * Terminally fail rows that burned their attempt budget so they surface to
     * an operator instead of being re-claimed forever. Runs under the same lock
     * discipline as the claim so two drainers cannot double-transition a row.
     */
    private function failExhaustedTasks(int $maxAttempts, int $batchSize): int
    {
        return DB::transaction(function () use ($maxAttempts, $batchSize): int {
            /** @var Collection<int, PaymentCancellationTask> $rows */
            $rows = PaymentCancellationTask::query()
                ->exhausted($maxAttempts)
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $task) {
                $task->markFailedPermanent(
                    sprintf('cancellation budget exhausted after %d attempts (max %d); manual review required', (int) $task->attempts, $maxAttempts),
                    'budget_exhausted',
                );

                Log::error('payment_cancellation_outbox.budget_exhausted', [
                    'task_id' => $task->id,
                    'booking_id' => $task->booking_id,
                    'payment_intent_id' => $task->payment_intent_id,
                    'attempts' => (int) $task->attempts,
                    'max_attempts' => $maxAttempts,
                ]);
            }

            return $rows->count();
        });
    }

    /**
     * Process a single claimed task. Returns its terminal-ish outcome for the
     * run summary. All Stripe I/O happens here — outside any DB lock.
     *
     * @return 'succeeded'|'retrying'|'failed_permanent'
     */
    private function processOne(PaymentCancellationTask $task, StripeService $stripe, int $maxAttempts): string
    {
        $booking = Booking::query()->whereKey($task->booking_id)->first();

        // Safety guards: never cancel a PaymentIntent that no longer belongs to
        // a terminal booking. The booking is normally CANCELLED here (expiry is
        // the only producer and CANCELLED is terminal); anything else is an
        // invariant violation we refuse to act on.
        if (! $booking instanceof Booking) {
            return $this->failPermanent($task, 'booking missing for cancellation task', 'booking_missing');
        }

        if ($booking->payment_intent_id !== $task->payment_intent_id) {
            return $this->failPermanent($task, 'booking payment_intent_id no longer matches task', 'payment_intent_mismatch');
        }

        if ($booking->status !== BookingStatus::CANCELLED) {
            return $this->failPermanent(
                $task,
                sprintf('booking #%d is %s, not cancelled; refusing to cancel its PaymentIntent', $booking->id, $booking->status->value),
                'booking_not_terminal',
            );
        }

        try {
            $outcome = $stripe->cancelPaymentIntentForBooking($booking, $task->idempotencyKey());
        } catch (RateLimitException|ApiConnectionException $e) {
            return $this->retryOrExhaust($task, $e, class_basename($e), $maxAttempts);
        } catch (AuthenticationException|PermissionException|InvalidRequestException $e) {
            // Permanent: bad credentials/permissions, or Stripe rejected the
            // request for this specific PaymentIntent (e.g. a race changed its
            // state after retrieve). Do not loop; surface for review.
            return $this->failPermanent($task, $e, class_basename($e));
        } catch (ApiErrorException $e) {
            // Other Stripe API/server errors — treat as transient (bounded).
            return $this->retryOrExhaust($task, $e, class_basename($e), $maxAttempts);
        } catch (Throwable $e) {
            // Non-Stripe failure (e.g. ownership mismatch RuntimeException) is a
            // data problem, not a network blip — fail permanently.
            return $this->failPermanent($task, $e, class_basename($e));
        }

        return match ($outcome) {
            PaymentIntentCancellationOutcome::Canceled,
            PaymentIntentCancellationOutcome::AlreadyCanceled => $this->succeed($task, $outcome),
            PaymentIntentCancellationOutcome::NotCancellable => $this->failPermanent(
                $task,
                'PaymentIntent is in a non-cancellable terminal state (e.g. succeeded/captured); manual review required',
                'payment_intent_not_cancellable',
            ),
        };
    }

    /**
     * @return 'succeeded'
     */
    private function succeed(PaymentCancellationTask $task, PaymentIntentCancellationOutcome $outcome): string
    {
        $task->markSucceeded();

        Log::info('payment_cancellation_outbox.canceled', [
            'task_id' => $task->id,
            'booking_id' => $task->booking_id,
            'payment_intent_id' => $task->payment_intent_id,
            'outcome' => $outcome->name,
        ]);

        return 'succeeded';
    }

    /**
     * Transient failure: back off and leave the row claimable for the next run,
     * unless the attempt budget is now spent (then fail permanently).
     *
     * @return 'retrying'|'failed_permanent'
     */
    private function retryOrExhaust(PaymentCancellationTask $task, Throwable $e, string $code, int $maxAttempts): string
    {
        if ((int) $task->attempts >= $maxAttempts) {
            return $this->failPermanent($task, $e, $code);
        }

        $availableAt = $this->backoff($task);
        $task->markRetrying($availableAt, $e, $code);

        Log::warning('payment_cancellation_outbox.transient_error', [
            'task_id' => $task->id,
            'booking_id' => $task->booking_id,
            'payment_intent_id' => $task->payment_intent_id,
            'attempts' => (int) $task->attempts,
            'available_at' => $availableAt->toIso8601String(),
            'error_code' => $code,
        ]);

        return 'retrying';
    }

    /**
     * @return 'failed_permanent'
     */
    private function failPermanent(PaymentCancellationTask $task, Throwable|string $error, string $code): string
    {
        $task->markFailedPermanent($error, $code);

        Log::error('payment_cancellation_outbox.failed_permanent', [
            'task_id' => $task->id,
            'booking_id' => $task->booking_id,
            'payment_intent_id' => $task->payment_intent_id,
            'error_code' => $code,
            'error' => PaymentCancellationTask::sanitizeError($error),
        ]);

        return 'failed_permanent';
    }

    /**
     * Exponential backoff with a cap, keyed on the (already incremented)
     * attempt count.
     */
    private function backoff(PaymentCancellationTask $task): Carbon
    {
        $base = max(1, (int) config('booking.reconciliation.payment_cancellation.initial_backoff_seconds', 60));
        $cap = max($base, (int) config('booking.reconciliation.payment_cancellation.max_backoff_seconds', 3600));

        $exponent = max(0, (int) $task->attempts - 1);
        $delay = $base * (2 ** $exponent);

        // now()->addSeconds() reports CarbonInterface in newer Carbon stubs;
        // Carbon::instance() pins it back to Illuminate\Support\Carbon so the
        // declared return type (and markRetrying's param) stays precise.
        return Carbon::instance(now()->addSeconds((int) min($cap, $delay)));
    }
}
