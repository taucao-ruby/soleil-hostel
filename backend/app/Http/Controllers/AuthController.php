<?php

namespace App\Http\Controllers;

use App\Exceptions\OtpCooldownException;
use App\Http\Controllers\Auth\AuthController as BearerAuthController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @deprecated Use Auth\AuthController (Bearer token) or HttpOnlyTokenController (httpOnly cookies) instead.
 *             This controller is maintained for backward compatibility with legacy endpoints only.
 *             Sunset date: July 2026 (see routes/api.php deprecation middleware)
 * @see \App\Http\Controllers\Auth\AuthController         Bearer token auth (v2)
 * @see \App\Http\Controllers\Auth\HttpOnlyTokenController HttpOnly cookie auth
 * @see \App\Http\Controllers\Auth\UnifiedAuthController   Mode-agnostic (auto-detect)
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Fire Registered event to trigger email verification notification.
        // Wrapped in try/catch: SMTP failures must not cause a 500 on registration.
        try {
            event(new Registered($user));
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch post-registration notification', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        $token = $this->issueLegacyBearerToken($user, $request, 'auth_token');

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'User registered successfully', 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        // Auto-resend verification code if unverified
        if (! $user->hasVerifiedEmail()) {
            try {
                app(EmailVerificationCodeService::class)->issue($user);
            } catch (OtpCooldownException) {
                // Cooldown active — silently skip; the user can resend via the
                // dedicated /api/email/send-code endpoint once the window elapses.
            } catch (\Exception $e) {
                Log::warning('Failed to send verification code on login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $token = $this->issueLegacyBearerToken($user, $request, 'auth_token');

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke old token
        $request->user()->currentAccessToken()->delete();

        // Create new token with full security columns populated.
        $token = $this->issueLegacyBearerToken($user, $request, 'auth_token');

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed');
    }

    /**
     * Issue a bearer token for legacy auth endpoints with all security columns populated.
     *
     * Replaces the default Sanctum createToken() (which leaves type/device_id/refresh_count/
     * token_hash/token_identifier NULL) with an explicit insert that sets every column required
     * by the middleware and reconciliation pipeline. Returns the plain-text token for the client.
     */
    private function issueLegacyBearerToken(User $user, Request $request, string $name): string
    {
        $plainTextToken = Str::random(40);
        $hashedToken = hash('sha256', $plainTextToken);
        $tokenIdentifier = (string) Str::uuid();
        $deviceId = (string) Str::uuid();
        $expiresAt = now()->addMinutes((int) config('sanctum.short_lived_token_expiration_minutes', 60));

        $user->tokens()->create([
            'name' => $name,
            'token' => $hashedToken,
            'token_identifier' => $tokenIdentifier,
            'token_hash' => $hashedToken,
            'abilities' => ['*'],
            'expires_at' => $expiresAt,
            'type' => 'short_lived',
            'device_id' => $deviceId,
            'device_fingerprint' => BearerAuthController::computeBearerFingerprint($request),
            'refresh_count' => 0,
        ]);

        return $plainTextToken;
    }
}
