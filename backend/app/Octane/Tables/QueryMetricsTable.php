<?php

namespace App\Octane\Tables;

use Laravel\Octane\Tables\Table;

/**
 * Query Metrics Table for Octane Workers
 *
 * Tracks query statistics across all workers for real-time performance monitoring
 */
class QueryMetricsTable
{
    public static function create(): Table
    {
        return Table::make('query-metrics')
            ->column('total_queries', initialValue: 0)
            ->column('slow_queries', initialValue: 0)
            ->column('request_count', initialValue: 0)
            ->column('last_nplusone_detection', initialValue: null)
            ->column('avg_query_time', initialValue: 0.0);
    }
}
