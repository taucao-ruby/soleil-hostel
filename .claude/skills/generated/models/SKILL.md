---
name: models
description: "Skill for the Models area of soleil-hostel. 81 symbols across 30 files."
---

# Models

81 symbols | 30 files | Cohesion: 72%

## When to Use

- Working with code in `backend/`
- Understanding how ArrivalResolutionResult, LocationResource, Room work
- Modifying models-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Models/UserRoleHelpersTest.php` | test_is_user_returns_true_only_for_user_role, test_has_role_exact_match, test_factory_moderator_state, test_is_moderator_returns_true_for_moderator_and_admin, test_role_can_be_set_with_enum (+6) |
| `backend/tests/Unit/Models/ReviewModelTest.php` | createReviewWithDeps, test_review_can_be_created, test_review_defaults_to_unapproved, test_approved_scope_filters_correctly, test_high_rated_scope_filters_correctly (+5) |
| `backend/app/Models/Room.php` | isEquivalentTo, isUpgradeOver, equivalentCandidatesAt, upgradeCandidatesAt, bookings (+2) |
| `backend/app/Models/User.php` | isUser, hasRole, isModerator, isAtLeast, hasAnyRole |
| `backend/app/Models/PersonalAccessToken.php` | isExpired, isRevoked, unrevoke, recordUsage, getStatus |
| `backend/tests/Feature/Authorization/GateTest.php` | test_admin_gate_denies_moderator, test_moderator_gate_allows_moderator, test_moderate_content_gate_allows_moderator_and_above, test_manage_rooms_gate_allows_admin_only |
| `backend/tests/Unit/Models/RoomTest.php` | test_room_has_many_bookings, test_active_bookings_relationship_filters_correctly, test_room_has_correct_fillable_attributes, test_lock_version_not_in_fillable |
| `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | test_moderator_cannot_access_admin_route, test_moderator_can_access_moderator_route, test_all_authenticated_users_can_access_user_route |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getRoomAvailability, isRoomAvailable, getBookedDates |
| `backend/app/Http/Resources/UserResource.php` | toArray, isCurrentUser, shouldIncludeStats |

## Entry Points

Start here when exploring this area:

- **`ArrivalResolutionResult`** (Class) — `backend/app/Services/ArrivalResolutionResult.php:11`
- **`LocationResource`** (Class) — `backend/app/Http/Resources/LocationResource.php:13`
- **`Room`** (Class) — `backend/app/Models/Room.php:39`
- **`moderator`** (Method) — `backend/database/factories/UserFactory.php:59`
- **`isUser`** (Method) — `backend/app/Models/User.php:89`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `ArrivalResolutionResult` | Class | `backend/app/Services/ArrivalResolutionResult.php` | 11 |
| `LocationResource` | Class | `backend/app/Http/Resources/LocationResource.php` | 13 |
| `Room` | Class | `backend/app/Models/Room.php` | 39 |
| `moderator` | Method | `backend/database/factories/UserFactory.php` | 59 |
| `isUser` | Method | `backend/app/Models/User.php` | 89 |
| `hasRole` | Method | `backend/app/Models/User.php` | 101 |
| `test_is_user_returns_true_only_for_user_role` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 45 |
| `test_has_role_exact_match` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 58 |
| `test_factory_moderator_state` | Method | `backend/tests/Unit/Models/UserRoleHelpersTest.php` | 158 |
| `test_moderator_cannot_access_admin_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 50 |
| `test_moderator_can_access_moderator_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 81 |
| `test_all_authenticated_users_can_access_user_route` | Method | `backend/tests/Feature/Middleware/EnsureUserHasRoleTest.php` | 101 |
| `test_admin_gate_denies_moderator` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 30 |
| `test_moderator_gate_allows_moderator` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 56 |
| `test_moderate_content_gate_allows_moderator_and_above` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 92 |
| `test_manage_rooms_gate_allows_admin_only` | Method | `backend/tests/Feature/Authorization/GateTest.php` | 128 |
| `approved` | Method | `backend/database/factories/ReviewFactory.php` | 44 |
| `createReviewWithDeps` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 22 |
| `test_review_can_be_created` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 44 |
| `test_review_defaults_to_unapproved` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 52 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Resolve → Active` | cross_community | 4 |
| `LogoutAll → IsExpired` | cross_community | 4 |
| `LogoutAll → IsRevoked` | cross_community | 4 |
| `Me → IsExpired` | cross_community | 4 |
| `Me → IsRevoked` | cross_community | 4 |
| `Logout → IsExpired` | cross_community | 4 |
| `Logout → IsRevoked` | cross_community | 4 |
| `ToArray → IsAtLeast` | cross_community | 4 |
| `Resolve → SourceRoomForStay` | cross_community | 3 |
| `Resolve → NormalizeReadinessStatus` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 11 calls |
| Authorization | 10 calls |
| Booking | 8 calls |
| Stays | 7 calls |
| Services | 6 calls |
| Cache | 3 calls |
| Policies | 2 calls |
| Auth | 2 calls |

## How to Explore

1. `gitnexus_context({name: "ArrivalResolutionResult"})` — see callers and callees
2. `gitnexus_query({query: "models"})` — find related execution flows
3. Read key files listed above for implementation details
