<?php

declare(strict_types=1);

namespace App\AiHarness\Middleware;

use App\Services\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kill switch middleware for AI harness.
 *
 * Returns 404 (no info leak) when the AI subsystem is disabled.
 *
 * Resolution order (Batch 4 / 3E):
 *   1. Redis-backed FeatureFlag `ai_harness.enabled` — operator can flip this
 *      without redeploying via `php artisan feature:toggle ai_harness.enabled off`.
 *   2. Falls back to config('ai_harness.enabled') (env-driven) when Redis is
 *      unavailable or the flag has never been set. This preserves existing
 *      env-only behaviour for legacy environments.
 */
class AiHarnessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $configDefault = (bool) config('ai_harness.enabled');

        if (! FeatureFlag::get('ai_harness.enabled', $configDefault)) {
            abort(404);
        }

        return $next($request);
    }
}
