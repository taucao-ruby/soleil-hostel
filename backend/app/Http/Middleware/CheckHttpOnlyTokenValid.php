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
 * - Token is always in the httpOnly cookie, NOT in the Authorization header
 * - XSS cannot access, CSRF mitigated by SameSite=Strict
 * - Validates: Existence, expiration, revocation, suspicious activity
 * - Sets resolved user on $request attributes for use in controllers
 *
 * Middleware Pipeline:
 * 1. Extract token_identifier from the httpOnly cookie
 * 2. Hash the identifier and look up token_hash in the database
 * 3. Validate token state (expired, revoked, refresh_count abuse)
 * 4. Validate device fingerprint if enabled
 * 5. Attach token and user to $request attributes
 */
class CheckHttpOnlyTokenValid
{
    public function handle(Request $request, Closure $next)
    {
        // ========== Extract Token from Cookie ==========
        // Browser automatically sends httpOnly cookie
        $cookieName = config('sanctum.cookie_name', 'soleil_token');
        $tokenIdentifier = $request->cookie($cookieName);

        // Fallback: Try to extract from Cookie header for testing compatibility
        if (! $tokenIdentifier && $request->hasHeader('Cookie')) {
            $cookieHeader = $request->header('Cookie');
            // Parse cookie header: "name=value; other=value"
            $cookies = array_map('trim', explode(';', $cookieHeader));
            foreach ($cookies as $cookie) {
                if (strpos($cookie, $cookieName.'=') === 0) {
                    $tokenIdentifier = substr($cookie, strlen($cookieName.'='));
                    break;
                }
            }
        }

        if (! $tokenIdentifier) {
            throw new AuthenticationException('Unauthenticated. Please log in.');
        }

        // ========== Find Token by Hash ==========
        // token_hash stored in DB allows secure lookup without plaintext comparison
        $tokenHash = hash('sha256', $tokenIdentifier);

        $token = PersonalAccessToken::where('token_hash', $tokenHash)->first();

        if (! $token) {
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
        // Set authenticated user & token on request attributes (used by HttpOnlyTokenController)
        $user = $token->tokenable;
        $request->attributes->set('user', $user);
        $request->attributes->set('token', $token);

        // Also set user resolver so $request->user() works for downstream middleware
        // (e.g. 'verified', 'role') when this middleware is used as a fallback
        $request->setUserResolver(fn () => $user);

        // Throttle last_used_at updates to 1-minute intervals to reduce DB writes
        if (! $token->last_used_at || $token->last_used_at->diffInMinutes(now()) >= 1) {
            $token->update(['last_used_at' => now()]);
        }

        return $next($request);
    }

    /**
     * Generate device fingerprint from request headers.
     * MUST MATCH HttpOnlyTokenController::generateDeviceFingerprint()
     */
    private function generateDeviceFingerprint(Request $request): ?string
    {
        if (! config('sanctum.verify_device_fingerprint')) {
            return null;
        }

        $components = [
            $request->header('User-Agent') ?? '',
            $request->header('Accept-Language') ?? '',
            $request->header('Accept-Encoding') ?? '',
        ];

        return hash('sha256', implode('|', array_map('strval', $components)));
    }
}
