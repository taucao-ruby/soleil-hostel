<?php

namespace App\Services;

use App\Enums\VerificationResult;
use App\Exceptions\OtpCooldownException;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailVerificationCodeService
{
    private const CODE_LENGTH = 6;

    private const EXPIRY_MINUTES = 15;

    public const COOLDOWN_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    /**
     * Issue a new verification code for the user.
     *
     * AUTH-004: the cooldown check and the new-code insert run inside a single
     * DB transaction with a pessimistic lock on the user's latest verification
     * record. Two concurrent resend requests therefore serialize on the same
     * row — one wins, the other re-reads the freshly inserted code, finds the
     * cooldown still active, and throws OtpCooldownException. This prevents
     * burning two codes in parallel and the OTP-farming abuse it enables.
     *
     * @throws OtpCooldownException When the per-user resend cooldown is still active.
     */
    public function issue(User $user): void
    {
        $cooldownSeconds = (int) config('auth.otp_cooldown_seconds', self::COOLDOWN_SECONDS);
        $ttlMinutes = (int) config('auth.otp_ttl_minutes', self::EXPIRY_MINUTES);

        $rawCode = null;

        DB::transaction(function () use ($user, $cooldownSeconds, $ttlMinutes, &$rawCode) {
            $latest = EmailVerificationCode::where('user_id', $user->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($latest !== null && $latest->last_sent_at !== null) {
                $cooldownExpiresAt = $latest->last_sent_at->copy()->addSeconds($cooldownSeconds);

                if ($cooldownExpiresAt->isFuture()) {
                    throw new OtpCooldownException($cooldownExpiresAt);
                }
            }

            // Invalidate every still-active code for this user before issuing the new one,
            // so a previously delivered code cannot be redeemed once a fresher one exists.
            EmailVerificationCode::where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $rawCode = $this->generateCode();

            EmailVerificationCode::create([
                'user_id' => $user->id,
                'code_hash' => hash('sha256', $rawCode),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'attempts' => 0,
                'max_attempts' => self::MAX_ATTEMPTS,
                'last_sent_at' => now(),
            ]);
        });

        // Notification dispatch lives outside the transaction so an SMTP/queue failure
        // cannot roll back the persisted code (the user can simply resend after cooldown).
        $user->notify(new EmailVerificationCodeNotification($rawCode, $ttlMinutes));

        Log::info('Verification code sent', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Verify a submitted code for a user.
     *
     * @return array{result: VerificationResult, attempts_remaining?: int}
     */
    public function verify(User $user, string $rawCode): array
    {
        // Already verified — idempotent, do not overwrite timestamp
        if ($user->hasVerifiedEmail()) {
            return ['result' => VerificationResult::AlreadyVerified];
        }

        return DB::transaction(function () use ($user, $rawCode) {
            $record = EmailVerificationCode::where('user_id', $user->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return ['result' => VerificationResult::NoActiveCode];
            }

            // Check exhausted BEFORE incrementing (the record may already be at max)
            if ($record->isExhausted()) {
                return [
                    'result' => VerificationResult::Exhausted,
                    'attempts_remaining' => 0,
                ];
            }

            // Increment attempts BEFORE comparing to prevent timing oracle
            $record->increment('attempts');

            // Check expiry (belt-and-suspenders with the active() scope)
            if ($record->isExpired()) {
                return ['result' => VerificationResult::Expired];
            }

            // Hash-compare
            $submittedHash = hash('sha256', $rawCode);
            if (! hash_equals($record->code_hash, $submittedHash)) {
                $remaining = $record->max_attempts - $record->attempts;

                // If this attempt exhausted the code, mark it consumed
                if ($remaining <= 0) {
                    $record->update(['consumed_at' => now()]);
                }

                return [
                    'result' => VerificationResult::Invalid,
                    'attempts_remaining' => max(0, $remaining),
                ];
            }

            // SUCCESS — atomic state transition
            $record->update(['consumed_at' => now()]);
            $user->forceFill(['email_verified_at' => now()])->save();

            event(new Verified($user));

            Log::info('Email verified via OTP', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return ['result' => VerificationResult::Verified];
        });
    }

    /**
     * Seconds remaining in the resend cooldown for this user.
     * Returns 0 if resend is allowed.
     */
    public function cooldownRemaining(User $user): int
    {
        $lastCode = EmailVerificationCode::where('user_id', $user->id)
            ->orderByDesc('last_sent_at')
            ->first();

        if (! $lastCode) {
            return 0;
        }

        $cooldownSeconds = (int) config('auth.otp_cooldown_seconds', self::COOLDOWN_SECONDS);
        $elapsed = now()->diffInSeconds($lastCode->last_sent_at, false);
        $remaining = $cooldownSeconds - abs($elapsed);

        return max(0, (int) $remaining);
    }

    /**
     * Generate a cryptographically random 6-digit code.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
