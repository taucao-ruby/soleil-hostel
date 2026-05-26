<?php

declare(strict_types=1);

namespace App\Exceptions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Thrown when an OTP resend is requested while the per-user cooldown is still active.
 *
 * Carries the absolute timestamp at which the next resend will be allowed so that
 * HTTP boundary code can surface a precise Retry-After header instead of a fuzzy
 * "wait a bit" message. Raised from inside EmailVerificationCodeService::issue()
 * within a per-user pessimistic lock, so two concurrent resends cannot both bypass
 * the cooldown check (AUTH-004).
 */
final class OtpCooldownException extends RuntimeException
{
    public const ERROR_CODE = 'OTP_COOLDOWN_ACTIVE';

    public function __construct(
        public readonly CarbonInterface $retryAfter,
    ) {
        parent::__construct('Please wait before requesting a new verification code.');
    }

    /**
     * Whole seconds until the next resend is allowed, clamped at zero.
     * Suitable for use as a Retry-After header value (RFC 7231 §7.1.3).
     */
    public function retryAfterSeconds(): int
    {
        $remaining = $this->retryAfter->getTimestamp() - CarbonImmutable::now('UTC')->getTimestamp();

        return max(0, $remaining);
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
