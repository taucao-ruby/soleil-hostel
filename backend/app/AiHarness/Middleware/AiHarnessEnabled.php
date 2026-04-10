<?php

declare(strict_types=1);

namespace App\AiHarness\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kill switch middleware for AI harness.
 *
 * When config('ai_harness.enabled') is false, returns 404
 * with no information leakage about the AI subsystem.
 */
class AiHarnessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai_harness.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
