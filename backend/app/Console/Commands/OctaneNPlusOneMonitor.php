<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OctaneNPlusOneMonitor extends Command
{
    protected $signature = 'octane:monitor-nplusone {--interval=5 : Check interval in seconds}';

    protected $description = 'Monitor N+1 queries in Octane workers and alert on issues';

    public function handle(): int
    {
        $interval = $this->option('interval');

        $this->info('🚀 Starting N+1 Query Monitor for Octane');
        $this->info("Checking every {$interval} seconds...\n");

        while (true) {
            $this->checkNPlusOneMetrics();
            sleep($interval);
        }

        return 0;
    }

    private function checkNPlusOneMetrics(): void
    {
        // Get metrics from Octane table
        $table = \Octane::table('query-metrics');

        $totalQueries = $table->get('total_queries');
        $slowQueries = $table->get('slow_queries');
        $requestCount = $table->get('request_count');
        $lastDetection = $table->get('last_nplusone_detection');

        if ($requestCount === 0) {
            return; // No data yet
        }

        $avgQueriesPerRequest = $totalQueries / $requestCount;

        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Total Queries', $totalQueries, '📊'],
                ['Requests Processed', $requestCount, '✅'],
                ['Avg Queries/Request', round($avgQueriesPerRequest, 2), $this->getStatus($avgQueriesPerRequest)],
                ['Slow Queries (>1s)', $slowQueries, $slowQueries > 0 ? '⚠️' : '✅'],
                ['Last N+1 Detection', $lastDetection ?? 'None', $lastDetection ? '🔴' : '✅'],
            ]
        );

        // Alert if too many queries
        if ($avgQueriesPerRequest > 20) {
            $this->error("⚠️  HIGH QUERY COUNT: {$avgQueriesPerRequest} queries per request!");
        }
    }

    private function getStatus(float $avgQueries): string
    {
        if ($avgQueries < 5) {
            return '✅ Excellent';
        }
        if ($avgQueries < 10) {
            return '🟢 Good';
        }
        if ($avgQueries < 20) {
            return '🟡 Fair';
        }

        return '🔴 Critical';
    }
}
