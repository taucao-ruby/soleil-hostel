---
name: stays
description: "Skill for the Stays area of soleil-hostel. 31 symbols across 10 files."
---

# Stays

31 symbols | 10 files | Cohesion: 50%

## When to Use

- Working with code in `backend/`
- Understanding how inHouse, closed, test_stay_creation_does_not_affect_booking_overlap_logic work
- Modifying stays-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Stays/RoomAssignmentTest.php` | test_active_assignment_lookup_via_scope, test_current_room_assignment_relation_returns_active, test_current_room_assignment_is_null_when_no_active_assignment, test_valid_assignment_type_accepted, test_assignment_belongs_to_stay_booking_and_room (+5) |
| `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | test_case_can_be_linked_to_a_stay, isPgsql, test_invalid_incident_type_rejected_by_check_constraint, test_invalid_severity_rejected_by_check_constraint, test_invalid_case_status_rejected_by_check_constraint |
| `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | resolve_prefers_same_location_equivalent_before_upgrade_and_cross_location, resolve_escalates_to_external_when_no_internal_candidate_exists, apply_accepted_recommendation_creates_internal_assignment_and_recovery_case, makeBlockedExpectedArrival |
| `backend/database/factories/StayFactory.php` | inHouse, checkedOut, noShow |
| `backend/tests/Feature/Stays/StayInvariantTest.php` | test_stay_creation_does_not_affect_booking_overlap_logic, test_scope_in_house_returns_in_house_and_late_checkout, test_scope_in_house_excludes_expected_and_checked_out |
| `backend/database/factories/RoomFactory.php` | forLocation, classified |
| `backend/database/factories/RoomAssignmentFactory.php` | closed |
| `backend/database/factories/ServiceRecoveryCaseFactory.php` | settled |
| `backend/app/Models/ServiceRecoveryCase.php` | totalExposure |
| `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | service_recovery_total_exposure_and_settlement_scopes_reflect_operational_financial_state |

## Entry Points

Start here when exploring this area:

- **`inHouse`** (Method) — `backend/database/factories/StayFactory.php:49`
- **`closed`** (Method) — `backend/database/factories/RoomAssignmentFactory.php:48`
- **`test_stay_creation_does_not_affect_booking_overlap_logic`** (Method) — `backend/tests/Feature/Stays/StayInvariantTest.php:137`
- **`test_case_can_be_linked_to_a_stay`** (Method) — `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php:53`
- **`test_active_assignment_lookup_via_scope`** (Method) — `backend/tests/Feature/Stays/RoomAssignmentTest.php:36`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `inHouse` | Method | `backend/database/factories/StayFactory.php` | 49 |
| `closed` | Method | `backend/database/factories/RoomAssignmentFactory.php` | 48 |
| `test_stay_creation_does_not_affect_booking_overlap_logic` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 137 |
| `test_case_can_be_linked_to_a_stay` | Method | `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | 53 |
| `test_active_assignment_lookup_via_scope` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 36 |
| `test_current_room_assignment_relation_returns_active` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 49 |
| `test_current_room_assignment_is_null_when_no_active_assignment` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 63 |
| `test_valid_assignment_type_accepted` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 130 |
| `test_assignment_belongs_to_stay_booking_and_room` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 173 |
| `settled` | Method | `backend/database/factories/ServiceRecoveryCaseFactory.php` | 131 |
| `forLocation` | Method | `backend/database/factories/RoomFactory.php` | 34 |
| `classified` | Method | `backend/database/factories/RoomFactory.php` | 84 |
| `totalExposure` | Method | `backend/app/Models/ServiceRecoveryCase.php` | 150 |
| `service_recovery_total_exposure_and_settlement_scopes_reflect_operational_financial_state` | Method | `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | 39 |
| `resolve_prefers_same_location_equivalent_before_upgrade_and_cross_location` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 32 |
| `resolve_escalates_to_external_when_no_internal_candidate_exists` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 100 |
| `apply_accepted_recommendation_creates_internal_assignment_and_recovery_case` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 115 |
| `makeBlockedExpectedArrival` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 172 |
| `checkedOut` | Method | `backend/database/factories/StayFactory.php` | 84 |
| `noShow` | Method | `backend/database/factories/StayFactory.php` | 101 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Run → ForLocation` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 19 calls |
| Feature | 7 calls |
| Cache | 4 calls |
| Enums | 3 calls |
| Models | 3 calls |
| Services | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "inHouse"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "stays"})` — find related execution flows
3. Read key files listed above for implementation details
