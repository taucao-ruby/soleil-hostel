# Soleil Hostel — Remaining Audit Fix Prompts (4 issues)

**Updated:** February 11, 2026
**Source:** [AUDIT_REPORT.md](./AUDIT_REPORT.md) (v2 — 98 issues, 94 resolved)
**Status:** 4 remaining issues (2 HIGH, 2 MEDIUM)

> These are the only unresolved issues from the v2 audit. Each prompt is self-contained.
> Copy a prompt section and send it for execution.

---

## Overview

| # | Issue ID   | Severity | Description                                        | Est. Effort |
| - | ---------- | -------- | -------------------------------------------------- | ----------- |
| 1 | DV-NEW-05  | HIGH     | Dockerfile uses `php artisan serve` (dev server)   | 2 hours     |
| 2 | BE-NEW-14  | HIGH     | 4 auth controllers with overlapping responsibility | 3 hours     |
| 3 | BE-NEW-28  | MEDIUM   | `validateDates()` blocks updates on active bookings | 30 min      |
| 4 | SEC-NEW-05 | MEDIUM   | `detectAuthMode()` manual token lookup bypasses middleware | 30 min |

**Total estimated effort: ~6.5 hours**

---

## PROMPT 1 — Migrate Dockerfile to PHP-FPM (DV-NEW-05)

**Severity:** HIGH
**Current state:** `backend/Dockerfile` uses `php artisan serve` (single-threaded dev server) as CMD. Warning comment exists at line 29-30 but no actual migration.
**Files:** `backend/Dockerfile`, `backend/docker/nginx.conf` (new), `docker-compose.yml`

### Copy this prompt:

```
You are a fix-forward agent. Minimal changes only. Do NOT refactor unrelated code.

═══ FIX: Migrate backend Dockerfile from php artisan serve to PHP-FPM [DV-NEW-05 — HIGH] ═══

Current state: backend/Dockerfile (line 31) uses:
  CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
This is a single-threaded dev server, not suitable for production.

Required changes:

1. File: backend/Dockerfile
   - Change base image from `php:8.3-cli` to `php:8.3-fpm` for the production stage (Stage 2, line 16)
   - Keep the builder stage as `php:8.3-cli` (it only runs composer)
   - Install `nginx` in the production stage
   - Copy an nginx config file
   - Update EXPOSE to `80` (nginx) instead of `8000`
   - Remove the HEALTHCHECK that hits port 8000 — update to port 80
   - Replace CMD with a script that starts both php-fpm and nginx:
     CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]

2. File: backend/docker/nginx.conf (NEW)
   Create a minimal nginx config:
   - Listen on port 80
   - Root: /var/www/public
   - index index.php
   - location / { try_files $uri $uri/ /index.php?$query_string; }
   - location ~ \.php$ { fastcgi_pass 127.0.0.1:9000; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; include fastcgi_params; }
   - location ~ /\.ht { deny all; }

3. File: docker-compose.yml
   - Update backend service port mapping: "127.0.0.1:8000:80"
   - Update the command to use php-fpm-aware startup:
     command: 'bash -lc "set -e; composer install --no-interaction --prefer-dist; (grep -q \"^APP_KEY=base64:\" .env || php artisan key:generate --force); echo \"Run: docker compose exec backend php artisan migrate\"; php-fpm -D && nginx -g \"daemon off;\""'

After changes:
- Run: docker compose config  (verify YAML is valid)
- Run: cd backend && php artisan test  (verify nothing broke)
- Do NOT commit. Print git diff for review.
```

---

## PROMPT 2 — Consolidate auth controllers (BE-NEW-14)

**Severity:** HIGH
**Current state:** 4 auth controllers exist, all with active routes:
- `app/Http/Controllers/AuthController.php` — legacy, marked @deprecated (sunset July 2026), routes: `/auth/register`, `/auth/login`, `/auth/logout`, `/auth/refresh`, `/auth/me`
- `app/Http/Controllers/Auth/AuthController.php` — bearer v2, routes: `/auth/login-v2`, etc.
- `app/Http/Controllers/Auth/HttpOnlyTokenController.php` — httpOnly cookie auth
- `app/Http/Controllers/Auth/UnifiedAuthController.php` — mode-agnostic dispatcher
**Files:** `backend/routes/api.php`, `backend/routes/api/legacy.php`, legacy AuthController

### Copy this prompt:

```
You are a fix-forward agent. Minimal changes only. Do NOT refactor unrelated code.

═══ FIX: Consolidate auth controllers — remove legacy AuthController routes [BE-NEW-14 — HIGH] ═══

Current state: 4 auth controllers exist with overlapping responsibilities.
The root-level AuthController.php is already marked @deprecated (sunset July 2026).

The goal is NOT to delete the controller yet, but to remove route duplication and clarify the architecture.

Required changes:

1. File: backend/routes/api.php
   Read the file first. Find the route group that uses the root-level AuthController
   (NOT Auth\AuthController). These are the legacy routes:
     POST /auth/register  → AuthController::register
     POST /auth/login     → AuthController::login
     POST /auth/logout    → AuthController::logout
     POST /auth/refresh   → AuthController::refreshToken
     GET  /auth/me        → AuthController::me

   Replace them with redirects or aliases that point to the NEW controllers:
   - /auth/register  → keep as-is (registration is only in legacy controller — move the register method to Auth\AuthController if it doesn't exist there, or keep legacy route for now)
   - /auth/login     → redirect to /auth/login-v2 (or alias)
   - /auth/logout    → already handled by UnifiedAuthController
   - /auth/refresh   → redirect to /auth/refresh-v2
   - /auth/me        → already handled by UnifiedAuthController

   Add comment block: "Legacy routes — deprecated, will be removed after July 2026"

2. File: backend/routes/api/legacy.php
   Read this file. If it duplicates routes from api.php, remove the duplicates.
   Keep only routes that are truly different from main api.php.
   If the file becomes empty, delete it and remove the require/include from api.php.

3. File: backend/app/Http/Controllers/AuthController.php
   - Verify @deprecated annotation exists
   - Add @see annotations pointing to replacement controllers:
     @see \App\Http\Controllers\Auth\AuthController (bearer token auth)
     @see \App\Http\Controllers\Auth\HttpOnlyTokenController (cookie auth)
     @see \App\Http\Controllers\Auth\UnifiedAuthController (auto-detect)

After changes:
- Run: cd backend && php artisan route:list --columns=method,uri,action | head -40
- Run: php artisan test
- Do NOT commit. Print git diff for review.
```

---

## PROMPT 3 — Fix validateDates() blocking active booking updates (BE-NEW-28)

**Severity:** MEDIUM
**Current state:** `backend/app/Services/CreateBookingService.php` line 394: `$checkIn->isPast()` rejects ALL past check-in dates, even for updates to bookings that have already started. The skip logic at line 384 only works when the request doesn't include date fields at all.
**Files:** `backend/app/Services/CreateBookingService.php`

### Copy this prompt:

```
You are a fix-forward agent. Minimal changes only. Do NOT refactor unrelated code.

═══ FIX: Allow updates on in-progress bookings [BE-NEW-28 — MEDIUM] ═══

File: backend/app/Services/CreateBookingService.php

Current code (lines 381-399):
    private function validateDates(Carbon $checkIn, Carbon $checkOut, bool $isUpdate = false, $request = null): void
    {
        if ($isUpdate && $request && !$request->has(['check_in_date', 'check_out_date'])) {
            return;
        }
        if (!$checkIn->lessThan($checkOut)) {
            throw new RuntimeException('Ngày check-out phải sau ngày check-in');
        }
        if ($checkIn->isPast()) {
            throw new RuntimeException('Ngày check-in phải là ngày trong tương lai');
        }
    }

Problem: For an in-progress booking (check-in date is in the past), ANY update that touches
dates will fail because isPast() rejects the existing check-in date.

Fix: Only enforce "check-in must be future" for NEW bookings. For updates, only enforce it
if the check-in date is actually being CHANGED to a new value.

Replace the method with:
    private function validateDates(Carbon $checkIn, Carbon $checkOut, bool $isUpdate = false, $request = null): void
    {
        // Skip all date validation for updates where dates aren't being changed
        if ($isUpdate && $request && !$request->has(['check_in_date', 'check_out_date'])) {
            return;
        }

        if (!$checkIn->lessThan($checkOut)) {
            throw new RuntimeException('Ngày check-out phải sau ngày check-in');
        }

        // Only enforce future check-in for new bookings, not updates to existing ones
        if (!$isUpdate && $checkIn->isPast()) {
            throw new RuntimeException('Ngày check-in phải là ngày trong tương lai');
        }
    }

After changes:
- Run: cd backend && php artisan test
- Verify no test regressions.
- Do NOT commit. Print git diff for review.
```

---

## PROMPT 4 — Harden detectAuthMode() token lookup (SEC-NEW-05)

**Severity:** MEDIUM
**Current state:** `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` method `detectAuthMode()` (lines 152-193) manually looks up tokens in the database by hash, bypassing middleware validation for `refresh_count` and `device_fingerprint`. It only calls `$token->isValid()`. The controller is behind `auth:sanctum` + `check_token_valid` middleware, so the risk is low — this is defense-in-depth.
**Files:** `backend/app/Http/Controllers/Auth/UnifiedAuthController.php`

### Copy this prompt:

```
You are a fix-forward agent. Minimal changes only. Do NOT refactor unrelated code.

═══ FIX: Add refresh_count check to detectAuthMode() [SEC-NEW-05 — MEDIUM] ═══

File: backend/app/Http/Controllers/Auth/UnifiedAuthController.php

Current state: detectAuthMode() (lines 152-193) manually looks up tokens and only
calls $token->isValid(). It does NOT check refresh_count or device_fingerprint.
The controller is behind auth:sanctum + check_token_valid middleware, so this is
defense-in-depth, not a critical bypass.

Required changes in detectAuthMode():

1. After the existing `$token->isValid()` check on cookie tokens (around line 174),
   add a refresh_count safety check:
     if (!$token->isValid() || $token->refresh_count > config('sanctum.max_refresh_count', 50)) {
         return null;
     }

2. After the existing `$token->isValid()` check on bearer tokens (around line 187),
   add the same check:
     if (!$token->isValid() || $token->refresh_count > config('sanctum.max_refresh_count', 50)) {
         return null;
     }

3. Update the security comment block (lines 146-150) to note that detectAuthMode now
   performs its own refresh_count validation as defense-in-depth.

This is minimal — we're NOT adding device_fingerprint validation here because the
middleware already handles it, and fingerprint checking requires request context that
detectAuthMode doesn't have. The refresh_count check is a cheap safety net.

After changes:
- Run: cd backend && php artisan test
- Verify no test regressions.
- Do NOT commit. Print git diff for review.
```

---

## Quick Reference

| Prompt | Issue ID   | Severity | Dependencies | Can run independently? |
| ------ | ---------- | -------- | ------------ | ---------------------- |
| 1      | DV-NEW-05  | HIGH     | None         | Yes                    |
| 2      | BE-NEW-14  | HIGH     | None         | Yes                    |
| 3      | BE-NEW-28  | MEDIUM   | None         | Yes                    |
| 4      | SEC-NEW-05 | MEDIUM   | None         | Yes                    |

All prompts are independent — execute in any order. Each prompt ends with test verification but does NOT auto-commit; review the diff first.

---

_Generated February 11, 2026. Covers the 4 remaining issues from AUDIT_REPORT.md v2 (94/98 resolved)._
