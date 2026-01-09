<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EmailVerificationController
 * 
 * Handles email verification for API-based authentication.
 * Uses Laravel's default Notification system (not custom Mailables).
 * 
 * Flow:
 * 1. User registers → Registered event fires → SendEmailVerificationNotification listener sends email
 * 2. User clicks verification link → verify() method marks email as verified
 * 3. User can request resend → resend() method sends new verification email
 * 
 * Routes:
 * - GET  /api/email/verify/{id}/{hash} → verify()
 * - POST /api/email/verification-notification → resend()
 * - GET  /api/email/verification-status → status()
 */
class EmailVerificationController extends Controller
{
    /**
     * Get the verification status for the authenticated user.
     * 
     * Used by frontend to check if user needs to verify email.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'verified' => $user->hasVerifiedEmail(),
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
        ]);
    }

    /**
     * Mark the authenticated user's email address as verified.
     * 
     * This is called when user clicks the verification link in email.
     * The link contains: user ID + signed hash for security.
     */
    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        // Already verified?
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
        }

        // Mark as verified and fire event
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        \Log::info('Email verified', [
            'user_id' => $request->user()->id,
            'email' => $request->user()->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'verified' => true,
        ]);
    }

    /**
     * Send a new email verification notification.
     * 
     * Rate limited to prevent spam (5 per hour via RateLimiterServiceProvider).
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        // Already verified?
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
        }

        // Send verification email (uses default Laravel notification)
        $user->sendEmailVerificationNotification();

        \Log::info('Verification email resent', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification link sent to your email.',
        ]);
    }

    /**
     * Display verification notice (API version).
     * 
     * Returns JSON indicating verification is required.
     * Named route: verification.notice (required by Laravel).
     */
    public function notice(Request $request): JsonResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please verify your email address.',
            'verified' => false,
        ], 403);
    }
}
