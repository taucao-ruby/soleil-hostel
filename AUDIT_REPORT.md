# Soleil Hostel — Full Project Audit Report (v2)

**Audit Date:** February 10, 2026  
**Previous Audit:** February 9, 2026 (v1 — 61 issues, 54 fixed)  
**Auditor:** AI Code Review Agent  
**Branch:** `dev`  
**Scope:** Full-stack review — Backend (Laravel 11), Frontend (React 19 + TypeScript), DevOps, Security, Tests, Documentation

---

## Executive Summary

This is the second comprehensive audit of Soleil Hostel, building on the v1 audit (Feb 9, 2026) which found 61 issues and resolved 54. This audit performs a fresh full-stack review and identifies **98 total issues** across backend, frontend, DevOps, security, testing, and documentation.

The project has strong foundations — service/repository pattern, 698+ backend tests, enum-based RBAC, comprehensive middleware pipeline. However, several **critical issues** remain that would cause production failures if deployed without fixes.

| Severity     | Count  | Description                                       |
| ------------ | ------ | ------------------------------------------------- |
| **CRITICAL** | 6      | Will break in production or create security holes |
| **HIGH**     | 20     | Significant bugs, security risks, or dead code    |
| **MEDIUM**   | 43     | Code quality, maintainability, inconsistencies    |
| **LOW**      | 29     | Minor improvements, cleanup, best practices       |
| **Total**    | **98** | Across backend, frontend, DevOps, security, docs  |

### Top 10 Must-Fix Before Production

| #   | Issue                                                                                                                          | Severity | ID         |
| --- | ------------------------------------------------------------------------------------------------------------------------------ | -------- | ---------- |
| 1   | **Cookie lifetime bug** — `HttpOnlyTokenController` divides minutes by 60, causing sessions to expire far sooner than intended | CRITICAL | BE-NEW-01  |
| 2   | **Revoked tokens work on unified auth endpoints** — `auth:sanctum` middleware doesn't check `revoked_at`                       | CRITICAL | SEC-NEW-01 |
| 3   | **APP_KEY regenerated on every Docker start** — `key:generate --force` invalidates all encrypted data                          | CRITICAL | DV-NEW-01  |
| 4   | **CI tests run MySQL but production uses PostgreSQL** — PostgreSQL-specific features untested                                  | CRITICAL | DV-NEW-02  |
| 5   | **Hardcoded Redis password in version control** — `redis.conf` committed with plaintext password                               | CRITICAL | SEC-NEW-02 |
| 6   | **Hardcoded Redis password in Docker healthcheck** — leaks via `docker inspect`                                                | CRITICAL | DV-NEW-03  |
| 7   | **AdvancedRateLimitMiddleware runs business logic BEFORE throttle check** — rate limiting doesn't prevent action               | HIGH     | BE-NEW-02  |
| 8   | **`cancellation_reason` column queried but never created** — will throw column-not-found error                                 | HIGH     | BE-NEW-03  |
| 9   | **Legacy token creation has no expiration** — infinite-lived tokens contradict security model                                  | HIGH     | SEC-NEW-03 |
| 10  | **Missing phpredis extension in production Dockerfile** — Redis won't work in built image                                      | HIGH     | DV-NEW-04  |

---

## Table of Contents

- [Soleil Hostel — Full Project Audit Report (v2)](#soleil-hostel--full-project-audit-report-v2)
  - [Executive Summary](#executive-summary)
    - [Top 10 Must-Fix Before Production](#top-10-must-fix-before-production)
  - [Table of Contents](#table-of-contents)
  - [1. Backend Issues](#1-backend-issues)
    - [1.1 Models](#11-models)
    - [1.2 Controllers](#12-controllers)
    - [1.3 Services](#13-services)
    - [1.4 Middleware](#14-middleware)
    - [1.5 Routes](#15-routes)
    - [1.6 Configuration](#16-configuration)
    - [1.7 Migrations](#17-migrations)
  - [2. Frontend Issues](#2-frontend-issues)
    - [2.1 API Layer \& Security](#21-api-layer--security)
    - [2.2 Features \& Components](#22-features--components)
    - [2.3 Types \& Dead Code](#23-types--dead-code)
    - [2.4 Dependencies \& Config](#24-dependencies--config)
  - [3. DevOps \& Deployment Issues](#3-devops--deployment-issues)
    - [3.1 Docker](#31-docker)
    - [3.2 Redis](#32-redis)
    - [3.3 CI/CD Pipelines](#33-cicd-pipelines)
    - [3.4 Deploy Scripts](#34-deploy-scripts)
  - [4. Security Issues](#4-security-issues)
  - [5. Testing Gaps](#5-testing-gaps)
  - [6. Documentation Issues](#6-documentation-issues)
  - [7. Action Plan](#7-action-plan)
    - [Phase 1: Critical Fixes (Before Any Deployment) 🔴](#phase-1-critical-fixes-before-any-deployment-)
    - [Phase 2: High-Priority Fixes (Within 1 Sprint) 🟠](#phase-2-high-priority-fixes-within-1-sprint-)
    - [Phase 3: Medium-Priority Improvements 🟡](#phase-3-medium-priority-improvements-)
    - [Phase 4: Low-Priority Cleanup 🟢](#phase-4-low-priority-cleanup-)
  - [Appendix A: Comparison with v1 Audit](#appendix-a-comparison-with-v1-audit)
  - [Appendix B: Statistics](#appendix-b-statistics)

---

## 1. Backend Issues

### 1.1 Models

| ID        | Severity     | Issue                                                                                                                                                                                                                                                                                                      | Location                                   | Recommended Fix                                                |
| --------- | ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ | -------------------------------------------------------------- |
| BE-NEW-01 | **CRITICAL** | **Cookie lifetime calculation bug**: `HttpOnlyTokenController::login()` does `ceil($expiresInMinutes / 60)` then passes result as `minutes` to `cookie()`. A 60-min token → 1-min cookie; a 30-day token → 12-hour cookie. All httpOnly sessions expire far sooner than intended. Same bug in `refresh()`. | `Auth/HttpOnlyTokenController.php:135,211` | Remove the `/ 60` division — pass `$expiresInMinutes` directly |
| BE-NEW-03 | **HIGH**     | **`cancellation_reason` column queried but missing from schema**: `BookingService` selects this column in 4 places, but no migration creates it. Strict DB mode will throw column-not-found.                                                                                                               | `BookingService.php:130,144,168,178`       | Create migration to add column, or remove from SELECT          |
| BE-NEW-04 | **HIGH**     | **`PersonalAccessToken.$fillable` missing critical columns**: `token_identifier`, `token_hash`, `device_fingerprint`, `last_rotated_at` exist in migrations but NOT in `$fillable`. Mass-assignment silently fails.                                                                                        | `Models/PersonalAccessToken.php`           | Add missing columns to `$fillable`                             |
| BE-NEW-05 | **HIGH**     | **`User::createToken()` override creates tokens without expiration**: Custom raw-SQL method doesn't set `expires_at`, producing infinite-lived tokens.                                                                                                                                                     | `Models/User.php:158`                      | Set `expires_at` or delegate to standardized token creation    |
| BE-NEW-06 | **MEDIUM**   | **`Booking::scopeByStatus` accepts `string` instead of `BookingStatus` enum**: Bypasses type safety since `status` is cast to enum.                                                                                                                                                                        | `Models/Booking.php:303`                   | Accept `BookingStatus` param                                   |
| BE-NEW-07 | **MEDIUM**   | **`Booking::selectColumns()` omits payment/refund/cancellation fields**: `amount`, `payment_intent_id`, `refund_*`, `cancelled_at`, `cancelled_by` silently null when scope used.                                                                                                                          | `Models/Booking.php:221`                   | Add missing columns to scope                                   |
| BE-NEW-08 | **MEDIUM**   | **`ContactMessage` model lacks XSS sanitization on `name`/`subject`**: Does not use `Purifiable` trait. Only `message` is purified in controller.                                                                                                                                                          | `Models/ContactMessage.php`                | Apply `Purifiable` trait or purify in FormRequest              |
| BE-NEW-09 | **MEDIUM**   | **`Room` status inconsistency**: `scopeActive` checks `status = 'active'` but `scopeAvailableBetween` checks `status = 'available'`. Different scopes filter for different statuses.                                                                                                                       | `Models/Room.php:157,175`                  | Standardize status values                                      |
| BE-NEW-10 | **MEDIUM**   | **`Review.approved` defaults to `true` and is not in `$fillable`**: All reviews auto-approved with no way to moderate.                                                                                                                                                                                     | `Models/Review.php`                        | Add to `$fillable` if moderation needed                        |
| BE-NEW-11 | **LOW**      | **Redundant scope wrappers**: `scopeOnlyTrashed`, `scopeWithTrashed`, `isTrashed()` duplicate `SoftDeletes` trait methods.                                                                                                                                                                                 | `Models/Booking.php:395-409`               | Remove redundant methods                                       |
| BE-NEW-12 | **LOW**      | **`Location.lock_version` in model but never used**: Cast and accessor exist but no controller/service uses it for Location updates.                                                                                                                                                                       | `Models/Location.php`                      | Remove or implement                                            |
| BE-NEW-13 | **LOW**      | **`Location::selectColumns` selects ALL columns**: Equivalent to `SELECT *`, no optimization benefit.                                                                                                                                                                                                      | `Models/Location.php:112`                  | Remove or narrow scope                                         |

### 1.2 Controllers

| ID        | Severity   | Issue                                                                                                                                                                                                                                  | Location                                                | Recommended Fix                         |
| --------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- | --------------------------------------- |
| BE-NEW-14 | **HIGH**   | **Three separate auth controller classes with overlapping responsibilities**: `AuthController` (legacy/infinite tokens), `Auth\AuthController` (bearer v2), `HttpOnlyTokenController` (cookie). Creates inconsistent security posture. | Multiple files                                          | Consolidate or clearly deprecate legacy |
| BE-NEW-15 | **HIGH**   | **Race condition in bearer auth refresh**: `incrementRefreshCount()` runs BEFORE threshold check with `>` not `>=`. Counter goes over threshold before triggering.                                                                     | `Auth/AuthController.php:200`                           | Check threshold BEFORE incrementing     |
| BE-NEW-16 | **MEDIUM** | **Duplicate `CspViolationReportController`**: Root version is used in routes; `Security/` version is dead code.                                                                                                                        | `Controllers/Security/CspViolationReportController.php` | Delete dead code version                |
| BE-NEW-17 | **MEDIUM** | **`Api\BookingCancellationController` is dead code**: No route points to it. Cancellation route uses `BookingController::cancel`.                                                                                                      | `Controllers/Api/BookingCancellationController.php`     | Delete unused controller                |
| BE-NEW-18 | **MEDIUM** | **`BookingController` uses raw `response()->json()` while `AdminBookingController` uses `ApiResponse` trait**: Inconsistent response envelope.                                                                                         | `Controllers/BookingController.php`                     | Apply `ApiResponse` trait consistently  |
| BE-NEW-19 | **MEDIUM** | **`ContactController::store()` logs PII (email)**: `...$validated` spread into log context includes email and potentially GDPR-sensitive data.                                                                                         | `Controllers/ContactController.php:48`                  | Mask email in log context               |
| BE-NEW-20 | **MEDIUM** | **`HealthController` detailed endpoint exposes server info unauthenticated**: `app.debug`, `app.env`, PHP version, Laravel version visible to anyone.                                                                                  | `Controllers/HealthController.php:213`                  | Add auth or IP restriction              |
| BE-NEW-21 | **MEDIUM** | **`Auth\AuthController` doesn't extend base `Controller`**: `$this->authorize()`, `$this->middleware()` unavailable.                                                                                                                   | `Auth/AuthController.php:33`                            | Extend base Controller                  |
| BE-NEW-22 | **MEDIUM** | **`HttpOnlyTokenController` CSRF token is random string, not session-bound**: `Str::random(64)` returned but no server-side verification. Provides no actual CSRF protection.                                                          | `Auth/HttpOnlyTokenController.php:120`                  | Use Laravel's session-bound CSRF        |
| BE-NEW-23 | **MEDIUM** | **`HttpOnlyTokenController` mixed-language error messages**: Vietnamese and Indonesian strings mixed.                                                                                                                                  | `Auth/HttpOnlyTokenController.php`                      | Use Laravel localization                |
| BE-NEW-24 | **LOW**    | **`RoomController` uses raw `$id` instead of route model binding**: Inconsistent with BookingController which uses `Booking $booking`.                                                                                                 | `Controllers/RoomController.php`                        | Use route model binding                 |
| BE-NEW-25 | **LOW**    | **Un-imported facades in BookingController**: `\Log::error` and `\App\Events\BookingCreated` used without imports.                                                                                                                     | `Controllers/BookingController.php:103`                 | Add proper `use` imports                |

### 1.3 Services

| ID        | Severity   | Issue                                                                                                                                                                            | Location                              | Recommended Fix                                     |
| --------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- | --------------------------------------------------- |
| BE-NEW-26 | **HIGH**   | **`CancellationService` imports `Laravel\Cashier\Exceptions\IncompletePayment`**: Cashier may not be installed — will fail at class-load time.                                   | `Services/CancellationService.php:16` | Add conditional import or remove                    |
| BE-NEW-27 | **MEDIUM** | **Heavy code duplication in `BookingService` cache methods**: Same SELECT column list repeated 4 times; tag-support branching duplicated.                                        | `Services/BookingService.php`         | Extract to method                                   |
| BE-NEW-28 | **MEDIUM** | **`CreateBookingService::validateDates()` blocks updates on started bookings**: Rejects past check-in dates, making it impossible to update any field on an in-progress booking. | `Services/CreateBookingService.php`   | Skip date validation on updates for non-date fields |
| BE-NEW-29 | **MEDIUM** | **`RateLimitService` (429 lines) may be entirely dead code**: Routes use Laravel's `throttle` middleware, not the custom `rate-limit:` middleware.                               | `Services/RateLimitService.php`       | Verify usage or remove                              |
| BE-NEW-30 | **LOW**    | **`BookingService::cancelBooking()` appears unused**: `BookingController::cancel()` delegates to `CancellationService::cancel()` directly.                                       | `Services/BookingService.php`         | Remove or deprecate                                 |
| BE-NEW-31 | **LOW**    | **Vietnamese error messages hardcoded in service layer**: Should use localization.                                                                                               | Multiple services                     | Use `__()` helper                                   |

### 1.4 Middleware

| ID        | Severity   | Issue                                                                                                                                                                                                       | Location                                                 | Recommended Fix                                 |
| --------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------- | ----------------------------------------------- |
| BE-NEW-02 | **HIGH**   | **`AdvancedRateLimitMiddleware` runs business logic BEFORE throttle check**: `$response = $next($request)` executes full controller, THEN checks throttle at line 70. Rate limiting doesn't prevent action. | `Middleware/AdvancedRateLimitMiddleware.php:59-70`       | Check throttle BEFORE calling `$next($request)` |
| BE-NEW-32 | **HIGH**   | **`CheckTokenNotRevokedAndNotExpired` authenticates user BEFORE checking expiration**: Expired token briefly authenticates user at line 60, checked at line 75.                                             | `Middleware/CheckTokenNotRevokedAndNotExpired.php:60-75` | Check expiration/revocation first               |
| BE-NEW-33 | **MEDIUM** | **`CheckHttpOnlyTokenValid` updates `last_used_at` on EVERY request**: Unlike `CheckTokenNotRevokedAndNotExpired` which throttles to 1-minute intervals. Higher DB load.                                    | `Middleware/CheckHttpOnlyTokenValid.php:115`             | Add 1-minute throttle                           |
| BE-NEW-34 | **MEDIUM** | **Custom CORS middleware duplicates Laravel's built-in CORS**: Both exist, potentially causing double headers.                                                                                              | `Middleware/Cors.php` + `config/cors.php`                | Use one or the other                            |
| BE-NEW-35 | **MEDIUM** | **`AdvancedRateLimitMiddleware` references nonexistent `subscription_tier` on User**: Always defaults to `'free'`.                                                                                          | `Middleware/AdvancedRateLimitMiddleware.php:153`         | Remove or implement attribute                   |
| BE-NEW-36 | **MEDIUM** | **`VerifyBookingOwnership` middleware is dead code**: Not registered in `bootstrap/app.php`, not used in routes.                                                                                            | `Middleware/VerifyBookingOwnership.php`                  | Delete or register                              |
| BE-NEW-37 | **MEDIUM** | **`SecurityHeaders` sets `Cross-Origin-Embedder-Policy: require-corp`**: Blocks all cross-origin resources (CDN images, payment iframes, analytics).                                                        | `Middleware/SecurityHeaders.php`                         | Use `credentialless` or `unsafe-none`           |
| BE-NEW-38 | **LOW**    | **CORS middleware fallback to `$allowedOrigins[0]` leaks valid origin**: Should return no CORS header when origin doesn't match.                                                                            | `Middleware/Cors.php`                                    | Return no header on mismatch                    |

### 1.5 Routes

| ID         | Severity     | Issue                                                                                                                                                                                                         | Location                | Recommended Fix                           |
| ---------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- | ----------------------------------------- |
| SEC-NEW-01 | **CRITICAL** | **Unified auth routes use `auth:sanctum` which doesn't check `revoked_at`**: Revoked tokens can access `/api/auth/unified/me`, `logout`, `logout-all`. Only `check_token_valid` middleware checks revocation. | `routes/api.php:156`    | Add `check_token_valid` middleware        |
| BE-NEW-39  | **HIGH**     | **`/health/detailed` and `/health/full` leak server info unauthenticated**: Environment, debug, PHP/Laravel version exposed.                                                                                  | `routes/api.php`        | Add auth or IP restriction                |
| BE-NEW-40  | **MEDIUM**   | **No Review API routes exist**: Review model, ReviewPolicy all exist, but zero API endpoints for reviews.                                                                                                     | `routes/api.php`        | Add routes or document as unimplemented   |
| BE-NEW-41  | **MEDIUM**   | **Legacy routes duplicate v1 routes identically**: Double route registration, wasted route table space.                                                                                                       | `routes/api/legacy.php` | Remove legacy duplicates or differentiate |
| BE-NEW-42  | **MEDIUM**   | **`/api/auth/csrf-token` returns random string not stored server-side**: No actual CSRF protection.                                                                                                           | `routes/api.php:88`     | Use session-bound CSRF                    |
| BE-NEW-43  | **LOW**      | **Redundant `/api/ping` health route**: Three other health endpoints exist.                                                                                                                                   | `routes/api.php`        | Remove or consolidate                     |

### 1.6 Configuration

| ID        | Severity   | Issue                                                                                                                                                             | Location                | Recommended Fix               |
| --------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- | ----------------------------- |
| BE-NEW-44 | **HIGH**   | **`config/sanctum.php` sets `expiration => null`**: Native token expiration disabled. Code using `auth:sanctum` directly (unified routes) won't check expiration. | `config/sanctum.php:63` | Set a default expiration      |
| BE-NEW-45 | **MEDIUM** | **`config/cors.php` has `max_age => 0`**: Preflight never cached, doubling network overhead per cross-origin request.                                             | `config/cors.php`       | Set to `86400` for production |
| BE-NEW-46 | **LOW**    | **`config/session.php` uses `database` driver for stateless API**: Sessions may be unused overhead.                                                               | `config/session.php`    | Evaluate necessity            |

### 1.7 Migrations

| ID        | Severity   | Issue                                                                                                                                                                     | Location                         | Recommended Fix         |
| --------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- | ----------------------- |
| BE-NEW-47 | **HIGH**   | **`cancellation_reason` column never created**: Referenced in BookingService but no migration adds it.                                                                    | `database/migrations/`           | Create migration        |
| BE-NEW-48 | **MEDIUM** | **`personal_access_tokens` `HasUuids` trait vs `insertGetId()` mismatch**: Model uses `HasUuids` but token creation uses `insertGetId()` which returns auto-increment ID. | `Models/PersonalAccessToken.php` | Verify primary key type |
| BE-NEW-49 | **LOW**    | **28+ migrations without squashing**: Incremental add/modify/drop migrations slow testing.                                                                                | `database/migrations/`           | Consider squashing      |

---

## 2. Frontend Issues

### 2.1 API Layer & Security

| ID        | Severity   | Issue                                                                                                                                              | Location                                                                                                 | Recommended Fix                             |
| --------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- | ------------------------------------------- | ----------------- |
| FE-NEW-01 | **HIGH**   | **Token refresh race condition**: Multiple simultaneous 401s each independently call `/auth/refresh-httponly`. No retry queue.                     | `src/shared/lib/api.ts:83-134`                                                                           | Implement token refresh mutex/queue         |
| FE-NEW-02 | **MEDIUM** | **Public route regex too loose**: `url?.match(/\/(rooms                                                                                            | $)/)` — `$`in character class matches literal`$`, not end-of-string. Only `/rooms` recognized as public. | `src/shared/lib/api.ts:128`                 | Fix regex pattern |
| FE-NEW-03 | **MEDIUM** | **`sanitizeInput()` HTML-encodes data BEFORE sending to API**: User named `O'Brien` stored as `O&#039;Brien`. Should escape on display, not input. | `src/features/auth/RegisterPage.tsx:86-87`                                                               | Remove `sanitizeInput` from API submissions |
| FE-NEW-04 | **MEDIUM** | **CSRF token stored in `sessionStorage`**: XSS can read sessionStorage (unlike httpOnly cookies).                                                  | `src/shared/lib/api.ts:48-55`                                                                            | Consider cookie-based CSRF                  |
| FE-NEW-05 | **LOW**    | **Hardcoded fallback base URL `http://localhost:8000/api`**: In production if env missing, all calls fail silently.                                | `src/shared/lib/api.ts:18`                                                                               | Add production guard                        |

### 2.2 Features & Components

| ID        | Severity   | Issue                                                                                                                                                    | Location                                     | Recommended Fix                      |
| --------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- | ------------------------------------ |
| FE-NEW-06 | **HIGH**   | **Duplicate `User` interface in 3 places with divergent schemas**: `AuthContext.tsx`, `auth.api.ts`, `types/api.ts` each define different `User` shapes. | Multiple files                               | Consolidate to single canonical type |
| FE-NEW-07 | **MEDIUM** | **Login min password 6 chars, Register min 8 chars**: Inconsistent validation.                                                                           | `LoginPage.tsx:46`, `RegisterPage.tsx:56`    | Align validation rules               |
| FE-NEW-08 | **MEDIUM** | **BookingForm ignores URL query parameters**: `LocationDetail` navigates to `/booking?room_id=X` but form starts blank.                                  | `src/features/booking/BookingForm.tsx:34-42` | Read and apply query params          |
| FE-NEW-09 | **MEDIUM** | **"Book Now" button on RoomList has no `onClick` handler**: Dead button, no navigation to booking.                                                       | `src/features/rooms/RoomList.tsx:195-197`    | Add navigation handler               |
| FE-NEW-10 | **MEDIUM** | **`Providers` component is dead code**: No-op passthrough, never imported.                                                                               | `src/app/providers.tsx`                      | Delete or use                        |
| FE-NEW-11 | **MEDIUM** | **`ToastContainer` never rendered**: CSS imported but container never mounted. Toasts silently fail.                                                     | `src/utils/toast.ts:97`, `src/main.tsx:4`    | Render container or remove           |
| FE-NEW-12 | **LOW**    | **`ProtectedRoute` doesn't preserve URL for redirect-after-login**: Always redirects to `/dashboard`.                                                    | `src/features/auth/ProtectedRoute.tsx:41`    | Pass `from` location                 |
| FE-NEW-13 | **LOW**    | **`NotFoundPage` uses `<a href="/">` instead of `<Link>`**: Full page reload.                                                                            | `src/pages/NotFoundPage.tsx:7`               | Use React Router `<Link>`            |
| FE-NEW-14 | **LOW**    | **Inline skeleton loaders duplicated**: Both `RoomList` and `LocationList` define identical `SkeletonCard` instead of using shared component.            | Multiple files                               | Use shared `SkeletonCard`            |
| FE-NEW-15 | **LOW**    | **`calculateNights` uses `Math.abs` masking incorrect date order**: Check-out < check-in returns positive, hiding bugs.                                  | `booking.validation.ts:99-103`               | Remove `Math.abs`, validate order    |
| FE-NEW-16 | **LOW**    | **Shared UI components (`Input`, `Label`) unused in actual forms**: All forms use raw `<input>`.                                                         | Multiple forms                               | Use design system components         |

### 2.3 Types & Dead Code

| ID        | Severity   | Issue                                                                                                                                                                          | Location                        | Recommended Fix                    |
| --------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------- | ---------------------------------- |
| FE-NEW-17 | **HIGH**   | **Zod schemas in `types/api.ts` completely unused (~160 lines)**: No runtime type validation of API responses anywhere.                                                        | `src/types/api.ts`              | Use schemas in API layer or remove |
| FE-NEW-18 | **MEDIUM** | **`auth.api.ts` entirely dead code (~90 lines)**: `AuthContext` reimplements all auth logic inline.                                                                            | `src/features/auth/auth.api.ts` | Delete or use                      |
| FE-NEW-19 | **MEDIUM** | **`Booking` type field mismatch**: `booking.types.ts` uses `number_of_guests`, `types/api.ts` uses `guests`.                                                                   | Multiple type files             | Standardize field names            |
| FE-NEW-20 | **MEDIUM** | **`Room` status enum inconsistency**: `location.types.ts` uses `'occupied'`, `room.types.ts` uses `'booked'` for same concept.                                                 | Type files                      | Standardize status values          |
| FE-NEW-21 | **LOW**    | **Dead code functions**: `getMyBookings`, `getBookingById`, `cancelBooking` (booking.api.ts), `getRoomById` (room.api.ts), `checkAvailability` (location.api.ts) never called. | Feature API files               | Remove or document as future       |

### 2.4 Dependencies & Config

| ID        | Severity   | Issue                                                                                                                                           | Location                      | Recommended Fix                                          |
| --------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------- | -------------------------------------------------------- |
| FE-NEW-22 | **MEDIUM** | **Unused dependency: `react-datepicker` + `@types/react-datepicker`**: Booking form uses native `<input type="date">`.                          | `package.json`                | `npm uninstall react-datepicker @types/react-datepicker` |
| FE-NEW-23 | **MEDIUM** | **Unused dependency: `framer-motion`**: No imports anywhere. ~30KB gzipped dead weight.                                                         | `package.json`                | `npm uninstall framer-motion`                            |
| FE-NEW-24 | **MEDIUM** | **ESLint config missing `eslint-config-prettier` integration**: Despite being in devDependencies, not configured. ESLint/Prettier may conflict. | `eslint.config.js`            | Add prettier config                                      |
| FE-NEW-25 | **LOW**    | **No `test` script in `package.json`**: Must manually run `npx vitest`.                                                                         | `package.json`                | Add `"test": "vitest run"`                               |
| FE-NEW-26 | **LOW**    | **Vite proxy targets `http://backend:8000`**: Docker hostname; won't resolve in local dev outside Docker.                                       | `vite.config.ts:38`           | Use env variable or localhost default                    |
| FE-NEW-27 | **LOW**    | **CSP nonce plugin uses `{{ csp_nonce() }}` Blade directive**: Only works when served through Laravel. Independent frontend deploy would break. | `vite-plugin-csp-nonce.js:26` | Document requirement or add fallback                     |

---

## 3. DevOps & Deployment Issues

### 3.1 Docker

| ID        | Severity     | Issue                                                                                                                                                                       | Location                   | Recommended Fix                                          |
| --------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- | -------------------------------------------------------- |
| DV-NEW-01 | **CRITICAL** | **`php artisan key:generate --force` runs on every container start**: Regenerates APP_KEY each startup, invalidating ALL encrypted data, sessions, and tokens.              | `docker-compose.yml:79`    | Only run if key doesn't exist                            |
| DV-NEW-03 | **CRITICAL** | **Hardcoded Redis password in healthcheck command**: Visible via `docker inspect`, process lists, CI logs.                                                                  | `docker-compose.yml:43`    | Use environment variable                                 |
| DV-NEW-04 | **HIGH**     | **Missing phpredis extension in production Dockerfile**: CI installs `redis` extension but Dockerfile only installs `pdo pdo_pgsql zip opcache`. Redis won't work.          | `backend/Dockerfile`       | Add `pecl install redis`                                 |
| DV-NEW-05 | **HIGH**     | **Backend Dockerfile uses `php artisan serve`**: Single-threaded dev server, not suitable for production.                                                                   | `backend/Dockerfile:24`    | Use PHP-FPM or Octane                                    |
| DV-NEW-06 | **HIGH**     | **`redis.conf` `bind 127.0.0.1` blocks Docker inter-container access**: Backend container can't reach Redis.                                                                | `redis.conf:10`            | Use `bind 0.0.0.0` in Docker (rely on network isolation) |
| DV-NEW-07 | **MEDIUM**   | **Backend/frontend ports not bound to localhost**: Backend `0.0.0.0:8000`, frontend `0.0.0.0:5173` exposed to network. DB/Redis correctly use `127.0.0.1`.                  | `docker-compose.yml:55,86` | Bind to `127.0.0.1`                                      |
| DV-NEW-08 | **MEDIUM**   | **`composer install` and `npm install` run at startup despite multi-stage build**: Bind-mount volumes overwrite built `node_modules`/`vendor`, negating build optimization. | `docker-compose.yml:79,90` | Use named volumes or remove runtime install              |
| DV-NEW-09 | **MEDIUM**   | **`php artisan migrate --force` runs on every startup**: Risky in production-like environments.                                                                             | `docker-compose.yml:79`    | Make migration a deliberate step                         |
| DV-NEW-10 | **MEDIUM**   | **No resource limits (memory/CPU)** on any service.                                                                                                                         | `docker-compose.yml`       | Add `deploy.resources.limits`                            |
| DV-NEW-11 | **LOW**      | **`version: "3.8"` deprecated**: Docker Compose v2+ ignores it.                                                                                                             | `docker-compose.yml:1`     | Remove version field                                     |
| DV-NEW-12 | **LOW**      | **No HEALTHCHECK in backend Dockerfile**: Orchestrators can't detect unhealthy containers.                                                                                  | `backend/Dockerfile`       | Add HEALTHCHECK                                          |

### 3.2 Redis

| ID         | Severity     | Issue                                                                                                                    | Location        | Recommended Fix                               |
| ---------- | ------------ | ------------------------------------------------------------------------------------------------------------------------ | --------------- | --------------------------------------------- |
| SEC-NEW-02 | **CRITICAL** | **Hardcoded password `soleil_redis_secret_2026` committed to VCS**: `redis.conf` tracked in git with plaintext password. | `redis.conf:58` | Externalize via env variable / Docker secrets |
| DV-NEW-13  | **MEDIUM**   | **No `rename-command` for dangerous operations**: `FLUSHALL`, `FLUSHDB`, `DEBUG`, `CONFIG` not restricted in production. | `redis.conf`    | Rename or disable dangerous commands          |

### 3.3 CI/CD Pipelines

| ID        | Severity     | Issue                                                                                                                                                        | Location                           | Recommended Fix                |
| --------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------- | ------------------------------ |
| DV-NEW-02 | **CRITICAL** | **CI uses MySQL but production uses PostgreSQL**: PostgreSQL ENUM types, exclusion constraints, `SELECT FOR UPDATE` behavior untested.                       | `.github/workflows/tests.yml`      | Switch CI to PostgreSQL        |
| DV-NEW-14 | **HIGH**     | **Gitleaks only scans `backend/` directory**: Secrets in `frontend/`, `redis.conf`, `docker-compose.yml` not scanned. `redis.conf` HAS a hardcoded password. | `.github/workflows/tests.yml`      | Scan entire repo               |
| DV-NEW-15 | **HIGH**     | **Production URL typo `solelhotel.com`**: Missing 'i' — should be `soleilhotel.com`. Appears in health checks and environment URL.                           | `.github/workflows/deploy.yml`     | Fix to `soleilhotel.com`       |
| DV-NEW-16 | **HIGH**     | **Migrations triggered via HTTP POST endpoint**: `/api/migrations/run` — massive security risk if exposed.                                                   | `.github/workflows/deploy.yml:387` | Remove HTTP migration endpoint |
| DV-NEW-17 | **MEDIUM**   | **N+1 report step always outputs "success"**: Hardcoded `echo` statements, not conditional on test results.                                                  | `.github/workflows/tests.yml:~270` | Make conditional               |
| DV-NEW-18 | **MEDIUM**   | **pnpm in CI but npm in Docker**: CI uses `pnpm`, `docker-compose.yml` uses `npm install`. Lockfile inconsistency.                                           | CI vs docker-compose               | Standardize package manager    |
| DV-NEW-19 | **LOW**      | **Outdated GitHub Action versions**: `docker/login-action@v2`, `codecov/codecov-action@v3` should be v3/v4+.                                                 | Workflow files                     | Update action versions         |

### 3.4 Deploy Scripts

| ID        | Severity   | Issue                                                                                                                                                | Location                       | Recommended Fix                          |
| --------- | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ | ---------------------------------------- |
| DV-NEW-20 | **HIGH**   | **`deploy-forge.sh` has `solelhotel.com` typo**: Same missing 'i'.                                                                                   | `deploy-forge.sh:321`          | Fix URL                                  |
| DV-NEW-21 | **MEDIUM** | **`deploy.php` references SQLite and hardcoded test count (48)**: Uses `database.sqlite` check but project uses PostgreSQL. Breaks when tests added. | `deploy.php:67,86`             | Update to PostgreSQL; dynamic test count |
| DV-NEW-22 | **MEDIUM** | **`deploy-forge.sh` suggests `curl \| bash` usage**: Security anti-pattern.                                                                          | `deploy-forge.sh:9`            | Remove suggestion                        |
| DV-NEW-23 | **LOW**    | **Multiple conditional deploy targets with no mutual exclusion**: If multiple secrets set, multiple deploys fire.                                    | `.github/workflows/deploy.yml` | Add mutual exclusion                     |

---

## 4. Security Issues

| ID         | Severity     | Issue                                                                                                                                                                                                                                         | Location                                 | Recommended Fix                                        |
| ---------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- | ------------------------------------------------------ |
| SEC-NEW-01 | **CRITICAL** | **Revoked tokens work on unified auth endpoints**: `auth:sanctum` middleware used by unified routes doesn't check `revoked_at` or `refresh_count`. `check_token_valid` middleware does. Revoked tokens fully functional on unified endpoints. | `routes/api.php:156`                     | Apply `check_token_valid` middleware to unified routes |
| SEC-NEW-02 | **CRITICAL** | **Redis password committed to VCS in plaintext**: `redis.conf` tracked with `requirepass soleil_redis_secret_2026`.                                                                                                                           | `redis.conf:58`                          | Externalize secrets                                    |
| SEC-NEW-03 | **HIGH**     | **Legacy `User::createToken()` produces infinite-lived tokens**: No `expires_at` set. Contradicts stated security model.                                                                                                                      | `Models/User.php:158`                    | Add expiration                                         |
| SEC-NEW-04 | **HIGH**     | **Three incompatible token creation flows**: (1) `User::createToken()` — raw SQL, no expiration; (2) `Auth\AuthController::login()` — raw SQL, has expiration; (3) Sanctum built-in — never used. Different columns populated per flow.       | Multiple files                           | Standardize to single flow                             |
| SEC-NEW-05 | **HIGH**     | **`UnifiedAuthController::detectAuthMode` bypasses middleware**: Manual token lookup skips `refresh_count` and device fingerprint checks. Auth bypass path for suspicious activity detection.                                                 | `Auth/UnifiedAuthController.php:138-162` | Delegate to middleware                                 |
| SEC-NEW-06 | **MEDIUM**   | **`ContactController::store()` doesn't purify `name`/`subject`**: Potential XSS if displayed in admin panel.                                                                                                                                  | `Controllers/ContactController.php`      | Purify all user input                                  |
| SEC-NEW-07 | **MEDIUM**   | **No rate limiting on authenticated endpoints**: Bookings list, admin views, room CRUD have no throttle. Compromised token → full DB enumeration.                                                                                             | `routes/api.php`                         | Add throttle middleware                                |
| SEC-NEW-08 | **MEDIUM**   | **`Cross-Origin-Embedder-Policy: require-corp`** blocks all cross-origin resources including CDN/payment iframes.                                                                                                                             | `Middleware/SecurityHeaders.php`         | Use less restrictive policy                            |
| SEC-NEW-09 | **LOW**      | **`config/sanctum.php` native expiration disabled (`null`)**: Custom middleware handles it, but direct `auth:sanctum` usage has no expiration.                                                                                                | `config/sanctum.php:63`                  | Set default expiration as safety net                   |

---

## 5. Testing Gaps

| ID         | Severity   | Issue                                                                                                                                                                                                                  | Location                               | Recommended Fix                        |
| ---------- | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- | -------------------------------------- |
| TST-NEW-01 | **HIGH**   | **E2E tests reference non-existent `data-testid` attributes**: Every Playwright test will fail immediately. `booking.spec.ts` expects `room-card`, `booking-modal`, `success-message` etc. — none exist in components. | `frontend/tests/e2e/booking.spec.ts`   | Add data-testid attrs or rewrite tests |
| TST-NEW-02 | **HIGH**   | **CI tests run MySQL but production uses PostgreSQL**: PostgreSQL-specific features (ENUM, exclusion constraints, `FOR UPDATE`) untested in CI.                                                                        | `.github/workflows/tests.yml`          | Switch CI to PostgreSQL                |
| TST-NEW-03 | **MEDIUM** | **~70% frontend components have zero test coverage**: AuthContext, RegisterPage, BookingForm, RoomList, LocationList, Header, Footer, ErrorBoundary, HomePage all untested.                                            | `frontend/tests/`                      | Add tests for critical components      |
| TST-NEW-04 | **MEDIUM** | **No password reset / forgot password tests**: Auth flow partially untested.                                                                                                                                           | Backend tests                          | Add password reset tests               |
| TST-NEW-05 | **MEDIUM** | **No CSRF protection tests**: Session-based auth untested.                                                                                                                                                             | Backend tests                          | Add CSRF tests                         |
| TST-NEW-06 | **MEDIUM** | **`DB_FOREIGN_KEYS=false` in phpunit.xml**: Constraint violations pass in tests but break production.                                                                                                                  | `backend/phpunit.xml`                  | Enable FKs in test env                 |
| TST-NEW-07 | **LOW**    | **`LoginPage.test.tsx` mocks react-router-dom entirely**: No routing integration tested; `useNavigate` mocked at module level.                                                                                         | `src/features/auth/LoginPage.test.tsx` | Use `MemoryRouter`                     |
| TST-NEW-08 | **LOW**    | **Playwright config references `pnpm` but project may use `npm`**: Lockfile inconsistency.                                                                                                                             | `frontend/playwright.config.ts:64`     | Align with package manager             |

---

## 6. Documentation Issues

| ID         | Severity   | Issue                                                                                                                                                                          | Location                       | Recommended Fix                |
| ---------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------ | ------------------------------ |
| DOC-NEW-01 | **HIGH**   | **`README.dev.md` references MySQL**: "DB: MySQL (optional when using Docker)" — project uses PostgreSQL.                                                                      | `README.dev.md`                | Correct to PostgreSQL          |
| DOC-NEW-02 | **MEDIUM** | **`docs/README.md` test count "609 tests (1657 assertions)"**: Outdated — currently 698+ tests, 1958 assertions.                                                               | `docs/README.md:3`             | Update counts                  |
| DOC-NEW-03 | **MEDIUM** | **`docs/DATABASE.md` migration count mismatch**: Says "24 migrations, 13 tables" but table lists only 18.                                                                      | `docs/DATABASE.md`             | Reconcile counts               |
| DOC-NEW-04 | **MEDIUM** | **`OPERATIONAL_PLAYBOOK.md` references Nginx**: Docker uses `php artisan serve`. Commands like `systemctl status nginx` won't work.                                            | `docs/OPERATIONAL_PLAYBOOK.md` | Align with actual deployment   |
| DOC-NEW-05 | **MEDIUM** | **`OPERATIONAL_PLAYBOOK.md` contacts are "TBD"**: DevOps Lead, Security contacts blank.                                                                                        | `docs/OPERATIONAL_PLAYBOOK.md` | Fill in or note as placeholder |
| DOC-NEW-06 | **MEDIUM** | **`README.dev.md` "Next steps" already completed**: Suggests "Add Dockerfiles" and "Wire VITE_API_URL" — both done.                                                            | `README.dev.md`                | Remove completed items         |
| DOC-NEW-07 | **LOW**    | **PostgreSQL version inconsistency**: `docs/README.md` says "PostgreSQL 15", `docker-compose.yml` uses `postgres:16-alpine`, `PERFORMANCE_BASELINE.md` says "PostgreSQL 15.1". | Multiple docs                  | Standardize to `16`            |
| DOC-NEW-08 | **LOW**    | **`docs/DATABASE.md` uses MySQL types** (`LONGTEXT`, `MEDIUMTEXT`): Should use PostgreSQL types (`TEXT`).                                                                      | `docs/DATABASE.md`             | Correct types                  |
| DOC-NEW-09 | **LOW**    | **`README.dev.md` has typos/artifacts**: Stray `y`, `kj---`, trailing `h`.                                                                                                     | `README.dev.md`                | Fix typos                      |
| DOC-NEW-10 | **LOW**    | **Missing ADR for multi-location architecture**: Migration guide exists but no ADR.                                                                                            | `docs/ADR.md`                  | Add ADR                        |
| DOC-NEW-11 | **LOW**    | **`deploy.yml` and `deploy-forge.sh` have `solelhotel.com` typo**: Missing 'i'.                                                                                                | Multiple files                 | Fix to `soleilhotel.com`       |

---

## 7. Action Plan

### Phase 1: Critical Fixes (Before Any Deployment) 🔴

| Priority | ID(s)                 | Action                                                           | Effort |
| -------- | --------------------- | ---------------------------------------------------------------- | ------ |
| P0       | BE-NEW-01             | Fix cookie lifetime calculation (remove `/ 60` division)         | 15 min |
| P0       | SEC-NEW-01            | Add `check_token_valid` middleware to unified auth routes        | 15 min |
| P0       | DV-NEW-01             | Conditional `key:generate` (only if key empty) in docker-compose | 15 min |
| P0       | DV-NEW-02             | Switch CI from MySQL to PostgreSQL                               | 1 hour |
| P0       | SEC-NEW-02, DV-NEW-03 | Externalize Redis password from `redis.conf` and healthcheck     | 30 min |
| P0       | DV-NEW-04             | Add phpredis extension to backend Dockerfile                     | 15 min |

### Phase 2: High-Priority Fixes (Within 1 Sprint) 🟠

| Priority | ID(s)                            | Action                                                              | Effort  |
| -------- | -------------------------------- | ------------------------------------------------------------------- | ------- |
| P1       | BE-NEW-02                        | Fix AdvancedRateLimitMiddleware — check throttle BEFORE `$next()`   | 30 min  |
| P1       | BE-NEW-03, BE-NEW-47             | Create `cancellation_reason` migration or remove from SELECTs       | 30 min  |
| P1       | BE-NEW-04                        | Add missing columns to PersonalAccessToken `$fillable`              | 15 min  |
| P1       | BE-NEW-05, SEC-NEW-03            | Add expiration to User::createToken() or deprecate                  | 30 min  |
| P1       | BE-NEW-14, SEC-NEW-04            | Consolidate auth flows — standardize token creation                 | 2 hours |
| P1       | BE-NEW-15                        | Fix refresh token race condition — check threshold before increment | 15 min  |
| P1       | BE-NEW-26                        | Fix CancellationService Cashier import — conditional or remove      | 15 min  |
| P1       | BE-NEW-32                        | Fix middleware auth order — check expiration before authenticating  | 30 min  |
| P1       | DV-NEW-05                        | Switch Dockerfile from `php artisan serve` to PHP-FPM               | 2 hours |
| P1       | DV-NEW-06                        | Fix Redis `bind` for Docker inter-container access                  | 15 min  |
| P1       | DV-NEW-14                        | Expand Gitleaks to scan entire repo                                 | 15 min  |
| P1       | DV-NEW-15, DV-NEW-20, DOC-NEW-11 | Fix `solelhotel.com` → `soleilhotel.com` everywhere                 | 15 min  |
| P1       | DV-NEW-16                        | Remove HTTP migration endpoint from deploy                          | 15 min  |
| P1       | FE-NEW-01                        | Implement token refresh mutex in API interceptor                    | 1 hour  |
| P1       | FE-NEW-06                        | Consolidate `User` type to single canonical definition              | 30 min  |
| P1       | FE-NEW-17                        | Use Zod schemas for runtime validation or remove dead code          | 1 hour  |
| P1       | TST-NEW-01                       | Fix E2E tests — add `data-testid` attributes to components          | 2 hours |
| P1       | DOC-NEW-01                       | Fix README.dev.md MySQL → PostgreSQL reference                      | 10 min  |

### Phase 3: Medium-Priority Improvements 🟡

| Priority | ID(s)                  | Action                                                          | Effort  |
| -------- | ---------------------- | --------------------------------------------------------------- | ------- |
| P2       | BE-NEW-06,07,09,10     | Model scope/type/status fixes                                   | 1 hour  |
| P2       | BE-NEW-16,17,36        | Delete dead code (controllers, middleware)                      | 30 min  |
| P2       | BE-NEW-18,19,20,21,23  | Controller consistency (ApiResponse, PII, language)             | 2 hours |
| P2       | BE-NEW-22,42           | Fix CSRF token and route issues                                 | 1 hour  |
| P2       | BE-NEW-27,28,29        | Service layer cleanup (duplication, date validation, dead code) | 2 hours |
| P2       | BE-NEW-33,34,35,37,38  | Middleware fixes (DB load, CORS, COEP)                          | 2 hours |
| P2       | FE-NEW-02,03,07,08,09  | Frontend feature fixes (regex, sanitize, validation)            | 2 hours |
| P2       | FE-NEW-10,11,18,19,20  | Frontend dead code and type cleanup                             | 2 hours |
| P2       | FE-NEW-22,23,24        | Frontend dependency and config cleanup                          | 30 min  |
| P2       | DV-NEW-07,08,09        | Docker dev environment hardening                                | 1 hour  |
| P2       | TST-NEW-03,04,05,06    | Test coverage expansion                                         | 4 hours |
| P2       | DOC-NEW-02,03,04,05,06 | Documentation accuracy updates                                  | 1 hour  |

### Phase 4: Low-Priority Cleanup 🟢

| Priority | ID(s)         | Action                                                 | Effort  |
| -------- | ------------- | ------------------------------------------------------ | ------- |
| P3       | All LOW items | Redundant code removal, typo fixes, minor improvements | 4 hours |

**Total estimated remaining effort: ~30 hours**

---

## Appendix A: Comparison with v1 Audit

| Metric              | v1 Audit (Feb 9)              | v2 Audit (Feb 10)       |
| ------------------- | ----------------------------- | ----------------------- |
| Total issues found  | 61                            | 98                      |
| Critical            | 11 (all fixed)                | 6 (new)                 |
| High                | 19 (17 fixed)                 | 20 (new)                |
| Medium              | 20 (16 fixed)                 | 43 (new)                |
| Low                 | 11 (all fixed)                | 29 (new)                |
| Scope depth         | Surface level + fix execution | Deep code-level review  |
| Backend tests       | 698 passing                   | 698+ passing            |
| Frontend unit tests | 90 passing                    | 90 passing              |
| E2E tests           | Scaffolded                    | Broken (no data-testid) |

The v2 audit goes deeper into code-level logic issues (race conditions, calculation bugs, middleware ordering) that the v1 audit didn't cover. Many v1 issues were configuration/dead-code problems; v2 focuses more on runtime behavior and security flow analysis.

---

## Appendix B: Statistics

| Metric                     | Value                           |
| -------------------------- | ------------------------------- |
| Total issues found         | 98                              |
| Critical                   | 6                               |
| High                       | 20                              |
| Medium                     | 43                              |
| Low                        | 29                              |
| Backend issues             | 49                              |
| Frontend issues            | 27                              |
| DevOps issues              | 23                              |
| Security issues            | 9                               |
| Testing gaps               | 8                               |
| Documentation issues       | 11                              |
| Backend tests              | **698+ passing**                |
| Frontend unit tests        | **90 passing** (7 files)        |
| E2E tests                  | **All broken** (no data-testid) |
| Estimated remaining effort | ~30 hours                       |

---

_Generated by full-project audit on February 10, 2026. This is v2 — supersedes the February 9, 2026 audit (v1). Previous audit archived as reference._

> **Methodology:** Three-pass review — (1) Backend models/controllers/services/middleware/routes/config/migrations, (2) Frontend package/app/API/features/types/components/security/config/tests, (3) DevOps Docker/Redis/CI-CD/deploy/docs/.gitignore with cross-cutting security and consistency analysis.
