<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to add correlation ID for request tracing.
 *
 * This middleware generates or propagates a correlation ID for each request,
 * enabling end-to-end request tracing across services and log aggregation.
 */
class AddCorrelationId
{
    /**
     * The header name for correlation ID.
     */
    public const HEADER_NAME = 'X-Correlation-ID';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get existing correlation ID from header or generate new one
        $correlationId = $request->header(self::HEADER_NAME) ?? $this->generateCorrelationId();

        // Store in request for later use
        $request->attributes->set('correlation_id', $correlationId);

        // Add to log context for all subsequent log calls
        Log::withContext([
            'correlation_id' => $correlationId,
            'request_id' => $this->generateRequestId(),
        ]);

        // Process request
        $response = $next($request);

        // Add correlation ID to response headers
        $response->headers->set(self::HEADER_NAME, $correlationId);

        return $response;
    }

    /**
     * Generate a unique correlation ID.
     *
     * Format: sol-{timestamp}-{random}
     * Example: sol-1704153600-a1b2c3d4
     *
     * @return string
     */
    protected function generateCorrelationId(): string
    {
        return sprintf(
            'sol-%d-%s',
            time(),
            Str::random(8)
        );
    }

    /**
     * Generate a unique request ID.
     *
     * @return string
     */
    protected function generateRequestId(): string
    {
        return Str::uuid()->toString();
    }
}
