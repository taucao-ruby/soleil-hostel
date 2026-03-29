---
name: database
description: "Skill for the Database area of soleil-hostel. 43 symbols across 13 files."
---

# Database

43 symbols | 13 files | Cohesion: 82%

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
| `backend/tests/Feature/Database/FkDeletePolicyTest.php` | isPgsql, test_room_deletion_blocked_when_booking_exists, test_room_deletion_succeeds_when_no_bookings, test_user_deletion_nullifies_booking_user_id, test_room_with_booking_and_review_blocks_deletion |
| `backend/tests/Feature/Database/CheckConstraintTest.php` | isPgsql, test_room_max_guests_zero_rejected, test_room_max_guests_negative_rejected, test_room_max_guests_positive_accepted |
| `backend/tests/Unit/Database/TransactionIsolationTest.php` | test_serializable_convenience_method, test_repeatable_read_convenience_method, test_pessimistic_lock_convenience_method |
| `backend/app/Services/CancellationService.php` | processRefund, handleRefundFailure |
| `backend/app/Database/TransactionMetrics.php` | recordSuccess, recordFailure |
| `backend/app/Exceptions/RefundFailedException.php` | fromException |
| `backend/database/factories/RoomFactory.php` | available |

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
| `available` | Method | `backend/database/factories/RoomFactory.php` | 44 |
| `cancelled` | Method | `backend/database/factories/BookingFactory.php` | 57 |
| `test_backfill_command_respects_scope` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 90 |
| `isPgsql` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 22 |
| `test_room_deletion_blocked_when_booking_exists` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 29 |
| `test_room_deletion_succeeds_when_no_bookings` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 43 |
| `test_user_deletion_nullifies_booking_user_id` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 59 |
| `test_room_with_booking_and_review_blocks_deletion` | Method | `backend/tests/Feature/Database/FkDeletePolicyTest.php` | 98 |
| `wasCompleted` | Method | `backend/app/Database/IdempotencyGuard.php` | 224 |
| `clear` | Method | `backend/app/Database/IdempotencyGuard.php` | 249 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Cancel → CalculateRefundAmount` | cross_community | 4 |
| `Cancel → BookingCancelled` | cross_community | 4 |
| `Store → RecordSuccess` | cross_community | 4 |
| `Handle → RecordSuccess` | cross_community | 4 |
| `Run → Available` | cross_community | 3 |
| `Run → Warning` | cross_community | 3 |
| `Cancel → GenerateKey` | cross_community | 3 |
| `Cancel → WasCompleted` | cross_community | 3 |
| `ExecuteWithTransaction → WaitForResult` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 6 calls |
| Feature | 3 calls |
| Cache | 2 calls |
| Jobs | 1 calls |
| Enums | 1 calls |
| Booking | 1 calls |
| Unit | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "processRefund"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "database"})` — find related execution flows
3. Read key files listed above for implementation details
