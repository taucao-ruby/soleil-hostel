---
name: middleware
description: "Skill for the Middleware area of soleil-hostel. 22 symbols across 6 files."
---

# Middleware

22 symbols | 6 files | Cohesion: 100%

## When to Use

- Working with code in `backend/`
- Understanding how RequestThrottled, handle, parseLimits work
- Modifying middleware-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php` | handle, parseLimits, adjustForUserTier, buildKey, getLimitValue |
| `backend/app/Http/Middleware/ThrottleApiRequests.php` | handle, resolveRequestKey, resolveMaxAttempts, resolveLimitingPeriod, buildException |
| `backend/app/Http/Middleware/SecurityHeaders.php` | handle, applySecurityHeaders, buildPermissionsPolicy, buildCspDevelopment, buildCspProduction |
| `backend/app/Http/Middleware/LogPerformance.php` | handle, logPerformanceMetrics, determineLogLevel |
| `backend/app/Http/Middleware/AddCorrelationId.php` | handle, generateCorrelationId, generateRequestId |
| `backend/app/Events/RequestThrottled.php` | RequestThrottled |

## Entry Points

Start here when exploring this area:

- **`RequestThrottled`** (Class) — `backend/app/Events/RequestThrottled.php:13`
- **`handle`** (Method) — `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php:34`
- **`parseLimits`** (Method) — `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php:101`
- **`adjustForUserTier`** (Method) — `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php:141`
- **`buildKey`** (Method) — `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php:167`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `RequestThrottled` | Class | `backend/app/Events/RequestThrottled.php` | 13 |
| `handle` | Method | `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php` | 34 |
| `parseLimits` | Method | `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php` | 101 |
| `adjustForUserTier` | Method | `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php` | 141 |
| `buildKey` | Method | `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php` | 167 |
| `getLimitValue` | Method | `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php` | 200 |
| `handle` | Method | `backend/app/Http/Middleware/ThrottleApiRequests.php` | 28 |
| `resolveRequestKey` | Method | `backend/app/Http/Middleware/ThrottleApiRequests.php` | 52 |
| `resolveMaxAttempts` | Method | `backend/app/Http/Middleware/ThrottleApiRequests.php` | 60 |
| `resolveLimitingPeriod` | Method | `backend/app/Http/Middleware/ThrottleApiRequests.php` | 73 |
| `buildException` | Method | `backend/app/Http/Middleware/ThrottleApiRequests.php` | 92 |
| `handle` | Method | `backend/app/Http/Middleware/SecurityHeaders.php` | 32 |
| `applySecurityHeaders` | Method | `backend/app/Http/Middleware/SecurityHeaders.php` | 47 |
| `buildPermissionsPolicy` | Method | `backend/app/Http/Middleware/SecurityHeaders.php` | 153 |
| `buildCspDevelopment` | Method | `backend/app/Http/Middleware/SecurityHeaders.php` | 182 |
| `buildCspProduction` | Method | `backend/app/Http/Middleware/SecurityHeaders.php` | 229 |
| `handle` | Method | `backend/app/Http/Middleware/LogPerformance.php` | 30 |
| `logPerformanceMetrics` | Method | `backend/app/Http/Middleware/LogPerformance.php` | 45 |
| `determineLogLevel` | Method | `backend/app/Http/Middleware/LogPerformance.php` | 92 |
| `handle` | Method | `backend/app/Http/Middleware/AddCorrelationId.php` | 26 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `Handle → AdjustForUserTier` | intra_community | 3 |
| `Handle → BuildPermissionsPolicy` | intra_community | 3 |
| `Handle → BuildCspDevelopment` | intra_community | 3 |
| `Handle → BuildCspProduction` | intra_community | 3 |
| `Handle → DetermineLogLevel` | intra_community | 3 |

## How to Explore

1. `gitnexus_context({name: "RequestThrottled"})` — see callers and callees
2. `gitnexus_query({query: "middleware"})` — find related execution flows
3. Read key files listed above for implementation details
