<?php

declare(strict_types=1);

namespace App\Database;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * IdempotencyGuard - Prevents duplicate execution of critical operations
 * 
 * Implements idempotency pattern for operations that must execute exactly once:
 * - Payment processing
 * - Refund processing
 * - Booking confirmation
 * 
 * Pattern:
 * 1. Check if idempotency key exists (operation already completed)
 * 2. If exists, return cached result
 * 3. If not, acquire lock and execute operation
 * 4. Store result with idempotency key
 * 5. Return result
 * 
 * Data Invariants Protected:
 * - Payment: Charge exactly once per intent
 * - Refund: Refund exactly once per booking
 * - Confirmation: Confirm exactly once per booking
 * 
 * @see https://stripe.com/docs/api/idempotent_requests
 */
final class IdempotencyGuard
{
    /**
     * Default lock TTL in seconds.
     * Should be longer than the maximum expected operation duration.
     */
    private const DEFAULT_LOCK_TTL = 60;

    /**
     * Default result cache TTL in seconds.
     * How long to remember the result of an idempotent operation.
     */
    private const DEFAULT_RESULT_TTL = 86400; // 24 hours

    /**
     * Cache key prefix for idempotency.
     */
    private const CACHE_PREFIX = 'idempotency:';

    /**
     * Lock key prefix.
     */
    private const LOCK_PREFIX = 'idempotency_lock:';

    /**
     * Execute an operation with idempotency guarantee.
     * 
     * @template T
     * @param string $key Unique idempotency key (e.g., "booking:123:refund")
     * @param Closure(): T $operation The operation to execute
     * @param array{
     *   lockTtl?: int,
     *   resultTtl?: int,
     *   operationName?: string
     * } $options Configuration options
     * @return array{result: T, wasExecuted: bool} Result and whether it was freshly executed
     * @throws RuntimeException If lock acquisition fails
     */
    public static function execute(
        string $key,
        Closure $operation,
        array $options = []
    ): array {
        $lockTtl = $options['lockTtl'] ?? self::DEFAULT_LOCK_TTL;
        $resultTtl = $options['resultTtl'] ?? self::DEFAULT_RESULT_TTL;
        $operationName = $options['operationName'] ?? 'unknown';

        $cacheKey = self::CACHE_PREFIX . $key;
        $lockKey = self::LOCK_PREFIX . $key;

        // Step 1: Check if operation was already completed
        $existingResult = Cache::get($cacheKey);
        if ($existingResult !== null) {
            Log::info('Idempotent operation skipped (already completed)', [
                'key' => $key,
                'operation' => $operationName,
            ]);

            return [
                'result' => $existingResult['result'],
                'wasExecuted' => false,
            ];
        }

        // Step 2: Try to acquire lock
        $lock = Cache::lock($lockKey, $lockTtl);
        
        if (!$lock->get()) {
            // Another process is executing this operation
            // Wait for result to appear in cache
            return self::waitForResult($cacheKey, $key, $operationName, $lockTtl);
        }

        try {
            // Step 3: Double-check after acquiring lock
            $existingResult = Cache::get($cacheKey);
            if ($existingResult !== null) {
                Log::info('Idempotent operation skipped (completed by another process)', [
                    'key' => $key,
                    'operation' => $operationName,
                ]);

                return [
                    'result' => $existingResult['result'],
                    'wasExecuted' => false,
                ];
            }

            // Step 4: Execute the operation
            $startTime = microtime(true);
            $result = $operation();
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Step 5: Store the result
            Cache::put($cacheKey, [
                'result' => $result,
                'completed_at' => now()->toIso8601String(),
                'duration_ms' => $duration,
            ], $resultTtl);

            Log::info('Idempotent operation completed', [
                'key' => $key,
                'operation' => $operationName,
                'duration_ms' => $duration,
            ]);

            return [
                'result' => $result,
                'wasExecuted' => true,
            ];

        } finally {
            $lock->release();
        }
    }

    /**
     * Wait for a result to appear in cache (another process is executing).
     * 
     * @template T
     * @param string $cacheKey Cache key to poll
     * @param string $originalKey Original idempotency key for logging
     * @param string $operationName Operation name for logging
     * @param int $maxWaitSeconds Maximum time to wait
     * @return array{result: T, wasExecuted: bool}
     */
    private static function waitForResult(
        string $cacheKey,
        string $originalKey,
        string $operationName,
        int $maxWaitSeconds
    ): array {
        $waited = 0;
        $pollIntervalMs = 100;

        while ($waited < $maxWaitSeconds * 1000) {
            usleep($pollIntervalMs * 1000);
            $waited += $pollIntervalMs;

            $result = Cache::get($cacheKey);
            if ($result !== null) {
                Log::info('Idempotent operation result retrieved (waited for another process)', [
                    'key' => $originalKey,
                    'operation' => $operationName,
                    'waited_ms' => $waited,
                ]);

                return [
                    'result' => $result['result'],
                    'wasExecuted' => false,
                ];
            }

            // Increase poll interval with backoff
            $pollIntervalMs = min($pollIntervalMs * 1.5, 1000);
        }

        // Timeout waiting for result
        Log::error('Timeout waiting for idempotent operation result', [
            'key' => $originalKey,
            'operation' => $operationName,
            'waited_seconds' => $maxWaitSeconds,
        ]);

        throw new RuntimeException(
            "Timeout waiting for operation '{$operationName}' to complete. " .
            "Another process may have failed. Please retry."
        );
    }

    /**
     * Generate a deterministic idempotency key for an operation.
     * 
     * @param string $operation Operation type (e.g., 'refund', 'payment')
     * @param int|string ...$identifiers Unique identifiers (booking_id, etc.)
     * @return string Idempotency key
     */
    public static function generateKey(string $operation, int|string ...$identifiers): string
    {
        return $operation . ':' . implode(':', $identifiers);
    }

    /**
     * Check if an operation with the given key has already been completed.
     * 
     * @param string $key Idempotency key
     * @return bool True if operation was already completed
     */
    public static function wasCompleted(string $key): bool
    {
        return Cache::has(self::CACHE_PREFIX . $key);
    }

    /**
     * Get the result of a previously completed operation.
     * 
     * @param string $key Idempotency key
     * @return mixed|null Result or null if not found
     */
    public static function getResult(string $key): mixed
    {
        $cached = Cache::get(self::CACHE_PREFIX . $key);
        return $cached['result'] ?? null;
    }

    /**
     * Clear an idempotency key (for testing or recovery).
     * 
     * ⚠️ Use with caution! This allows an operation to execute again.
     * 
     * @param string $key Idempotency key
     */
    public static function clear(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
        Cache::forget(self::LOCK_PREFIX . $key);

        Log::warning('Idempotency key cleared', ['key' => $key]);
    }

    /**
     * Execute a database operation with idempotency and transaction.
     * 
     * Combines idempotency guard with transaction isolation.
     * 
     * @template T
     * @param string $key Idempotency key
     * @param Closure(): T $operation Operation to execute
     * @param string $isolationLevel Transaction isolation level
     * @param string $operationName Operation name for logging
     * @return array{result: T, wasExecuted: bool}
     */
    public static function executeWithTransaction(
        string $key,
        Closure $operation,
        string $isolationLevel = TransactionIsolation::READ_COMMITTED,
        string $operationName = 'unknown'
    ): array {
        return self::execute($key, function () use ($operation, $isolationLevel, $operationName) {
            return TransactionIsolation::run(
                $operation,
                $isolationLevel,
                ['operationName' => $operationName]
            );
        }, [
            'operationName' => $operationName,
        ]);
    }
}
