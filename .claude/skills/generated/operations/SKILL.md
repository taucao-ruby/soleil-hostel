---
name: operations
description: "Skill for the Operations area of soleil-hostel. 68 symbols across 11 files."
---

# Operations

68 symbols | 11 files | Cohesion: 79%

## When to Use

- Working with code in `backend/`
- Understanding how arrivalDepartureBoard, exceptionBoard, activeAssignmentSubquery work
- Modifying operations-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Operations/OperationalPassTwoTest.php` | test_room_transitions_to_dirty_when_stay_checks_out, test_room_transitions_to_occupied_when_stay_checks_in, test_room_assignment_creation_blocked_when_room_not_ready, test_out_of_service_room_excluded_from_available_rooms, test_readiness_log_records_each_transition (+20) |
| `backend/tests/Feature/Operations/OperationalPassThreeTest.php` | test_equivalent_swap_creates_room_assignment_and_recovery_case, test_equivalent_swap_only_considers_ready_rooms, test_complimentary_upgrade_selects_minimum_higher_tier, test_upgrade_never_selects_lower_or_equal_tier, test_internal_relocation_recommended_when_no_same_location_room (+15) |
| `backend/app/Services/OperationalDashboardService.php` | arrivalDepartureBoard, exceptionBoard, activeAssignmentSubquery, baseStayBoardQuery, mapStayRows (+2) |
| `backend/app/Services/CheckInBlockageResolver.php` | resolve, findCandidateRooms, closeActiveAssignment, detectBlockageType |
| `backend/app/Models/ServiceRecoveryCase.php` | isUnsettled, isSettled, outstandingAmount |
| `backend/app/Services/FinancialOperationsService.php` | compensationExposure, relocationCostSummary |
| `backend/app/Models/Stay.php` | scopeExpectedToday, scopeDueOutToday |
| `backend/app/Enums/StayStatus.php` | inHouseStatuses, isInHouse |
| `backend/app/Exceptions/RoomNotReadyForAssignmentException.php` | forRoom |
| `backend/app/Console/Commands/BackfillOperationalStays.php` | handle |

## Entry Points

Start here when exploring this area:

- **`arrivalDepartureBoard`** (Method) — `backend/app/Services/OperationalDashboardService.php:53`
- **`exceptionBoard`** (Method) — `backend/app/Services/OperationalDashboardService.php:234`
- **`activeAssignmentSubquery`** (Method) — `backend/app/Services/OperationalDashboardService.php:448`
- **`baseStayBoardQuery`** (Method) — `backend/app/Services/OperationalDashboardService.php:461`
- **`mapStayRows`** (Method) — `backend/app/Services/OperationalDashboardService.php:498`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `arrivalDepartureBoard` | Method | `backend/app/Services/OperationalDashboardService.php` | 53 |
| `exceptionBoard` | Method | `backend/app/Services/OperationalDashboardService.php` | 234 |
| `activeAssignmentSubquery` | Method | `backend/app/Services/OperationalDashboardService.php` | 448 |
| `baseStayBoardQuery` | Method | `backend/app/Services/OperationalDashboardService.php` | 461 |
| `mapStayRows` | Method | `backend/app/Services/OperationalDashboardService.php` | 498 |
| `mapRecoveryCaseRow` | Method | `backend/app/Services/OperationalDashboardService.php` | 549 |
| `compensationExposure` | Method | `backend/app/Services/FinancialOperationsService.php` | 91 |
| `relocationCostSummary` | Method | `backend/app/Services/FinancialOperationsService.php` | 144 |
| `resolve` | Method | `backend/app/Services/CheckInBlockageResolver.php` | 39 |
| `findCandidateRooms` | Method | `backend/app/Services/CheckInBlockageResolver.php` | 255 |
| `closeActiveAssignment` | Method | `backend/app/Services/CheckInBlockageResolver.php` | 298 |
| `detectBlockageType` | Method | `backend/app/Services/CheckInBlockageResolver.php` | 308 |
| `scopeExpectedToday` | Method | `backend/app/Models/Stay.php` | 120 |
| `scopeDueOutToday` | Method | `backend/app/Models/Stay.php` | 130 |
| `forRoom` | Method | `backend/app/Exceptions/RoomNotReadyForAssignmentException.php` | 11 |
| `inHouseStatuses` | Method | `backend/app/Enums/StayStatus.php` | 33 |
| `isInHouse` | Method | `backend/app/Enums/StayStatus.php` | 41 |
| `test_room_transitions_to_dirty_when_stay_checks_out` | Method | `backend/tests/Feature/Operations/OperationalPassTwoTest.php` | 48 |
| `test_room_transitions_to_occupied_when_stay_checks_in` | Method | `backend/tests/Feature/Operations/OperationalPassTwoTest.php` | 80 |
| `test_room_assignment_creation_blocked_when_room_not_ready` | Method | `backend/tests/Feature/Operations/OperationalPassTwoTest.php` | 111 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `ExecuteWarmup → Today` | cross_community | 5 |
| `ExceptionBoard → ActiveAssignmentSubquery` | intra_community | 4 |
| `Resolve → InHouseStatuses` | intra_community | 3 |
| `ExceptionBoard → MapStayRows` | intra_community | 3 |
| `ExceptionBoard → InHouseStatuses` | intra_community | 3 |
| `Creating → ForRoom` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Feature | 40 calls |
| Authorization | 2 calls |

## How to Explore

1. `gitnexus_context({name: "arrivalDepartureBoard"})` — see callers and callees
2. `gitnexus_query({query: "operations"})` — find related execution flows
3. Read key files listed above for implementation details
