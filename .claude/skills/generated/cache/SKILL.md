---
name: cache
description: "Skill for the Cache area of soleil-hostel. 64 symbols across 20 files."
---

# Cache

64 symbols | 20 files | Cohesion: 74%

## When to Use

- Working with code in `backend/`
- Understanding how warning, supportsTags, getRoomDetailWithBookings work
- Modifying cache-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Cache/CacheWarmupTest.php` | test_warm_config_cache, test_warm_rooms_cache, test_warm_users_cache, test_warm_bookings_cache, test_warm_static_cache (+14) |
| `backend/app/Services/Cache/CacheWarmer.php` | warmGroup, warmConfigCache, warmUsersCache, warmBookingsCache, warmStaticCache (+8) |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getAvailableRooms, invalidateRoomAvailability, invalidateAllAvailability, buildCacheKey, warmUpCache (+2) |
| `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | test_cache_hit_on_second_request, test_cache_expiration_after_ttl, test_cache_invalidation_on_different_capacities, test_cache_warmup |
| `backend/app/Services/RoomAvailabilityService.php` | getRoomDetailWithBookings, normalizeDate, getAllRoomsWithAvailability |
| `backend/app/Console/Commands/CacheWarmupCommand.php` | executeWarmup, executeWithProgress, runPreflightChecks |
| `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | test_cache_invalidation_listener_executes, test_listener_handles_failed_invalidation_gracefully |
| `deploy.php` | warning |
| `backend/app/Traits/HasCacheTagSupport.php` | supportsTags |
| `backend/app/Services/BookingService.php` | getTrashedBookings |

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
| `findByIdWithConfirmedBookings` | Method | `backend/app/Repositories/Contracts/RoomRepositoryInterface.php` | 33 |
| `handlePaymentIntentSucceeded` | Method | `backend/app/Http/Controllers/Payment/StripeWebhookController.php` | 31 |
| `test_warm_config_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 240 |
| `test_warm_rooms_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 250 |
| `test_warm_users_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 260 |
| `test_warm_bookings_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 270 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `ExecuteWithProgress → Active` | cross_community | 7 |
| `WarmAll → Active` | cross_community | 7 |
| `Update → SupportsTags` | cross_community | 6 |
| `Handle → SupportsTags` | cross_community | 6 |
| `ExecuteWithProgress → BuildCacheKey` | cross_community | 6 |
| `ExecuteWithProgress → SupportsTags` | cross_community | 6 |
| `Destroy → SupportsTags` | cross_community | 6 |
| `WarmAll → BuildCacheKey` | cross_community | 6 |
| `WarmAll → SupportsTags` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Operations | 4 calls |
| Services | 4 calls |
| Feature | 2 calls |
| Stays | 1 calls |
| Notifications | 1 calls |

## How to Explore

1. `gitnexus_context({name: "warning"})` — see callers and callees
2. `gitnexus_query({query: "cache"})` — find related execution flows
3. Read key files listed above for implementation details
