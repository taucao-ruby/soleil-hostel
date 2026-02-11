<?php

namespace App\Octane;

use Laravel\Octane\Events\RequestReceived;

/**
 * N+1 Query Prevention Listener for Octane
 *
 * Attaches query listeners to each request to track N+1 patterns
 * and provide real-time metrics to performance monitoring tools.
 */
class NPlusOneDetectionListener
{
    private array $queryMetrics = [];

    public function handle(RequestReceived $event): void
    {
        $this->queryMetrics = [
            'total' => 0,
            'models' => [],
            'start_time' => microtime(true),
        ];

        // Attach query listener to database
        $manager = app('db');

        foreach ($manager->getConnections() as $connection) {
            $connection->listen(function ($query) {
                $this->recordQuery($query);
            });
        }
    }

    private function recordQuery($query): void
    {
        $this->queryMetrics['total']++;

        // Extract model name from SQL (simplified detection)
        if (preg_match('/from\s+`?(\w+)`?/i', $query->sql, $matches)) {
            $table = $matches[1];
            $this->queryMetrics['models'][$table] = ($this->queryMetrics['models'][$table] ?? 0) + 1;
        }

        // Log warning if threshold exceeded
        if ($this->queryMetrics['total'] > 50) {
            \Log::warning('N+1 Query Detection: Query count exceeded 50', [
                'total_queries' => $this->queryMetrics['total'],
                'request_path' => request()->path(),
            ]);
        }
    }

    /**
     * Get current request metrics
     */
    public function getMetrics(): array
    {
        $duration = microtime(true) - $this->queryMetrics['start_time'];

        return [
            'total_queries' => $this->queryMetrics['total'],
            'duration_ms' => round($duration * 1000, 2),
            'models' => $this->queryMetrics['models'],
            'possible_nplusone' => $this->detectNPlusOne(),
        ];
    }

    /**
     * Detect potential N+1 patterns
     */
    private function detectNPlusOne(): bool
    {
        if ($this->queryMetrics['total'] < 20) {
            return false;
        }

        // If single table queried many times, likely N+1
        foreach ($this->queryMetrics['models'] as $table => $count) {
            if ($count > 5) {
                return true;
            }
        }

        return false;
    }
}
