<?php

namespace App\Http\Controllers\Auth;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * UnifiedAuthController - Mode-agnostic auth endpoints
 * 
 * Provides unified endpoints that detect authentication mode (Bearer vs HttpOnly cookie)
 * and delegate to the appropriate controller. This allows clients to use a single
 * endpoint regardless of their auth mode.
 * 
 * Endpoints:
 * - GET  /api/auth/me          → Detects mode, returns user info
 * - POST /api/auth/logout      → Detects mode, revokes appropriate token
 * - POST /api/auth/logout-all  → Detects mode, revokes all user tokens
 * 
 * Mode Detection Priority:
 * 1. HttpOnly cookie present → Cookie mode
 * 2. Bearer token in header → Bearer mode
 * 3. Neither → 401 Unauthenticated
 */
class UnifiedAuthController extends Controller
{
    private AuthController $bearerController;
    private HttpOnlyTokenController $cookieController;

    public function __construct(AuthController $bearerController, HttpOnlyTokenController $cookieController)
    {
        $this->bearerController = $bearerController;
        $this->cookieController = $cookieController;
    }

    /**
     * Unified /auth/me - Get current user info
     * 
     * Detects authentication mode and delegates to appropriate controller.
     * Response format matches the mode's native format for backward compatibility.
     */
    public function me(Request $request): JsonResponse
    {
        $mode = $this->detectAuthMode($request);

        if ($mode === 'cookie') {
            return $this->cookieController->me($request);
        }

        if ($mode === 'bearer') {
            return $this->bearerController->me($request);
        }

        throw new AuthenticationException('Unauthenticated. Please log in.');
    }

    /**
     * Unified /auth/logout - Revoke current token/session
     * 
     * Detects authentication mode and delegates to appropriate controller.
     */
    public function logout(Request $request): JsonResponse
    {
        $mode = $this->detectAuthMode($request);

        if ($mode === 'cookie') {
            return $this->cookieController->logout($request);
        }

        if ($mode === 'bearer') {
            return $this->bearerController->logout($request);
        }

        throw new AuthenticationException('Unauthenticated. Please log in.');
    }

    /**
     * Unified /auth/logout-all - Revoke all user tokens/sessions
     * 
     * Works for both Bearer and HttpOnly cookie modes.
     * For cookie mode, we need to get the user from the cookie token first.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $mode = $this->detectAuthMode($request);

        if ($mode === 'bearer') {
            return $this->bearerController->logoutAll($request);
        }

        if ($mode === 'cookie') {
            // For cookie mode, get user from token and revoke all tokens
            $token = $request->attributes->get('token');
            
            if (!$token) {
                throw new AuthenticationException('Token cookie không tồn tại.');
            }

            $user = $token->tokenable;
            $revokedCount = 0;

            $user->tokens()
                ->notRevoked()
                ->each(function ($t) use (&$revokedCount) {
                    if ($t->revoke()) {
                        $revokedCount++;
                    }
                });

            // Clear the httpOnly cookie
            $response = response()->json([
                'success' => true,
                'message' => "Logout tất cả thiết bị thành công. Đã revoke {$revokedCount} token.",
                'revoked_count' => $revokedCount,
            ], 200);

            $response->cookie(
                env('SANCTUM_COOKIE_NAME', 'soleil_token'),
                '',
                -1,
                '/',
                config('session.domain'),
                config('app.env') === 'production',
                true,
                false,
                'strict'
            );

            return $response;
        }

        throw new AuthenticationException('Unauthenticated. Please log in.');
    }

    /**
     * Detect authentication mode from request
     * 
     * Priority:
     * 1. HttpOnly cookie present → 'cookie'
     * 2. Bearer token in Authorization header → 'bearer'
     * 3. Neither → null
     */
    private function detectAuthMode(Request $request): ?string
    {
        // Check for HttpOnly cookie first (preferred for web)
        $cookieName = env('SANCTUM_COOKIE_NAME', 'soleil_token');
        $cookieToken = $request->cookie($cookieName);
        
        // Fallback: parse Cookie header manually for testing
        if (!$cookieToken && $request->hasHeader('Cookie')) {
            $cookieHeader = $request->header('Cookie');
            $cookies = array_map('trim', explode(';', $cookieHeader));
            foreach ($cookies as $cookie) {
                if (strpos($cookie, $cookieName . '=') === 0) {
                    $cookieToken = substr($cookie, strlen($cookieName . '='));
                    break;
                }
            }
        }

        if ($cookieToken) {
            // Verify cookie token exists in database
            $tokenHash = hash('sha256', $cookieToken);
            $token = PersonalAccessToken::where('token_hash', $tokenHash)->first();
            if ($token && $token->isValid()) {
                // Store token in request attributes for downstream use
                $request->attributes->set('token', $token);
                $request->attributes->set('user', $token->tokenable);
                return 'cookie';
            }
        }

        // Check for Bearer token
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            $tokenHash = hash('sha256', $bearerToken);
            $token = PersonalAccessToken::where('token', $tokenHash)->first();
            if ($token && $token->isValid()) {
                return 'bearer';
            }
        }

        return null;
    }
}
