<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * PersonalAccessToken Model — Sanctum override with token expiration and revocation
 *
 * CRITICAL component: every token MUST have an expiration date
 * No expiration = infinite access = security disaster
 *
 * Token lifecycle:
 * 1. Create: created_at, expires_at (now + 1h or 30 days)
 * 2. Use: last_used_at updated each request
 * 3. Refresh: create new token, revoke old token
 * 4. Logout: set revoked_at = now
 * 5. Expired: token expired → 401 Unauthorized
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    /**
     * Return column names that should receive auto-generated UUIDs on model creation.
     *
     * Overrides HasUuids default (which returns [$this->getKeyName()]) to exclude
     * the bigint auto-increment 'id' primary key. The 'id' column is not a UUID column;
     * assigning a UUID string to it causes a QueryException on SQLite and a type error
     * on PostgreSQL. All UUID-typed fields (token_identifier, device_id, etc.) are set
     * explicitly by the controllers that create tokens, so no auto-fill is needed here.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [];
    }

    /**
     * @var string[]
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'revoked_at',
        'remember_token_id',
        'type',
        'device_id',
        'refresh_count',
        'token_identifier',
        'token_hash',
        'device_fingerprint',
        'last_rotated_at',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'last_used_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /**
     * Get abilities as array
     */
    public function getAbilitiesAttribute($value)
    {
        if (is_null($value)) {
            return ['*'];
        }

        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true) ?? ['*'];
    }

    /**
     * Set abilities - store as JSON string
     */
    public function setAbilitiesAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['abilities'] = json_encode($value);
        } else {
            $this->attributes['abilities'] = $value;
        }
    }

    /**
     * Scope: Get non-expired tokens (expires_at NULL or > now)
     *
     * Queries:
     * - expires_at IS NULL OR expires_at > now()
     * - Efficient with index on expires_at
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: Get non-revoked tokens
     *
     * Queries:
     * - revoked_at IS NULL
     */
    public function scopeNotRevoked($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Scope: Fully valid tokens (not expired + not revoked)
     *
     * Called on each request to check:
     * - token is not expired
     * - token is not revoked
     * - last_used_at is updated
     */
    public function scopeValid($query)
    {
        return $query->notExpired()->notRevoked();
    }

    /**
     * Scope: Expired tokens (expires_at < now)
     *
     * Used by cleanup cron job to delete old tokens
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('expires_at')
                ->where('expires_at', '<', now());
        });
    }

    /**
     * Scope: Get user tokens by type (short_lived or long_lived)
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Get all active tokens for a user (exclude current)
     *
     * Used for "logout all other devices" feature
     */
    public function scopeOtherDevices($query, ?string $currentDeviceId = null)
    {
        return $query->where(function ($q) use ($currentDeviceId) {
            if ($currentDeviceId) {
                $q->where('device_id', '!=', $currentDeviceId);
            }
        })->notRevoked()->notExpired();
    }

    /**
     * Check: Is the current token expired?
     *
     * Used in middleware or request validation:
     * if ($token->isExpired()) {
     *     throw new AuthenticationException('Token expired');
     * }
     */
    public function isExpired(): bool
    {
        // expires_at NULL = never expires (legacy tokens)
        if ($this->expires_at === null) {
            return false;
        }

        // expires_at < now = expired
        return $this->expires_at->isPast();
    }

    /**
     * Check: Is the token revoked?
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Check: Is the token valid (not expired + not revoked)?
     *
     * Single entry point for token validity checks
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }

    /**
     * Revoke token (logout)
     *
     * Logic:
     * - Set revoked_at = now
     * - Save to DB
     * - Return boolean
     *
     * Call this method on logout or token refresh:
     * $oldToken->revoke();
     */
    public function revoke(): bool
    {
        if ($this->isRevoked()) {
            return false; // Already revoked
        }

        $this->update(['revoked_at' => now()]);

        return true;
    }

    /**
     * Unrevoke token (for emergency restore only)
     *
     * Caution: only use when absolutely certain it is safe (e.g., app restore)
     */
    public function unrevoke(): bool
    {
        if (! $this->isRevoked()) {
            return false;
        }

        $this->update(['revoked_at' => null]);

        return true;
    }

    /**
     * Update last_used_at when token is used
     *
     * Used in middleware after token validation:
     * $token->recordUsage();
     *
     * Optimization: batch update (once per minute) instead of every request
     * to avoid excessive DB writes
     */
    public function recordUsage(): bool
    {
        // Only update if last_used_at is more than 1 minute ago
        if (
            $this->last_used_at === null ||
            $this->last_used_at->addMinute()->isPast()
        ) {
            return (bool) $this->update(['last_used_at' => now()]);
        }

        return false;
    }

    /**
     * Increment refresh count (used to detect suspicious activity)
     *
     * If token is refreshed too many times within seconds → suspicious
     */
    public function incrementRefreshCount(): void
    {
        $this->increment('refresh_count');
    }

    /**
     * Reset refresh count (on logout or session end)
     */
    public function resetRefreshCount(): void
    {
        $this->update(['refresh_count' => 0]);
    }

    /**
     * Check: Is this token expiring soon?
     *
     * Used for proactive refresh before expiration
     * Typically checks 5 minutes before expires_at
     */
    public function isExpiringSoon(int $minutesBefore = 5): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        // If expires_at < now + threshold minutes → return true
        return $this->expires_at->lessThanOrEqualTo(now()->addMinutes($minutesBefore));
    }

    /**
     * Calculate remaining time (in minutes)
     *
     * Used by frontend to display "token expires in X minutes"
     */
    public function getMinutesUntilExpiration(): ?int
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return null;
        }

        return (int) now()->diffInMinutes($this->expires_at, false);
    }

    /**
     * Calculate remaining time (in seconds)
     *
     * Used in response header: X-Token-Expires-In: 3600
     */
    public function getSecondsUntilExpiration(): ?int
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Logout all other devices (single device login)
     *
     * Logic:
     * 1. Get all user tokens (except current device)
     * 2. Revoke all
     * 3. Return count of revoked tokens
     *
     * Example: When user logs in on a new device:
     * $newToken->revokeOtherDevices($user);
     */
    public function revokeOtherDevices($user): int
    {
        $count = 0;

        // Get all non-revoked tokens for the user (except current device)
        $otherTokens = $user->tokens()
            ->otherDevices($this->device_id)
            ->get();

        // Revoke each token
        foreach ($otherTokens as $token) {
            if ($token->revoke()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Logout all sessions (force logout)
     *
     * Used when user changes password or detects a compromise
     */
    public function revokeAllUserTokens($user): int
    {
        $count = 0;

        $user->tokens()
            ->notRevoked()
            ->each(function ($token) use (&$count) {
                if ($token->revoke()) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Cleanup: Delete expired tokens older than 7 days
     *
     * Cron job: php artisan schedule:work
     *
     * Used in: Console\Kernel.php
     * $schedule->call(function () {
     *     PersonalAccessToken::cleanup();
     * })->daily();
     */
    public static function cleanup(): void
    {
        // Delete tokens expired more than 7 days ago
        static::expired()
            ->where('expires_at', '<', now()->subDays(7))
            ->delete();

        // Delete tokens revoked more than 7 days ago
        static::where('revoked_at', '<', now()->subDays(7))
            ->delete();
    }

    /**
     * Get human-readable status
     *
     * Used for debugging or logging: token.status = 'expired' | 'revoked' | 'valid'
     */
    public function getStatus(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isRevoked()) {
            return 'revoked';
        }

        return 'valid';
    }
}
