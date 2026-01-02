<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for database query logging.
 *
 * This provider enables detailed query logging for debugging
 * and performance monitoring purposes.
 */
class QueryLogServiceProvider extends ServiceProvider
{
    /**
     * Slow query threshold in milliseconds.
     */
    protected const SLOW_QUERY_THRESHOLD_MS = 100;

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only enable query logging in non-production or when explicitly enabled
        if (! $this->shouldLogQueries()) {
            return;
        }

        DB::listen(function (QueryExecuted $query) {
            $this->logQuery($query);
        });
    }

    /**
     * Determine if queries should be logged.
     *
     * @return bool
     */
    protected function shouldLogQueries(): bool
    {
        // Always log in local/testing environments
        if (app()->environment(['local', 'testing'])) {
            return config('logging.log_queries', true);
        }

        // In production, only log slow queries or when explicitly enabled
        return config('logging.log_queries', false);
    }

    /**
     * Log a database query.
     *
     * @param  \Illuminate\Database\Events\QueryExecuted  $query
     * @return void
     */
    protected function logQuery(QueryExecuted $query): void
    {
        $timeMs = $query->time;
        $sql = $query->sql;
        $bindings = $query->bindings;
        $connection = $query->connectionName;

        $context = [
            'type' => 'query',
            'sql' => $sql,
            'bindings' => $this->maskSensitiveBindings($bindings),
            'time_ms' => round($timeMs, 2),
            'connection' => $connection,
        ];

        // Log slow queries with warning level
        if ($timeMs >= self::SLOW_QUERY_THRESHOLD_MS) {
            Log::channel('query')->warning('Slow query detected', array_merge($context, [
                'threshold_ms' => self::SLOW_QUERY_THRESHOLD_MS,
            ]));

            return;
        }

        // Log all queries at debug level
        if (config('logging.log_all_queries', false)) {
            Log::channel('query')->debug('Query executed', $context);
        }
    }

    /**
     * Mask sensitive values in query bindings.
     *
     * @param  array  $bindings
     * @return array
     */
    protected function maskSensitiveBindings(array $bindings): array
    {
        $sensitivePatterns = ['password', 'token', 'secret', 'key'];

        return array_map(function ($binding) use ($sensitivePatterns) {
            if (is_string($binding)) {
                foreach ($sensitivePatterns as $pattern) {
                    if (stripos($binding, $pattern) !== false) {
                        return '********';
                    }
                }

                // Mask long strings that might be tokens
                if (strlen($binding) > 64 && preg_match('/^[A-Za-z0-9+\/=]+$/', $binding)) {
                    return '********';
                }
            }

            return $binding;
        }, $bindings);
    }
}
