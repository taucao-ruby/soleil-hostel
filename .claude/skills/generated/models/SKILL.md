---
name: models
description: "Skill for the Models area of soleil-hostel. 68 symbols across 27 files."
---

# Models

68 symbols | 27 files | Cohesion: 77%

## When to Use

- Working with code in `backend/`
- Understanding how ArrivalResolutionResult, LocationResource, Room work
- Modifying models-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/Models/ReviewModelTest.php` | createReviewWithDeps, test_review_can_be_created, test_review_defaults_to_unapproved, test_approved_scope_filters_correctly, test_high_rated_scope_filters_correctly (+5) |
| `backend/tests/Unit/Models/UserRoleHelpersTest.php` | test_is_moderator_returns_true_for_moderator_and_admin, test_role_can_be_set_with_enum, test_is_at_least_user_level, test_is_at_least_moderator_level, test_is_at_least_admin_level (+3) |
| `backend/app/Models/Room.php` | isEquivalentTo, isUpgradeOver, equivalentCandidatesAt, upgradeCandidatesAt, bookings (+2) |
| `backend/app/Models/PersonalAccessToken.php` | isExpired, isRevoked, unrevoke, recordUsage, getStatus |
| `backend/tests/Unit/Models/RoomTest.php` | test_room_has_many_bookings, test_active_bookings_relationship_filters_correctly, test_room_has_correct_fillable_attributes, test_lock_version_not_in_fillable |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getRoomAvailability, isRoomAvailable, getBookedDates |
| `backend/app/Models/User.php` | isModerator, isAtLeast, hasAnyRole |
| `backend/app/Http/Resources/UserResource.php` | toArray, isCurrentUser, shouldIncludeStats |
| `backend/app/Services/ArrivalResolutionService.php` | resolve, buildResult |
| `backend/app/Enums/ResolutionStep.php` | assignmentType, requiresOperatorApproval |

## Entry Points

Start here when exploring this area:

- **`ArrivalResolutionResult`** (Class) — `backend/app/Services/ArrivalResolutionResult.php:11`
- **`LocationResource`** (Class) — `backend/app/Http/Resources/LocationResource.php:13`
- **`Room`** (Class) — `backend/app/Models/Room.php:39`
- **`approved`** (Method) — `backend/database/factories/ReviewFactory.php:44`
- **`createReviewWithDeps`** (Method) — `backend/tests/Unit/Models/ReviewModelTest.php:22`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `ArrivalResolutionResult` | Class | `backend/app/Services/ArrivalResolutionResult.php` | 11 |
| `LocationResource` | Class | `backend/app/Http/Resources/LocationResource.php` | 13 |
| `Room` | Class | `backend/app/Models/Room.php` | 39 |
| `approved` | Method | `backend/database/factories/ReviewFactory.php` | 44 |
| `createReviewWithDeps` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 22 |
| `test_review_can_be_created` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 44 |
| `test_review_defaults_to_unapproved` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 52 |
| `test_approved_scope_filters_correctly` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 59 |
| `test_high_rated_scope_filters_correctly` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 68 |
| `test_review_belongs_to_room` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 78 |
| `test_review_belongs_to_user` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 86 |
| `test_review_belongs_to_booking` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 94 |
| `test_review_purifies_xss_on_save` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 112 |
| `test_recent_scope_orders_by_created_at_desc` | Method | `backend/tests/Unit/Models/ReviewModelTest.php` | 126 |
| `resolve` | Method | `backend/app/Services/ArrivalResolutionService.php` | 30 |
| `buildResult` | Method | `backend/app/Services/ArrivalResolutionService.php` | 222 |
| `isEquivalentTo` | Method | `backend/app/Models/Room.php` | 269 |
| `isUpgradeOver` | Method | `backend/app/Models/Room.php` | 281 |
| `equivalentCandidatesAt` | Method | `backend/app/Models/Room.php` | 294 |
| `upgradeCandidatesAt` | Method | `backend/app/Models/Room.php` | 311 |

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
| Authorization | 10 calls |
| Stays | 7 calls |
| Services | 6 calls |
| Room | 4 calls |
| Cache | 3 calls |
| Policies | 2 calls |
| Auth | 2 calls |
| Booking | 1 calls |

## How to Explore

1. `gitnexus_context({name: "ArrivalResolutionResult"})` — see callers and callees
2. `gitnexus_query({query: "models"})` — find related execution flows
3. Read key files listed above for implementation details
