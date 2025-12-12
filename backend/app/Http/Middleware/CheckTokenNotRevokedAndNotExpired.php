<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;

/**
 * CheckTokenNotRevokedAndNotExpired Middleware
 * 
 * CRITICAL: Validate mỗi request có token:
 * 1. Không hết hạn (expires_at > now)
 * 2. Không bị revoke (revoked_at IS NULL)
 * 3. Cập nhật last_used_at
 * 
 * Nếu token không valid → return 401 Unauthorized
 * 
 * Middleware này được gọi TRƯỚC khi request đến controller
 * Đảm bảo mọi protected endpoint đều check token validity
 */
class CheckTokenNotRevokedAndNotExpired
{
    public function handle(Request $request, Closure $next)
    {
        // Lấy token từ Authorization header
        // Format: "Authorization: Bearer <token>"
        $bearerToken = $request->bearerToken();

        // If no bearer token is present:
        // - Allow if authenticated via 'web' guard (session auth)
        // - Allow if authenticated via 'sanctum' guard (test framework or valid token already authenticated by Sanctum)
        if (!$bearerToken) {
            if ($request->user('web') || $request->user('sanctum')) {
                return $next($request);
            }
            // No bearer token AND not authenticated by any guard
            throw new AuthenticationException('Token không được cấp trong Authorization header.');
        }

        // Tìm token ở database
        // Sanctum hashes token, nên phải search theo hash
        $tokenHash = hash('sha256', $bearerToken);
        $token = PersonalAccessToken::where(
            'token',
            $tokenHash
        )->first();

        if (!$token) {
            // Token không tồn tại → 401
            throw new AuthenticationException('Token không hợp lệ hoặc không tồn tại.');
        }

        // Authenticate the user (since we're not using auth:sanctum middleware)
        $user = $token->tokenable;
        if (!$user) {
            throw new AuthenticationException('Không tìm được user cho token này.');
        }
        
        // Set user resolver so $request->user() works
        $request->setUserResolver(fn () => $user);
        
        // Also authenticate on the 'sanctum' guard for Laravel compatibility
        try {
            auth()->guard('sanctum')->setUser($user);
        } catch (\Throwable $e) {
            // If guard doesn't exist, just continue - the request resolver should work
        }
        
        // Store the access token on the user so currentAccessToken() works
        $user->accessToken = $token;

        // ========== CHECK: Token hết hạn? ==========
        if ($token->isExpired()) {
            // Token hết hạn → 401
            // Frontend sẽ nhận 401 → gọi refresh endpoint
            return response()->json([
                'message' => 'Token đã hết hạn. Vui lòng refresh token.',
                'code' => 'TOKEN_EXPIRED',
                'expires_at' => $token->expires_at?->toIso8601String(),
            ], 401);
        }

        // ========== CHECK: Token bị revoke? ==========
        if ($token->isRevoked()) {
            // Token bị revoke (logout/refresh/force logout) → 401
            // User phải login lại
            return response()->json([
                'message' => 'Token đã bị revoke. Vui lòng login lại.',
                'code' => 'TOKEN_REVOKED',
                'revoked_at' => $token->revoked_at?->toIso8601String(),
            ], 401);
        }

        // ========== CHECK: Refresh count (suspicious activity) ==========
        if ($token->refresh_count > config('sanctum.max_refresh_count_per_hour')) {
            // Quá nhiều refresh trong ngắn thời gian → suspicious
            // Revoke token này để bảo vệ account
            $token->revoke();

            return response()->json([
                'message' => 'Phát hiện hoạt động bất thường. Token đã bị vô hiệu hóa. Vui lòng login lại.',
                'code' => 'SUSPICIOUS_ACTIVITY',
            ], 401);
        }

        // ========== SUCCESS: Token hợp lệ ==========
        // Cập nhật last_used_at để track token usage
        // Optimization: chỉ update nếu cách lần trước > 1 phút (avoid too many writes)
        $token->recordUsage();

        // Continue with request
        return $next($request);
    }
}
