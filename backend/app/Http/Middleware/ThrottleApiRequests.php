<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ThrottleApiRequests
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    /**
     * Create a new middleware instance.
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$limits): Response
    {
        foreach ($limits as $limit) {
            $key = $this->resolveRequestKey($request, $limit);
            $maxAttempts = $this->resolveMaxAttempts($limit);

            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                throw $this->buildException($request, $key, $maxAttempts);
            }

            $this->limiter->hit($key, $this->resolveLimitingPeriod($limit));
        }

        $key = $this->resolveRequestKey($request, $limits[0]);
        $maxAttempts = $this->resolveMaxAttempts($limits[0]);

        return $next($request)
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', $this->limiter->remaining($key, $maxAttempts));
    }

    /**
     * Resolve the cache key for the rate limiter.
     */
    protected function resolveRequestKey(Request $request, string $limit): string
    {
        return $limit . '|' . ($request->user()?->id ?: $request->ip());
    }

    /**
     * Resolve the number of attempts allowed per limit.
     */
    protected function resolveMaxAttempts(string $limit): int
    {
        // Format: "limit-per-period" (e.g., "60-1" = 60 requests per minute)
        if (str_contains($limit, '-')) {
            return (int) explode('-', $limit)[0];
        }
        return 60; // Default
    }

    /**
     * Resolve the limiting period in seconds.
     */
    protected function resolveLimitingPeriod(string $limit): int
    {
        // Format: "limit-period" where period is in minutes
        // "60-1" = 60 requests per 1 minute = 60 seconds
        // "30-60" = 30 requests per 60 minutes = 3600 seconds
        if (str_contains($limit, '-')) {
            $parts = explode('-', $limit);
            return (int) $parts[1] * 60;
        }
        return 60; // Default 1 minute
    }

    /**
     * Create a rate limit exceeded exception.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function buildException(Request $request, string $key, int $maxAttempts): HttpException
    {
        $retryAfter = $this->limiter->availableIn($key);

        return new HttpException(429, 'Too many requests. Please try again in ' . $retryAfter . ' seconds.', null, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }
}
