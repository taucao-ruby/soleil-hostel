---
name: models
description: "Skill for the Models area of soleil-hostel. 56 symbols across 23 files."
---

# Models

56 symbols | 23 files | Cohesion: 80%

## When to Use

- Working with code in `backend/`
- Understanding how LocationResource, Room, test_logout_revokes_token_and_clears_cookie work
- Modifying models-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Models/PersonalAccessToken.php` | isExpired, isRevoked, isValid, unrevoke, recordUsage (+3) |
| `backend/tests/Unit/Models/UserRoleHelpersTest.php` | test_is_moderator_returns_true_for_moderator_and_admin, test_role_can_be_set_with_enum, test_is_at_least_user_level, test_is_at_least_moderator_level, test_is_at_least_admin_level (+3) |
| `backend/tests/Unit/Models/RoomTest.php` | test_room_has_many_bookings, test_active_bookings_relationship_filters_correctly, test_room_has_correct_fillable_attributes, test_lock_version_not_in_fillable |
| `backend/tests/Unit/Models/ReviewModelTest.php` | createReviewWithDeps, test_approved_scope_filters_correctly, test_high_rated_scope_filters_correctly, test_review_purifies_xss_on_save |
| `backend/app/Models/Room.php` | bookings, activeBookings, Room |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getRoomAvailability, isRoomAvailable, getBookedDates |
| `backend/app/Models/User.php` | isModerator, isAtLeast, hasAnyRole |
| `backend/app/Http/Resources/UserResource.php` | toArray, isCurrentUser, shouldIncludeStats |
| `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` | handle, generateDeviceFingerprint |
| `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | me, detectAuthMode |

## Entry Points

Start here when exploring this area:

- **`LocationResource`** (Class) — `backend/app/Http/Resources/LocationResource.php:13`
- **`Room`** (Class) — `backend/app/Models/Room.php:37`
- **`test_logout_revokes_token_and_clears_cookie`** (Method) — `backend/tests/Feature/HttpOnlyCookieAuthenticationTest.php:173`
- **`isExpired`** (Method) — `backend/app/Models/PersonalAccessToken.php:180`
- **`isRevoked`** (Method) — `backend/app/Models/PersonalAccessToken.php:194`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `LocationResource` | Class | `backend/app/Http/Resources/LocationResource.php` | 13 |
| `Room` | Class | `backend/app/Models/Room.php` | 37 |
| `test_logout_revokes_token_and_clears_cookie` | Method | `backend/tests/Feature/HttpOnlyCookieAuthenticationTest.php` | 173 |
| `isExpired` | Method | `backend/app/Models/PersonalAccessToken.php` | 180 |
| `isRevoked` | Method | `backend/app/Models/PersonalAccessToken.php` | 194 |
| `isValid` | Method | `backend/app/Models/PersonalAccessToken.php` | 204 |
| `unrevoke` | Method | `backend/app/Models/PersonalAccessToken.php` | 236 |
| `recordUsage` | Method | `backend/app/Models/PersonalAccessToken.php` | 256 |
| `getMinutesUntilExpiration` | Method | `backend/app/Models/PersonalAccessToken.php` | 308 |
| `getSecondsUntilExpiration` | Method | `backend/app/Models/PersonalAccessToken.php` | 322 |
| `getStatus` | Method | `backend/app/Models/PersonalAccessToken.php` | 408 |
| `test_unified_logout_works_with_httponly_cookie` | Method | `backend/tests/Feature/Auth/AuthConsolidationTest.php` | 233 |
| `handle` | Method | `backend/app/Http/Middleware/CheckTokenNotRevokedAndNotExpired.php` | 25 |
| `handle` | Method | `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` | 27 |
| `generateDeviceFingerprint` | Method | `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` | 129 |
| `me` | Method | `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | 48 |
| `detectAuthMode` | Method | `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | 153 |
| `me` | Method | `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` | 279 |
| `me` | Method | `backend/app/Http/Controllers/Auth/AuthController.php` | 376 |
| `isRoomAvailable` | Method | `backend/app/Services/RoomAvailabilityService.php` | 161 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `LogoutAll → IsExpired` | cross_community | 4 |
| `LogoutAll → IsRevoked` | cross_community | 4 |
| `Me → IsExpired` | intra_community | 4 |
| `Me → IsRevoked` | intra_community | 4 |
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
| Cache | 4 calls |
| Feature | 3 calls |
| Policies | 2 calls |
| Auth | 2 calls |

## How to Explore

1. `gitnexus_context({name: "LocationResource"})` — see callers and callees
2. `gitnexus_query({query: "models"})` — find related execution flows
3. Read key files listed above for implementation details
