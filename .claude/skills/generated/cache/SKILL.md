---
name: cache
description: "Skill for the Cache area of soleil-hostel. 71 symbols across 22 files."
---

# Cache

71 symbols | 22 files | Cohesion: 71%

## When to Use

- Working with code in `backend/`
- Understanding how warning, supportsTags, getRoomDetailWithBookings work
- Modifying cache-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Cache/CacheWarmupTest.php` | test_warm_config_cache, test_warm_rooms_cache, test_warm_users_cache, test_warm_bookings_cache, test_warm_static_cache (+16) |
| `backend/app/Services/Cache/CacheWarmer.php` | warmGroup, warmConfigCache, warmUsersCache, warmStaticCache, warmRoomsCache (+8) |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getAvailableRooms, invalidateRoomAvailability, invalidateAllAvailability, buildCacheKey, queryAvailableRooms (+2) |
| `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | test_cache_hit_on_second_request, test_cache_expiration_after_ttl, test_cache_invalidation_on_different_capacities, test_cache_warmup |
| `backend/app/Services/RoomAvailabilityService.php` | getRoomDetailWithBookings, normalizeDate, getAllRoomsWithAvailability |
| `backend/app/Console/Commands/CacheWarmupCommand.php` | executeWarmup, executeWithProgress, runPreflightChecks |
| `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | test_cache_invalidation_listener_executes, test_listener_handles_failed_invalidation_gracefully |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_backfill_command_dry_run_does_not_persist, test_backfill_command_is_idempotent |
| `backend/app/Models/Stay.php` | scopeExpectedToday, scopeDueOutToday |
| `backend/app/Console/Commands/BackfillOperationalStays.php` | handle, stayAttributesFor |

## Entry Points

Start here when exploring this area:

- **`warning`** (Method) — `deploy.php:522`
- **`supportsTags`** (Method) — `backend/app/Traits/HasCacheTagSupport.php:11`
- **`getRoomDetailWithBookings`** (Method) — `backend/app/Services/RoomAvailabilityService.php:112`
- **`normalizeDate`** (Method) — `backend/app/Services/RoomAvailabilityService.php:253`
- **`getTrashedBookings`** (Method) — `backend/app/Services/BookingService.php:369`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `warning` | Method | `deploy.php` | 522 |
| `supportsTags` | Method | `backend/app/Traits/HasCacheTagSupport.php` | 11 |
| `getRoomDetailWithBookings` | Method | `backend/app/Services/RoomAvailabilityService.php` | 112 |
| `normalizeDate` | Method | `backend/app/Services/RoomAvailabilityService.php` | 253 |
| `getTrashedBookings` | Method | `backend/app/Services/BookingService.php` | 369 |
| `test_cache_hit_on_second_request` | Method | `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | 25 |
| `test_cache_expiration_after_ttl` | Method | `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | 51 |
| `test_cache_invalidation_on_different_capacities` | Method | `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | 88 |
| `test_cache_invalidation_listener_executes` | Method | `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | 51 |
| `test_listener_handles_failed_invalidation_gracefully` | Method | `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | 81 |
| `getAvailableRooms` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 33 |
| `invalidateRoomAvailability` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 108 |
| `invalidateAllAvailability` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 132 |
| `buildCacheKey` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 190 |
| `queryAvailableRooms` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 205 |
| `findByIdWithConfirmedBookings` | Method | `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | 33 |
| `handlePaymentIntentSucceeded` | Method | `backend/app/Http/Controllers/Payment/StripeWebhookController.php` | 31 |
| `test_warm_config_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 240 |
| `test_warm_rooms_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 250 |
| `test_warm_users_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 260 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → SupportsTags` | cross_community | 6 |
| `ExecuteWithProgress → BuildCacheKey` | cross_community | 6 |
| `ExecuteWithProgress → SupportsTags` | cross_community | 6 |
| `ExecuteWithProgress → QueryAvailableRooms` | cross_community | 6 |
| `Destroy → SupportsTags` | cross_community | 6 |
| `WarmAll → BuildCacheKey` | cross_community | 6 |
| `WarmAll → SupportsTags` | cross_community | 6 |
| `WarmAll → QueryAvailableRooms` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Feature | 3 calls |
| Services | 3 calls |
| Controllers | 1 calls |
| Notifications | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "warning"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "cache"})` — find related execution flows
3. Read key files listed above for implementation details
