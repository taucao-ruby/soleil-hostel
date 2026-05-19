<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

final class StripeWebhookEvent extends Model
{
    use HasFactory;

    /**
     * Max length stored in the `error` column. Stripe error bodies can be
     * arbitrarily large and may echo back fragments of the request — clamp
     * to a safe operational length to keep the row small and to avoid
     * accidentally persisting client secrets or PII present in upstream
     * error payloads.
     */
    public const ERROR_MAX_LENGTH = 1000;

    /**
     * Event types the reconciliation reaper is allowed to replay.
     *
     * The reaper MUST NOT dynamically dispatch arbitrary event types: each
     * supported type needs an explicit, idempotent server-side handler. New
     * types are added one at a time, with tests proving the replay path.
     */
    public const RECONCILABLE_TYPES = [
        'payment_intent.succeeded',
    ];

    protected $fillable = [
        'stripe_event_id',
        'type',
        'status',
        'payload',
        'processed_at',
        'error',
        'failed_at',
        'reconcile_started_at',
        'reconcile_finished_at',
        'reconcile_attempts',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'reconcile_started_at' => 'datetime',
        'reconcile_finished_at' => 'datetime',
        'reconcile_attempts' => 'integer',
    ];

    public function markProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Mark the event as terminally failed with sanitized failure context.
     *
     * The `error` column is truncated and stripped of secrets-looking
     * substrings (Stripe sk_/rk_ keys, client_secret values) before being
     * persisted. Callers can pass either a Throwable or a plain string;
     * the default null preserves backward compatibility with the existing
     * controller call sites that did not surface a message.
     */
    public function markFailed(Throwable|string|null $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => self::sanitizeError($error),
        ]);
    }

    /**
     * Record a non-terminal reconciliation attempt that ended in a transient
     * error. The event stays in `processing` so the next reaper run retries;
     * the error column carries the sanitized last-failure context and the
     * reconcile_finished_at timestamp marks the end of this attempt.
     */
    public function recordReconciliationError(Throwable|string $error): void
    {
        $this->update([
            'error' => self::sanitizeError($error),
            'reconcile_finished_at' => now(),
        ]);
    }

    /**
     * Stale rows the reaper is eligible to consider — `processing` status,
     * supported event type, older than the cutoff.
     */
    public function scopeStaleProcessing(Builder $query, Carbon $cutoff): Builder
    {
        return $query
            ->where('status', 'processing')
            ->where('created_at', '<', $cutoff)
            ->whereIn('type', self::RECONCILABLE_TYPES);
    }

    /**
     * Resolve the PaymentIntent id this event refers to. Defensive: payloads
     * are stored as-received from Stripe.
     */
    public function paymentIntentId(): ?string
    {
        $payload = $this->payload ?? [];

        $id = data_get($payload, 'data.object.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private static function sanitizeError(Throwable|string|null $error): ?string
    {
        if ($error === null) {
            return null;
        }

        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        if ($message === '') {
            return null;
        }

        // Strip anything that looks like a Stripe secret/restricted key or a
        // PaymentIntent client_secret. We do not want these landing in
        // operational dashboards or audit exports.
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
