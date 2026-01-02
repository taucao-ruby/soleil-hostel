<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Health check controller for monitoring endpoints.
 *
 * Provides endpoints for load balancers, monitoring systems,
 * and container orchestration health checks.
 */
class HealthController extends Controller
{
    /**
     * Basic liveness probe.
     *
     * Returns 200 if the application is running.
     * Used by Kubernetes/Docker for liveness checks.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe with dependency checks.
     *
     * Returns 200 only if all critical dependencies are available.
     * Used by Kubernetes for readiness checks.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function readiness(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
        ];

        $allHealthy = ! in_array(false, array_column($checks, 'healthy'));
        $status = $allHealthy ? 'ok' : 'degraded';
        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }

    /**
     * Detailed health check with metrics.
     *
     * Provides comprehensive health information for monitoring dashboards.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailed(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = ! in_array(false, array_column($checks, 'healthy'));
        $healthyCount = count(array_filter(array_column($checks, 'healthy')));
        $totalCount = count($checks);

        $metrics = [
            'uptime_seconds' => $this->getUptimeSeconds(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
            ],
            'checks' => $checks,
            'summary' => [
                'healthy' => $healthyCount,
                'total' => $totalCount,
                'percentage' => round(($healthyCount / $totalCount) * 100, 1),
            ],
            'metrics' => $metrics,
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Check database connectivity.
     *
     * @return array
     */
    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latency,
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity.
     *
     * @return array
     */
    protected function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . uniqid();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => $value === 'test',
                'latency_ms' => $latency,
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connectivity.
     *
     * @return array
     */
    protected function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage writability.
     *
     * @return array
     */
    protected function checkStorage(): array
    {
        try {
            $testFile = storage_path('app/health_check_' . uniqid());
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            return [
                'healthy' => $content === 'test',
                'writable' => true,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue connectivity.
     *
     * @return array
     */
    protected function checkQueue(): array
    {
        try {
            $driver = config('queue.default');

            // For sync driver, just return healthy
            if ($driver === 'sync') {
                return [
                    'healthy' => true,
                    'driver' => $driver,
                    'note' => 'Sync driver - no external connection',
                ];
            }

            // For Redis queue, check connection
            if ($driver === 'redis') {
                Redis::connection(config('queue.connections.redis.connection', 'default'))->ping();

                return [
                    'healthy' => true,
                    'driver' => $driver,
                ];
            }

            return [
                'healthy' => true,
                'driver' => $driver,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get application uptime in seconds.
     *
     * @return int
     */
    protected function getUptimeSeconds(): int
    {
        // Use Laravel's startup time if using Octane
        if (defined('LARAVEL_START')) {
            return (int) (microtime(true) - LARAVEL_START);
        }

        return 0;
    }
}
