<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to log request performance metrics.
 *
 * This middleware tracks request duration, memory usage, and other
 * performance metrics for monitoring and alerting purposes.
 */
class LogPerformance
{
    /**
     * Slow request threshold in milliseconds.
     */
    protected const SLOW_REQUEST_THRESHOLD_MS = 1000;

    /**
     * High memory threshold in MB.
     */
    protected const HIGH_MEMORY_THRESHOLD_MB = 64;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $this->logPerformanceMetrics($request, $response, $startTime, $startMemory);

        return $response;
    }

    /**
     * Log performance metrics for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  float  $startTime
     * @param  int  $startMemory
     * @return void
     */
    protected function logPerformanceMetrics(
        Request $request,
        Response $response,
        float $startTime,
        int $startMemory
    ): void {
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024; // Convert to MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // Convert to MB

        $metrics = [
            'type' => 'performance',
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'route' => $request->route()?->getName() ?? 'unknown',
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'memory_used_mb' => round($memoryUsed, 2),
            'peak_memory_mb' => round($peakMemory, 2),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ];

        // Determine log level based on performance
        $level = $this->determineLogLevel($duration, $peakMemory, $response->getStatusCode());

        Log::channel('performance')->{$level}('Request completed', $metrics);

        // Log slow requests separately for alerting
        if ($duration >= self::SLOW_REQUEST_THRESHOLD_MS) {
            Log::channel('performance')->warning('Slow request detected', array_merge($metrics, [
                'threshold_ms' => self::SLOW_REQUEST_THRESHOLD_MS,
            ]));
        }

        // Log high memory usage
        if ($peakMemory >= self::HIGH_MEMORY_THRESHOLD_MB) {
            Log::channel('performance')->warning('High memory usage detected', array_merge($metrics, [
                'threshold_mb' => self::HIGH_MEMORY_THRESHOLD_MB,
            ]));
        }
    }

    /**
     * Determine the appropriate log level based on metrics.
     *
     * @param  float  $duration
     * @param  float  $peakMemory
     * @param  int  $statusCode
     * @return string
     */
    protected function determineLogLevel(float $duration, float $peakMemory, int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'error';
        }

        if ($statusCode >= 400) {
            return 'warning';
        }

        if ($duration >= self::SLOW_REQUEST_THRESHOLD_MS || $peakMemory >= self::HIGH_MEMORY_THRESHOLD_MB) {
            return 'warning';
        }

        return 'info';
    }
}
