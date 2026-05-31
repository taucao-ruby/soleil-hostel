<?php

namespace App\Services\Auth;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class TokenRefreshRateLimiter
{
    private const DECAY_SECONDS = 3600;

    public function enforce(PersonalAccessToken $token): void
    {
        $maxAttempts = (int) config('sanctum.max_token_refreshes_per_hour', 10);

        if ($maxAttempts <= 0) {
            return;
        }

        $key = $this->key($token);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new TooManyRequestsHttpException(
                RateLimiter::availableIn($key),
                'Too many token refresh attempts. Please try again later.'
            );
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    private function key(PersonalAccessToken $token): string
    {
        // Token rows rotate on every refresh, so device_id is the stable
        // token-session identifier. Legacy tokens without one fall back to row id.
        $subject = $token->device_id
            ? sprintf(
                '%s:%s:%s',
                (string) $token->tokenable_type,
                (string) $token->tokenable_id,
                (string) $token->device_id
            )
            : sprintf('%s:%s', $token->getTable(), (string) $token->getKey());

        return 'auth:token-refresh:'.hash('sha256', $subject);
    }
}
