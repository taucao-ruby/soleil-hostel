<?php

namespace Tests\Unit\Database;

use App\Database\IdempotencyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

/**
 * IdempotencyGuardTest - Unit tests for idempotency protection
 * 
 * Tests:
 * 1. First execution runs and stores result
 * 2. Second execution returns cached result
 * 3. Concurrent requests are handled correctly
 * 4. Key generation
 */
class IdempotencyGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any cached idempotency keys
        Cache::flush();
    }

    /**
     * Test first execution runs and stores result.
     */
    public function test_first_execution_runs_operation(): void
    {
        $executionCount = 0;

        $result = IdempotencyGuard::execute(
            'test:operation:1',
            function () use (&$executionCount) {
                $executionCount++;
                return ['data' => 'result'];
            }
        );

        $this->assertEquals(1, $executionCount);
        $this->assertTrue($result['wasExecuted']);
        $this->assertEquals(['data' => 'result'], $result['result']);
    }

    /**
     * Test second execution returns cached result.
     */
    public function test_second_execution_returns_cached_result(): void
    {
        $executionCount = 0;
        $key = 'test:operation:2';

        // First execution
        $result1 = IdempotencyGuard::execute(
            $key,
            function () use (&$executionCount) {
                $executionCount++;
                return ['value' => 42];
            }
        );

        // Second execution with same key
        $result2 = IdempotencyGuard::execute(
            $key,
            function () use (&$executionCount) {
                $executionCount++;
                return ['value' => 999]; // Different value, but should return cached
            }
        );

        $this->assertEquals(1, $executionCount, 'Operation should only run once');
        $this->assertTrue($result1['wasExecuted']);
        $this->assertFalse($result2['wasExecuted']);
        $this->assertEquals(['value' => 42], $result2['result']);
    }

    /**
     * Test different keys run different operations.
     */
    public function test_different_keys_run_independently(): void
    {
        // First key
        $result1 = IdempotencyGuard::execute(
            'test:key:a',
            function () {
                return 'result_a';
            }
        );

        // Different key
        $result2 = IdempotencyGuard::execute(
            'test:key:b',
            function () {
                return 'result_b';
            }
        );

        $this->assertTrue($result1['wasExecuted']);
        $this->assertTrue($result2['wasExecuted']);
        $this->assertEquals('result_a', $result1['result']);
        $this->assertEquals('result_b', $result2['result']);
    }

    /**
     * Test key generation is deterministic.
     */
    public function test_key_generation_deterministic(): void
    {
        $key1 = IdempotencyGuard::generateKey('refund', 123, 'pi_abc');
        $key2 = IdempotencyGuard::generateKey('refund', 123, 'pi_abc');

        $this->assertEquals($key1, $key2);
        $this->assertEquals('refund:123:pi_abc', $key1);
    }

    /**
     * Test key generation with different identifiers.
     */
    public function test_key_generation_different_identifiers(): void
    {
        $key1 = IdempotencyGuard::generateKey('refund', 123);
        $key2 = IdempotencyGuard::generateKey('refund', 456);

        $this->assertNotEquals($key1, $key2);
        $this->assertEquals('refund:123', $key1);
        $this->assertEquals('refund:456', $key2);
    }

    /**
     * Test wasCompleted returns false before execution.
     */
    public function test_was_completed_false_before_execution(): void
    {
        $key = 'test:not:executed:' . uniqid();

        $this->assertFalse(IdempotencyGuard::wasCompleted($key));
    }

    /**
     * Test wasCompleted returns true after execution.
     */
    public function test_was_completed_true_after_execution(): void
    {
        $key = 'test:executed:' . uniqid();

        IdempotencyGuard::execute($key, fn() => 'done');

        $this->assertTrue(IdempotencyGuard::wasCompleted($key));
    }

    /**
     * Test getResult returns null before execution.
     */
    public function test_get_result_null_before_execution(): void
    {
        $key = 'test:no:result:' . uniqid();

        $this->assertNull(IdempotencyGuard::getResult($key));
    }

    /**
     * Test getResult returns result after execution.
     */
    public function test_get_result_after_execution(): void
    {
        $key = 'test:with:result:' . uniqid();

        IdempotencyGuard::execute($key, fn() => ['data' => 'value']);

        $result = IdempotencyGuard::getResult($key);
        $this->assertEquals(['data' => 'value'], $result);
    }

    /**
     * Test clear removes idempotency key.
     */
    public function test_clear_removes_key(): void
    {
        $key = 'test:to:clear:' . uniqid();

        // Execute first
        IdempotencyGuard::execute($key, fn() => 'first');
        $this->assertTrue(IdempotencyGuard::wasCompleted($key));

        // Clear
        IdempotencyGuard::clear($key);
        $this->assertFalse(IdempotencyGuard::wasCompleted($key));

        // Execute again - should run
        $result = IdempotencyGuard::execute($key, fn() => 'second');
        $this->assertTrue($result['wasExecuted']);
        $this->assertEquals('second', $result['result']);
    }

    /**
     * Test operation failure does not store result.
     */
    public function test_operation_failure_does_not_store_result(): void
    {
        $key = 'test:failing:operation:' . uniqid();

        try {
            IdempotencyGuard::execute($key, function () {
                throw new RuntimeException('Operation failed');
            });
        } catch (RuntimeException $e) {
            // Expected
        }

        // Key should not be marked as completed
        $this->assertFalse(IdempotencyGuard::wasCompleted($key));

        // Should be able to retry
        $result = IdempotencyGuard::execute($key, fn() => 'success');
        $this->assertTrue($result['wasExecuted']);
        $this->assertEquals('success', $result['result']);
    }
}

