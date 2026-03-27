---
name: cache
description: "Skill for the Cache area of soleil-hostel. 67 symbols across 20 files."
---

# Cache

67 symbols | 20 files | Cohesion: 72%

## When to Use

- Working with code in `backend/`
- Understanding how test_warm_config_cache, test_warm_rooms_cache, test_warm_users_cache work
- Modifying cache-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Cache/CacheWarmupTest.php` | test_warm_config_cache, test_warm_rooms_cache, test_warm_users_cache, test_warm_bookings_cache, test_warm_static_cache (+16) |
| `backend/app/Services/Cache/CacheWarmer.php` | warmGroup, warmConfigCache, warmUsersCache, warmStaticCache, warmRoomsCache (+8) |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getAvailableRooms, invalidateRoomAvailability, warmUpCache, getCacheStats, buildCacheKey (+2) |
| `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | test_cache_hit_on_second_request, test_cache_invalidation_on_different_capacities, test_cache_warmup, test_cache_expiration_after_ttl |
| `backend/app/Console/Commands/CacheWarmupCommand.php` | executeWarmup, executeWithProgress, runPreflightChecks |
| `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | test_cache_invalidation_listener_executes, test_listener_handles_failed_invalidation_gracefully |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_backfill_command_dry_run_does_not_persist, test_backfill_command_is_idempotent |
| `backend/app/Models/Stay.php` | scopeExpectedToday, scopeDueOutToday |
| `backend/app/Console/Commands/BackfillOperationalStays.php` | handle, stayAttributesFor |
| `backend/tests/TestCase.php` | artisan |

## Entry Points

Start here when exploring this area:

- **`test_warm_config_cache`** (Method) — `backend/tests/Feature/Cache/CacheWarmupTest.php:240`
- **`test_warm_rooms_cache`** (Method) — `backend/tests/Feature/Cache/CacheWarmupTest.php:250`
- **`test_warm_users_cache`** (Method) — `backend/tests/Feature/Cache/CacheWarmupTest.php:260`
- **`test_warm_bookings_cache`** (Method) — `backend/tests/Feature/Cache/CacheWarmupTest.php:270`
- **`test_warm_static_cache`** (Method) — `backend/tests/Feature/Cache/CacheWarmupTest.php:280`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `test_warm_config_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 240 |
| `test_warm_rooms_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 250 |
| `test_warm_users_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 260 |
| `test_warm_bookings_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 270 |
| `test_warm_static_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 280 |
| `test_warm_computed_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 289 |
| `test_room_statistics_cached_correctly` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 305 |
| `test_booking_statistics_cached_correctly` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 321 |
| `test_cache_warmup_unknown_group_skipped` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 351 |
| `warmGroup` | Method | `backend/app/Services/Cache/CacheWarmer.php` | 180 |
| `warmConfigCache` | Method | `backend/app/Services/Cache/CacheWarmer.php` | 243 |
| `warmUsersCache` | Method | `backend/app/Services/Cache/CacheWarmer.php` | 335 |
| `warmStaticCache` | Method | `backend/app/Services/Cache/CacheWarmer.php` | 453 |
| `test_cache_hit_on_second_request` | Method | `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | 25 |
| `test_cache_invalidation_on_different_capacities` | Method | `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | 88 |
| `test_cache_warmup` | Method | `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | 148 |
| `test_cache_invalidation_listener_executes` | Method | `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | 51 |
| `test_listener_handles_failed_invalidation_gracefully` | Method | `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | 81 |
| `getAvailableRooms` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 33 |
| `invalidateRoomAvailability` | Method | `backend/app/Services/Cache/RoomAvailabilityCache.php` | 108 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `ExecuteWithProgress → BuildCacheKey` | cross_community | 6 |
| `ExecuteWithProgress → SupportsTags` | cross_community | 6 |
| `ExecuteWithProgress → QueryAvailableRooms` | cross_community | 6 |
| `WarmAll → BuildCacheKey` | cross_community | 6 |
| `WarmAll → SupportsTags` | cross_community | 6 |
| `WarmAll → QueryAvailableRooms` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `ExecuteWarmup → Active` | cross_community | 5 |
| `ExecuteWarmup → Today` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Services | 7 calls |
| Feature | 3 calls |
| Controllers | 1 calls |
| Notifications | 1 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "test_warm_config_cache"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "cache"})` — find related execution flows
3. Read key files listed above for implementation details
