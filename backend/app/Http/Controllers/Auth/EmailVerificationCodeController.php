<?php

namespace App\Http\Controllers\Auth;

use App\Enums\VerificationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyCodeRequest;
use App\Services\EmailVerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationCodeController extends Controller
{
    public function __construct(
        private readonly EmailVerificationCodeService $service,
    ) {}

    /**
     * Send a new verification code to the authenticated user.
     */
    public function sendCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
        }

        $result = $this->service->issue($user);

        if ($result['cooldown'] > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting a new code.',
                'cooldown_remaining_seconds' => $result['cooldown'],
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'data' => [
                'cooldown' => EmailVerificationCodeService::COOLDOWN_SECONDS,
            ],
        ]);
    }

    /**
     * Verify a submitted OTP code.
     */
    public function verifyCode(VerifyCodeRequest $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->service->verify($user, $request->validated('code'));

        return match ($result['result']) {
            VerificationResult::Verified => response()->json([
                'success' => true,
                'message' => 'Email verified successfully.',
                'verified' => true,
            ]),

            VerificationResult::AlreadyVerified => response()->json([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]),

            VerificationResult::Invalid => response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
                'attempts_remaining' => $result['attempts_remaining'],
            ], 422),

            VerificationResult::Expired => response()->json([
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.',
            ], 422),

            VerificationResult::Exhausted => response()->json([
                'success' => false,
                'message' => 'Too many incorrect attempts. Please request a new code.',
            ], 422),

            VerificationResult::NoActiveCode => response()->json([
                'success' => false,
                'message' => 'No active verification code found. Please request a new one.',
            ], 422),
        };
    }

    /**
     * Return current verification status and cooldown for the UI.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'verified' => $user->hasVerifiedEmail(),
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'cooldown_remaining_seconds' => $this->service->cooldownRemaining($user),
        ]);
    }
}
