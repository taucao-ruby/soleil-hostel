<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AuthController - Token-based authentication (Sanctum)
 * 
 * CRITICAL: Token expiration + refresh logic
 * 
 * Endpoints:
 * - POST /api/auth/login → Tạo token (short_lived hoặc long_lived)
 * - POST /api/auth/refresh → Tạo token mới + revoke token cũ
 * - POST /api/auth/logout → Revoke token hiện tại
 * - GET /api/auth/me → Get current user info + token expiration
 * 
 * Token lifecycle:
 * 1. Login: Create token (expires_at = now + 1h hoặc 30 ngày)
 * 2. Use: Update last_used_at mỗi request
 * 3. Refresh: Create new token + revoke old
 * 4. Logout: Revoke token
 * 5. Expired: Return 401
 */
class AuthController
{
    /**
     * Login - Tạo personal access token
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

        if (!$user || !password_verify($request->getPassword(), $user->password)) {
            // Email hoặc password sai → 401
            throw new AuthenticationException('Email hoặc mật khẩu không đúng.');
        }

        // ========== Determine: Short-lived hoặc Long-lived ==========
        $shouldRemember = $request->shouldRemember();
        
        if ($shouldRemember) {
            // Remember me = true → long_lived token (30 ngày)
            $tokenType = 'long_lived';
            $expiresInMinutes = config('sanctum.long_lived_token_expiration_days') * 24 * 60;
            $expiresAt = now()->addDays(config('sanctum.long_lived_token_expiration_days'));
        } else {
            // Remember me = false → short_lived token (1 giờ)
            $tokenType = 'short_lived';
            $expiresInMinutes = config('sanctum.short_lived_token_expiration_minutes');
            $expiresAt = now()->addMinutes($expiresInMinutes);
        }

        // ========== Single Device Login ==========
        // Nếu enabled, logout tất cả device khác khi login device mới
        if (config('sanctum.single_device_login')) {
            // Revoke tất cả token chưa revoke/hết hạn của user
            PersonalAccessToken::where('user_id', $user->id)
                ->notExpired()
                ->notRevoked()
                ->each(fn($token) => $token->revoke());
        }

        // ========== Create Token ==========
        // Device ID: UUID unique per device (dùng để identify device)
        $deviceId = Str::uuid();

        // Token name: device name hoặc "Web Browser"
        $tokenName = $request->getDeviceName();

        // Tạo plain text token
        $plainTextToken = \Illuminate\Support\Str::random(40);
        
        // Hash token (Sanctum stores hashed token in DB)
        $hashedToken = hash('sha256', $plainTextToken);

        // Manually create token model using raw SQL to avoid Eloquent ability casting issues
        $tokenModel = DB::table('personal_access_tokens')->insertGetId([
            'name' => $tokenName,
            'token' => $hashedToken,
            'abilities' => '["*"]',  // Store as literal JSON string
            'expires_at' => $expiresAt->toDateTimeString(),
            'type' => $tokenType,
            'device_id' => $deviceId,
            'refresh_count' => 0,
            'user_id' => $user->id,
            'tokenable_id' => $user->id,
            'tokenable_type' => 'App\\Models\\User',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        // Retrieve the created model
        $tokenModel = PersonalAccessToken::find($tokenModel);

        // ========== Response ==========
        return response()->json([
            'message' => 'Đăng nhập thành công.',
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
        ], 201);
    }

    /**
     * Refresh Token - Tạo token mới + revoke token cũ
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
     * IMPORTANT: Token refresh là CRITICAL operation
     * - Phải lấy token cũ từ Authorization header
     * - Validate: token không expired, không revoke
     * - Create token mới (cùng loại: short_lived/long_lived)
     * - Revoke token cũ (tránh duplicate token)
     * - Return token mới
     * 
     * Nếu token cũ expired/revoke → return 401 (phải login lại)
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        // ========== Lấy token cũ từ Authorization header ==========
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            throw new AuthenticationException('Authorization header không tồn tại.');
        }

        // Tìm token ở database
        $oldToken = PersonalAccessToken::where(
            'token',
            hash('sha256', $bearerToken)
        )->first();

        if (!$oldToken) {
            throw new AuthenticationException('Token không hợp lệ.');
        }

        // ========== Validate: Token chưa expire? ==========
        if ($oldToken->isExpired()) {
            return response()->json([
                'message' => 'Token đã hết hạn. Vui lòng login lại.',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        }

        // ========== Validate: Token chưa revoke? ==========
        if ($oldToken->isRevoked()) {
            return response()->json([
                'message' => 'Token đã bị revoke. Vui lòng login lại.',
                'code' => 'TOKEN_REVOKED',
            ], 401);
        }

        // ========== Check: Refresh count (suspicious activity) ==========
        // Increment refresh count BEFORE checking to detect suspicious activity
        $oldToken->incrementRefreshCount();
        
        if ($oldToken->refresh_count > config('sanctum.max_refresh_count_per_hour')) {
            $oldToken->revoke();

            return response()->json([
                'message' => 'Phát hiện hoạt động bất thường. Vui lòng login lại.',
                'code' => 'SUSPICIOUS_ACTIVITY',
            ], 401);
        }

        // ========== Get user + token info ==========
        $user = $oldToken->tokenable;
        $tokenType = $oldToken->type;

        // ========== Determine expiration (giữ nguyên type) ==========
        if ($tokenType === 'long_lived') {
            $expiresInMinutes = config('sanctum.long_lived_token_expiration_days') * 24 * 60;
            $expiresAt = now()->addDays(config('sanctum.long_lived_token_expiration_days'));
        } else {
            $expiresInMinutes = config('sanctum.short_lived_token_expiration_minutes');
            $expiresAt = now()->addMinutes($expiresInMinutes);
        }

        // ========== Create token mới ==========
        $newPlainTextToken = \Illuminate\Support\Str::random(40);
        $newHashedToken = hash('sha256', $newPlainTextToken);

        // Manually create new token using raw SQL
        $newTokenId = DB::table('personal_access_tokens')->insertGetId([
            'name' => $oldToken->name, // Giữ nguyên device name
            'token' => $newHashedToken,
            'abilities' => is_array($oldToken->abilities) 
                ? json_encode($oldToken->abilities)
                : $oldToken->attributes['abilities'], // Get raw from DB
            'expires_at' => $expiresAt->toDateTimeString(),
            'type' => $tokenType,
            'device_id' => $oldToken->device_id,
            'remember_token_id' => $oldToken->remember_token_id,
            'refresh_count' => $oldToken->refresh_count, // Copy refresh count to track token chain
            'user_id' => $user->id,
            'tokenable_id' => $user->id,
            'tokenable_type' => 'App\\Models\\User',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $newTokenModel = PersonalAccessToken::find($newTokenId);

        // ========== Revoke token cũ ==========
        // CRITICAL: Refresh token rotation
        // Revoke token cũ → tránh duplicate token access
        $oldToken->revoke();
        // Note: refresh_count already incremented in suspicious activity check above

        // ========== Response ==========
        return response()->json([
            'message' => 'Token refreshed thành công.',
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
        ], 200);
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
     * IMPORTANT: Nếu logout → token không thể dùng tiếp
     * revoked_at được set → middleware sẽ return 401
     */
    public function logout(Request $request): JsonResponse
    {
        // ========== Lấy token ==========
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            throw new AuthenticationException('Authorization header không tồn tại.');
        }

        $token = PersonalAccessToken::where(
            'token',
            hash('sha256', $bearerToken)
        )->first();

        if (!$token) {
            throw new AuthenticationException('Token không hợp lệ.');
        }

        // ========== Revoke token ==========
        $token->revoke();
        $revokedAt = $token->revoked_at;

        // ========== Response ==========
        return response()->json([
            'message' => 'Logout thành công.',
            'revoked_at' => $revokedAt?->toIso8601String(),
        ], 200);
    }

    /**
     * Logout All Devices (Bonus)
     * 
     * POST /api/auth/logout-all
     * Headers: Authorization: Bearer <token>
     * 
     * Revoke tất cả token của user (force logout all devices)
     * 
     * Dùng khi:
     * - User đổi password → force logout tất cả
     * - User phát hiện hack → force logout + reset password
     * - 2FA enabled → force logout tất cả (re-auth with 2FA)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            throw new AuthenticationException('Authorization header không tồn tại.');
        }

        $token = PersonalAccessToken::where(
            'token',
            hash('sha256', $bearerToken)
        )->first();

        if (!$token) {
            throw new AuthenticationException('Token không hợp lệ.');
        }

        // Get user
        $user = $token->tokenable;

        // Revoke tất cả token
        $revokedCount = 0;
        $user->tokens()
            ->notRevoked()
            ->each(function ($t) use (&$revokedCount) {
                if ($t->revoke()) {
                    $revokedCount++;
                }
            });

        return response()->json([
            'message' => "Logout tất cả thiết bị thành công. Đã revoke $revokedCount token.",
            'revoked_count' => $revokedCount,
        ], 200);
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
        if (!$user) {
            $user = auth()->user();
        }
        
        // If still null, try sanctum guard
        if (!$user) {
            $user = auth()->guard('sanctum')->user();
        }
        
        // Last resort - check if there's an accessToken on the request/auth context
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }
        
        // Try to get the current access token
        $token = $user->currentAccessToken();
        
        // If currentAccessToken() doesn't work, fall back to the accessToken property we set in the middleware
        if (!$token && isset($user->accessToken)) {
            $token = $user->accessToken;
        }

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found',
            ], 401);
        }

        return response()->json([
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
        ], 200);
    }
}
