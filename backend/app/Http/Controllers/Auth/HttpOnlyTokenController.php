<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Auth\AuthenticationException;

/**
 * HttpOnlyTokenController - Token authentication qua httpOnly cookie
 * 
 * CRITICAL SECURITY:
 * - Token lưu trong httpOnly cookie, KHÔNG localStorage
 * - XSS attacks không thể lấy được token
 * - CSRF protection qua SameSite=Strict
 * - Token rotation + device binding mitigates token theft
 */
class HttpOnlyTokenController extends Controller
{
    /**
     * LOGIN - Cấp httpOnly Cookie token
     * 
     * POST /api/auth/login-httponly
     * 
     * CRITICAL SECURITY FLOW:
     * 1. Authenticate user
     * 2. Create token_identifier (UUID) + token_hash
     * 3. Set httpOnly Cookie (Secure, SameSite=Strict)
     * 4. Return CSRF token để frontend dùng X-XSRF-TOKEN header
     * 
     * Result: XSS không access được token
     */
    public function login(Request $request): JsonResponse
    {
        // Validate credentials
        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !password_verify($request->input('password'), $user->password)) {
            throw new AuthenticationException('Email hoặc mật khẩu không đúng.');
        }

        // ========== Token Configuration ==========
        $rememberMe = $request->boolean('remember_me', false);
        $tokenType = $rememberMe ? 'long_lived' : 'short_lived';
        
        if ($tokenType === 'long_lived') {
            $expiresInMinutes = config('sanctum.long_lived_token_expiration_days') * 24 * 60;
            $expiresAt = now()->addDays(config('sanctum.long_lived_token_expiration_days'));
        } else {
            $expiresInMinutes = config('sanctum.short_lived_token_expiration_minutes');
            $expiresAt = now()->addMinutes($expiresInMinutes);
        }

        // ========== Generate Security Identifiers ==========
        // token_identifier: UUID dùng trong cookie
        // token_hash: hash(identifier) dùng lookup DB
        $tokenIdentifier = Str::uuid()->toString();
        $tokenHash = hash('sha256', $tokenIdentifier);

        // ========== Device Fingerprint (Optional) ==========
        // Bind token với device (phòng token theft nếu cookie bị leak)
        $deviceFingerprint = $this->generateDeviceFingerprint($request);

        // ========== Create Token in DB ==========
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'name' => 'httponly-web-cookie',
            'token' => $tokenHash,  // Hashed identifier
            'token_identifier' => $tokenIdentifier,  // Plain UUID dùng trong cookie
            'token_hash' => $tokenHash,  // Index để lookup nhanh
            'abilities' => json_encode(['*']),
            'tokenable_id' => $user->id,
            'tokenable_type' => 'App\\Models\\User',
            'expires_at' => $expiresAt->toDateTimeString(),
            'type' => $tokenType,
            'device_id' => $request->header('X-Device-ID') ?? Str::uuid()->toString(),
            'device_fingerprint' => $deviceFingerprint,
            'refresh_count' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        // ========== Build Response ==========
        $response = response()->json([
            'success' => true,
            'message' => 'Login thành công. Token đã được set trong httpOnly cookie.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'expires_in_minutes' => $expiresInMinutes,
            'expires_at' => $expiresAt->toIso8601String(),
            'token_type' => $tokenType,
            // CSRF token dùng cho X-XSRF-TOKEN header
            // Generate a random token for HTTP-only API
            'csrf_token' => \Illuminate\Support\Str::random(64),
        ], 200);

        // ========== SET httpOnly COOKIE ==========
        // 🔐 CRITICAL SECURITY FLAGS:
        // - httpOnly=true: JavaScript không thể access qua document.cookie
        // - Secure=true: Chỉ gửi qua HTTPS
        // - SameSite=Strict: Không gửi cross-site requests (phòng CSRF + XSS worm)
        // - Path=/: Accessible ở tất cả routes
        // - Domain=.soleilhostel.com: Share với subdomains
        
        $response->cookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),  // name
            $tokenIdentifier,  // Plain UUID token (value)
            ceil($expiresInMinutes / 60),  // minutes
            '/',  // path
            config('session.domain'),  // domain
            config('app.env') === 'production',  // secure (HTTPS only in production)
            true,  // httpOnly (⚡ XSS cannot steal via JavaScript)
            false,  // raw
            'strict'  // sameSite (⚡ CSRF protected, no cross-site sending)
        );

        return $response;
    }

    /**
     * REFRESH TOKEN - Rotate token (mitigates token theft)
     * 
     * POST /api/auth/refresh-httponly
     * 
     * SECURITY FLOW:
     * 1. Validate old token từ cookie
     * 2. Check expiration + refresh count
     * 3. Create new token với fresh refresh_count
     * 4. Revoke old token
     * 5. Set new httpOnly cookie
     * 
     * Result: Leaked tokens become useless after rotation
     */
    public function refresh(Request $request): JsonResponse
    {
        // Middleware already validated token, get it from request attributes
        $oldToken = $request->attributes->get('token');

        if (!$oldToken) {
            throw new AuthenticationException('Token cookie tidak ditemukan.');
        }

        // ========== Validate Token ==========
        // Middleware already validated expiration/revocation/suspicious activity
        // No additional validation needed here
        
        // ========== Check Suspicious Activity ==========
        // Detect token refresh abuse (possible token theft)
        // Check BEFORE incrementing to catch the threshold exactly
        if ($oldToken->refresh_count >= config('sanctum.max_refresh_count_per_hour', 10)) {
            $oldToken->revoke();

            return response()->json([
                'success' => false,
                'message' => 'Phát hiện hoạt động bất thường. Vui lòng login lại.',
                'code' => 'SUSPICIOUS_ACTIVITY',
            ], 401);
        }

        $oldToken->incrementRefreshCount();

        // ========== Create New Token ==========
        $user = $oldToken->tokenable;
        $tokenType = $oldToken->type;

        if ($tokenType === 'long_lived') {
            $expiresInMinutes = config('sanctum.long_lived_token_expiration_days') * 24 * 60;
            $expiresAt = now()->addDays(config('sanctum.long_lived_token_expiration_days'));
        } else {
            $expiresInMinutes = config('sanctum.short_lived_token_expiration_minutes');
            $expiresAt = now()->addMinutes($expiresInMinutes);
        }

        // New token identifier
        $newTokenIdentifier = Str::uuid()->toString();
        $newTokenHash = hash('sha256', $newTokenIdentifier);

        $newTokenId = DB::table('personal_access_tokens')->insertGetId([
            'name' => $oldToken->name,
            'token' => $newTokenHash,
            'token_identifier' => $newTokenIdentifier,
            'token_hash' => $newTokenHash,
            'abilities' => json_encode($oldToken->abilities ?? ['*']),
            'tokenable_id' => $user->id,
            'tokenable_type' => 'App\\Models\\User',
            'expires_at' => $expiresAt->toDateTimeString(),
            'type' => $tokenType,
            'device_id' => $oldToken->device_id,
            'device_fingerprint' => $oldToken->device_fingerprint,
            'refresh_count' => $oldToken->refresh_count,  // Carry over refresh count to track total refreshes
            'last_rotated_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        // Revoke old token
        $oldToken->revoke();

        // ========== Response ==========
        $response = response()->json([
            'success' => true,
            'message' => 'Token refreshed thành công.',
            'expires_in_minutes' => $expiresInMinutes,
            'expires_at' => $expiresAt->toIso8601String(),
            'token_type' => $tokenType,
            'csrf_token' => csrf_token(),
        ], 200);

        // ========== Set New httpOnly Cookie ==========
        $response->cookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),  // name
            $newTokenIdentifier,  // value
            ceil($expiresInMinutes / 60),  // minutes
            '/',  // path
            config('session.domain'),  // domain
            config('app.env') === 'production',  // secure
            true,  // httpOnly
            false,  // raw
            'strict'  // sameSite
        );

        return $response;
    }

    /**
     * LOGOUT - Revoke token + delete cookie
     * 
     * POST /api/auth/logout-httponly
     * 
     * SECURITY: Removes both token from DB + clears httpOnly cookie
     * Ensures token cannot be used again even if cookie wasn't cleared client-side
     */
    public function logout(Request $request): JsonResponse
    {
        // Middleware already validated token, get it from request attributes
        $token = $request->attributes->get('token');

        if ($token) {
            $token->revoke();
        }

        // Clear httpOnly cookie by setting empty value with past expiry
        $response = response()->json([
            'success' => true,
            'message' => 'Logout thành công.',
        ], 200);

        $response->cookie(
            env('SANCTUM_COOKIE_NAME', 'soleil_token'),  // name
            '',  // value (empty to clear)
            -1,  // minutes (expire immediately)
            '/',  // path
            config('session.domain'),  // domain
            config('app.env') === 'production',  // secure
            true,  // httpOnly
            false,  // raw
            'strict'  // sameSite
        );

        return $response;
    }

    /**
     * ME - Get current user info từ httpOnly cookie token
     * 
     * GET /api/auth/me-httponly
     */
    public function me(Request $request): JsonResponse
    {
        // Middleware already validated token, get it from request attributes
        $token = $request->attributes->get('token');

        if (!$token) {
            throw new AuthenticationException('Token cookie không ditemukan.');
        }

        $user = $token->tokenable;

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => [
                'name' => $token->name,
                'type' => $token->type,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'expires_in_minutes' => $token->getMinutesUntilExpiration(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Generate device fingerprint từ request headers
     * 
     * Bind token với device (nếu cookie bị leak, hacker tidak thể dùng từ device khác)
     * Trade-off: Legitimate users changing device sẽ bị reject
     * 
     * Components:
     * - User-Agent: Browser identification
     * - Accept-Language: Language preference
     * - Accept-Encoding: Compression support
     */
    private function generateDeviceFingerprint(Request $request): ?string
    {
        if (!config('sanctum.verify_device_fingerprint')) {
            return null;
        }

        $components = [
            $request->header('User-Agent', ''),
            $request->header('Accept-Language', ''),
            $request->header('Accept-Encoding', ''),
        ];

        return hash('sha256', implode('|', $components));
    }
}
