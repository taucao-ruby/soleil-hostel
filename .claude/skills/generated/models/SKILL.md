---
name: models
description: "Skill for the Models area of soleil-hostel. 64 symbols across 27 files."
---

# Models

64 symbols | 27 files | Cohesion: 74%

## When to Use

- Working with code in `backend/`
- Understanding how LocationResource, Room, test_logout_revokes_token_and_clears_cookie work
- Modifying models-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Models/PersonalAccessToken.php` | isExpired, isRevoked, isValid, unrevoke, recordUsage (+3) |
| `backend/tests/Unit/Models/UserRoleHelpersTest.php` | test_is_moderator_returns_true_for_moderator_and_admin, test_role_can_be_set_with_enum, test_is_at_least_user_level, test_is_at_least_moderator_level, test_is_at_least_admin_level (+3) |
| `backend/app/Models/Room.php` | bookings, activeBookings, isEquivalentTo, isUpgradeOver, equivalentCandidatesAt (+2) |
| `backend/tests/Unit/Models/RoomTest.php` | test_room_has_many_bookings, test_active_bookings_relationship_filters_correctly, test_room_has_correct_fillable_attributes, test_lock_version_not_in_fillable |
| `backend/tests/Unit/Models/ReviewModelTest.php` | createReviewWithDeps, test_approved_scope_filters_correctly, test_high_rated_scope_filters_correctly, test_review_purifies_xss_on_save |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getRoomAvailability, isRoomAvailable, getBookedDates |
| `backend/app/Models/User.php` | isModerator, isAtLeast, hasAnyRole |
| `backend/app/Http/Resources/UserResource.php` | toArray, isCurrentUser, shouldIncludeStats |
| `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` | handle, generateDeviceFingerprint |
| `backend/tests/Feature/Stays/FinancialLifecycleTest.php` | invalid_deposit_and_settlement_status_values_are_rejected_by_postgresql_checks, invalid_settlement_status_value_is_rejected_by_postgresql_check_constraint |

## Entry Points

Start here when exploring this area:

- **`LocationResource`** (Class) — `backend/app/Http/Resources/LocationResource.php:13`
- **`Room`** (Class) — `backend/app/Models/Room.php:39`
- **`test_logout_revokes_token_and_clears_cookie`** (Method) — `backend/tests/Feature/HttpOnlyCookieAuthenticationTest.php:173`
- **`isExpired`** (Method) — `backend/app/Models/PersonalAccessToken.php:180`
- **`isRevoked`** (Method) — `backend/app/Models/PersonalAccessToken.php:194`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
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
| `isRoomAvailable` | Method | `backend/app/Services/RoomAvailabilityService.php` | 161 |
| `bookings` | Method | `backend/app/Models/Room.php` | 103 |
| `activeBookings` | Method | `backend/app/Models/Room.php` | 119 |
| `test_room_has_many_bookings` | Method | `backend/tests/Unit/Models/RoomTest.php` | 123 |
| `test_active_bookings_relationship_filters_correctly` | Method | `backend/tests/Unit/Models/RoomTest.php` | 138 |
| `test_single_room_availability_cache` | Method | `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | 132 |
| `getRoomAvailability` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 70 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `LogoutAll → IsExpired` | cross_community | 4 |
| `LogoutAll → IsRevoked` | cross_community | 4 |
| `Me → IsExpired` | cross_community | 4 |
| `Me → IsRevoked` | cross_community | 4 |
| `Logout → IsExpired` | cross_community | 4 |
| `Logout → IsRevoked` | cross_community | 4 |
| `ToArray → IsAtLeast` | cross_community | 4 |
| `Handle → IsRevoked` | cross_community | 3 |
| `Handle → IsRevoked` | cross_community | 3 |
| `Login → IsRevoked` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Authorization | 10 calls |
| Room | 6 calls |
| Cache | 4 calls |
| Stays | 4 calls |
| Policies | 2 calls |
| Auth | 2 calls |
| Services | 1 calls |
| Feature | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "LocationResource"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "models"})` — find related execution flows
3. Read key files listed above for implementation details
