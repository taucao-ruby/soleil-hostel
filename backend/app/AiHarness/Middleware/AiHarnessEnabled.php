<?php

declare(strict_types=1);

namespace App\AiHarness\Middleware;

use App\Services\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kill switch middleware for the AI harness.
 *
 * Returns 404 (no info leak about the harness's existence) when the AI
 * subsystem is disabled.
 *
 * Resolution (Batch 8 platform hardening):
 *   FeatureFlag::killSwitch('ai_harness.enabled') — sticky-off Redis lookup.
 *   The harness is considered ON only when an operator (or deploy script)
 *   has explicitly set the Redis flag `feature:ai_harness.enabled` to a
 *   truthy value via `php artisan feature:toggle ai_harness.enabled on`.
 *
 * Why we removed the config('ai_harness.enabled') fallback:
 *   The earlier soft-toggle implementation fell back to the env-default
 *   when Redis was unreachable. If config('ai_harness.enabled') is true
 *   and an operator had previously flipped the runtime flag OFF, a Redis
 *   outage would silently re-enable the harness — defeating the point of
 *   having a runtime kill switch. killSwitch() is sticky-off, so a Redis
 *   outage now keeps the harness disabled until Redis recovers and the
 *   operator re-sets the flag to ON.
 *
 * Bootstrap note: new deployments must run `php artisan feature:toggle
 * ai_harness.enabled on` once before the harness will accept traffic.
 * The env variable is no longer load-bearing for runtime gating.
 */
class AiHarnessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! FeatureFlag::killSwitch('ai_harness.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
