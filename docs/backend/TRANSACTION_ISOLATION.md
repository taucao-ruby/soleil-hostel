# Database Transaction Isolation Design

> **Version**: 1.0.0  
> **Last Updated**: 2026-01-24  
> **Status**: Implemented

## Overview

This document describes the database transaction isolation strategy implemented in the Soleil Hostel booking system. The design ensures zero race conditions, prevents double-booking scenarios, and maintains optimal performance under concurrent load.

## Architecture

### Core Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     Application Layer                            │
├─────────────────────────────────────────────────────────────────┤
│  CreateBookingService  │  CancellationService  │  RoomService   │
├─────────────────────────────────────────────────────────────────┤
│                   Transaction Layer                              │
├───────────────────┬─────────────────────┬───────────────────────┤
│ TransactionIsolation │ IdempotencyGuard │ TransactionMetrics    │
├───────────────────┴─────────────────────┴───────────────────────┤
│                     Database Layer                               │
├─────────────────────────────────────────────────────────────────┤
│  PostgreSQL (Primary)  │  MySQL (Supported)  │  SQLite (Tests)  │
└─────────────────────────────────────────────────────────────────┘
```

### Key Classes

| Class                   | Purpose                                             | Location                                   |
| ----------------------- | --------------------------------------------------- | ------------------------------------------ |
| `TransactionIsolation`  | Configurable isolation levels with retry logic      | `app/Database/TransactionIsolation.php`    |
| `IdempotencyGuard`      | Prevents duplicate execution of critical operations | `app/Database/IdempotencyGuard.php`        |
| `TransactionMetrics`    | Monitoring and logging for transaction health       | `app/Database/TransactionMetrics.php`      |
| `TransactionExceptions` | Typed exceptions for transaction errors             | `app/Exceptions/TransactionExceptions.php` |

---

## Concurrency Risk Matrix

| Operation           | Risk Level | Failure Scenario                | Protection Strategy                  | Isolation Level |
| ------------------- | ---------- | ------------------------------- | ------------------------------------ | --------------- |
| **Create Booking**  | HIGH       | 2 users book same room/dates    | Pessimistic locking (FOR UPDATE)     | READ COMMITTED  |
| **Update Booking**  | MEDIUM     | Concurrent date changes overlap | Pessimistic locking (FOR UPDATE)     | READ COMMITTED  |
| **Cancel Booking**  | MEDIUM     | Double cancellation             | Pessimistic locking + status check   | READ COMMITTED  |
| **Confirm Booking** | LOW        | Double confirmation emails      | Status check in transaction          | READ COMMITTED  |
| **Process Refund**  | CRITICAL   | Double refund                   | Idempotency guard + two-phase commit | READ COMMITTED  |
| **Room Update**     | MEDIUM     | Stale update overwrites         | Optimistic locking (lock_version)    | READ COMMITTED  |

---

## Data Invariants

### Protected Invariants

1. **No Overlapping Bookings**: For any room, no two active bookings can have overlapping date ranges.

   ```sql
   -- Invariant: For room R, all bookings satisfy:
   -- ∀ b1, b2 ∈ Bookings(R): b1 ≠ b2 → (b1.check_out ≤ b2.check_in ∨ b2.check_out ≤ b1.check_in)
   ```

2. **Payment Processed Exactly Once**: Each payment intent is processed at most once.

   ```sql
   -- Invariant: COUNT(refunds WHERE booking_id = B) ≤ 1
   ```

3. **Status State Machine**: Booking status transitions follow defined rules.

   ```
   PENDING → CONFIRMED, REFUND_PENDING, CANCELLED
   CONFIRMED → REFUND_PENDING, CANCELLED
   REFUND_PENDING → CANCELLED, REFUND_FAILED
   CANCELLED → (terminal)
   REFUND_FAILED → REFUND_PENDING, CANCELLED
   ```

4. **Inventory Non-Negative**: Room availability is never negative.
   ```sql
   -- Invariant: available_slots >= 0
   ```

---

## Transaction Patterns

### Pattern 1: Pessimistic Locking (Booking Creation)

**When to Use**: High contention operations where conflicts are expected.

```php
// CreateBookingService.php
return DB::transaction(function () use ($roomId, $checkIn, $checkOut) {
    // Step 1: Lock overlapping bookings
    $existingBookings = Booking::query()
        ->overlappingBookings($roomId, $checkIn, $checkOut)
        ->lockForUpdate()  // FOR UPDATE
        ->get();

    // Step 2: Check for conflicts
    if ($existingBookings->isNotEmpty()) {
        throw new DoubleBookingException();
    }

    // Step 3: Create booking (lock still held)
    return Booking::create([...]);

    // Step 4: Commit releases lock
});
```

**Why FOR UPDATE over SERIALIZABLE?**

- Lower contention than SERIALIZABLE
- Faster in read-heavy workloads
- Explicit lock scope (only affected rows)
- PostgreSQL serialization failures require retry; FOR UPDATE blocks

### Pattern 2: Two-Phase Commit (Refund Processing)

**When to Use**: Operations involving external APIs that should not hold locks.

```php
// CancellationService.php

// Phase 1: Acquire lock and transition to intermediate state
$booking = DB::transaction(function () use ($booking) {
    $locked = Booking::where('id', $booking->id)
        ->lockForUpdate()
        ->first();

    $locked->update(['status' => BookingStatus::REFUND_PENDING]);
    return $locked;
});

// Phase 2: External API call (no lock held)
$refund = $stripe->refund($booking->payment_intent_id);

// Phase 3: Finalize
DB::transaction(function () use ($booking, $refund) {
    $booking->update([
        'status' => BookingStatus::CANCELLED,
        'refund_id' => $refund->id,
    ]);
});
```

### Pattern 3: Idempotency Guard (Payment Operations)

**When to Use**: Operations that must execute exactly once.

```php
$result = IdempotencyGuard::execute(
    IdempotencyGuard::generateKey('refund', $booking->id, $paymentIntentId),
    function () use ($booking) {
        return $stripe->refund($booking->payment_intent_id);
    },
    ['operationName' => 'stripe_refund']
);

if (!$result['wasExecuted']) {
    // Already processed, return cached result
    return $result['result'];
}
```

### Pattern 4: Optimistic Locking (Room Updates)

**When to Use**: Low contention operations where conflicts are rare.

```php
// RoomService.php
$rowsAffected = DB::table('rooms')
    ->where('id', $room->id)
    ->where('lock_version', $expectedVersion)
    ->update([
        ...$data,
        'lock_version' => DB::raw('lock_version + 1'),
    ]);

if ($rowsAffected === 0) {
    throw new OptimisticLockException();
}
```

---

## Error Handling

### PostgreSQL Error Codes

| SQLSTATE | Name                  | Handling                       |
| -------- | --------------------- | ------------------------------ |
| `40001`  | serialization_failure | Retry with exponential backoff |
| `40P01`  | deadlock_detected     | Immediate retry with jitter    |
| `23505`  | unique_violation      | Business error, no retry       |
| `23503`  | foreign_key_violation | Business error, no retry       |

### Retry Strategy

```php
// TransactionIsolation.php

private static function calculateDelay(int $attempt, string $errorType): int
{
    return match ($errorType) {
        // Deadlocks: Quick retry with small random jitter (10-50ms)
        'deadlock' => random_int(10, 50),

        // Serialization: Exponential backoff with jitter
        'serialization' => (int) ($baseDelay * pow(2, $attempt - 1) + random_int(0, 50)),

        // Lock timeout: Longer delay to allow lock release
        'lock_timeout' => (int) ($baseDelay * pow(2, $attempt) + random_int(0, 100)),

        default => (int) ($baseDelay * pow(2, $attempt - 1)),
    };
}
```

### Exception Hierarchy

```
TransactionException (base)
├── SerializationFailureException (retryable, 40001)
├── DeadlockException (retryable, 40P01)
├── LockTimeoutException (retryable)
├── ConcurrencyException (not retryable)
├── InsufficientInventoryException (not retryable)
├── DoubleBookingException (not retryable)
└── DuplicateOperationException (not retryable)
```

---

## Monitoring

### Key Metrics

| Metric                     | Target  | Alert Threshold |
| -------------------------- | ------- | --------------- |
| Transaction success rate   | > 99%   | < 98%           |
| P95 latency                | < 100ms | > 200ms         |
| Serialization failure rate | < 1%    | > 5%            |
| Deadlock rate              | < 0.1%  | > 1%            |
| Retry success rate         | > 95%   | < 90%           |

### Log Format

```json
{
  "metric": "transaction.success",
  "operation": "create_booking",
  "isolation_level": "READ COMMITTED",
  "duration_ms": 45.2,
  "retry_count": 0,
  "timestamp": "2026-01-24T10:30:00Z"
}
```

### Alerting Rules

1. **High Serialization Failures**: > 5% in 5 minutes → Page on-call
2. **High Deadlock Rate**: > 1% in 5 minutes → Slack alert
3. **Transaction Timeout**: Any timeout → Log and monitor trend
4. **Idempotency Collision**: Duplicate key hit → Audit for issues

---

## Testing Strategy

### Unit Tests

```php
// tests/Unit/Database/TransactionIsolationTest.php
- test_basic_transaction_execution
- test_transaction_rollback_on_exception
- test_serializable_convenience_method
- test_non_retryable_exception_thrown_immediately
```

### Integration Tests

```php
// tests/Feature/Database/TransactionIsolationIntegrationTest.php
- test_concurrent_bookings_same_dates_exactly_one_succeeds
- test_overlap_detection_patterns
- test_booking_update_overlap_detection
- test_database_integrity_after_concurrent_operations
```

### Load Tests

```bash
# k6 concurrent booking test
k6 run --vus 50 --duration 30s tests/k6/concurrent_booking.js

# Expected results:
# - 0 double bookings
# - < 1% serialization failures
# - P95 < 100ms
```

---

## Operational Runbook

### Handling Serialization Failures

1. **Normal Rate (< 1%)**: No action needed, retries handle this
2. **Elevated Rate (1-5%)**:
   - Check for hot spots (same room being booked)
   - Consider transaction ordering
   - Review lock acquisition order
3. **High Rate (> 5%)**:
   - Switch to pessimistic locking for affected operations
   - Increase retry count temporarily
   - Investigate root cause

### Handling Deadlocks

1. **Check log for involved tables**: `grep "deadlock" /var/log/app.log`
2. **Identify transaction pattern**: Which operations conflict?
3. **Ensure consistent lock order**: Always lock in same order (e.g., by ID ascending)
4. **Consider lock escalation**: Table lock vs row locks

### Recovery from Refund Failures

1. **Check idempotency cache**: `redis-cli GET "idempotency:refund:booking:{id}"`
2. **Check Stripe dashboard**: Verify refund status
3. **If in REFUND_FAILED state**:
   - Clear idempotency key: `IdempotencyGuard::clear($key)`
   - Retry via admin panel
4. **If orphaned (Stripe succeeded, DB failed)**:
   - Run reconciliation job: `php artisan refunds:reconcile`

---

## Future Improvements

1. **Advisory Locks**: For distributed scenarios with multiple app servers
2. **Distributed Transactions**: For microservices architecture
3. **Connection Pooling**: Optimize for high concurrent load
4. **Prepared Transactions**: Two-phase commit for critical operations

---

## References

- [PostgreSQL Transaction Isolation](https://www.postgresql.org/docs/current/transaction-iso.html)
- [Laravel Database Transactions](https://laravel.com/docs/eloquent#transactions)
- [Stripe Idempotent Requests](https://stripe.com/docs/api/idempotent_requests)
- [Martin Kleppmann - Designing Data-Intensive Applications](https://dataintensive.net/)
