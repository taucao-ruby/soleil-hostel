---
name: models
description: "Skill for the Models area of soleil-hostel. 67 symbols across 29 files."
---

# Models

67 symbols | 29 files | Cohesion: 76%

## When to Use

- Working with code in `backend/`
- Understanding how ArrivalResolutionResult, LocationResource, Room work
- Modifying models-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Models/PersonalAccessToken.php` | isExpired, isRevoked, isValid, unrevoke, recordUsage (+3) |
| `backend/tests/Unit/Models/UserRoleHelpersTest.php` | test_is_moderator_returns_true_for_moderator_and_admin, test_role_can_be_set_with_enum, test_is_at_least_user_level, test_is_at_least_moderator_level, test_is_at_least_admin_level (+3) |
| `backend/app/Models/Room.php` | isEquivalentTo, isUpgradeOver, equivalentCandidatesAt, upgradeCandidatesAt, bookings (+2) |
| `backend/tests/Unit/Models/RoomTest.php` | test_room_has_many_bookings, test_active_bookings_relationship_filters_correctly, test_room_has_correct_fillable_attributes, test_lock_version_not_in_fillable |
| `backend/tests/Unit/Models/ReviewModelTest.php` | createReviewWithDeps, test_approved_scope_filters_correctly, test_high_rated_scope_filters_correctly, test_review_purifies_xss_on_save |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getRoomAvailability, isRoomAvailable, getBookedDates |
| `backend/app/Models/User.php` | isModerator, isAtLeast, hasAnyRole |
| `backend/app/Http/Resources/UserResource.php` | toArray, isCurrentUser, shouldIncludeStats |
| `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` | handle, generateDeviceFingerprint |
| `backend/app/Services/ArrivalResolutionService.php` | resolve, buildResult |

## Entry Points

Start here when exploring this area:

- **`ArrivalResolutionResult`** (Class) — `backend/app/Services/ArrivalResolutionResult.php:11`
- **`LocationResource`** (Class) — `backend/app/Http/Resources/LocationResource.php:13`
- **`Room`** (Class) — `backend/app/Models/Room.php:39`
- **`test_logout_revokes_token_and_clears_cookie`** (Method) — `backend/tests/Feature/HttpOnlyCookieAuthenticationTest.php:173`
- **`isExpired`** (Method) — `backend/app/Models/PersonalAccessToken.php:180`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `ArrivalResolutionResult` | Class | `backend/app/Services/ArrivalResolutionResult.php` | 11 |
| `LocationResource` | Class | `backend/app/Http/Resources/LocationResource.php` | 13 |
| `Room` | Class | `backend/app/Models/Room.php` | 39 |
| `test_logout_revokes_token_and_clears_cookie` | Method | `backend/tests/Feature/HttpOnlyCookieAuthenticationTest.php` | 173 |
| `isExpired` | Method | `backend/app/Models/PersonalAccessToken.php` | 180 |
| `isRevoked` | Method | `backend/app/Models/PersonalAccessToken.php` | 194 |
| `isValid` | Method | `backend/app/Models/PersonalAccessToken.php` | 204 |
| `unrevoke` | Method | `backend/app/Models/PersonalAccessToken.php` | 236 |
| `recordUsage` | Method | `backend/app/Models/PersonalAccessToken.php` | 256 |
| `getStatus` | Method | `backend/app/Models/PersonalAccessToken.php` | 408 |
| `test_unified_logout_works_with_httponly_cookie` | Method | `backend/tests/Feature/Auth/AuthConsolidationTest.php` | 233 |
| `handle` | Method | `backend/app/Http/Middleware/CheckTokenNotRevokedAndNotExpired.php` | 25 |
| `handle` | Method | `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` | 27 |
| `generateDeviceFingerprint` | Method | `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` | 129 |
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
| Services | 5 calls |
| Room | 4 calls |
| Cache | 3 calls |
| Policies | 2 calls |
| Auth | 2 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "ArrivalResolutionResult"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "models"})` — find related execution flows
3. Read key files listed above for implementation details
