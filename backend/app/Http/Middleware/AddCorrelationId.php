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
 * OBS-001: The server ALWAYS generates the canonical correlation ID. A
 * client-supplied X-Correlation-ID is captured separately and only kept
 * after format validation. Untrusted client IDs cannot poison server logs
 * or be returned as the authoritative trace identifier.
 */
class AddCorrelationId
{
    /**
     * The header name for the server-generated correlation ID.
     */
    public const HEADER_NAME = 'X-Correlation-ID';

    /**
     * The header name used when echoing a validated client-supplied ID.
     */
    public const CLIENT_HEADER_NAME = 'X-Client-Correlation-ID';

    /**
     * Allowed format for a client-supplied correlation ID:
     * 8–64 chars, alphanumerics + hyphen only.
     */
    private const CLIENT_ID_PATTERN = '/^[a-zA-Z0-9\-]{8,64}$/';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $serverCorrelationId = (string) Str::uuid();
        $clientCorrelationId = $this->extractClientCorrelationId($request);

        $request->attributes->set('correlation_id', $serverCorrelationId);
        $request->attributes->set('client_correlation_id', $clientCorrelationId);

        Log::shareContext([
            'correlation_id' => $serverCorrelationId,
            'client_correlation_id' => $clientCorrelationId,
            'request_id' => (string) Str::uuid(),
        ]);

        $response = $next($request);

        $response->headers->set(self::HEADER_NAME, $serverCorrelationId);
        if ($clientCorrelationId !== null) {
            $response->headers->set(self::CLIENT_HEADER_NAME, $clientCorrelationId);
        }

        return $response;
    }

    /**
     * Validate and capture a client-supplied correlation ID, or return null.
     */
    private function extractClientCorrelationId(Request $request): ?string
    {
        $supplied = $request->header(self::HEADER_NAME);

        if (! is_string($supplied) || $supplied === '') {
            return null;
        }

        if (preg_match(self::CLIENT_ID_PATTERN, $supplied) !== 1) {
            return null;
        }

        return $supplied;
    }
}
