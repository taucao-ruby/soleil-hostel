<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * Query Debugger Listener - Automatic N+1 detection
 *
 * N+1 pattern:
 * 1 query fetches 100 items
 * + 100 queries fetch child items
 * = 101 queries instead of 1 query + JOIN
 *
 * This listener tracks and alerts on threshold violations
 */
class QueryDebuggerListener
{
    private array $queries = [];

    private array $bindings = [];

    public function handle(QueryExecuted $event): void
    {
        if (! config('query-detector.enabled')) {
            return;
        }

        // Track query
        $sql = $this->formatSql($event->sql, $event->bindings);

        $this->queries[] = [
            'sql' => $sql,
            'time' => $event->time,
            'bindings' => $event->bindings,
        ];

        // Alert if threshold exceeded
        if (count($this->queries) > config('query-detector.threshold')) {
            Log::warning('⚠️ N+1 QUERY DETECTED!', [
                'total_queries' => count($this->queries),
                'threshold' => config('query-detector.threshold'),
                'last_queries' => array_slice($this->queries, -5), // last 5 queries
            ]);

            // Throw exception during testing to fail CI
            if (app()->runningUnitTests()) {
                throw new \RuntimeException(
                    sprintf(
                        'N+1 Query Detected: %d queries executed, threshold is %d',
                        count($this->queries),
                        config('query-detector.threshold')
                    )
                );
            }
        }
    }

    private function formatSql(string $sql, array $bindings): string
    {
        $sql = trim($sql);

        foreach ($bindings as $binding) {
            $value = is_string($binding) ? "'$binding'" : $binding;
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }

    public static function getQueryCount(): int
    {
        return app(self::class)->count(app(self::class)->queries);
    }
}
