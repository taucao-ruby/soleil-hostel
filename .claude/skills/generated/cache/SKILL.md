---
name: cache
description: "Skill for the Cache area of soleil-hostel. 73 symbols across 20 files."
---

# Cache

73 symbols | 20 files | Cohesion: 72%

## When to Use

- Working with code in `backend/`
- Understanding how artisan, migrateDatabases, scopeExpectedToday work
- Modifying cache-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Cache/CacheWarmupTest.php` | setUp, createTestData, test_cache_warmup_command_runs_successfully, test_cache_warmup_command_specific_group, test_cache_warmup_command_multiple_groups (+22) |
| `backend/app/Services/Cache/CacheWarmer.php` | warmGroup, warmConfigCache, warmUsersCache, warmStaticCache, warmRoomsCache (+7) |
| `backend/app/Services/Cache/RoomAvailabilityCache.php` | getAvailableRooms, invalidateRoomAvailability, warmUpCache, getCacheStats, buildCacheKey (+2) |
| `backend/tests/Feature/Cache/RoomAvailabilityCacheTest.php` | test_cache_hit_on_second_request, test_cache_invalidation_on_different_capacities, test_cache_warmup, test_cache_expiration_after_ttl |
| `backend/tests/Feature/Stays/StayBackfillTest.php` | test_backfill_command_is_idempotent, test_backfill_command_respects_scope, test_backfill_command_dry_run_does_not_persist |
| `backend/app/Console/Commands/CacheWarmupCommand.php` | runPreflightChecks, executeWarmup, executeWithProgress |
| `backend/app/Models/Stay.php` | scopeExpectedToday, scopeDueOutToday |
| `backend/app/Console/Commands/BackfillOperationalStays.php` | handle, stayAttributesFor |
| `backend/tests/Feature/Cache/CacheInvalidationOnBookingTest.php` | test_cache_invalidation_listener_executes, test_listener_handles_failed_invalidation_gracefully |
| `backend/tests/TestCase.php` | artisan |

## Entry Points

Start here when exploring this area:

- **`artisan`** (Method) — `backend/tests/TestCase.php:71`
- **`migrateDatabases`** (Method) — `backend/tests/Traits/RefreshDatabaseWithoutPrompts.php:18`
- **`scopeExpectedToday`** (Method) — `backend/app/Models/Stay.php:142`
- **`scopeDueOutToday`** (Method) — `backend/app/Models/Stay.php:152`
- **`test_backfill_command_is_idempotent`** (Method) — `backend/tests/Feature/Stays/StayBackfillTest.php:63`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `artisan` | Method | `backend/tests/TestCase.php` | 71 |
| `migrateDatabases` | Method | `backend/tests/Traits/RefreshDatabaseWithoutPrompts.php` | 18 |
| `scopeExpectedToday` | Method | `backend/app/Models/Stay.php` | 142 |
| `scopeDueOutToday` | Method | `backend/app/Models/Stay.php` | 152 |
| `test_backfill_command_is_idempotent` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 63 |
| `test_backfill_command_respects_scope` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 90 |
| `test_backfill_command_dry_run_does_not_persist` | Method | `backend/tests/Feature/Stays/StayBackfillTest.php` | 132 |
| `setUp` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 28 |
| `createTestData` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 41 |
| `test_cache_warmup_command_runs_successfully` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 69 |
| `test_cache_warmup_command_specific_group` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 91 |
| `test_cache_warmup_command_multiple_groups` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 98 |
| `test_cache_warmup_command_force_option` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 105 |
| `test_cache_warmup_command_chunk_option` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 117 |
| `test_cache_warmup_command_invalid_group` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 124 |
| `test_cache_warmup_command_verbose_output` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 132 |
| `handle` | Method | `backend/app/Console/Commands/BackfillOperationalStays.php` | 46 |
| `stayAttributesFor` | Method | `backend/app/Console/Commands/BackfillOperationalStays.php` | 114 |
| `test_warm_config_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 240 |
| `test_warm_rooms_cache` | Method | `backend/tests/Feature/Cache/CacheWarmupTest.php` | 250 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `ExecuteWithProgress → BuildCacheKey` | cross_community | 6 |
| `ExecuteWithProgress → SupportsTags` | cross_community | 6 |
| `ExecuteWithProgress → QueryAvailableRooms` | cross_community | 6 |
| `HandlePaymentIntentSucceeded → SupportsTags` | cross_community | 5 |
| `HandlePaymentIntentSucceeded → Flush` | cross_community | 5 |
| `ExecuteWarmup → Active` | cross_community | 5 |
| `ExecuteWarmup → Today` | cross_community | 5 |
| `ExecuteWithProgress → Active` | cross_community | 5 |
| `ExecuteWithProgress → BookingSelectColumns` | cross_community | 5 |
| `ManualReviewRequired → Active` | cross_community | 5 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Services | 7 calls |
| Feature | 4 calls |
| Database | 1 calls |
| Enums | 1 calls |
| Room | 1 calls |
| Controllers | 1 calls |
| Notifications | 1 calls |

## How to Explore

1. `gitnexus_context({name: "artisan"})` — see callers and callees
2. `gitnexus_query({query: "cache"})` — find related execution flows
3. Read key files listed above for implementation details
