<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get allowed origins from config (array)
        $allowedOrigins = config('cors.allowed_origins', ['http://localhost:5173']);
        if (is_string($allowedOrigins)) {
            $allowedOrigins = explode(',', $allowedOrigins);
        }
        $origin = $request->header('Origin');

        $isAllowed = in_array($origin, $allowedOrigins);

        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');

            // Only set Allow-Origin if origin is in the allowed list — omit header entirely on mismatch
            if ($isAllowed) {
                $response->header('Access-Control-Allow-Origin', $origin);
            }

            return $response;
        }

        $response = $next($request);

        // Add CORS headers — omit Allow-Origin entirely when origin is not allowed
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN')
            ->header('Access-Control-Allow-Credentials', 'true');

        if ($isAllowed) {
            $response->header('Access-Control-Allow-Origin', $origin);
        }

        return $response;
    }
}
