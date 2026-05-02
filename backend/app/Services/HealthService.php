<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * HealthService — encapsulates all infrastructure health check logic.
 *
 * Extracted from HealthController (M-01) to keep the controller thin.
 *
 * FAILURE SEMANTICS for Soleil Hostel Booking System:
 * - Database: CRITICAL — 503 if down (booking engine, optimistic locking, money paths)
 * - Cache: DEGRADED — 200 with warning (system still operable, reduced performance)
 * - Queue: DEGRADED — 200 with warning (async jobs delayed, OTA sync may lag)
 *
 * OBS-002: Exception messages are NEVER propagated to HTTP responses. They
 * are logged server-side and replaced with a static `error_code` so that
 * connection strings, hostnames, and stack-trace-like text never leak.
 */
class HealthService
{
    /**
     * Component criticality levels for failure semantics.
     */
    public const CRITICAL_COMPONENTS = ['database'];

    public const DEGRADED_COMPONENTS = ['cache', 'queue', 'redis'];

    /**
     * Static error code returned in place of raw exception messages.
     */
    private const ERROR_CODE_CHECK_FAILED = 'check_failed';

    private const ERROR_CODE_DEPENDENCY_MISSING = 'dependency_missing';

    /**
     * Run a basic health check (DB + Redis + memory).
     *
     * @return array{status: string, timestamp: string, services: array, status_code: int}
     */
    public function basicCheck(): array
    {
        $services = [];
        $status = 'healthy';

        try {
            DB::connection()->getPdo();
            $services['database'] = [
                'status' => 'up',
                'connection' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            $this->logCheckFailure('database', $e);
            $status = 'unhealthy';
            $services['database'] = [
                'status' => 'down',
                'error_code' => self::ERROR_CODE_CHECK_FAILED,
            ];
        }

        try {
            if (! extension_loaded('redis')) {
                $status = 'unhealthy';
                $services['redis'] = [
                    'status' => 'down',
                    'error_code' => self::ERROR_CODE_DEPENDENCY_MISSING,
                ];
            } else {
                Redis::ping();
                $services['redis'] = [
                    'status' => 'up',
                    'connections' => ['default'],
                ];
            }
        } catch (\Throwable $e) {
            $this->logCheckFailure('redis', $e);
            $status = 'unhealthy';
            $services['redis'] = [
                'status' => 'down',
                'error_code' => self::ERROR_CODE_CHECK_FAILED,
            ];
        }

        $services['memory'] = [
            'status' => 'ok',
            'usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            'limit_mb' => (int) ini_get('memory_limit'),
        ];

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
            'status_code' => $status === 'healthy' ? 200 : 503,
        ];
    }

    /**
     * Run readiness check (DB + cache + Redis).
     *
     * @return array{status: string, timestamp: string, checks: array, failure_semantics: array, status_code: int}
     */
    public function readinessCheck(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
        ];

        [$status, $statusCode] = $this->determineOverallStatus($checks);

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'failure_semantics' => [
                'critical' => self::CRITICAL_COMPONENTS,
                'degraded' => self::DEGRADED_COMPONENTS,
            ],
            'status_code' => $statusCode,
        ];
    }

    /**
     * Run detailed health check with metrics.
     *
     * @return array{status: string, timestamp: string, app: array, checks: array, summary: array, failure_semantics: array, metrics: array, status_code: int}
     */
    public function detailedCheck(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        [$status, $statusCode] = $this->determineOverallStatus($checks);

        $healthyCount = count(array_filter(array_column($checks, 'healthy')));
        $totalCount = count($checks);

        $degradedComponents = [];
        foreach (self::DEGRADED_COMPONENTS as $component) {
            if (isset($checks[$component]) && ! $checks[$component]['healthy']) {
                $degradedComponents[] = $component;
            }
        }

        return [
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
            'metrics' => [
                'uptime_seconds' => $this->getUptimeSeconds(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'status_code' => $statusCode,
        ];
    }

    /**
     * Check a single component.
     *
     * @return array<string, mixed>
     */
    public function checkComponent(string $component): array
    {
        return match ($component) {
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            default => ['healthy' => false, 'error' => "Unknown component: {$component}"],
        };
    }

    /**
     * Check database connectivity.
     *
     * @return array<string, mixed>
     */
    public function checkDatabase(): array
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
        } catch (\Throwable $e) {
            $this->logCheckFailure('database', $e);

            return [
                'healthy' => false,
                'error_code' => self::ERROR_CODE_CHECK_FAILED,
            ];
        }
    }

    /**
     * Check cache connectivity.
     *
     * @return array<string, mixed>
     */
    public function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_'.uniqid();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => $value === 'test',
                'latency_ms' => $latency,
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            $this->logCheckFailure('cache', $e);

            return [
                'healthy' => false,
                'error_code' => self::ERROR_CODE_CHECK_FAILED,
            ];
        }
    }

    /**
     * Check Redis connectivity.
     *
     * @return array<string, mixed>
     */
    public function checkRedis(): array
    {
        try {
            if (! extension_loaded('redis')) {
                return [
                    'healthy' => false,
                    'error_code' => self::ERROR_CODE_DEPENDENCY_MISSING,
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
            $this->logCheckFailure('redis', $e);

            return [
                'healthy' => false,
                'error_code' => self::ERROR_CODE_CHECK_FAILED,
            ];
        }
    }

    /**
     * Check storage writability.
     *
     * @return array<string, mixed>
     */
    public function checkStorage(): array
    {
        try {
            $testFile = storage_path('app/health_check_'.uniqid());
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            return [
                'healthy' => $content === 'test',
                'writable' => true,
            ];
        } catch (\Throwable $e) {
            $this->logCheckFailure('storage', $e);

            return [
                'healthy' => false,
                'error_code' => self::ERROR_CODE_CHECK_FAILED,
            ];
        }
    }

    /**
     * Check queue connectivity.
     *
     * @return array<string, mixed>
     */
    public function checkQueue(): array
    {
        try {
            $driver = config('queue.default');

            if ($driver === 'sync') {
                return [
                    'healthy' => true,
                    'driver' => $driver,
                    'note' => 'Sync driver - no external connection',
                ];
            }

            if ($driver === 'redis') {
                if (! extension_loaded('redis')) {
                    return [
                        'healthy' => false,
                        'driver' => $driver,
                        'error_code' => self::ERROR_CODE_DEPENDENCY_MISSING,
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
            $this->logCheckFailure('queue', $e);

            return [
                'healthy' => false,
                'error_code' => self::ERROR_CODE_CHECK_FAILED,
            ];
        }
    }

    /**
     * Determine overall status based on component criticality.
     *
     * @return array{0: string, 1: int} [status, statusCode]
     */
    private function determineOverallStatus(array $checks): array
    {
        $criticalHealthy = $this->areCriticalComponentsHealthy($checks);
        $allHealthy = ! in_array(false, array_column($checks, 'healthy'));

        if (! $criticalHealthy) {
            return ['unhealthy', 503];
        }

        if (! $allHealthy) {
            return ['degraded', 200];
        }

        return ['ok', 200];
    }

    /**
     * Check if all critical components are healthy.
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
     * Get application uptime in seconds.
     */
    private function getUptimeSeconds(): int
    {
        if (defined('LARAVEL_START')) {
            return (int) (microtime(true) - LARAVEL_START);
        }

        return 0;
    }

    /**
     * Log a health-check exception server-side without surfacing details to the caller.
     */
    private function logCheckFailure(string $component, \Throwable $e): void
    {
        Log::warning('Health check failed', [
            'component' => $component,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
