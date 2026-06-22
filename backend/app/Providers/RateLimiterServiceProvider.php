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
        // Strategy: 5 requests per minute per normalized email+IP + 20 per minute per IP
        // Purpose: Prevent brute force attacks
        RateLimiter::for('login', function (Request $request) {
            if ($this->rateLimitingDisabled()) {
                return Limit::none();
            }

            $email = strtolower(trim((string) $request->input('email')));
            $ip = $this->requestIp($request);

            return [
                // Per account candidate + IP: 5 per minute.
                Limit::perMinute(5)
                    ->by($this->hashedLimiterKey('login:email-ip', "{$email}|{$ip}"))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many login attempts. Please try again in 60 seconds.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),

                // Per IP: 20 per minute across account candidates.
                Limit::perMinute(20)
                    ->by($this->hashedLimiterKey('login:ip', $ip))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many login attempts from this network. Please try again later.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),
            ];
        });

        // ========== BOOKING RATE LIMITING ==========
        // Strategy: 5 requests per minute per actor+IP + 20 per minute per IP
        // Purpose: Prevent spam bookings
        RateLimiter::for('booking', function (Request $request) {
            if ($this->rateLimitingDisabled()) {
                return Limit::none();
            }

            $ip = $this->requestIp($request);
            $actor = $request->user()?->getAuthIdentifier()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.$ip;

            return [
                // Per authenticated user + IP: 5 per minute.
                Limit::perMinute(5)
                    ->by($this->hashedLimiterKey('booking:actor-ip', "{$actor}|{$ip}"))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many booking requests. Please wait before making another booking.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),

                // Per IP: 20 per minute across actors.
                Limit::perMinute(20)
                    ->by($this->hashedLimiterKey('booking:ip', $ip))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many booking requests from this network. Please try again later.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),
            ];
        });

        // ========== TOKEN REFRESH RATE LIMITING ==========
        // Strategy: 5 requests per minute per token-derived actor + 20 per minute per IP
        // Purpose: Prevent abuse of token refresh endpoint
        RateLimiter::for('refresh-token', function (Request $request) {
            if ($this->rateLimitingDisabled()) {
                return Limit::none();
            }

            $ip = $this->requestIp($request);

            return [
                Limit::perMinute(5)
                    ->by($this->hashedLimiterKey('refresh-token:actor', $this->refreshActorSubject($request)))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many token refresh attempts. Please try again later.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),

                Limit::perMinute(20)
                    ->by($this->hashedLimiterKey('refresh-token:ip', $ip))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'message' => 'Too many token refresh attempts from this network. Please try again later.',
                            'retry_after' => $headers['Retry-After'],
                        ], 429, $headers);
                    }),
            ];
        });

        // ========== CSRF TOKEN RATE LIMITING ==========
        // Strategy: 5 requests per minute per authenticated user
        // Purpose: Secondary abuse control for the authenticated supplementary token endpoint
        RateLimiter::for('csrf-token', function (Request $request) {
            if ($this->rateLimitingDisabled()) {
                return Limit::none();
            }

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

        // ========== EMAIL VERIFICATION RATE LIMITING ==========
        // Strategy: 5 requests per hour per email
        // Purpose: Prevent email verification spam
        RateLimiter::for('email-verification', function (Request $request) {
            $email = $request->user()?->email ?? $request->input('email');

            return Limit::perHour(5)
                ->by($this->hashedLimiterKey('email-verification', (string) $email))
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

    private function refreshActorSubject(Request $request): string
    {
        $cookieName = (string) config('sanctum.cookie_name', 'soleil_token');
        $cookieToken = $request->cookie($cookieName)
            ?? $this->extractCookieFromHeader($request, $cookieName);

        if (is_string($cookieToken) && $cookieToken !== '') {
            return 'cookie:'.hash('sha256', $cookieToken);
        }

        $bearerToken = $request->bearerToken();
        if (is_string($bearerToken) && $bearerToken !== '') {
            return 'bearer:'.hash('sha256', $bearerToken);
        }

        $userId = $request->user()?->getAuthIdentifier();
        if ($userId !== null) {
            return 'user:'.$userId;
        }

        return 'ip:'.$this->requestIp($request);
    }

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
     * E2E-only bypass for the auth/booking limiters.
     *
     * The nightly Playwright run executes every flow across 4 browser projects
     * sharing one runner IP and the single seeded user@soleil.test, which trips
     * the 5/min-per-email + 20/min-per-IP login limits. The E2E backend bootstrap
     * sets DISABLE_RATE_LIMITING (config ratelimit.disable); the `php artisan test`
     * suite does not, so its rate-limit feature tests keep their limits.
     *
     * Double-gated on app()->isProduction() so a leaked production env var can
     * never disable brute-force protection.
     */
    private function rateLimitingDisabled(): bool
    {
        return ! app()->isProduction() && (bool) config('ratelimit.disable', false);
    }

    private function requestIp(Request $request): string
    {
        return (string) ($request->ip() ?: 'unknown');
    }

    private function hashedLimiterKey(string $prefix, string $subject): string
    {
        return $prefix.':'.hash('sha256', $subject);
    }
}
