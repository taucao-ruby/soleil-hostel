<?php

declare(strict_types=1);

namespace App\Queue\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

/**
 * ThrottlesPerRecipient — queue middleware that enforces a Laravel named rate
 * limiter against the *recipient identity carried by the job*, and on limit
 * RELEASES the job back to the queue rather than silently completing it.
 *
 * Why not Illuminate\Queue\Middleware\RateLimited?
 *   The built-in middleware also releases-on-limit, which is the correct
 *   behavior for BL-4 — but it logs nothing at release time. Comms-integrity
 *   audits demand that "this confirmation email was delayed by recipient
 *   throttling" be explicitly observable in logs. This middleware is a thin
 *   wrapper that adds that single log line; everything else (limit resolution,
 *   release semantics) is identical to the framework primitive.
 *
 * Why not Redis-backed RateLimitedWithRedis?
 *   The application's default cache store is `database`/`array-in-tests`
 *   (see config/cache.php and phpunit.xml). Forcing Redis here would introduce
 *   an infrastructure coupling for a feature that does not need atomicity
 *   beyond what the configured cache provides. Operators can swap the cache
 *   store globally without touching this middleware.
 */
class ThrottlesPerRecipient
{
    public function __construct(
        private readonly string $limiterName,
    ) {}

    /**
     * @param  object  $job
     */
    public function handle($job, Closure $next): mixed
    {
        $limits = $this->resolveLimits($job);

        foreach ($limits as $limit) {
            if ($limit instanceof Unlimited) {
                continue;
            }

            $key = $this->buildKey($limit);

            if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
                $availableIn = RateLimiter::availableIn($key);

                Log::info('queue.recipient_throttle.released', [
                    'job_class' => $job::class,
                    'limiter' => $this->limiterName,
                    'recipient_key' => $key,
                    'release_in_seconds' => $availableIn,
                    'attempt' => method_exists($job, 'attempts') ? $job->attempts() : null,
                ]);

                // release() re-queues with the supplied delay. The job is NOT
                // marked completed; the recipient throttle release also does
                // not consume a $tries attempt, matching framework behavior.
                $job->release($availableIn);

                return null;
            }

            RateLimiter::hit($key, $limit->decaySeconds);
        }

        return $next($job);
    }

    /**
     * Unlimited extends Limit, so list<Limit> covers both single-Limit and
     * Unlimited callback returns without widening the contract.
     *
     * @return list<Limit>
     */
    private function resolveLimits(object $job): array
    {
        $callback = RateLimiter::limiter($this->limiterName);

        if ($callback === null) {
            throw new LogicException(
                "Named rate limiter [{$this->limiterName}] is not registered."
            );
        }

        /** @var mixed $result */
        $result = $callback($job);

        try {
            return $this->normalizeLimits($result);
        } catch (LogicException $e) {
            // Preserve the operator-friendly limiter-name context that the
            // pre-refactor exception carried, while still going through the
            // strict normalizer for Psalm soundness.
            throw new LogicException(
                "Named rate limiter [{$this->limiterName}] returned an invalid value: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Reduce a limiter callback's dynamic return value to a strict list<Limit>.
     * Runtime checks here are what allow resolveLimits() to honor its declared
     * return type without a Psalm suppression.
     *
     * @return list<Limit>
     */
    private function normalizeLimits(mixed $value): array
    {
        if ($value instanceof Limit) {
            return [$value];
        }

        if (! is_array($value)) {
            throw new LogicException(
                'Throttle limits must be a Limit instance or an array of Limit instances.'
            );
        }

        $limits = array_values($value);

        foreach ($limits as $limit) {
            if (! $limit instanceof Limit) {
                throw new LogicException(
                    'Every throttle limit must be an instance of Illuminate\\Cache\\RateLimiting\\Limit.'
                );
            }
        }

        /** @var list<Limit> $limits */
        return $limits;
    }

    /**
     * Mirrors Laravel's internal key derivation: limiter-name + Limit::by()
     * scope. Falls back to the limiter name alone if the callback did not
     * supply a per-recipient scope (which would defeat the purpose, but is
     * harmless to handle gracefully).
     */
    private function buildKey(Limit $limit): string
    {
        $scope = (string) ($limit->key ?? '');

        return $scope === ''
            ? $this->limiterName
            : $this->limiterName.':'.$scope;
    }
}
