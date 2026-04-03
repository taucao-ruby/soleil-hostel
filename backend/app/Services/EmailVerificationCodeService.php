<?php

namespace App\Services;

use App\Enums\VerificationResult;
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
     * Generate a new verification code for the user.
     * Invalidates all existing active codes first.
     * Enforces resend cooldown.
     *
     * @return array{code: EmailVerificationCode, cooldown: int}|array{cooldown: int}
     */
    public function issue(User $user): array
    {
        // Check cooldown
        $cooldown = $this->cooldownRemaining($user);
        if ($cooldown > 0) {
            return ['cooldown' => $cooldown];
        }

        // Generate raw code
        $rawCode = $this->generateCode();
        $codeHash = hash('sha256', $rawCode);

        $record = DB::transaction(function () use ($user, $codeHash) {
            // Invalidate all existing active codes for this user
            EmailVerificationCode::where('user_id', $user->id)
                ->active()
                ->update(['consumed_at' => now()]);

            // Create new code
            return EmailVerificationCode::create([
                'user_id' => $user->id,
                'code_hash' => $codeHash,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
                'attempts' => 0,
                'max_attempts' => self::MAX_ATTEMPTS,
                'last_sent_at' => now(),
            ]);
        });

        // Send notification with the raw code (not the hash)
        $user->notify(new EmailVerificationCodeNotification($rawCode, self::EXPIRY_MINUTES));

        Log::info('Verification code sent', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return ['code' => $record, 'cooldown' => 0];
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

        $elapsed = now()->diffInSeconds($lastCode->last_sent_at, false);
        $remaining = self::COOLDOWN_SECONDS - abs($elapsed);

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
