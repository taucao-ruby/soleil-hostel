<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Durable outbox row for a Stripe PaymentIntent cancellation (PAY-03).
 *
 * Written inside ExpireStaleBookings' expiry transaction (no network I/O) and
 * drained by ProcessPaymentCancellationOutbox outside any booking/room lock.
 * The row is the recoverable unit of work: status + attempts + available_at
 * model a visibility-timeout queue with a max-attempt circuit breaker, so a
 * crashed worker cannot strand a cancellation and a hung Stripe call can never
 * hold a booking lock.
 */
final class PaymentCancellationTask extends Model
{
    use HasFactory;

    public const ACTION_CANCEL = 'payment_intent.cancel';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED_PERMANENT = 'failed_permanent';

    /**
     * Statuses that are not terminal — a sweep may still pick these up.
     */
    public const NON_TERMINAL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_RETRYING,
    ];

    /**
     * Max length stored in last_error. Stripe error bodies can echo request
     * fragments; clamp to keep the row small and avoid persisting secrets/PII.
     */
    public const ERROR_MAX_LENGTH = 1000;

    protected $fillable = [
        'booking_id',
        'payment_intent_id',
        'action',
        'status',
        'attempts',
        'available_at',
        'claimed_at',
        'processed_at',
        'last_error_code',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'claimed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Booking, $this>
     *
     * @phpstan-return BelongsTo<Booking, $this>
     *
     * @psalm-return BelongsTo<Booking, static>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Stable idempotency key for the Stripe cancel call. Versioned so a future
     * semantic change can mint a fresh key without colliding with old attempts.
     */
    public function idempotencyKey(): string
    {
        return sprintf('booking:%d:payment_intent_cancel:v1', (int) $this->booking_id);
    }

    /**
     * Rows a drainer may claim now: either eligible by visibility timeout
     * (pending/retrying and available_at has arrived) or a stale processing
     * row whose worker likely crashed before reaching a terminal status. The
     * attempts < $maxAttempts gate is the circuit breaker — exhausted rows are
     * routed to scopeExhausted and failed permanently instead of looping.
     */
    public function scopeClaimable(
        Builder $query,
        Carbon $now,
        Carbon $staleCutoff,
        int $maxAttempts,
    ): Builder {
        return $query
            ->where('attempts', '<', $maxAttempts)
            ->where(function (Builder $q) use ($now, $staleCutoff): void {
                $q->where(function (Builder $eligible) use ($now): void {
                    $eligible
                        ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RETRYING])
                        ->where('available_at', '<=', $now);
                })->orWhere(function (Builder $stale) use ($staleCutoff): void {
                    $stale
                        ->where('status', self::STATUS_PROCESSING)
                        ->where('claimed_at', '<', $staleCutoff);
                });
            });
    }

    /**
     * Non-terminal rows that have burned their attempt budget. The drainer
     * fails these permanently so they surface to an operator rather than being
     * re-claimed forever.
     */
    public function scopeExhausted(Builder $query, int $maxAttempts): Builder
    {
        return $query
            ->whereIn('status', self::NON_TERMINAL_STATUSES)
            ->where('attempts', '>=', $maxAttempts);
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'claimed_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markSucceeded(): void
    {
        $this->update([
            'status' => self::STATUS_SUCCEEDED,
            'processed_at' => now(),
            'last_error' => null,
            'last_error_code' => null,
        ]);
    }

    public function markRetrying(Carbon $availableAt, Throwable|string $error, ?string $code = null): void
    {
        $this->update([
            'status' => self::STATUS_RETRYING,
            'available_at' => $availableAt,
            'last_error_code' => $code,
            'last_error' => self::sanitizeError($error),
        ]);
    }

    public function markFailedPermanent(Throwable|string $error, ?string $code = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED_PERMANENT,
            'processed_at' => now(),
            'last_error_code' => $code,
            'last_error' => self::sanitizeError($error),
        ]);
    }

    /**
     * Strip Stripe secrets / client secrets and clamp length before persisting
     * failure context. Mirrors StripeWebhookEvent::sanitizeError so operational
     * dashboards and audit exports never carry live keys.
     */
    public static function sanitizeError(Throwable|string|null $error): ?string
    {
        if ($error === null) {
            return null;
        }

        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        if ($message === '') {
            return null;
        }

        $patterns = [
            '/sk_(?:live|test)_[A-Za-z0-9]+/' => 'sk_[REDACTED]',
            '/rk_(?:live|test)_[A-Za-z0-9]+/' => 'rk_[REDACTED]',
            '/pi_[A-Za-z0-9]+_secret_[A-Za-z0-9]+/' => 'pi_[REDACTED]_secret_[REDACTED]',
            '/seti_[A-Za-z0-9]+_secret_[A-Za-z0-9]+/' => 'seti_[REDACTED]_secret_[REDACTED]',
            '/"client_secret"\s*:\s*"[^"]+"/' => '"client_secret":"[REDACTED]"',
        ];

        $clean = preg_replace(array_keys($patterns), array_values($patterns), $message) ?? $message;

        if (mb_strlen($clean) > self::ERROR_MAX_LENGTH) {
            $clean = mb_substr($clean, 0, self::ERROR_MAX_LENGTH - 1).'…';
        }

        return $clean;
    }
}
