---
name: stays
description: "Skill for the Stays area of soleil-hostel. 26 symbols across 6 files."
---

# Stays

26 symbols | 6 files | Cohesion: 62%

## When to Use

- Working with code in `backend/`
- Understanding how expected, inHouse, lateCheckout work
- Modifying stays-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Stays/RoomAssignmentTest.php` | test_active_assignment_lookup_via_scope, test_current_room_assignment_is_null_when_no_active_assignment, test_multiple_closed_assignments_allowed_for_same_stay, test_valid_assignment_type_accepted, test_assignment_belongs_to_stay_booking_and_room (+4) |
| `backend/database/factories/StayFactory.php` | expected, inHouse, lateCheckout, checkedOut, noShow |
| `backend/tests/Feature/Stays/StayInvariantTest.php` | test_scope_in_house_returns_in_house_and_late_checkout, test_scope_in_house_excludes_expected_and_checked_out, test_scope_expected_today_returns_arrivals_scheduled_for_today, test_stay_creation_does_not_affect_booking_overlap_logic, test_scope_due_out_today_returns_in_house_with_checkout_today |
| `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | test_case_can_be_linked_to_a_stay, isPgsql, test_invalid_incident_type_rejected_by_check_constraint, test_invalid_severity_rejected_by_check_constraint, test_invalid_case_status_rejected_by_check_constraint |
| `backend/database/factories/RoomAssignmentFactory.php` | closed |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_backfill_command_skips_confirmed_booking_that_already_has_stay |

## Entry Points

Start here when exploring this area:

- **`expected`** (Method) — `backend/database/factories/StayFactory.php:37`
- **`inHouse`** (Method) — `backend/database/factories/StayFactory.php:49`
- **`lateCheckout`** (Method) — `backend/database/factories/StayFactory.php:64`
- **`checkedOut`** (Method) — `backend/database/factories/StayFactory.php:84`
- **`noShow`** (Method) — `backend/database/factories/StayFactory.php:101`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `expected` | Method | `backend/database/factories/StayFactory.php` | 37 |
| `inHouse` | Method | `backend/database/factories/StayFactory.php` | 49 |
| `lateCheckout` | Method | `backend/database/factories/StayFactory.php` | 64 |
| `checkedOut` | Method | `backend/database/factories/StayFactory.php` | 84 |
| `noShow` | Method | `backend/database/factories/StayFactory.php` | 101 |
| `closed` | Method | `backend/database/factories/RoomAssignmentFactory.php` | 48 |
| `test_scope_in_house_returns_in_house_and_late_checkout` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 56 |
| `test_scope_in_house_excludes_expected_and_checked_out` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 77 |
| `test_scope_expected_today_returns_arrivals_scheduled_for_today` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 89 |
| `test_stay_creation_does_not_affect_booking_overlap_logic` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 115 |
| `test_scope_due_out_today_returns_in_house_with_checkout_today` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 177 |
| `test_backfill_command_skips_confirmed_booking_that_already_has_stay` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 125 |
| `test_case_can_be_linked_to_a_stay` | Method | `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | 53 |
| `test_active_assignment_lookup_via_scope` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 36 |
| `test_current_room_assignment_is_null_when_no_active_assignment` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 63 |
| `test_multiple_closed_assignments_allowed_for_same_stay` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 115 |
| `test_valid_assignment_type_accepted` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 130 |
| `test_assignment_belongs_to_stay_booking_and_room` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 173 |
| `isPgsql` | Method | `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | 30 |
| `test_invalid_incident_type_rejected_by_check_constraint` | Method | `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | 162 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Feature | 22 calls |
| Operations | 3 calls |
| Cache | 3 calls |

## How to Explore

1. `gitnexus_context({name: "expected"})` — see callers and callees
2. `gitnexus_query({query: "stays"})` — find related execution flows
3. Read key files listed above for implementation details
