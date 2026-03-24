---
name: unit
description: "Skill for the Unit area of soleil-hostel. 26 symbols across 8 files."
---

# Unit

26 symbols | 8 files | Cohesion: 85%

## When to Use

- Working with code in `backend/`
- Understanding how test_service_creates_booking_successfully, test_service_throws_exception_when_room_not_found, test_service_throws_exception_with_invalid_dates work
- Modifying unit-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/CreateBookingServiceTest.php` | test_service_creates_booking_successfully, test_service_throws_exception_when_room_not_found, test_service_throws_exception_with_invalid_dates, test_service_throws_exception_when_overlap_detected, test_service_allows_booking_on_same_day_boundary (+3) |
| `backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php` | test_concurrent_bookings_same_dates_exactly_one_succeeds, test_overlap_detection_patterns, test_booking_update_overlap_detection, test_database_integrity_after_concurrent_operations, test_transaction_metrics_recorded |
| `backend/app/Services/CreateBookingService.php` | create, update, validateDates, parseDate |
| `backend/tests/Unit/BookingFactoryMethodsTest.php` | test_expired_factory_creates_past_booking, test_multi_day_factory_creates_correct_duration, test_multi_day_defaults_to_three_days |
| `backend/database/factories/BookingFactory.php` | expired, forDays, multiDay |
| `backend/app/Jobs/CreateBookingJob.php` | handle |
| `backend/app/Models/PersonalAccessToken.php` | cleanup |
| `backend/tests/Feature/Database/FkDeletePolicyTest.php` | test_user_deletion_nullifies_review_user_id |

## Entry Points

Start here when exploring this area:

- **`test_service_creates_booking_successfully`** (Method) — `backend/tests/Unit/CreateBookingServiceTest.php:41`
- **`test_service_throws_exception_when_room_not_found`** (Method) — `backend/tests/Unit/CreateBookingServiceTest.php:64`
- **`test_service_throws_exception_with_invalid_dates`** (Method) — `backend/tests/Unit/CreateBookingServiceTest.php:81`
- **`test_service_throws_exception_when_overlap_detected`** (Method) — `backend/tests/Unit/CreateBookingServiceTest.php:99`
- **`test_service_allows_booking_on_same_day_boundary`** (Method) — `backend/tests/Unit/CreateBookingServiceTest.php:138`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `test_service_creates_booking_successfully` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 41 |
| `test_service_throws_exception_when_room_not_found` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 64 |
| `test_service_throws_exception_with_invalid_dates` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 81 |
| `test_service_throws_exception_when_overlap_detected` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 99 |
| `test_service_allows_booking_on_same_day_boundary` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 138 |
| `test_service_allows_booking_over_cancelled_booking` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 172 |
| `test_service_handles_string_dates` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 268 |
| `test_service_merges_additional_data` | Method | `backend/tests/Unit/CreateBookingServiceTest.php` | 289 |
| `create` | Method | `backend/app/Services/CreateBookingService.php` | 61 |
| `update` | Method | `backend/app/Services/CreateBookingService.php` | 331 |
| `validateDates` | Method | `backend/app/Services/CreateBookingService.php` | 369 |
| `parseDate` | Method | `backend/app/Services/CreateBookingService.php` | 393 |
| `handle` | Method | `backend/app/Jobs/CreateBookingJob.php` | 64 |
| `test_concurrent_bookings_same_dates_exactly_one_succeeds` | Method | `backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php` | 58 |
| `test_overlap_detection_patterns` | Method | `backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php` | 109 |
| `test_booking_update_overlap_detection` | Method | `backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php` | 190 |
| `test_database_integrity_after_concurrent_operations` | Method | `backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php` | 260 |
| `test_transaction_metrics_recorded` | Method | `backend/tests/Feature/Database/TransactionIsolationIntegrationTest.php` | 308 |
| `test_expired_factory_creates_past_booking` | Method | `backend/tests/Unit/BookingFactoryMethodsTest.php` | 14 |
| `expired` | Method | `backend/database/factories/BookingFactory.php` | 224 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Store → ClassifyDatabaseError` | cross_community | 5 |
| `Handle → ClassifyDatabaseError` | cross_community | 5 |
| `Store → CreateBookingWithLocking` | cross_community | 4 |
| `Store → RecordSuccess` | cross_community | 4 |
| `Handle → CreateBookingWithLocking` | cross_community | 4 |
| `Handle → RecordSuccess` | cross_community | 4 |
| `Store → ParseDate` | cross_community | 3 |
| `Store → ValidateDates` | cross_community | 3 |
| `Handle → ParseDate` | intra_community | 3 |
| `Handle → ValidateDates` | intra_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 4 calls |
| Services | 1 calls |
| Database | 1 calls |
| Booking | 1 calls |

## How to Explore

1. `gitnexus_context({name: "test_service_creates_booking_successfully"})` — see callers and callees
2. `gitnexus_query({query: "unit"})` — find related execution flows
3. Read key files listed above for implementation details
