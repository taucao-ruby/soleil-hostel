---
name: database
description: "Skill for the Database area of soleil-hostel. 45 symbols across 12 files."
---

# Database

45 symbols | 12 files | Cohesion: 78%

## When to Use

- Working with code in `backend/`
- Understanding how execute, waitForResult, getResult work
- Modifying database-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Database/IdempotencyGuardTest.php` | test_first_execution_runs_operation, test_second_execution_returns_cached_result, test_different_keys_run_independently, test_get_result_null_before_execution, test_get_result_after_execution (+6) |
| `backend/app/Database/IdempotencyGuard.php` | execute, waitForResult, getResult, executeWithTransaction, generateKey (+2) |
| `backend/app/Database/TransactionIsolation.php` | run, executeWithIsolation, parseErrorInfo, calculateDelay, serializable (+2) |
| `backend/tests/Feature/Database/FkDeletePolicyTest.php` | isPgsql, test_room_deletion_blocked_when_booking_exists, test_room_deletion_succeeds_when_no_bookings, test_user_deletion_nullifies_booking_user_id, test_room_with_booking_and_review_blocks_deletion |
| `backend/tests/Feature/Database/CheckConstraintTest.php` | isPgsql, test_room_max_guests_zero_rejected, test_room_max_guests_negative_rejected, test_room_max_guests_positive_accepted |
| `backend/tests/Unit/Database/TransactionIsolationTest.php` | test_serializable_convenience_method, test_repeatable_read_convenience_method, test_pessimistic_lock_convenience_method |
| `backend/app/Services/CancellationService.php` | processRefund, handleRefundFailure |
| `backend/app/Database/TransactionMetrics.php` | recordSuccess, recordFailure |
| `backend/database/factories/RoomFactory.php` | available |
| `backend/database/factories/BookingFactory.php` | cancelled |

## Entry Points

Start here when exploring this area:

- **`execute`** (Method) — `backend/app/Database/IdempotencyGuard.php:73`
- **`waitForResult`** (Method) — `backend/app/Database/IdempotencyGuard.php:162`
- **`getResult`** (Method) — `backend/app/Database/IdempotencyGuard.php:235`
- **`executeWithTransaction`** (Method) — `backend/app/Database/IdempotencyGuard.php:270`
- **`test_first_execution_runs_operation`** (Method) — `backend/tests/Unit/Database/IdempotencyGuardTest.php:34`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `execute` | Method | `backend/app/Database/IdempotencyGuard.php` | 73 |
| `waitForResult` | Method | `backend/app/Database/IdempotencyGuard.php` | 162 |
| `getResult` | Method | `backend/app/Database/IdempotencyGuard.php` | 235 |
| `executeWithTransaction` | Method | `backend/app/Database/IdempotencyGuard.php` | 270 |
| `test_first_execution_runs_operation` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 34 |
| `test_second_execution_returns_cached_result` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 55 |
| `test_different_keys_run_independently` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 89 |
| `test_get_result_null_before_execution` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 163 |
| `test_get_result_after_execution` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 173 |
| `available` | Method | `backend/database/factories/RoomFactory.php` | 44 |
| `cancelled` | Method | `backend/database/factories/BookingFactory.php` | 57 |
| `isPgsql` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 22 |
| `test_room_deletion_blocked_when_booking_exists` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 29 |
| `test_room_deletion_succeeds_when_no_bookings` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 43 |
| `test_user_deletion_nullifies_booking_user_id` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 59 |
| `test_room_with_booking_and_review_blocks_deletion` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 98 |
| `processRefund` | Method | `backend/app/Services/CancellationService.php` | 178 |
| `handleRefundFailure` | Method | `backend/app/Services/CancellationService.php` | 288 |
| `fromException` | Method | `backend/app/Exceptions/RefundFailedException.php` | 27 |
| `recordSuccess` | Method | `backend/app/Database/TransactionMetrics.php` | 31 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Store → RecordSuccess` | cross_community | 4 |
| `Cancel → CalculateRefundAmount` | cross_community | 4 |
| `Cancel → BookingCancelled` | cross_community | 4 |
| `Handle → RecordSuccess` | cross_community | 4 |
| `Run → Available` | cross_community | 3 |
| `Run → Warning` | cross_community | 3 |
| `Cancel → GenerateKey` | cross_community | 3 |
| `Cancel → WasCompleted` | cross_community | 3 |
| `ExecuteWithTransaction → WaitForResult` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 5 calls |
| Feature | 2 calls |
| Jobs | 1 calls |
| Cache | 1 calls |
| Booking | 1 calls |
| Unit | 1 calls |

## How to Explore

1. `gitnexus_context({name: "execute"})` — see callers and callees
2. `gitnexus_query({query: "database"})` — find related execution flows
3. Read key files listed above for implementation details
