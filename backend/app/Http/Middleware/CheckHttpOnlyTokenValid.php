<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Services\Auth\TokenRefreshRateLimiter;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * CheckHttpOnlyTokenValid - Validate httpOnly cookie token
 *
 * CRITICAL SECURITY:
 * - Token is always in the httpOnly cookie, NOT in the Authorization header
 * - XSS cannot access httpOnly cookies; JavaScript-readable value cannot be forged
 * - CSRF protection: soleil_token cookie is SameSite=Strict — browsers block
 *   cross-origin requests from carrying the cookie (active server-side defence)
 * - X-XSRF-TOKEN header is sent by the frontend but is NOT validated here;
 *   it provides a supplementary XSS barrier, not the primary CSRF control
 * - Validates: Existence, expiration, revocation, and refresh-rate limits
 * - Sets resolved user on $request attributes for use in controllers
 *
 * Middleware Pipeline:
 * 1. Extract token_identifier from the httpOnly cookie
 * 2. Hash the identifier and look up token_hash in the database
 * 3. Validate token state (expired, revoked)
 * 4. Validate device fingerprint if enabled
 * 5. Enforce refresh-rate limit on refresh requests
 * 6. Attach token and user to $request attributes
 */
class CheckHttpOnlyTokenValid
{
    public function handle(Request $request, Closure $next)
    {
        // ========== Extract Token from Cookie ==========
        // Browser automatically sends httpOnly cookie
        $cookieName = config('sanctum.cookie_name', 'soleil_token');
        $tokenIdentifier = $request->cookie($cookieName)
            ?? $this->extractCookieFromHeader($request, $cookieName);

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

        // ========== Validate Token Refresh Rate ==========
        // refresh_count is lifetime telemetry only. Hourly refresh enforcement is
        // cache-backed and scoped to the token session, separate from expiry/revocation.
        if ($request->is('api/auth/refresh-httponly')) {
            app(TokenRefreshRateLimiter::class)->enforce($token);
        }

        // ========== Attach to Request ==========
        // Set authenticated user & token on request attributes (used by HttpOnlyTokenController)
        $user = $token->tokenable;
        if (! $user instanceof \App\Models\User) {
            throw new AuthenticationException('Unauthenticated. Please log in.');
        }
        $request->attributes->set('user', $user);
        $request->attributes->set('token', $token);

        // Also set user resolver so $request->user() works for downstream middleware
        // (e.g. 'verified', 'role') when this middleware is used as a fallback
        $request->setUserResolver(fn () => $user);

        // Authenticate on the default auth guard so Gate::authorize() works.
        // Gate resolves users via auth()->user() (default guard = 'web'), not $request->user().
        // Also set on sanctum guard for compatibility with auth:sanctum middleware consumers.
        auth()->setUser($user);
        try {
            auth()->guard('sanctum')->setUser($user);
        } catch (\Throwable $e) {
            // Guard unavailable in test context — request resolver above is sufficient
        }

        // Throttle last_used_at updates to 1-minute intervals to reduce DB writes
        if (! $token->last_used_at || $token->last_used_at->diffInMinutes(now()) >= 1) {
            $token->update(['last_used_at' => now()]);
        }

        return $next($request);
    }

    /**
     * Fallback cookie extraction from the raw Cookie header.
     *
     * Needed because feature tests construct requests with
     * `withHeader('Cookie', ...)` rather than `withCookie(...)`, which does
     * not populate Laravel's cookie bag. Production traffic goes through
     * the normal cookie bag and never reaches this fallback.
     *
     * Hardened against the F-39 finding:
     * - Exact name match (no prefix collision like `soleil_token_backup`
     *   being accepted as `soleil_token`).
     * - Splits each pair on the first `=` only, so cookie values containing
     *   `=` are preserved instead of being truncated.
     * - Uses rawurldecode, which preserves `+` (urldecode would convert
     *   `+` to space — form-body semantics, wrong for cookie material).
     */
    private function extractCookieFromHeader(Request $request, string $cookieName): ?string
    {
        $cookieHeader = $request->header('Cookie');

        if (! is_string($cookieHeader) || $cookieHeader === '') {
            return null;
        }

        foreach (explode(';', $cookieHeader) as $cookiePair) {
            $parts = explode('=', trim($cookiePair), 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$name, $value] = $parts;

            if (rawurldecode(trim($name)) !== $cookieName) {
                continue;
            }

            return rawurldecode($value);
        }

        return null;
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
