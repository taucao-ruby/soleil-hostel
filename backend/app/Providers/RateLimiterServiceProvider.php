<?php

namespace App\Providers;

use App\Jobs\SendBookingConfirmationEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimiterServiceProvider extends RouteServiceProvider
{
    /**
     * Bootstrap rate limiters for the application.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Define the rate limiters for the application.
     */
    protected function configureRateLimiters(): void
    {
        // ========== LOGIN RATE LIMITING ==========
        // Strategy: 5 requests per minute per IP + 20 per hour per email
        // Purpose: Prevent brute force attacks
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                // Per IP: 5 per minute (sliding window)
                Limit::perMinute(5)
                    ->by($request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many login attempts. Please try again in 60 seconds.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),

                // Per email: 20 per hour (sliding window)
                Limit::perHour(20)
                    ->by("login:{$email}")
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many login attempts for this email. Please try again later.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),
            ];
        });

        // ========== BOOKING RATE LIMITING ==========
        // Strategy: 3 requests per minute per user + 20 per hour per user
        // Purpose: Prevent spam bookings
        RateLimiter::for('booking', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();

            return [
                // Per user: 3 per minute
                Limit::perMinute(3)
                    ->by("booking:{$userId}")
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many booking requests. Please wait before making another booking.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),

                // Per user: 20 per hour
                Limit::perHour(20)
                    ->by("booking_hour:{$userId}")
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Daily booking limit reached. Please try tomorrow.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),
            ];
        });

        // ========== API PUBLIC RATE LIMITING ==========
        // Strategy: 100 requests per minute per IP (for listing rooms)
        // Purpose: Allow mobile app + web browsing without throttling
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(100)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Rate limit exceeded. Please wait before making more requests.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429, $headers);
                });
        });

        // ========== TOKEN REFRESH RATE LIMITING ==========
        // Strategy: 10 requests per minute per user
        // Purpose: Prevent abuse of token refresh endpoint
        RateLimiter::for('refresh-token', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(10)
                ->by("refresh_token:{$userId}")
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many token refresh attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429, $headers);
                });
        });

        // ========== CSRF TOKEN RATE LIMITING ==========
        // Strategy: 5 requests per minute per authenticated user
        // Purpose: Secondary abuse control for the authenticated supplementary token endpoint
        RateLimiter::for('csrf-token', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(5)
                ->by($user
                    ? 'csrf-token:user:'.$user->getAuthIdentifier()
                    : 'csrf-token:ip:'.$request->ip()
                )
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many CSRF token requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429, $headers);
                });
        });

        // ========== CSP VIOLATION REPORT RATE LIMITING ==========
        // Strategy: 60 requests per minute per IP + 300 per hour per IP
        // Purpose: Bound public browser telemetry ingestion and log amplification
        RateLimiter::for('csp-violation-report', function (Request $request) {
            return [
                Limit::perMinute(60)
                    ->by('csp-report:minute:'.$request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many CSP violation reports. Please try again later.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),

                Limit::perHour(300)
                    ->by('csp-report:hour:'.$request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many CSP violation reports. Please try again later.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),
            ];
        });

        // ========== GLOBAL API RATE LIMITING ==========
        // Strategy: 1000 requests per minute per IP (catch-all)
        // Purpose: Prevent DoS attacks
        RateLimiter::for('global-api', function (Request $request) {
            return Limit::perMinute(1000)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests. Rate limited.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429, $headers);
                });
        });

        // ========== PASSWORD RESET RATE LIMITING ==========
        // Strategy: 3 requests per hour per email
        // Purpose: Prevent reset email spam
        RateLimiter::for('password-reset', function (Request $request) {
            $email = $request->input('email');

            return Limit::perHour(3)
                ->by("reset:{$email}")
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many password reset requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429, $headers);
                });
        });

        // ========== EMAIL VERIFICATION RATE LIMITING ==========
        // Strategy: 5 requests per hour per email
        // Purpose: Prevent email verification spam
        RateLimiter::for('email-verification', function (Request $request) {
            $email = $request->user()?->email ?? $request->input('email');

            return Limit::perHour(5)
                ->by("verify:{$email}")
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many verification requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'],
                    ], 429, $headers);
                });
        });

        // ========== BOOKING CONFIRMATION EMAIL — RECIPIENT-LEVEL THROTTLING ==========
        // Strategy: 5 emails per minute per recipient (guest user_id)
        // Purpose: Guest mailbox protection. Used by ThrottlesPerRecipient queue
        //          middleware on SendBookingConfirmationEmail — when exceeded, the
        //          job is RELEASED back to the queue (delayed retry), never silently
        //          dropped. See BL-4 fix: BookingService::confirmBooking() used to
        //          suppress emails inline at the dispatch site when this limit was
        //          hit, which lost (N-5) confirmations in bulk-admin scenarios.
        //
        //          Actor abuse protection lives at the HTTP layer
        //          (throttle:10,1 on POST /api/v1/bookings/{booking}/confirm).
        //          This limiter is intentionally recipient-scoped, not actor-scoped.
        RateLimiter::for('booking-confirmation-email-recipient', function (SendBookingConfirmationEmail $job) {
            return Limit::perMinute(5)->by((string) $job->recipientUserId());
        });
    }
}
