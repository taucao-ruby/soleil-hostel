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
 *
 * FAILURE SEMANTICS for Soleil Hostel Booking System:
 * ================================================
 * - Database: CRITICAL - 503 if down (booking engine, optimistic locking, money paths)
 * - Cache: DEGRADED - 200 with warning (system still operable, reduced performance)
 * - Queue: DEGRADED - 200 with warning (async jobs delayed, OTA sync may lag)
 *
 * This aligns with the booking-critical invariant: "No database = no bookings"
 */
class HealthController extends Controller
{
    /**
     * Component criticality levels for failure semantics.
     */
    private const CRITICAL_COMPONENTS = ['database'];
    private const DEGRADED_COMPONENTS = ['cache', 'queue', 'redis'];

    /**
     * Basic liveness probe.
     *
     * Returns 200 if the application is running.
     * Used by Kubernetes/Docker for liveness checks.
     * This is a "shallow" check - just verifies the app process is alive.
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
     * Returns 200 only if CRITICAL dependencies are available.
     * Non-critical component failures result in 200 with 'degraded' status.
     * Used by Kubernetes for readiness checks.
     *
     * FAILURE SEMANTICS:
     * - Database down → 503 (system unhealthy, cannot accept requests)
     * - Cache/Queue down → 200 degraded (system operable but impaired)
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

        // Determine overall status based on criticality
        $criticalHealthy = $this->areCriticalComponentsHealthy($checks);
        $allHealthy = ! in_array(false, array_column($checks, 'healthy'));

        if (! $criticalHealthy) {
            $status = 'unhealthy';
            $statusCode = 503;
        } elseif (! $allHealthy) {
            $status = 'degraded';
            $statusCode = 200; // Still accept traffic, but warn
        } else {
            $status = 'ok';
            $statusCode = 200;
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'failure_semantics' => [
                'critical' => self::CRITICAL_COMPONENTS,
                'degraded' => self::DEGRADED_COMPONENTS,
            ],
        ], $statusCode);
    }

    /**
     * Check if all critical components are healthy.
     *
     * @param array<string, array> $checks
     * @return bool
     */
    private function areCriticalComponentsHealthy(array $checks): bool
    {
        foreach (self::CRITICAL_COMPONENTS as $component) {
            if (isset($checks[$component]) && ! $checks[$component]['healthy']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Detailed health check with metrics.
     *
     * Provides comprehensive health information for monitoring dashboards.
     * Includes all component checks, system metrics, and degradation info.
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

        $criticalHealthy = $this->areCriticalComponentsHealthy($checks);
        $allHealthy = ! in_array(false, array_column($checks, 'healthy'));
        $healthyCount = count(array_filter(array_column($checks, 'healthy')));
        $totalCount = count($checks);

        // Determine status based on criticality
        if (! $criticalHealthy) {
            $status = 'unhealthy';
            $statusCode = 503;
        } elseif (! $allHealthy) {
            $status = 'degraded';
            $statusCode = 200;
        } else {
            $status = 'ok';
            $statusCode = 200;
        }

        $metrics = [
            'uptime_seconds' => $this->getUptimeSeconds(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        // Identify degraded components for alerting
        $degradedComponents = [];
        foreach (self::DEGRADED_COMPONENTS as $component) {
            if (isset($checks[$component]) && ! $checks[$component]['healthy']) {
                $degradedComponents[] = $component;
            }
        }

        return response()->json([
            'status' => $status,
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
                'degraded_components' => $degradedComponents,
            ],
            'failure_semantics' => [
                'critical' => self::CRITICAL_COMPONENTS,
                'degraded' => self::DEGRADED_COMPONENTS,
                'note' => 'Database failure = 503 (booking engine down). Cache/Queue failure = 200 degraded (still operable).',
            ],
            'metrics' => $metrics,
        ], $statusCode);
    }

    /**
     * Individual database health check endpoint.
     *
     * CRITICAL component - 503 if unhealthy.
     * The booking system cannot operate without database (optimistic locking, money paths).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function database(): JsonResponse
    {
        $check = $this->checkDatabase();

        return response()->json([
            'component' => 'database',
            'criticality' => 'critical',
            'timestamp' => now()->toIso8601String(),
            ...$check,
        ], $check['healthy'] ? 200 : 503);
    }

    /**
     * Individual cache health check endpoint.
     *
     * DEGRADED component - 200 even if unhealthy (system still operable).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cache(): JsonResponse
    {
        $check = $this->checkCache();

        return response()->json([
            'component' => 'cache',
            'criticality' => 'degraded',
            'timestamp' => now()->toIso8601String(),
            ...$check,
        ], 200); // Always 200 - degraded component
    }

    /**
     * Individual queue health check endpoint.
     *
     * DEGRADED component - 200 even if unhealthy (async jobs delayed, OTA sync may lag).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function queue(): JsonResponse
    {
        $check = $this->checkQueue();

        return response()->json([
            'component' => 'queue',
            'criticality' => 'degraded',
            'timestamp' => now()->toIso8601String(),
            ...$check,
        ], 200); // Always 200 - degraded component
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
            // Check if Redis extension is loaded
            if (!extension_loaded('redis')) {
                return [
                    'healthy' => false,
                    'error' => 'Redis PHP extension not loaded',
                ];
            }

            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
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
                // Check if Redis extension is loaded
                if (!extension_loaded('redis')) {
                    return [
                        'healthy' => false,
                        'driver' => $driver,
                        'error' => 'Redis PHP extension not loaded',
                    ];
                }

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
        } catch (\Throwable $e) {
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
