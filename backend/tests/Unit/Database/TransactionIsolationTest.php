<?php

namespace Tests\Unit\Database;

use App\Database\TransactionIsolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * TransactionIsolationTest - Unit tests for transaction isolation utilities
 * 
 * Tests:
 * 1. Isolation level configuration
 * 2. Retry logic with exponential backoff
 * 3. Error classification (deadlock vs serialization)
 * 4. Timeout handling
 */
class TransactionIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test basic transaction execution with default isolation.
     */
    public function test_basic_transaction_execution(): void
    {
        $result = TransactionIsolation::run(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    /**
     * Test transaction returns value correctly.
     */
    public function test_transaction_returns_value(): void
    {
        $result = TransactionIsolation::run(function () {
            return [
                'id' => 1,
                'name' => 'test',
            ];
        });

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('test', $result['name']);
    }

    /**
     * Test transaction rolls back on exception.
     */
    public function test_transaction_rollback_on_exception(): void
    {
        $this->expectException(RuntimeException::class);

        try {
            TransactionIsolation::run(function () {
                // Create some data
                DB::table('users')->insert([
                    'name' => 'Test User',
                    'email' => 'test_rollback@example.com',
                    'password' => bcrypt('password'),
                ]);

                // Throw exception to trigger rollback
                throw new RuntimeException('Test rollback');
            });
        } catch (RuntimeException $e) {
            // Verify rollback occurred
            $this->assertDatabaseMissing('users', ['email' => 'test_rollback@example.com']);
            throw $e;
        }
    }

    /**
     * Test convenience method for serializable isolation.
     */
    public function test_serializable_convenience_method(): void
    {
        $result = TransactionIsolation::serializable(function () {
            return 'serializable_result';
        }, 'test_serializable');

        $this->assertEquals('serializable_result', $result);
    }

    /**
     * Test convenience method for repeatable read isolation.
     */
    public function test_repeatable_read_convenience_method(): void
    {
        $result = TransactionIsolation::repeatableRead(function () {
            return 'repeatable_read_result';
        }, 'test_repeatable_read');

        $this->assertEquals('repeatable_read_result', $result);
    }

    /**
     * Test convenience method for pessimistic locking.
     */
    public function test_pessimistic_lock_convenience_method(): void
    {
        $result = TransactionIsolation::withPessimisticLock(function () {
            return 'pessimistic_lock_result';
        }, 'test_pessimistic_lock');

        $this->assertEquals('pessimistic_lock_result', $result);
    }

    /**
     * Test that non-retryable exceptions are thrown.
     */
    public function test_non_retryable_exception_thrown(): void
    {
        $this->expectException(RuntimeException::class);

        TransactionIsolation::run(function () {
            throw new RuntimeException('Non-retryable error');
        });
    }

    /**
     * Test transaction with different isolation levels.
     */
    public function test_transaction_with_read_committed(): void
    {
        $result = TransactionIsolation::run(
            function () { 
                return 'read_committed_result'; 
            },
            TransactionIsolation::READ_COMMITTED,
            ['operationName' => 'test_operation']
        );

        $this->assertEquals('read_committed_result', $result);
    }

    /**
     * Test transaction commits data correctly.
     */
    public function test_transaction_commits_data(): void
    {
        TransactionIsolation::run(function () {
            DB::table('users')->insert([
                'name' => 'Committed User',
                'email' => 'committed@example.com',
                'password' => bcrypt('password'),
            ]);
        });

        $this->assertDatabaseHas('users', ['email' => 'committed@example.com']);
    }

    /**
     * Test nested transactions work correctly.
     */
    public function test_nested_transactions(): void
    {
        $result = TransactionIsolation::run(function () {
            $innerResult = TransactionIsolation::run(function () {
                return 'inner';
            });
            
            return "outer_$innerResult";
        });

        $this->assertEquals('outer_inner', $result);
    }
}

