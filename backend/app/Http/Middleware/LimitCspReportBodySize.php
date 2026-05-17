<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitCspReportBodySize
{
    public function handle(Request $request, Closure $next, int|string|null $maxBytes = null): Response
    {
        $limit = is_numeric($maxBytes) ? (int) $maxBytes : 4096;
        $contentLength = $request->headers->get('Content-Length') ?? $request->server->get('CONTENT_LENGTH');

        if (is_numeric($contentLength) && (int) $contentLength > $limit) {
            return response()->json([
                'message' => 'CSP violation report payload is too large.',
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (strlen($request->getContent()) > $limit) {
            return response()->json([
                'message' => 'CSP violation report payload is too large.',
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
