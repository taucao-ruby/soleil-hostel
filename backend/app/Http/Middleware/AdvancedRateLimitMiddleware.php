<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\RateLimitService;
use App\Events\RequestThrottled;
use Illuminate\Support\Facades\Log;

/**
 * Advanced Rate Limit Middleware
 * 
 * Applies multi-level rate limiting to requests with support for:
 * - Sliding window limits (time-strict)
 * - Token bucket limits (burst-friendly)
 * - Multiple limits per endpoint
 * - User-tier based quota adjustments
 * - Automatic retry-after header calculation
 * 
 * Usage in routes:
 *   ->middleware('rate-limit:sliding:5:60,token:20:1')
 *   Where: sliding:limit:window token:capacity:refill_rate
 */
class AdvancedRateLimitMiddleware
{
    public function __construct(private RateLimitService $rateLimiter)
    {
    }

    /**
     * Handle the request
     * 
     * @param Request $request
     * @param Closure $next
     * @param string ...$limitSpecs Rate limit specifications (e.g., "sliding:5:60" or "token:20:1")
     */
    public function handle(Request $request, Closure $next, string ...$limitSpecs): Response
    {
        if (empty($limitSpecs)) {
            return $next($request);
        }

        // Parse limit specifications
        $limits = $this->parseLimits($limitSpecs, $request);

        if (empty($limits)) {
            return $next($request);
        }

        // Build rate limit key
        $key = $this->buildKey($request, $limits);

        // Check rate limits
        $result = $this->rateLimiter->check($key, $limits);

        // Add rate limit headers to response
        $response = $next($request);

        $response->header('X-RateLimit-Limit', $this->getLimitValue($limits[0] ?? []))
                 ->header('X-RateLimit-Remaining', $result['remaining'])
                 ->header('X-RateLimit-Reset', now()->addSeconds($result['reset_after'])->timestamp);

        // If throttled, return 429
        if (!$result['allowed']) {
            Log::warning('Rate limit exceeded', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
                'key' => $key,
                'retry_after' => $result['retry_after'],
            ]);

            event(new RequestThrottled([
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'endpoint' => "{$request->method()} {$request->path()}",
                'limits' => $limits,
                'retry_after' => $result['retry_after'],
            ]));

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $result['retry_after'],
            ], Response::HTTP_TOO_MANY_REQUESTS)
            ->header('Retry-After', $result['retry_after'])
            ->header('X-RateLimit-Remaining', 0)
            ->header('X-RateLimit-Reset', now()->addSeconds($result['retry_after'])->timestamp);
        }

        return $response;
    }

    /**
     * Parse rate limit specifications from middleware parameters
     * 
     * Format:
     * - Sliding window: "sliding:max:window"  (e.g., "sliding:5:60")
     * - Token bucket: "token:capacity:refill"  (e.g., "token:20:1")
     */
    private function parseLimits(array $specs, Request $request): array
    {
        $limits = [];

        foreach ($specs as $spec) {
            $parts = explode(':', $spec);

            if (count($parts) < 3) {
                continue;
            }

            $type = $parts[0];

            if ($type === 'sliding') {
                $limits[] = [
                    'type' => 'sliding_window',
                    'max' => (int) $parts[1],
                    'window' => (int) $parts[2],
                ];
            } elseif ($type === 'token') {
                $limits[] = [
                    'type' => 'token_bucket',
                    'capacity' => (int) $parts[1],
                    'refill_rate' => (float) $parts[2],
                    'cost' => (int) ($parts[3] ?? 1),
                ];
            }
        }

        // Adjust for user tier if authenticated
        if ($request->user()) {
            $limits = $this->adjustForUserTier($limits, $request->user());
        }

        return $limits;
    }

    /**
     * Adjust rate limits based on user tier (free vs premium)
     */
    private function adjustForUserTier(array $limits, $user): array
    {
        // Assuming user model has 'subscription_tier' attribute
        $tier = $user->subscription_tier ?? 'free';

        $multipliers = [
            'free' => 1.0,
            'premium' => 3.0,
            'enterprise' => 10.0,
        ];

        $multiplier = $multipliers[$tier] ?? 1.0;

        return array_map(function ($limit) use ($multiplier) {
            if ($limit['type'] === 'sliding_window') {
                $limit['max'] = (int) ($limit['max'] * $multiplier);
            } elseif ($limit['type'] === 'token_bucket') {
                $limit['capacity'] = (int) ($limit['capacity'] * $multiplier);
            }

            return $limit;
        }, $limits);
    }

    /**
     * Build a unique rate limit key for the request
     */
    private function buildKey(Request $request, array $limits): string
    {
        $components = [];

        // Determine primary identifier
        if ($request->user()) {
            $components[] = "user:{$request->user()->id}";
        } else {
            $components[] = "ip:{$request->ip()}";
        }

        // Add endpoint identifier
        $route = $request->route();
        if ($route) {
            $routeName = $route->getName();
            if (!$routeName) {
                $routeName = md5($route->uri());
            }
            $components[] = "endpoint:{$routeName}";
        }

        // Add room ID if present in request
        if ($request->route('room') || $request->input('room_id')) {
            $roomId = $request->route('room')?->id ?? $request->input('room_id');
            $components[] = "room:{$roomId}";
        }

        return implode(':', $components);
    }

    /**
     * Get human-readable limit value for headers
     */
    private function getLimitValue(array $limit): string
    {
        if ($limit['type'] === 'sliding_window') {
            return "{$limit['max']} per {$limit['window']}s";
        } elseif ($limit['type'] === 'token_bucket') {
            return "{$limit['capacity']} capacity, {$limit['refill_rate']}/s refill";
        }

        return 'N/A';
    }
}
