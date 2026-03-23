---
name: ratelimiting
description: "Skill for the RateLimiting area of soleil-hostel. 17 symbols across 3 files."
---

# RateLimiting

17 symbols | 3 files | Cohesion: 79%

## When to Use

- Working with code in `backend/`
- Understanding how getMetrics, uniqueKey, sliding_window_allows_within_limit work
- Modifying ratelimiting-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | uniqueKey, sliding_window_allows_within_limit, sliding_window_blocks_exceeding_limit, token_bucket_allows_bursts, multiple_limits_all_must_pass (+4) |
| `backend/tests/Feature/RateLimiting/AdvancedRateLimitServiceTest.php` | test_metrics_track_requests, uniqueKey, test_sliding_window_allows_within_limit, test_token_bucket_allows_bursts, test_multiple_limits_all_must_pass (+1) |
| `backend/app/Services/RateLimitService.php` | getMetrics, reset |

## Entry Points

Start here when exploring this area:

- **`getMetrics`** (Method) — `backend/app/Services/RateLimitService.php:392`
- **`uniqueKey`** (Method) — `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php:31`
- **`sliding_window_allows_within_limit`** (Method) — `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php:36`
- **`sliding_window_blocks_exceeding_limit`** (Method) — `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php:54`
- **`token_bucket_allows_bursts`** (Method) — `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php:78`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `getMetrics` | Method | `backend/app/Services/RateLimitService.php` | 392 |
| `uniqueKey` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 31 |
| `sliding_window_allows_within_limit` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 36 |
| `sliding_window_blocks_exceeding_limit` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 54 |
| `token_bucket_allows_bursts` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 78 |
| `multiple_limits_all_must_pass` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 101 |
| `metrics_track_requests` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 183 |
| `composite_key_building` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 209 |
| `degradation_to_memory_fallback` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 237 |
| `test_metrics_track_requests` | Method | `backend/tests/Feature/RateLimiting/AdvancedRateLimitServiceTest.php` | 157 |
| `reset` | Method | `backend/app/Services/RateLimitService.php` | 338 |
| `reset_clears_limit` | Method | `backend/tests/Unit/RateLimiting/AdvancedRateLimitServiceTest.php` | 131 |
| `uniqueKey` | Method | `backend/tests/Feature/RateLimiting/AdvancedRateLimitServiceTest.php` | 27 |
| `test_sliding_window_allows_within_limit` | Method | `backend/tests/Feature/RateLimiting/AdvancedRateLimitServiceTest.php` | 32 |
| `test_token_bucket_allows_bursts` | Method | `backend/tests/Feature/RateLimiting/AdvancedRateLimitServiceTest.php` | 58 |
| `test_multiple_limits_all_must_pass` | Method | `backend/tests/Feature/RateLimiting/AdvancedRateLimitServiceTest.php` | 82 |
| `test_reset_clears_limit` | Method | `backend/tests/Feature/RateLimiting/AdvancedRateLimitServiceTest.php` | 110 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Services | 2 calls |

## How to Explore

1. `gitnexus_context({name: "getMetrics"})` — see callers and callees
2. `gitnexus_query({query: "ratelimiting"})` — find related execution flows
3. Read key files listed above for implementation details
