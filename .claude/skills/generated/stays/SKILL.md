---
name: stays
description: "Skill for the Stays area of soleil-hostel. 37 symbols across 11 files."
---

# Stays

37 symbols | 11 files | Cohesion: 55%

## When to Use

- Working with code in `backend/`
- Understanding how settled, forLocation, ready work
- Modifying stays-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Stays/RoomAssignmentTest.php` | test_active_assignment_lookup_via_scope, test_current_room_assignment_relation_returns_active, test_current_room_assignment_is_null_when_no_active_assignment, test_valid_assignment_type_accepted, test_assignment_belongs_to_stay_booking_and_room (+5) |
| `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | test_case_can_be_linked_to_a_stay, isPgsql, test_invalid_incident_type_rejected_by_check_constraint, test_invalid_severity_rejected_by_check_constraint, test_invalid_case_status_rejected_by_check_constraint |
| `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | booking_deposit_scopes_filter_collected_and_applied_deposits, service_recovery_total_exposure_and_settlement_scopes_reflect_operational_financial_state, invalid_deposit_and_settlement_status_values_are_rejected_by_postgresql_checks, invalid_settlement_status_value_is_rejected_by_postgresql_check_constraint |
| `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | resolve_prefers_same_location_equivalent_before_upgrade_and_cross_location, resolve_escalates_to_external_when_no_internal_candidate_exists, apply_accepted_recommendation_creates_internal_assignment_and_recovery_case, makeBlockedExpectedArrival |
| `backend/database/factories/RoomFactory.php` | forLocation, ready, classified |
| `backend/database/factories/StayFactory.php` | inHouse, checkedOut, noShow |
| `backend/tests/Feature/Stays/StayInvariantTest.php` | test_stay_creation_does_not_affect_booking_overlap_logic, test_scope_in_house_returns_in_house_and_late_checkout, test_scope_in_house_excludes_expected_and_checked_out |
| `backend/database/factories/BookingFactory.php` | withDeposit, depositApplied |
| `backend/database/factories/ServiceRecoveryCaseFactory.php` | settled |
| `backend/app/Models/ServiceRecoveryCase.php` | totalExposure |

## Entry Points

Start here when exploring this area:

- **`settled`** (Method) — `backend/database/factories/ServiceRecoveryCaseFactory.php:131`
- **`forLocation`** (Method) — `backend/database/factories/RoomFactory.php:34`
- **`ready`** (Method) — `backend/database/factories/RoomFactory.php:54`
- **`classified`** (Method) — `backend/database/factories/RoomFactory.php:84`
- **`withDeposit`** (Method) — `backend/database/factories/BookingFactory.php:129`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `settled` | Method | `backend/database/factories/ServiceRecoveryCaseFactory.php` | 131 |
| `forLocation` | Method | `backend/database/factories/RoomFactory.php` | 34 |
| `ready` | Method | `backend/database/factories/RoomFactory.php` | 54 |
| `classified` | Method | `backend/database/factories/RoomFactory.php` | 84 |
| `withDeposit` | Method | `backend/database/factories/BookingFactory.php` | 129 |
| `depositApplied` | Method | `backend/database/factories/BookingFactory.php` | 141 |
| `totalExposure` | Method | `backend/app/Models/ServiceRecoveryCase.php` | 150 |
| `booking_deposit_scopes_filter_collected_and_applied_deposits` | Method | `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | 17 |
| `service_recovery_total_exposure_and_settlement_scopes_reflect_operational_financial_state` | Method | `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | 39 |
| `invalid_deposit_and_settlement_status_values_are_rejected_by_postgresql_checks` | Method | `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | 63 |
| `invalid_settlement_status_value_is_rejected_by_postgresql_check_constraint` | Method | `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | 89 |
| `resolve_prefers_same_location_equivalent_before_upgrade_and_cross_location` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 32 |
| `resolve_escalates_to_external_when_no_internal_candidate_exists` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 100 |
| `apply_accepted_recommendation_creates_internal_assignment_and_recovery_case` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 115 |
| `makeBlockedExpectedArrival` | Method | `backend/tests/Feature/Stays/ArrivalResolutionServiceTest.php` | 172 |
| `inHouse` | Method | `backend/database/factories/StayFactory.php` | 49 |
| `closed` | Method | `backend/database/factories/RoomAssignmentFactory.php` | 48 |
| `test_stay_creation_does_not_affect_booking_overlap_logic` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 137 |
| `test_case_can_be_linked_to_a_stay` | Method | `backend/tests/Feature/Stays/ServiceRecoveryCaseTest.php` | 53 |
| `test_active_assignment_lookup_via_scope` | Method | `backend/tests/Feature/Stays/RoomAssignmentTest.php` | 36 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Run → ForLocation` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 22 calls |
| Feature | 8 calls |
| Cache | 4 calls |
| Enums | 3 calls |
| Services | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "settled"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "stays"})` — find related execution flows
3. Read key files listed above for implementation details
