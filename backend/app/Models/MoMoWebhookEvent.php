<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class MoMoWebhookEvent extends Model
{
    use HasFactory;

    /**
     * Max length stored in the `error` column. A MoMo failure body can echo back
     * fragments of the signed request payload — clamp to a safe operational
     * length to keep the row small and avoid persisting secret/PII fragments
     * present in upstream error payloads.
     */
    public const ERROR_MAX_LENGTH = 1000;

    /**
     * Eloquent would infer `mo_mo_webhook_events` from the class name's internal
     * capital (Str::snake('MoMoWebhookEvent') === 'mo_mo_webhook_event'), which
     * does not match the migration. Pin the real table name so model + migration
     * agree.
     */
    protected $table = 'momo_webhook_events';

    protected $fillable = [
        'order_id',
        'request_id',
        'trans_id',
        'type',
        'status',
        'result_code',
        'payload',
        'processed_at',
        'failed_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'result_code' => 'integer',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
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
     * The `error` column is truncated and stripped of MoMo secret/HMAC-looking
     * substrings before being persisted. Callers can pass either a Throwable or
     * a plain string; the default null preserves call sites that do not surface
     * a message.
     */
    public function markFailed(Throwable|string|null $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => self::sanitizeError($error),
        ]);
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

        // Scrub the shared MoMo secret value if it leaked into the message, then
        // any raw 64-hex HMAC signature and the public accessKey. A MoMo failure
        // message can echo back the signed payload, so neither the secret nor a
        // raw signature should land in operational dashboards or audit exports.
        $secret = config('services.momo.secret_key');

        if (is_string($secret) && $secret !== '') {
            $message = str_replace($secret, '[REDACTED_SECRET]', $message);
        }

        $patterns = [
            '/"signature"\s*:\s*"[A-Fa-f0-9]{64}"/' => '"signature":"[REDACTED]"',
            '/\bsignature=[A-Fa-f0-9]{64}\b/' => 'signature=[REDACTED]',
            '/\b[A-Fa-f0-9]{64}\b/' => '[REDACTED_HMAC]',
            '/"accessKey"\s*:\s*"[^"]+"/' => '"accessKey":"[REDACTED]"',
            '/\baccessKey=[A-Za-z0-9]+/' => 'accessKey=[REDACTED]',
        ];

        $clean = preg_replace(array_keys($patterns), array_values($patterns), $message) ?? $message;

        if (mb_strlen($clean) > self::ERROR_MAX_LENGTH) {
            $clean = mb_substr($clean, 0, self::ERROR_MAX_LENGTH - 1).'…';
        }

        return $clean;
    }
}
