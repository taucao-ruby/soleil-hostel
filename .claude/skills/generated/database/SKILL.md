---
name: database
description: "Skill for the Database area of soleil-hostel. 40 symbols across 12 files."
---

# Database

40 symbols | 12 files | Cohesion: 83%

## When to Use

- Working with code in `backend/`
- Understanding how processRefund, handleRefundFailure, fromException work
- Modifying database-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Database/IdempotencyGuardTest.php` | test_key_generation_deterministic, test_key_generation_different_identifiers, test_was_completed_false_before_execution, test_was_completed_true_after_execution, test_clear_removes_key (+3) |
| `backend/app/Database/IdempotencyGuard.php` | execute, waitForResult, generateKey, executeWithTransaction, wasCompleted (+2) |
| `backend/app/Database/TransactionIsolation.php` | run, executeWithIsolation, parseErrorInfo, calculateDelay, serializable (+2) |
| `backend/tests/Feature/Database/CheckConstraintTest.php` | isPgsql, test_room_max_guests_zero_rejected, test_room_max_guests_negative_rejected, test_room_max_guests_positive_accepted |
| `backend/tests/Feature/Database/FkDeletePolicyTest.php` | isPgsql, test_room_deletion_blocked_when_booking_exists, test_user_deletion_nullifies_booking_user_id |
| `backend/tests/Unit/Database/TransactionIsolationTest.php` | test_serializable_convenience_method, test_repeatable_read_convenience_method, test_pessimistic_lock_convenience_method |
| `backend/app/Services/CancellationService.php` | processRefund, handleRefundFailure |
| `backend/app/Database/TransactionMetrics.php` | recordSuccess, recordFailure |
| `backend/app/Exceptions/RefundFailedException.php` | fromException |
| `backend/tests/Feature/Notifications/BookingNotificationTest.php` | setUp |

## Entry Points

Start here when exploring this area:

- **`processRefund`** (Method) — `backend/app/Services/CancellationService.php:178`
- **`handleRefundFailure`** (Method) — `backend/app/Services/CancellationService.php:288`
- **`fromException`** (Method) — `backend/app/Exceptions/RefundFailedException.php:27`
- **`recordSuccess`** (Method) — `backend/app/Database/TransactionMetrics.php:31`
- **`execute`** (Method) — `backend/app/Database/IdempotencyGuard.php:73`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `processRefund` | Method | `backend/app/Services/CancellationService.php` | 178 |
| `handleRefundFailure` | Method | `backend/app/Services/CancellationService.php` | 288 |
| `fromException` | Method | `backend/app/Exceptions/RefundFailedException.php` | 27 |
| `recordSuccess` | Method | `backend/app/Database/TransactionMetrics.php` | 31 |
| `execute` | Method | `backend/app/Database/IdempotencyGuard.php` | 73 |
| `waitForResult` | Method | `backend/app/Database/IdempotencyGuard.php` | 162 |
| `generateKey` | Method | `backend/app/Database/IdempotencyGuard.php` | 213 |
| `executeWithTransaction` | Method | `backend/app/Database/IdempotencyGuard.php` | 270 |
| `test_key_generation_deterministic` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 116 |
| `test_key_generation_different_identifiers` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 128 |
| `wasCompleted` | Method | `backend/app/Database/IdempotencyGuard.php` | 224 |
| `clear` | Method | `backend/app/Database/IdempotencyGuard.php` | 249 |
| `test_was_completed_false_before_execution` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 141 |
| `test_was_completed_true_after_execution` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 151 |
| `test_clear_removes_key` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 186 |
| `test_operation_failure_does_not_store_result` | Method | `backend/tests/Unit/Database/IdempotencyGuardTest.php` | 207 |
| `setUp` | Method | `backend/tests/Feature/Notifications/BookingNotificationTest.php` | 41 |
| `cancelled` | Method | `backend/database/factories/BookingFactory.php` | 53 |
| `test_backfill_command_skips_cancelled_booking` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 112 |
| `isPgsql` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 22 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Cancel → CalculateRefundAmount` | cross_community | 4 |
| `Cancel → BookingCancelled` | cross_community | 4 |
| `Store → RecordSuccess` | cross_community | 4 |
| `Handle → RecordSuccess` | cross_community | 4 |
| `Run → Warning` | cross_community | 3 |
| `Cancel → GenerateKey` | cross_community | 3 |
| `Cancel → WasCompleted` | cross_community | 3 |
| `ExecuteWithTransaction → WaitForResult` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Feature | 5 calls |
| Jobs | 1 calls |
| Cache | 1 calls |
| Operations | 1 calls |
| Factories | 1 calls |
| Booking | 1 calls |

## How to Explore

1. `gitnexus_context({name: "processRefund"})` — see callers and callees
2. `gitnexus_query({query: "database"})` — find related execution flows
3. Read key files listed above for implementation details
