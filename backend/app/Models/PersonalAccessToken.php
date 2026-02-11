<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * PersonalAccessToken Model - Override Sanctum với token expiration + revocation
 *
 * Đây là CRITICAL component: mỗi token PHẢI có expiration date
 * Không có expiration = infinite access = bảo mật thảm họa
 *
 * Token lifecycle:
 * 1. Create: created_at, expires_at (now + 1h hoặc 30 ngày)
 * 2. Use: last_used_at cập nhật mỗi request
 * 3. Refresh: tạo token mới, revoke token cũ
 * 4. Logout: set revoked_at = now
 * 5. Expired: hết hạn → 401 Unauthorized
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

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
     * Scope: Lấy token chưa hết hạn (expires_at NULL hoặc > now)
     *
     * Queries:
     * - expires_at IS NULL OR expires_at > now()
     * - Efficient với index trên expires_at
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: Lấy token chưa bị revoke
     *
     * Queries:
     * - revoked_at IS NULL
     */
    public function scopeNotRevoked($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Scope: Token hoàn toàn hợp lệ (không hết hạn + không revoke)
     *
     * Lần mỗi request gọi, check:
     * - token chưa hết hạn
     * - token chưa bị revoke
     * - last_used_at cập nhật
     */
    public function scopeValid($query)
    {
        return $query->notExpired()->notRevoked();
    }

    /**
     * Scope: Token hết hạn (expires_at < now)
     *
     * Dùng cho cleanup cron job, xóa old tokens
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('expires_at')
                ->where('expires_at', '<', now());
        });
    }

    /**
     * Scope: Lấy token của user theo type (short_lived hoặc long_lived)
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Lấy tất cả token chủ động trên 1 user (exclude current)
     *
     * Dùng cho "logout all other devices" feature
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
     * Check: Token hiện tại có hết hạn?
     *
     * Dùng trong middleware hoặc request:
     * if ($token->isExpired()) {
     *     throw new AuthenticationException('Token đã hết hạn');
     * }
     */
    public function isExpired(): bool
    {
        // expires_at NULL = không hết hạn (legacy)
        if ($this->expires_at === null) {
            return false;
        }

        // expires_at < now = hết hạn
        return $this->expires_at->isPast();
    }

    /**
     * Check: Token có bị revoke không?
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Check: Token hợp lệ (chưa hết hạn + chưa revoke)?
     *
     * Entry point duy nhất để check token validity
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
     * Khi logout hoặc refresh token, gọi method này:
     * $oldToken->revoke();
     */
    public function revoke(): bool
    {
        if ($this->isRevoked()) {
            return false; // Đã revoke rồi
        }

        $this->update(['revoked_at' => now()]);

        return true;
    }

    /**
     * Unrevoke token (nếu cần emergency restore)
     *
     * Cẩn thận: chỉ dùng khi CHẮC CHẮN safe (e.g., app restore)
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
     * Cập nhật last_used_at khi token được dùng
     *
     * Dùng trong middleware, sau khi validate token:
     * $token->recordUsage();
     *
     * Optimization: batch update (1x/phút) thay vì mỗi request
     * để tránh quá nhiều writes
     */
    public function recordUsage(): bool
    {
        // Chỉ update nếu last_used_at cách đó > 1 phút
        if (
            $this->last_used_at === null ||
            $this->last_used_at->addMinute()->isPast()
        ) {
            return (bool) $this->update(['last_used_at' => now()]);
        }

        return false;
    }

    /**
     * Increment refresh count (dùng để detect suspicious activity)
     *
     * Nếu token bị refresh quá nhiều lần trong vài giây → suspicious
     */
    public function incrementRefreshCount(): void
    {
        $this->increment('refresh_count');
    }

    /**
     * Reset refresh count (khi logout hoặc hết session)
     */
    public function resetRefreshCount(): void
    {
        $this->update(['refresh_count' => 0]);
    }

    /**
     * Check: Token này sắp hết hạn không?
     *
     * Dùng để proactive refresh trước khi hết hạn
     * Thường check 5 phút trước expires_at
     */
    public function isExpiringSoon(int $minutesBefore = 5): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        // Nếu expires_at < now + 5 phút → return true
        return $this->expires_at->lessThanOrEqualTo(now()->addMinutes($minutesBefore));
    }

    /**
     * Tính thời gian còn lại (đơn vị: phút)
     *
     * Dùng frontend để display "token hết hạn trong X phút"
     */
    public function getMinutesUntilExpiration(): ?int
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return null;
        }

        return (int) now()->diffInMinutes($this->expires_at, false);
    }

    /**
     * Tính thời gian còn lại (đơn vị: giây)
     *
     * Dùng trong response header: X-Token-Expires-In: 3600
     */
    public function getSecondsUntilExpiration(): ?int
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Logout tất cả device khác (single device login)
     *
     * Logic:
     * 1. Lấy tất cả token của user (trừ current device)
     * 2. Revoke tất cả
     * 3. Return số token bị revoke
     *
     * Ví dụ: Khi user login trên device mới:
     * $newToken->revokeOtherDevices($user);
     */
    public function revokeOtherDevices($user): int
    {
        $count = 0;

        // Lấy tất cả token chưa revoke của user (except current device)
        $otherTokens = $user->tokens()
            ->otherDevices($this->device_id)
            ->get();

        // Revoke từng token
        foreach ($otherTokens as $token) {
            if ($token->revoke()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Logout tất cả session (force logout)
     *
     * Dùng khi user đổi password hoặc phát hiện hack
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
     * Cleanup: Xóa expired tokens cũ hơn 7 ngày
     *
     * Cron job: php artisan schedule:work
     *
     * Dùng trong: Console\Kernel.php
     * $schedule->call(function () {
     *     PersonalAccessToken::cleanup();
     * })->daily();
     */
    public static function cleanup(): void
    {
        // Xóa token expired > 7 ngày
        static::expired()
            ->where('expires_at', '<', now()->subDays(7))
            ->delete();

        // Xóa token revoke > 7 ngày
        static::where('revoked_at', '<', now()->subDays(7))
            ->delete();
    }

    /**
     * Get human-readable status
     *
     * Dùng để debug hoặc log: token.status = 'expired' | 'revoked' | 'valid'
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
