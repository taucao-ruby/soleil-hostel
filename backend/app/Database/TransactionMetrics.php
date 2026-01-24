<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Support\Facades\Log;

/**
 * TransactionMetrics - Transaction Monitoring and Metrics Collection
 * 
 * Provides centralized metrics collection for transaction health monitoring.
 * Integrates with application logging and can be extended for external
 * metrics systems (Prometheus, StatsD, etc.).
 * 
 * Metrics tracked:
 * - Transaction success/failure rates
 * - Duration by operation and isolation level
 * - Retry counts and patterns
 * - Error type distribution
 */
final class TransactionMetrics
{
    /**
     * Record a successful transaction.
     * 
     * @param string $operation Operation name (e.g., 'create_booking')
     * @param string $isolationLevel Isolation level used
     * @param float $durationMs Duration in milliseconds
     * @param int $retryCount Number of retries before success (0 = first attempt)
     */
    public static function recordSuccess(
        string $operation,
        string $isolationLevel,
        float $durationMs,
        int $retryCount = 0
    ): void {
        Log::info('Transaction completed successfully', [
            'metric' => 'transaction.success',
            'operation' => $operation,
            'isolation_level' => $isolationLevel,
            'duration_ms' => round($durationMs, 2),
            'retry_count' => $retryCount,
        ]);

        // For production: Send to metrics backend
        // Example: Prometheus, StatsD, CloudWatch
        // 
        // Metrics::increment("transaction.success", [
        //     'operation' => $operation,
        //     'isolation_level' => $isolationLevel,
        // ]);
        // 
        // Metrics::histogram("transaction.duration_ms", $durationMs, [
        //     'operation' => $operation,
        // ]);
    }

    /**
     * Record a failed transaction.
     * 
     * @param string $operation Operation name
     * @param string $isolationLevel Isolation level used
     * @param int $attemptCount Total attempts made
     * @param float $totalDurationMs Total duration across all attempts
     */
    public static function recordFailure(
        string $operation,
        string $isolationLevel,
        int $attemptCount,
        float $totalDurationMs
    ): void {
        Log::error('Transaction failed permanently', [
            'metric' => 'transaction.failure',
            'operation' => $operation,
            'isolation_level' => $isolationLevel,
            'attempt_count' => $attemptCount,
            'total_duration_ms' => round($totalDurationMs, 2),
        ]);

        // For production: Send alert
        // 
        // Metrics::increment("transaction.failure", [
        //     'operation' => $operation,
        //     'isolation_level' => $isolationLevel,
        // ]);
    }

    /**
     * Record a retry attempt.
     * 
     * @param string $operation Operation name
     * @param int $attemptNumber Current attempt number
     * @param string $errorType Type of error that triggered retry
     * @param int $delayMs Delay before retry in milliseconds
     */
    public static function recordRetry(
        string $operation,
        int $attemptNumber,
        string $errorType,
        int $delayMs
    ): void {
        Log::warning('Transaction retry scheduled', [
            'metric' => 'transaction.retry',
            'operation' => $operation,
            'attempt_number' => $attemptNumber,
            'error_type' => $errorType,
            'delay_ms' => $delayMs,
        ]);

        // For production:
        // Metrics::increment("transaction.retry", [
        //     'operation' => $operation,
        //     'error_type' => $errorType,
        // ]);
    }

    /**
     * Record lock wait time.
     * 
     * @param string $operation Operation name
     * @param float $waitTimeMs Time spent waiting for lock
     */
    public static function recordLockWait(
        string $operation,
        float $waitTimeMs
    ): void {
        if ($waitTimeMs > 100) { // Only log significant waits
            Log::info('Lock wait recorded', [
                'metric' => 'transaction.lock_wait',
                'operation' => $operation,
                'wait_time_ms' => round($waitTimeMs, 2),
            ]);
        }

        // For production:
        // Metrics::histogram("transaction.lock_wait_ms", $waitTimeMs, [
        //     'operation' => $operation,
        // ]);
    }

    /**
     * Record serialization failure rate for monitoring.
     * 
     * High serialization failure rates may indicate:
     * - Need for transaction ordering
     * - Potential for optimistic locking
     * - Hot-spot contention issues
     */
    public static function recordSerializationFailure(
        string $operation,
        int $attemptNumber
    ): void {
        Log::warning('Serialization failure detected', [
            'metric' => 'transaction.serialization_failure',
            'operation' => $operation,
            'attempt_number' => $attemptNumber,
        ]);

        // Alert if too many serialization failures
        // This could indicate a design problem
    }

    /**
     * Record deadlock occurrence for analysis.
     * 
     * @param string $operation Operation name
     * @param array<string> $involvedTables Tables involved in deadlock (if known)
     */
    public static function recordDeadlock(
        string $operation,
        array $involvedTables = []
    ): void {
        Log::warning('Deadlock detected', [
            'metric' => 'transaction.deadlock',
            'operation' => $operation,
            'involved_tables' => $involvedTables,
        ]);

        // For production: Alert on high deadlock rate
        // Consider reordering lock acquisition
    }

    /**
     * Get summary metrics for monitoring dashboard.
     * 
     * In production, this would query the metrics backend.
     * Here we provide a placeholder structure.
     * 
     * @param string $timeRange e.g., '1h', '24h', '7d'
     * @return array<string, mixed>
     */
    public static function getSummary(string $timeRange = '1h'): array
    {
        // Placeholder - in production, query metrics backend
        return [
            'time_range' => $timeRange,
            'total_transactions' => 0,
            'success_rate' => 0.0,
            'avg_duration_ms' => 0.0,
            'retry_rate' => 0.0,
            'serialization_failure_rate' => 0.0,
            'deadlock_rate' => 0.0,
            'by_operation' => [],
            'by_isolation_level' => [],
        ];
    }
}
