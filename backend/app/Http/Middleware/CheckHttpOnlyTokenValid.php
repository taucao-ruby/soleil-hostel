<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * CheckHttpOnlyTokenValid - Validate httpOnly cookie token
 * 
 * CRITICAL SECURITY:
 * - Token luôn trong httpOnly cookie, KHÔNG Authorization header
 * - XSS cannot access, CSRF mitigated by SameSite=Strict
 * - Validates: Existence, expiration, revocation, suspicious activity
 * - Sets $request->auth() để dùng trong controllers
 * 
 * Middleware Pipeline:
 * 1. Extract token_identifier từ httpOnly cookie
 * 2. Hash & lookup token_hash trong DB
 * 3. Validate token state (expired, revoked, refresh_count abuse)
 * 4. Validate device fingerprint nếu enabled
 * 5. Attach token & user vào $request attributes
 */
class CheckHttpOnlyTokenValid
{
    public function handle(Request $request, Closure $next)
    {
        // ========== Extract Token from Cookie ==========
        // Browser automatically sends httpOnly cookie
        $cookieName = env('SANCTUM_COOKIE_NAME', 'soleil_token');
        $tokenIdentifier = $request->cookie($cookieName);

        // Fallback: Try to extract from Cookie header for testing compatibility
        if (!$tokenIdentifier && $request->hasHeader('Cookie')) {
            $cookieHeader = $request->header('Cookie');
            // Parse cookie header: "name=value; other=value"
            $cookies = array_map('trim', explode(';', $cookieHeader));
            foreach ($cookies as $cookie) {
                if (strpos($cookie, $cookieName . '=') === 0) {
                    $tokenIdentifier = substr($cookie, strlen($cookieName . '='));
                    break;
                }
            }
        }

        if (!$tokenIdentifier) {
            throw new AuthenticationException('Unauthenticated. Please log in.');
        }

        // ========== Find Token by Hash ==========
        // token_hash stored in DB allows secure lookup without plaintext comparison
        $tokenHash = hash('sha256', $tokenIdentifier);

        $token = PersonalAccessToken::where('token_hash', $tokenHash)->first();

        if (!$token) {
            throw new AuthenticationException('Unauthenticated. Please log in.');
        }

        // ========== Validate Token Expiration ==========
        if ($token->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Token đã hết hạn.',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        }

        // ========== Validate Token Revocation ==========
        if ($token->isRevoked()) {
            return response()->json([
                'success' => false,
                'message' => 'Token đã bị revoke.',
                'code' => 'TOKEN_REVOKED',
            ], 401);
        }

        // ========== Validate Suspicious Activity ==========
        // Token refresh > threshold suggests token theft
        if ($token->refresh_count > config('sanctum.max_refresh_count_per_hour', 10)) {
            $token->revoke();

            return response()->json([
                'success' => false,
                'message' => 'Phát hiện hoạt động bất thường.',
                'code' => 'SUSPICIOUS_ACTIVITY',
            ], 401);
        }

        // ========== Validate Device Fingerprint ==========
        if (config('sanctum.verify_device_fingerprint') && $token->device_fingerprint) {
            $currentFingerprint = $this->generateDeviceFingerprint($request);

            if ($currentFingerprint !== $token->device_fingerprint) {
                // Token being used from different device - possible theft
                $token->revoke();

                return response()->json([
                    'success' => false,
                    'message' => 'Token được sử dụng từ device khác. Vui lòng login lại.',
                    'code' => 'DEVICE_MISMATCH',
                ], 401);
            }
        }

        // ========== Attach to Request ==========
        // Set authenticated user & token
        $request->attributes->set('user', $token->tokenable);
        $request->attributes->set('token', $token);

        // Update last_used_at
        $token->update(['last_used_at' => now()]);

        return $next($request);
    }

    /**
     * Generate device fingerprint từ request headers
     * MUST MATCH HttpOnlyTokenController::generateDeviceFingerprint()
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
