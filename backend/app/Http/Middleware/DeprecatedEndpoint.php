<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * DeprecatedEndpoint Middleware
 * 
 * Adds RFC 8594 deprecation headers to responses for deprecated endpoints.
 * Non-blocking: request proceeds normally, headers signal deprecation.
 * 
 * Usage in routes:
 *   Route::post('/auth/login', ...)->middleware('deprecated:2026-07-01,/api/auth/login-v2');
 * 
 * Headers set:
 *   - Deprecation: RFC 7231 date when deprecation started
 *   - Sunset: RFC 7231 date when endpoint will be removed
 *   - Link: rel="successor-version" pointing to replacement
 *   - X-Deprecation-Notice: Human-readable message
 * 
 * @see https://datatracker.ietf.org/doc/html/rfc8594
 */
class DeprecatedEndpoint
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $sunset Date in Y-m-d format when endpoint will be removed
     * @param string|null $successor Optional replacement endpoint URL
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $sunset, ?string $successor = null): Response
    {
        $response = $next($request);

        // Parse sunset date
        try {
            $sunsetDate = \Carbon\Carbon::parse($sunset);
        } catch (\Exception $e) {
            // Invalid date format, skip deprecation headers
            Log::warning('DeprecatedEndpoint: Invalid sunset date format', [
                'sunset' => $sunset,
                'endpoint' => $request->path(),
            ]);
            return $response;
        }

        // Set RFC 8594 deprecation headers
        $response->headers->set('Deprecation', now()->toRfc7231String());
        $response->headers->set('Sunset', $sunsetDate->toRfc7231String());
        
        $notice = "This endpoint is deprecated and will be removed on {$sunsetDate->format('Y-m-d')}.";
        if ($successor) {
            $notice .= " Use {$successor} instead.";
            $response->headers->set('Link', "<{$successor}>; rel=\"successor-version\"");
        }
        $response->headers->set('X-Deprecation-Notice', $notice);

        // Log deprecation usage for monitoring migration progress
        Log::channel('single')->info('Deprecated endpoint accessed', [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'client' => $request->header('User-Agent'),
            'sunset' => $sunset,
            'successor' => $successor,
        ]);

        return $response;
    }
}
