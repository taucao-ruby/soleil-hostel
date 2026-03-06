<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AuthController - Token-based authentication (Sanctum)
 *
 * CRITICAL: Token expiration + refresh logic
 *
 * Endpoints:
 * - POST /api/auth/login → Create token (short_lived or long_lived)
 * - POST /api/auth/refresh → Create new token and revoke the old one
 * - POST /api/auth/logout → Revoke the current token
 * - GET /api/auth/me → Get current user info + token expiration
 *
 * Token lifecycle:
 * 1. Login: Create token (expires_at = now + 1h or 30 days)
 * 2. Use: Update last_used_at on every request
 * 3. Refresh: Create new token + revoke old
 * 4. Logout: Revoke token
 * 5. Expired: Return 401
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Login — Create a personal access token
     *
     * POST /api/auth/login
     * {
     *   "email": "user@example.com",
     *   "password": "password123",
     *   "remember_me": false,
     *   "device_name": "iPhone 15"
     * }
     *
     * Response:
     * {
     *   "message": "Login thành công",
     *   "token": "1|abcdef...",
     *   "user": { id, name, email, ... },
     *   "expires_at": "2025-11-20T14:00:00Z",
     *   "expires_in_minutes": 60,
     *   "type": "short_lived"
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // ========== Validate: Email + Password ==========
        $user = User::where('email', $request->getEmail())->first();

        if (! $user || ! password_verify($request->getPassword(), $user->password)) {
            // Invalid email or password → 401
            throw new AuthenticationException('Email hoặc mật khẩu không đúng.');
        }

        // Auto-resend verification email if unverified
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // ========== Determine: Short-lived or Long-lived ==========
        $shouldRemember = $request->shouldRemember();

        if ($shouldRemember) {
            // Remember me = true → long_lived token (30 days)
            $tokenType = 'long_lived';
            $expiresInMinutes = config('sanctum.long_lived_token_expiration_days') * 24 * 60;
            $expiresAt = now()->addDays(config('sanctum.long_lived_token_expiration_days'));
        } else {
            // Remember me = false → short_lived token (1 hour)
            $tokenType = 'short_lived';
            $expiresInMinutes = config('sanctum.short_lived_token_expiration_minutes');
            $expiresAt = now()->addMinutes($expiresInMinutes);
        }

        // ========== Single Device Login ==========
        // If enabled, revoke all other device tokens on new device login
        if (config('sanctum.single_device_login')) {
            // Revoke all active (non-revoked, non-expired) tokens for the user
            PersonalAccessToken::where('tokenable_id', $user->id)
                ->where('tokenable_type', 'App\\Models\\User')
                ->notExpired()
                ->notRevoked()
                ->each(fn ($token) => $token->revoke());
        }

        // ========== Create Token ==========
        // Device ID: unique UUID per device, used to identify the device
        $deviceId = Str::uuid();

        // Token name: device name or "Web Browser" fallback
        $tokenName = $request->getDeviceName();

        // Generate plain text token
        $plainTextToken = Str::random(40);

        // Hash token (Sanctum stores hashed token in DB)
        $hashedToken = hash('sha256', $plainTextToken);

        // Create token via Eloquent relationship so model events fire (HasUuids, future observers, etc.)
        // The morphMany relation auto-sets tokenable_id + tokenable_type; mutator handles abilities encoding.
        $user->tokens()->create([
            'name' => $tokenName,
            'token' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => $expiresAt,
            'type' => $tokenType,
            'device_id' => $deviceId,
            'refresh_count' => 0,
        ]);

        // ========== Response ==========
        return $this->success([
            'token' => $plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_minutes' => $expiresInMinutes,
            'expires_in_seconds' => $expiresAt->diffInSeconds(now()),
            'type' => $tokenType,
            'device_id' => $deviceId,
        ], 'Đăng nhập thành công.', 201);
    }

    /**
     * Refresh Token — Create a new token and revoke the old one
     *
     * POST /api/auth/refresh
     * Headers: Authorization: Bearer <token>
     *
     * Response:
     * {
     *   "message": "Token refreshed thành công",
     *   "token": "1|new_token...",
     *   "user": { ... },
     *   "expires_at": "2025-11-20T15:00:00Z",
     *   "expires_in_minutes": 60,
     *   "old_token_status": "revoked"
     * }
     *
     * IMPORTANT: Token refresh is a CRITICAL operation
     * - The old token must be extracted from the Authorization header
     * - Validate: token is not expired and not revoked
     * - Create new token (same type: short_lived/long_lived)
     * - Revoke the old token (prevents duplicate token access)
     * - Return the new token
     *
     * If the old token is expired or revoked → return 401 (user must re-authenticate)
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        // ========== Retrieve old token from Authorization header ==========
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            throw new AuthenticationException('Authorization header không tồn tại.');
        }

        // ========== Transaction with pessimistic lock (prevents race conditions) ==========
        return DB::transaction(function () use ($bearerToken) {
            // Lock token row to prevent concurrent refresh
            $oldToken = PersonalAccessToken::where(
                'token',
                hash('sha256', $bearerToken)
            )->lockForUpdate()->first();

            if (! $oldToken) {
                throw new AuthenticationException('Token không hợp lệ.');
            }

            // ========== Validate: Token not expired? ==========
            if ($oldToken->isExpired()) {
                return $this->error('Token đã hết hạn. Vui lòng login lại.', 401, ['code' => 'TOKEN_EXPIRED']);
            }

            // ========== Validate: Token not revoked? ==========
            if ($oldToken->isRevoked()) {
                return $this->error('Token đã bị revoke. Vui lòng login lại.', 401, ['code' => 'TOKEN_REVOKED']);
            }

            // ========== Check: Refresh count (suspicious activity) ==========
            // IMPORTANT: Check threshold BEFORE incrementing using >= to catch exact threshold
            if ($oldToken->refresh_count >= config('sanctum.max_refresh_count_per_hour')) {
                $oldToken->revoke();

                return $this->error('Phát hiện hoạt động bất thường. Vui lòng login lại.', 401, ['code' => 'SUSPICIOUS_ACTIVITY']);
            }

            // Only increment after passing threshold check
            $oldToken->incrementRefreshCount();

            // ========== Get user + token info ==========
            $user = $oldToken->tokenable;
            $tokenType = $oldToken->type;

            // ========== Determine expiration (preserve token type) ==========
            if ($tokenType === 'long_lived') {
                $expiresInMinutes = config('sanctum.long_lived_token_expiration_days') * 24 * 60;
                $expiresAt = now()->addDays(config('sanctum.long_lived_token_expiration_days'));
            } else {
                $expiresInMinutes = config('sanctum.short_lived_token_expiration_minutes');
                $expiresAt = now()->addMinutes($expiresInMinutes);
            }

            // ========== Create new token ==========
            $newPlainTextToken = Str::random(40);
            $newHashedToken = hash('sha256', $newPlainTextToken);

            // Create new token via Eloquent relationship so model events fire.
            // The morphMany relation auto-sets tokenable_id + tokenable_type;
            // accessor returns abilities as array; mutator handles encoding.
            $user->tokens()->create([
                'name' => $oldToken->name,
                'token' => $newHashedToken,
                'abilities' => $oldToken->abilities,
                'expires_at' => $expiresAt,
                'type' => $tokenType,
                'device_id' => $oldToken->device_id,
                'remember_token_id' => $oldToken->remember_token_id,
                'refresh_count' => $oldToken->refresh_count,
            ]);

            // ========== Revoke old token (inside transaction) ==========
            // CRITICAL: Refresh token rotation with pessimistic lock
            // Revoke old token → prevents duplicate token access
            $oldToken->revoke();

            // ========== Response ==========
            return $this->success([
                'token' => $newPlainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'expires_at' => $expiresAt->toIso8601String(),
                'expires_in_minutes' => $expiresInMinutes,
                'expires_in_seconds' => $expiresAt->diffInSeconds(now()),
                'type' => $tokenType,
                'old_token_status' => 'revoked',
            ], 'Token refreshed thành công.');
        });
    }

    /**
     * Logout - Revoke current token
     *
     * POST /api/auth/logout
     * Headers: Authorization: Bearer <token>
     *
     * Response:
     * {
     *   "message": "Logout thành công",
     *   "revoked_at": "2025-11-20T13:45:00Z"
     * }
     *
     * IMPORTANT: After logout, the token is permanently unusable.
     * revoked_at is set → subsequent requests via middleware return 401
     */
    public function logout(Request $request): JsonResponse
    {
        // ========== Retrieve token ==========
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            throw new AuthenticationException('Authorization header không tồn tại.');
        }

        $token = PersonalAccessToken::where(
            'token',
            hash('sha256', $bearerToken)
        )->first();

        if (! $token) {
            throw new AuthenticationException('Token không hợp lệ.');
        }

        // ========== Revoke token ==========
        $token->revoke();
        $revokedAt = $token->revoked_at;

        // ========== Response ==========
        return $this->success([
            'revoked_at' => $revokedAt?->toIso8601String(),
        ], 'Logout thành công.');
    }

    /**
     * Logout All Devices (Bonus)
     *
     * POST /api/auth/logout-all
     * Headers: Authorization: Bearer <token>
     *
     * Revoke all tokens for the user (force logout from all devices)
     *
     * Use cases:
     * - User changes password → force logout from all devices
     * - User detects a security breach → force logout + reset password
     * - 2FA enabled → force logout from all devices (re-auth with 2FA required)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            throw new AuthenticationException('Authorization header không tồn tại.');
        }

        $token = PersonalAccessToken::where(
            'token',
            hash('sha256', $bearerToken)
        )->first();

        if (! $token) {
            throw new AuthenticationException('Token không hợp lệ.');
        }

        // Get user
        $user = $token->tokenable;

        // Revoke all active tokens
        $revokedCount = 0;
        $user->tokens()
            ->notRevoked()
            ->each(function ($t) use (&$revokedCount) {
                if ($t->revoke()) {
                    $revokedCount++;
                }
            });

        return $this->success([
            'revoked_count' => $revokedCount,
        ], "Logout tất cả thiết bị thành công. Đã revoke $revokedCount token.");
    }

    /**
     * Get Current User Info (Bonus)
     *
     * GET /api/auth/me
     * Headers: Authorization: Bearer <token>
     *
     * Return user info + token expiration info
     *
     * Response:
     * {
     *   "user": { ... },
     *   "token": {
     *     "name": "iPhone 15",
     *     "type": "short_lived",
     *     "expires_at": "2025-11-20T14:00:00Z",
     *     "expires_in_minutes": 45,
     *     "expires_in_seconds": 2700,
     *     "created_at": "2025-11-20T13:00:00Z",
     *     "last_used_at": "2025-11-20T13:45:00Z"
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // If $request->user() is null, try to get from the auth guard
        if (! $user) {
            $user = auth()->user();
        }

        // If still null, try sanctum guard
        if (! $user) {
            $user = auth()->guard('sanctum')->user();
        }

        // Last resort - check if there's an accessToken on the request/auth context
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        // Try to get the current access token
        $token = $user->currentAccessToken();

        // If currentAccessToken() doesn't work, fall back to the accessToken property we set in the middleware
        if (! $token && isset($user->accessToken)) {
            $token = $user->accessToken;
        }

        if (! $token) {
            return $this->error('Token not found', 401);
        }

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => [
                'name' => $token->name,
                'type' => $token->type,
                'device_id' => $token->device_id,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'expires_in_minutes' => $token->getMinutesUntilExpiration(),
                'expires_in_seconds' => $token->getSecondsUntilExpiration(),
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
            ],
        ]);
    }
}
