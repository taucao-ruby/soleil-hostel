<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\AuthController as BearerAuthController;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

/**
 * CheckTokenNotRevokedAndNotExpired Middleware
 *
 * CRITICAL: Validates every request that carries a token:
 * 1. Not expired (expires_at > now)
 * 2. Not revoked (revoked_at IS NULL)
 * 3. Records last_used_at
 *
 * If token is invalid → return 401 Unauthorized
 *
 * This middleware runs BEFORE the request reaches the controller.
 * Ensures every protected endpoint validates token state.
 */
class CheckTokenNotRevokedAndNotExpired
{
    public function handle(Request $request, Closure $next)
    {
        // Extract token from Authorization header
        // Format: "Authorization: Bearer <token>"
        $bearerToken = $request->bearerToken();

        // If no bearer token is present:
        // - Allow if authenticated via 'web' guard (session auth)
        // - Allow if authenticated via 'sanctum' guard (test framework or valid token already authenticated by Sanctum)
        // - Fallback to httpOnly cookie token (SPA frontend uses cookie auth)
        if (! $bearerToken) {
            if ($request->user('web') || $request->user('sanctum')) {
                return $next($request);
            }

            // Fallback: delegate to CheckHttpOnlyTokenValid for cookie-based auth
            $cookieMiddleware = app(CheckHttpOnlyTokenValid::class);

            return $cookieMiddleware->handle($request, $next);
        }

        // Look up token in the database
        // Sanctum tokens use "{id}|{unhashed_token}" format from createToken()
        // findToken() handles splitting the ID prefix and hashing correctly
        $token = PersonalAccessToken::findToken($bearerToken);

        if (! $token) {
            // Token not found → 401
            throw new AuthenticationException('Token không hợp lệ hoặc không tồn tại.');
        }

        // ========== CHECK: Token expired? ==========
        // IMPORTANT: Check expiration BEFORE authenticating user
        if ($token->isExpired()) {
            // Token expired → 401
            // Frontend receives 401 → calls refresh endpoint
            return response()->json([
                'message' => 'Token đã hết hạn. Vui lòng refresh token.',
                'code' => 'TOKEN_EXPIRED',
                'expires_at' => $token->expires_at?->toIso8601String(),
            ], 401);
        }

        // ========== CHECK: Token revoked? ==========
        // IMPORTANT: Check revocation BEFORE authenticating user
        if ($token->isRevoked()) {
            // Token revoked (logout/refresh/force-logout) → 401
            // User must log in again
            return response()->json([
                'message' => 'Token đã bị revoke. Vui lòng login lại.',
                'code' => 'TOKEN_REVOKED',
                'revoked_at' => $token->revoked_at?->toIso8601String(),
            ], 401);
        }

        // ========== AUTHENTICATE USER (ONLY AFTER validation checks pass) ==========
        // Now that we've verified token is valid, not expired, and not revoked, authenticate the user
        $user = $token->tokenable;
        if (! $user instanceof \App\Models\User) {
            throw new AuthenticationException('Không tìm được user cho token này.');
        }

        // Set user resolver so $request->user() works
        $request->setUserResolver(fn () => $user);

        // Authenticate on the default guard and sanctum guard for full Laravel compatibility.
        // Without this, auth()->user() returns null in controllers because the default
        // 'web' guard has no user set — only $request->user() resolves correctly.
        try {
            auth()->setUser($user);
            auth()->guard('sanctum')->setUser($user);
        } catch (\Throwable $e) {
            // If a guard is unavailable, the request resolver above is sufficient
        }

        // Store the access token on the user so currentAccessToken() works
        $user->withAccessToken($token);

        // ========== CHECK: Refresh count (suspicious activity) ==========
        // Inclusive cap: refresh_count >= max → blocked. Unified with controllers and cookie middleware.
        if ($token->refresh_count >= (int) config('sanctum.max_refresh_count_per_hour', 10)) {
            // Too many refreshes in a short period → suspicious
            // Revoke this token to protect the account
            $token->revoke();

            return response()->json([
                'success' => false,
                'message' => 'Phát hiện hoạt động bất thường. Token đã bị vô hiệu hóa. Vui lòng login lại.',
                'errors' => ['code' => 'SUSPICIOUS_ACTIVITY'],
            ], 401);
        }

        // ========== CHECK: Device fingerprint (bearer-mode replay defence) ==========
        // If a bearer token carries a stored fingerprint, replays from a different
        // UA/IP-/24 combination are treated as theft and rejected. Tokens without a
        // stored fingerprint (e.g. legacy tokens issued before binding was added) are
        // not blocked here — they remain subject to the other checks above.
        if (config('sanctum.verify_device_fingerprint') && $token->device_fingerprint) {
            $currentFingerprint = BearerAuthController::computeBearerFingerprint($request);
            if ($currentFingerprint !== null && $currentFingerprint !== $token->device_fingerprint) {
                $token->revoke();

                return response()->json([
                    'success' => false,
                    'message' => 'Token được sử dụng từ device khác. Vui lòng login lại.',
                    'errors' => ['code' => 'DEVICE_MISMATCH'],
                ], 401);
            }
        }

        // ========== SUCCESS: Token is valid ==========
        // Update last_used_at to track token usage
        // Optimization: only update if more than 1 minute has passed since last update (reduces write frequency)
        $token->recordUsage();

        // Continue with request
        return $next($request);
    }
}
