<?php

declare(strict_types=1);

namespace App\Database;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use RuntimeException;

/**
 * TransactionIsolation - Database Transaction Isolation Manager
 * 
 * Provides robust transaction handling with configurable isolation levels,
 * automatic retry logic for transient failures, and comprehensive monitoring.
 * 
 * Isolation Levels (PostgreSQL):
 * - READ COMMITTED: Default, sees committed changes during transaction
 * - REPEATABLE READ: Consistent snapshot, prevents non-repeatable reads
 * - SERIALIZABLE: Strongest, fully serializable execution
 * 
 * Error Codes (PostgreSQL):
 * - 40001: serialization_failure - Retry with backoff
 * - 40P01: deadlock_detected - Immediate retry
 * - 23505: unique_violation - Business logic error, no retry
 * - 23503: foreign_key_violation - Business logic error, no retry
 * 
 * @see https://www.postgresql.org/docs/current/transaction-iso.html
 */
final class TransactionIsolation
{
    /**
     * Isolation level constants matching PostgreSQL/MySQL naming.
     */
    public const READ_COMMITTED = 'READ COMMITTED';
    public const REPEATABLE_READ = 'REPEATABLE READ';
    public const SERIALIZABLE = 'SERIALIZABLE';

    /**
     * PostgreSQL SQLSTATE codes for transient failures.
     */
    private const SQLSTATE_SERIALIZATION_FAILURE = '40001';
    private const SQLSTATE_DEADLOCK_DETECTED = '40P01';

    /**
     * MySQL error codes for transient failures.
     */
    private const MYSQL_DEADLOCK_CODE = 1213;
    private const MYSQL_LOCK_WAIT_TIMEOUT = 1205;

    /**
     * Default retry configuration.
     */
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_BASE_DELAY_MS = 100;
    private const DEFAULT_TIMEOUT_SECONDS = 30;

    /**
     * Execute a callback within a transaction with specified isolation level.
     * 
     * @template T
     * @param Closure(): T $callback The operation to execute
     * @param string $isolationLevel One of: READ COMMITTED, REPEATABLE READ, SERIALIZABLE
     * @param array{
     *   maxRetries?: int,
     *   baseDelayMs?: int,
     *   timeout?: int,
     *   operationName?: string
     * } $options Additional options
     * @return T The callback result
     * @throws RuntimeException When all retries exhausted
     * @throws PDOException For non-transient database errors
     */
    public static function run(
        Closure $callback,
        string $isolationLevel = self::READ_COMMITTED,
        array $options = []
    ): mixed {
        $maxRetries = $options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES;
        $baseDelayMs = $options['baseDelayMs'] ?? self::DEFAULT_BASE_DELAY_MS;
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT_SECONDS;
        $operationName = $options['operationName'] ?? 'unknown_operation';

        $attempt = 0;
        $startTime = microtime(true);
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            $attemptStart = microtime(true);

            try {
                return self::executeWithIsolation($callback, $isolationLevel, $timeout);

            } catch (PDOException $e) {
                $lastException = $e;
                $errorInfo = self::parseErrorInfo($e);

                // Log the failure
                Log::warning("Transaction attempt {$attempt}/{$maxRetries} failed", [
                    'operation' => $operationName,
                    'isolation_level' => $isolationLevel,
                    'error_type' => $errorInfo['type'],
                    'sqlstate' => $errorInfo['sqlstate'],
                    'message' => $e->getMessage(),
                    'duration_ms' => round((microtime(true) - $attemptStart) * 1000, 2),
                ]);

                // Determine if we should retry
                if (!$errorInfo['retryable'] || $attempt >= $maxRetries) {
                    break;
                }

                // Calculate delay with exponential backoff
                $delayMs = self::calculateDelay(
                    $attempt,
                    $baseDelayMs,
                    $errorInfo['type']
                );

                // Wait before retry
                usleep($delayMs * 1000);
            }
        }

        // All retries exhausted or non-retryable error
        $totalDuration = round((microtime(true) - $startTime) * 1000, 2);

        Log::error("Transaction failed after {$attempt} attempts", [
            'operation' => $operationName,
            'isolation_level' => $isolationLevel,
            'total_duration_ms' => $totalDuration,
            'final_error' => $lastException?->getMessage(),
        ]);

        // Fire metric event for monitoring
        TransactionMetrics::recordFailure($operationName, $isolationLevel, $attempt, $totalDuration);

        throw new RuntimeException(
            "Transaction failed after {$attempt} attempts: " . ($lastException?->getMessage() ?? 'Unknown error'),
            0,
            $lastException
        );
    }

    /**
     * Execute callback with explicit isolation level.
     * 
     * @template T
     * @param Closure(): T $callback
     * @param string $isolationLevel
     * @param int $timeout
     * @return T
     */
    private static function executeWithIsolation(
        Closure $callback,
        string $isolationLevel,
        int $timeout
    ): mixed {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        // Set isolation level before beginning transaction
        if ($isolationLevel !== self::READ_COMMITTED) {
            match ($driver) {
                'pgsql' => $connection->statement(
                    "SET TRANSACTION ISOLATION LEVEL {$isolationLevel}"
                ),
                'mysql' => $connection->statement(
                    "SET TRANSACTION ISOLATION LEVEL {$isolationLevel}"
                ),
                default => Log::warning("Isolation level not supported for driver: {$driver}"),
            };
        }

        // Set statement timeout for PostgreSQL
        if ($driver === 'pgsql' && $timeout > 0) {
            $timeoutMs = $timeout * 1000;
            $connection->statement("SET LOCAL statement_timeout = {$timeoutMs}");
        }

        return DB::transaction($callback);
    }

    /**
     * Parse PDOException to determine error type and retryability.
     * 
     * @param PDOException $e
     * @return array{type: string, sqlstate: string, retryable: bool}
     */
    private static function parseErrorInfo(PDOException $e): array
    {
        $sqlstate = (string) ($e->errorInfo[0] ?? $e->getCode());
        $errorCode = (int) ($e->errorInfo[1] ?? 0);
        $message = strtolower($e->getMessage());

        // PostgreSQL serialization failure (40001)
        if ($sqlstate === self::SQLSTATE_SERIALIZATION_FAILURE) {
            return [
                'type' => 'serialization_failure',
                'sqlstate' => $sqlstate,
                'retryable' => true,
            ];
        }

        // PostgreSQL deadlock (40P01)
        if ($sqlstate === self::SQLSTATE_DEADLOCK_DETECTED) {
            return [
                'type' => 'deadlock',
                'sqlstate' => $sqlstate,
                'retryable' => true,
            ];
        }

        // MySQL deadlock
        if ($errorCode === self::MYSQL_DEADLOCK_CODE || str_contains($message, 'deadlock')) {
            return [
                'type' => 'deadlock',
                'sqlstate' => $sqlstate,
                'retryable' => true,
            ];
        }

        // MySQL lock wait timeout
        if ($errorCode === self::MYSQL_LOCK_WAIT_TIMEOUT || str_contains($message, 'lock wait timeout')) {
            return [
                'type' => 'lock_timeout',
                'sqlstate' => $sqlstate,
                'retryable' => true,
            ];
        }

        // SQLite busy
        if (str_contains($message, 'database is locked') || str_contains($message, 'sqlite_busy')) {
            return [
                'type' => 'sqlite_busy',
                'sqlstate' => $sqlstate,
                'retryable' => true,
            ];
        }

        // Non-retryable errors (constraint violations, etc.)
        return [
            'type' => 'other',
            'sqlstate' => $sqlstate,
            'retryable' => false,
        ];
    }

    /**
     * Calculate delay with exponential backoff and jitter.
     * 
     * Strategy:
     * - Deadlocks: Immediate retry with small random delay (0-50ms)
     * - Serialization: Exponential backoff with jitter
     * - Lock timeout: Longer delays to allow lock release
     * 
     * @param int $attempt Current attempt number (1-based)
     * @param int $baseDelayMs Base delay in milliseconds
     * @param string $errorType Type of error for strategy selection
     * @return int Delay in milliseconds
     */
    private static function calculateDelay(int $attempt, int $baseDelayMs, string $errorType): int
    {
        return match ($errorType) {
            // Deadlocks: Quick retry with small jitter
            'deadlock' => random_int(10, 50),
            
            // Serialization: Exponential backoff with jitter
            'serialization_failure' => (int) (
                $baseDelayMs * pow(2, $attempt - 1) + random_int(0, $baseDelayMs)
            ),
            
            // Lock timeout: Longer delays
            'lock_timeout' => (int) (
                $baseDelayMs * pow(2, $attempt) + random_int(0, $baseDelayMs * 2)
            ),
            
            // SQLite: Short delays
            'sqlite_busy' => random_int(50, 150),
            
            // Default: Standard exponential backoff
            default => (int) ($baseDelayMs * pow(2, $attempt - 1)),
        };
    }

    /**
     * Convenience method for SERIALIZABLE isolation.
     * 
     * Use for:
     * - Booking the last available slot
     * - Financial calculations requiring exact correctness
     * - Any operation with zero tolerance for anomalies
     * 
     * @template T
     * @param Closure(): T $callback
     * @param string $operationName For logging/metrics
     * @return T
     */
    public static function serializable(Closure $callback, string $operationName = 'serializable_op'): mixed
    {
        return self::run($callback, self::SERIALIZABLE, [
            'operationName' => $operationName,
            'maxRetries' => 5, // Higher retries for SERIALIZABLE
            'baseDelayMs' => 50, // Lower base delay
        ]);
    }

    /**
     * Convenience method for REPEATABLE READ isolation.
     * 
     * Use for:
     * - Financial reports
     * - Consistent reads across multiple queries
     * - Payment processing
     * 
     * @template T
     * @param Closure(): T $callback
     * @param string $operationName For logging/metrics
     * @return T
     */
    public static function repeatableRead(Closure $callback, string $operationName = 'repeatable_read_op'): mixed
    {
        return self::run($callback, self::REPEATABLE_READ, [
            'operationName' => $operationName,
        ]);
    }

    /**
     * Execute with pessimistic locking (SELECT FOR UPDATE).
     * 
     * This is the recommended approach for most booking operations:
     * 1. Uses READ COMMITTED (default, good performance)
     * 2. Relies on FOR UPDATE locks for consistency
     * 3. Automatic retry on deadlocks
     * 
     * @template T
     * @param Closure(): T $callback
     * @param string $operationName For logging/metrics
     * @return T
     */
    public static function withPessimisticLock(Closure $callback, string $operationName = 'pessimistic_lock_op'): mixed
    {
        return self::run($callback, self::READ_COMMITTED, [
            'operationName' => $operationName,
            'maxRetries' => 3,
        ]);
    }
}
