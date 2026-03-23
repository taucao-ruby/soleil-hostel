---
name: stays
description: "Skill for the Stays area of soleil-hostel. 37 symbols across 11 files."
---

# Stays

37 symbols | 11 files | Cohesion: 55%

## When to Use

- Working with code in `backend/`
- Understanding how expected, lateCheckout, checkedOut work
- Modifying stays-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Stays/RoomAssignmentTest.php` | test_multiple_closed_assignments_allowed_for_same_stay, test_active_assignment_lookup_via_scope, test_current_room_assignment_relation_returns_active, test_current_room_assignment_is_null_when_no_active_assignment, test_valid_assignment_type_accepted (+5) |
| `backend/database/factories/StayFactory.php` | expected, lateCheckout, checkedOut, noShow, inHouse |
| `backend/tests/Feature/Stays/StayInvariantTest.php` | test_scope_in_house_returns_in_house_and_late_checkout, test_scope_in_house_excludes_expected_and_checked_out, test_scope_expected_today_returns_arrivals_scheduled_for_today, test_scope_due_out_today_returns_in_house_with_checkout_today, test_stay_creation_does_not_affect_booking_overlap_logic |
| `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | test_case_can_be_linked_to_a_stay, isPgsql, test_invalid_incident_type_rejected_by_check_constraint, test_invalid_severity_rejected_by_check_constraint, test_invalid_case_status_rejected_by_check_constraint |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_backfill_command_skips_confirmed_booking_that_already_has_stay, test_backfill_command_is_idempotent, test_backfill_command_creates_stay_for_confirmed_future_booking, test_backfill_command_skips_confirmed_booking_with_past_checkout |
| `backend/app/Models/Stay.php` | scopeExpectedToday, scopeDueOutToday |
| `backend/tests/Feature/Cache/CacheWarmupTest.php` | setUp, createTestData |
| `backend/app/Services/Cache/CacheWarmer.php` | warmBookingsCache |
| `backend/app/Console/Commands/BackfillOperationalStays.php` | handle |
| `frontend/src/features/admin/bookings/BookingCalendar.tsx` | today |

## Entry Points

Start here when exploring this area:

- **`expected`** (Method) — `backend/database/factories/StayFactory.php:37`
- **`lateCheckout`** (Method) — `backend/database/factories/StayFactory.php:64`
- **`checkedOut`** (Method) — `backend/database/factories/StayFactory.php:84`
- **`noShow`** (Method) — `backend/database/factories/StayFactory.php:101`
- **`test_scope_in_house_returns_in_house_and_late_checkout`** (Method) — `backend/tests/Feature/Stays/StayInvariantTest.php:56`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `expected` | Method | `backend/database/factories/StayFactory.php` | 37 |
| `lateCheckout` | Method | `backend/database/factories/StayFactory.php` | 64 |
| `checkedOut` | Method | `backend/database/factories/StayFactory.php` | 84 |
| `noShow` | Method | `backend/database/factories/StayFactory.php` | 101 |
| `test_scope_in_house_returns_in_house_and_late_checkout` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 56 |
| `test_scope_in_house_excludes_expected_and_checked_out` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 77 |
| `test_scope_expected_today_returns_arrivals_scheduled_for_today` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 89 |
| `test_scope_due_out_today_returns_in_house_with_checkout_today` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 177 |
| `test_backfill_command_skips_confirmed_booking_that_already_has_stay` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 125 |
| `test_multiple_closed_assignments_allowed_for_same_stay` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 115 |
| `scopeExpectedToday` | Method | `backend/app/Models/Stay.php` | 118 |
| `scopeDueOutToday` | Method | `backend/app/Models/Stay.php` | 128 |
| `test_backfill_command_is_idempotent` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 63 |
| `test_backfill_command_creates_stay_for_confirmed_future_booking` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 86 |
| `test_backfill_command_skips_confirmed_booking_with_past_checkout` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 99 |
| `setUp` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 28 |
| `createTestData` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 41 |
| `warmBookingsCache` | Method | `backend/app/Services/Cache/CacheWarmer.php` | 390 |
| `handle` | Method | `backend/app/Console/Commands/BackfillOperationalStays.php` | 45 |
| `inHouse` | Method | `backend/database/factories/StayFactory.php` | 49 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `ExecuteWarmup → Today` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 17 calls |
| Feature | 10 calls |
| Cache | 4 calls |

## How to Explore

1. `gitnexus_context({name: "expected"})` — see callers and callees
2. `gitnexus_query({query: "stays"})` — find related execution flows
3. Read key files listed above for implementation details
