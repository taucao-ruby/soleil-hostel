---
name: enums
description: "Skill for the Enums area of soleil-hostel. 20 symbols across 9 files."
---

# Enums

20 symbols | 9 files | Cohesion: 82%

## When to Use

- Working with code in `backend/`
- Understanding how expected, transitionTo, canTransitionTo work
- Modifying enums-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Enums/StayStatus.php` | canTransitionTo, inHouseStatuses, isInHouse, terminalStatuses, isTerminal |
| `backend/tests/Unit/Enums/StayStatusTest.php` | test_can_transition_to_allows_only_pm_approved_stay_state_machine, test_can_transition_to_rejects_illegal_stay_transitions, test_in_house_statuses_include_only_live_occupancy_states, test_terminal_statuses_match_completed_or_relocated_states |
| `backend/tests/Feature/Stays/StayInvariantTest.php` | test_scope_expected_today_returns_arrivals_scheduled_for_today, test_transition_to_allows_valid_stay_lifecycle_change, test_transition_to_rejects_illegal_stay_lifecycle_change |
| `backend/app/Enums/BookingStatus.php` | isTerminal, isRefundInProgress |
| `backend/app/Enums/CaseStatus.php` | closedStatuses, isOpen |
| `backend/database/factories/StayFactory.php` | expected |
| `backend/app/Models/Stay.php` | transitionTo |
| `backend/tests/Feature/BookingCancellationTest.php` | test_booking_status_enum_helpers |
| `backend/app/Policies/BookingPolicy.php` | forceCancel |

## Entry Points

Start here when exploring this area:

- **`expected`** (Method) — `backend/database/factories/StayFactory.php:37`
- **`transitionTo`** (Method) — `backend/app/Models/Stay.php:109`
- **`canTransitionTo`** (Method) — `backend/app/Enums/StayStatus.php:72`
- **`test_can_transition_to_allows_only_pm_approved_stay_state_machine`** (Method) — `backend/tests/Unit/Enums/StayStatusTest.php:32`
- **`test_can_transition_to_rejects_illegal_stay_transitions`** (Method) — `backend/tests/Unit/Enums/StayStatusTest.php:43`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `expected` | Method | `backend/database/factories/StayFactory.php` | 37 |
| `transitionTo` | Method | `backend/app/Models/Stay.php` | 109 |
| `canTransitionTo` | Method | `backend/app/Enums/StayStatus.php` | 72 |
| `test_can_transition_to_allows_only_pm_approved_stay_state_machine` | Method | `backend/tests/Unit/Enums/StayStatusTest.php` | 32 |
| `test_can_transition_to_rejects_illegal_stay_transitions` | Method | `backend/tests/Unit/Enums/StayStatusTest.php` | 43 |
| `test_scope_expected_today_returns_arrivals_scheduled_for_today` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 89 |
| `test_transition_to_allows_valid_stay_lifecycle_change` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 115 |
| `test_transition_to_rejects_illegal_stay_lifecycle_change` | Method | `backend/tests/Feature/Stays/StayInvariantTest.php` | 125 |
| `test_booking_status_enum_helpers` | Method | `backend/tests/Feature/BookingCancellationTest.php` | 488 |
| `forceCancel` | Method | `backend/app/Policies/BookingPolicy.php` | 135 |
| `isTerminal` | Method | `backend/app/Enums/BookingStatus.php` | 39 |
| `isRefundInProgress` | Method | `backend/app/Enums/BookingStatus.php` | 47 |
| `inHouseStatuses` | Method | `backend/app/Enums/StayStatus.php` | 33 |
| `isInHouse` | Method | `backend/app/Enums/StayStatus.php` | 56 |
| `test_in_house_statuses_include_only_live_occupancy_states` | Method | `backend/tests/Unit/Enums/StayStatusTest.php` | 11 |
| `terminalStatuses` | Method | `backend/app/Enums/StayStatus.php` | 43 |
| `isTerminal` | Method | `backend/app/Enums/StayStatus.php` | 64 |
| `test_terminal_statuses_match_completed_or_relocated_states` | Method | `backend/tests/Unit/Enums/StayStatusTest.php` | 19 |
| `closedStatuses` | Method | `backend/app/Enums/CaseStatus.php` | 26 |
| `isOpen` | Method | `backend/app/Enums/CaseStatus.php` | 34 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 2 calls |
| Services | 1 calls |
| Policies | 1 calls |
| Booking | 1 calls |
| Stays | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "expected"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "enums"})` — find related execution flows
3. Read key files listed above for implementation details
