# Soleil Hostel — Audit v2 Fix Prompts (Batched for Execution)

**Created:** February 10, 2026
**Source:** [AUDIT_REPORT.md](./AUDIT_REPORT.md) (v2 — 98 issues)
**Status:** 0/98 fixed

> Prompts grouped into **10 execution batches** — each batch is sized to fit one AI context window.
> Copy a full batch and send it for execution. Complete batches in order (1→10).

---

## Execution Plan Overview

| Batch | Scope                              | Issues | Prompts    | Files Touched | Est.  | Status |
| ----- | ---------------------------------- | ------ | ---------- | ------------- | ----- | ------ |
| 1     | Docker + Redis + Dockerfile        | 7      | 5 changes  | 3 files       | ~3h   | ⬜     |
| 2     | CI/CD + Deploy Pipeline            | 7      | 4 changes  | 3 files       | ~1.5h | ⬜     |
| 3     | Backend Auth Security Chain        | 4      | 4 changes  | 4 files       | ~1.5h | ⬜     |
| 4     | Backend Auth Models & Token Flow   | 8      | 5 changes  | 6 files       | ~3h   | ⬜     |
| 5     | Backend Middleware + Services      | 5      | 4 changes  | 5 files       | ~2h   | ⬜     |
| 6     | Frontend Core (API + Types + E2E)  | 5      | 5 changes  | 7+ files      | ~4h   | ⬜     |
| 7     | Backend Code Quality (Models+Ctrl) | 20     | 4 changes  | 12+ files     | ~4h   | ⬜     |
| 8     | Middleware + Frontend Features     | 17     | 4 changes  | 12+ files     | ~4h   | ⬜     |
| 9     | Docker Hardening + Tests + Docs    | 15     | 4 changes  | 10+ files     | ~6h   | ⬜     |
| 10    | All Low Priority Cleanup           | 29     | 3 changes  | 20+ files     | ~4h   | ⬜     |
| **Σ** |                                    | **98** | **42 ops** |               | ~30h  |        |

---

## BATCH 1 — Docker + Redis + Dockerfile

**Scope:** Infrastructure config — docker-compose.yml, redis.conf, backend/Dockerfile
**Issues:** DV-NEW-01, SEC-NEW-02, DV-NEW-03, DV-NEW-04, DV-NEW-05, DV-NEW-06 + redis bind
**Files (3):** `docker-compose.yml`, `redis.conf`, `backend/Dockerfile`

### Copy this prompt:

```
Execute all 5 fixes below. Read each file first, make the changes, then run tests.

═══ FIX 1: Conditional APP_KEY generation [DV-NEW-01 — CRITICAL] ═══
File: docker-compose.yml (backend service command)
Find: php artisan key:generate --force &&
Replace with: (grep -q "^APP_KEY=base64:" .env || php artisan key:generate --force) &&
This only generates a key if one doesn't already exist in .env.

═══ FIX 2: Externalize Redis password [SEC-NEW-02, DV-NEW-03 — CRITICAL] ═══
File: redis.conf
- Change: requirepass soleil_redis_secret_2026
  To:     # requirepass — set via --requirepass flag in docker-compose.yml

File: docker-compose.yml (redis service)
- Change healthcheck from hardcoded password:
  FROM: test: ["CMD", "redis-cli", "-a", "soleil_redis_secret_2026", "ping"]
  TO:   test: ["CMD-SHELL", "redis-cli -a $${REDIS_PASSWORD:-soleil_redis_secret_2026} ping || exit 1"]
- Update redis command to pass password dynamically:
  command: redis-server /usr/local/etc/redis/redis.conf --requirepass ${REDIS_PASSWORD:-soleil_redis_secret_2026}
- Ensure REDIS_PASSWORD env var is referenced (not hardcoded literal) wherever possible.

═══ FIX 3: Add phpredis extension to Dockerfile [DV-NEW-04 — HIGH] ═══
File: backend/Dockerfile
Find the PHP extension install line:
  RUN docker-php-ext-install pdo pdo_pgsql zip opcache
Add redis extension before or after:
  RUN pecl install redis && docker-php-ext-enable redis
  RUN docker-php-ext-install pdo pdo_pgsql zip opcache

═══ FIX 4: Fix Dockerfile production server [DV-NEW-05 — HIGH] ═══
File: backend/Dockerfile
The CMD uses php artisan serve (single-threaded dev server).
Minimal fix: add a clear WARNING comment above the CMD line:
  # ⚠️ WARNING: php artisan serve is DEVELOPMENT ONLY.
  # For production, use PHP-FPM + Nginx or Laravel Octane (FrankenPHP/Swoole).
  CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
(Full Octane migration is out of scope for this batch.)

═══ FIX 5: Fix Redis bind for Docker [DV-NEW-06 — HIGH] ═══
File: redis.conf
Change: bind 127.0.0.1
To:     bind 0.0.0.0
Add comment: # Docker: containers use Docker network, not localhost. Use bind 127.0.0.1 for non-Docker.

After all fixes: verify docker-compose.yml is valid YAML, check redis.conf syntax.
Run: cd backend && php artisan test
Then: git add -A && git commit -m "fix(infra): batch 1 — Docker key:generate, Redis password, phpredis, bind [DV-NEW-01,SEC-NEW-02,DV-NEW-03,DV-NEW-04,DV-NEW-05,DV-NEW-06]"
```

---

## BATCH 2 — CI/CD + Deploy Pipeline

**Scope:** GitHub Actions workflows + deploy scripts
**Issues:** DV-NEW-02, TST-NEW-02, DV-NEW-14, DV-NEW-15, DV-NEW-20, DOC-NEW-11, DV-NEW-16
**Files (3):** `.github/workflows/tests.yml`, `.github/workflows/deploy.yml`, `deploy-forge.sh`

### Copy this prompt:

```
Execute all 4 fixes below. Read each file, make changes, verify no syntax errors.

═══ FIX 1: Switch CI from MySQL to PostgreSQL [DV-NEW-02, TST-NEW-02 — CRITICAL] ═══
File: .github/workflows/tests.yml
1. Replace MySQL service with PostgreSQL:
   FROM:
     mysql:
       image: mysql:8.0
       env: MYSQL_ROOT_PASSWORD: password / MYSQL_DATABASE: testing
       ports: 3306:3306
       options: --health-cmd="mysqladmin ping" ...
   TO:
     postgres:
       image: postgres:16-alpine
       env:
         POSTGRES_USER: testing
         POSTGRES_PASSWORD: password
         POSTGRES_DB: testing
       ports:
         - 5432:5432
       options: --health-cmd="pg_isready -U testing" --health-interval=10s --health-timeout=5s --health-retries=5

2. Replace ALL env variable blocks throughout the file:
   DB_CONNECTION: mysql → pgsql
   DB_HOST: 127.0.0.1 (keep)
   DB_PORT: 3306 → 5432
   DB_DATABASE: testing (keep)
   DB_USERNAME: root → testing
   DB_PASSWORD: password (keep)

3. Search for any remaining "mysql" references and update.

═══ FIX 2: Expand Gitleaks to full repo [DV-NEW-14 — HIGH] ═══
File: .github/workflows/tests.yml
Find the Gitleaks step with: source: backend
Change to: source: .
(Scan entire repo, not just backend/ — redis.conf has hardcoded secrets)

═══ FIX 3: Fix solelhotel.com → soleilhotel.com typo [DV-NEW-15, DV-NEW-20, DOC-NEW-11 — HIGH] ═══
Files: .github/workflows/deploy.yml AND deploy-forge.sh
Search & replace ALL occurrences: solelhotel.com → soleilhotel.com
Verify with: grep -r "solelhotel" . --include="*.yml" --include="*.sh"

═══ FIX 4: Remove HTTP migration endpoint [DV-NEW-16 — HIGH] ═══
File: .github/workflows/deploy.yml
Find: curl -X POST "${APP_URL}/api/migrations/run"
Replace with SSH-based migration or remove entirely. Add comment:
  # Migrations should run via SSH or deploy hook, never via HTTP endpoint
Also check backend/routes/ for any /migrations/run route and DELETE it if found.

After all fixes: git add -A && git commit -m "fix(ci): batch 2 — PostgreSQL CI, Gitleaks scope, URL typo, HTTP migration [DV-NEW-02,DV-NEW-14,DV-NEW-15,DV-NEW-16,DV-NEW-20,DOC-NEW-11]"
```

---

## BATCH 3 — Backend Auth Security Chain

**Scope:** Auth middleware pipeline — cookie lifetime, token validation, middleware order, expiration config
**Issues:** BE-NEW-01, SEC-NEW-01, BE-NEW-32, BE-NEW-44
**Files (4):** `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php`, `backend/routes/api.php`, `backend/app/Http/Middleware/CheckTokenNotRevokedAndNotExpired.php`, `backend/config/sanctum.php`

### Copy this prompt:

```
Execute all 4 fixes below. Read each target file first, then apply changes.

═══ FIX 1: Fix cookie lifetime calculation bug [BE-NEW-01 — CRITICAL] ═══
File: backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php
In login() method: find `ceil($expiresInMinutes / 60)` and replace with `$expiresInMinutes`
In refresh() method: find same `ceil($expiresInMinutes / 60)` and replace with `$expiresInMinutes`
Add comment: // cookie() 3rd param expects minutes — pass $expiresInMinutes directly

═══ FIX 2: Fix revoked token bypass on unified auth routes [SEC-NEW-01 — CRITICAL] ═══
File: backend/routes/api.php
Find the unified auth route group:
  Route::prefix('auth/unified')->middleware('auth:sanctum')->group(...)
Change to:
  Route::prefix('auth/unified')->middleware(['auth:sanctum', 'check_token_valid'])->group(...)
Verify 'check_token_valid' alias exists in bootstrap/app.php. If not, register it.

═══ FIX 3: Fix middleware auth order — check expiration BEFORE authenticating [BE-NEW-32 — HIGH] ═══
File: backend/app/Http/Middleware/CheckTokenNotRevokedAndNotExpired.php
Current problem: user is authenticated at line ~60, expiration checked at line ~75.
Restructure handle() so that:
  1. Extract token from request header (parse Bearer token, look up in DB)
  2. Check isExpired() → return 401 if expired
  3. Check isRevoked() / revoked_at → return 401 if revoked
  4. ONLY THEN set authenticated user on request
  5. Update last_used_at (keep existing 1-min throttle)
Be careful to preserve existing logic for last_used_at throttling and response headers.

═══ FIX 4: Set Sanctum default expiration as safety net [BE-NEW-44 — HIGH] ═══
File: backend/config/sanctum.php
Change: 'expiration' => null,
To:     'expiration' => 60 * 24, // 24 hours — safety net for routes using raw auth:sanctum
Add comment: // Custom CheckTokenNotRevokedAndNotExpired middleware provides additional checks

Run: cd backend && php artisan test
Then: git add -A && git commit -m "fix(auth): batch 3 — cookie lifetime, unified token check, middleware order, sanctum expiry [BE-NEW-01,SEC-NEW-01,BE-NEW-32,BE-NEW-44]"
```

---

## BATCH 4 — Backend Auth Models & Token Flow

**Scope:** Auth model fixes, token creation standardization, auth controller consolidation
**Issues:** BE-NEW-04, BE-NEW-05, SEC-NEW-03, BE-NEW-14, SEC-NEW-04, SEC-NEW-05, BE-NEW-15, BE-NEW-26
**Files (6):** `backend/app/Models/PersonalAccessToken.php`, `backend/app/Models/User.php`, `backend/app/Http/Controllers/AuthController.php`, `backend/app/Http/Controllers/Auth/AuthController.php`, `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php`, `backend/app/Services/CancellationService.php`

### Copy this prompt:

```
Execute all 5 fixes below. Read each file first.

═══ FIX 1: Fix PersonalAccessToken $fillable [BE-NEW-04 — HIGH] ═══
File: backend/app/Models/PersonalAccessToken.php
Add missing columns to $fillable array:
  'token_identifier', 'token_hash', 'device_fingerprint', 'last_rotated_at'
Keep all existing $fillable entries. Verify column names match migration definitions.

═══ FIX 2: Add default token expiration to User::createToken() [BE-NEW-05, SEC-NEW-03 — HIGH] ═══
File: backend/app/Models/User.php
Find createToken() method (~line 152).
Change $expiresAt default from null to a 24-hour default:
  $expiresAt = $expiresAt ?? now()->addHours(24);
Ensure $expiresAt is passed to the DB insert's 'expires_at' field.
Add @deprecated docblock suggesting use of Auth\AuthController or HttpOnlyTokenController instead.

═══ FIX 3: Consolidate auth controllers [BE-NEW-14, SEC-NEW-04, SEC-NEW-05 — HIGH] ═══
1. Check routes: run `php artisan route:list --columns=uri,action` and grep for AuthController
2. If root AuthController.php (app/Http/Controllers/AuthController.php) has NO routes pointing to it:
   Add @deprecated docblock at class level: "Use Auth\AuthController or HttpOnlyTokenController instead"
3. Make Auth\AuthController extend base Controller:
   class AuthController extends Controller (currently doesn't extend it — BE-NEW-21)
4. Add `use ApiResponse;` trait to Auth\AuthController if not present
5. In Auth\UnifiedAuthController::detectAuthMode() — add comment noting it bypasses middleware checks (SEC-NEW-05)

═══ FIX 4: Fix bearer auth refresh race condition [BE-NEW-15 — HIGH] ═══
File: backend/app/Http/Controllers/Auth/AuthController.php
In the refresh() method:
1. Wrap logic in DB::transaction() with pessimistic lock on the token
2. Check threshold BEFORE incrementing: use >= instead of >
3. Only increment after passing the threshold check
4. Revoke old token inside the transaction

═══ FIX 5: Fix CancellationService Cashier import [BE-NEW-26 — HIGH] ═══
File: backend/app/Services/CancellationService.php
Remove: use Laravel\Cashier\Exceptions\IncompletePayment;
Replace any catch(IncompletePayment $e) block with:
  // TODO: Add Cashier exception handling when payment integration is implemented
  // catch(\Laravel\Cashier\Exceptions\IncompletePayment $e) { ... }

Run: cd backend && php artisan test
Then: git add -A && git commit -m "fix(auth): batch 4 — PAT fillable, token expiry, auth consolidation, refresh race, Cashier import [BE-NEW-04,05,14,15,26,SEC-NEW-03,04,05]"
```

---

## BATCH 5 — Backend Middleware + Services + Routes

**Scope:** Rate limiting, booking service, health routes, service cleanup
**Issues:** BE-NEW-02, BE-NEW-03, BE-NEW-47, BE-NEW-39, BE-NEW-27, BE-NEW-28
**Files (5):** `backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php`, `backend/app/Services/BookingService.php`, `backend/app/Services/CreateBookingService.php`, `backend/routes/api.php`, new migration

### Copy this prompt:

```
Execute all 4 fixes below. Read each file first.

═══ FIX 1: Fix rate limit middleware — check throttle BEFORE $next() [BE-NEW-02 — HIGH] ═══
File: backend/app/Http/Middleware/AdvancedRateLimitMiddleware.php
Current bug: $response = $next($request) at line ~61 runs the full request,
then checks rate limit at line ~68. Rate limiting doesn't prevent anything.

Restructure handle():
1. Resolve rate limit key and limits
2. Check rate limit BEFORE calling $next()
3. If not allowed → return 429 immediately
4. If allowed → $response = $next($request)
5. Add rate limit headers to response

═══ FIX 2: Create cancellation_reason migration [BE-NEW-03, BE-NEW-47 — HIGH] ═══
Create a new migration: add_cancellation_reason_to_bookings_table
  Schema::table('bookings', function (Blueprint $table) {
      $table->text('cancellation_reason')->nullable()->after('cancelled_by');
  });
Run: cd backend && php artisan migrate

═══ FIX 3: Restrict detailed health endpoints [BE-NEW-39 — HIGH] ═══
File: backend/routes/api.php
Keep public: /health, /health/live, /health/ready (for load balancers)
Move behind auth: /health/detailed, /health/full, /health/db, /health/cache, /health/queue
Wrap restricted ones in: Route::middleware(['auth:sanctum', 'role:admin'])->group(...)

═══ FIX 4: Service layer cleanup [BE-NEW-27, BE-NEW-28] ═══
File: backend/app/Services/BookingService.php
- Extract repeated SELECT column list into a class constant:
  private const BOOKING_COLUMNS = ['id', 'room_id', 'user_id', ...];
  (Include 'cancellation_reason' now that migration exists)
- Use this constant in all 4 cache query methods

File: backend/app/Services/CreateBookingService.php
- In validateDates(): skip date validation for updates on non-date fields:
  if ($isUpdate && !$request->has(['check_in_date', 'check_out_date'])) { return; }

Run: cd backend && php artisan test
Then: git add -A && git commit -m "fix(backend): batch 5 — rate limit ordering, cancellation_reason, health auth, service cleanup [BE-NEW-02,03,27,28,39,47]"
```

---

## BATCH 6 — Frontend Core (API + Types + E2E)

**Scope:** Frontend API layer, type consolidation, Zod schemas, E2E tests, README.dev.md
**Issues:** FE-NEW-01, FE-NEW-06, FE-NEW-17, TST-NEW-01, DOC-NEW-01
**Files (7+):** `frontend/src/shared/lib/api.ts`, `frontend/src/types/api.ts`, `frontend/src/features/auth/AuthContext.tsx`, `frontend/src/features/auth/auth.api.ts`, `frontend/tests/e2e/booking.spec.ts`, frontend components, `README.dev.md`

### Copy this prompt:

```
Execute all 5 fixes below. Read each file first.

═══ FIX 1: Fix token refresh race condition — implement refresh mutex [FE-NEW-01 — HIGH] ═══
File: frontend/src/shared/lib/api.ts

Add module-level state:
  let isRefreshing = false;
  let failedQueue: Array<{ resolve: (v?: unknown) => void; reject: (e?: unknown) => void }> = [];
  function processQueue(error: unknown = null) {
    failedQueue.forEach(({ resolve, reject }) => error ? reject(error) : resolve());
    failedQueue = [];
  }

In the 401 interceptor, REPLACE the current retry logic with:
  if (isRefreshing) {
    return new Promise((resolve, reject) => {
      failedQueue.push({ resolve, reject });
    }).then(() => apiClient(originalRequest));
  }
  originalRequest._retry = true;
  isRefreshing = true;
  try {
    await apiClient.post('/auth/refresh-httponly');
    processQueue();
    return apiClient(originalRequest);
  } catch (refreshError) {
    processQueue(refreshError);
    throw refreshError;
  } finally {
    isRefreshing = false;
  }

═══ FIX 2: Consolidate User type to single definition [FE-NEW-06 — HIGH] ═══
1. In frontend/src/types/api.ts: ensure canonical User interface exists with ALL fields:
   export interface User { id: number; name: string; email: string; role: 'guest'|'staff'|'admin'; email_verified_at: string|null; created_at: string; updated_at: string; }
2. In AuthContext.tsx: remove local User type, import from '@/types/api'
3. In auth.api.ts: remove local User type, import from '@/types/api'
4. Update all other files importing User to use the canonical one.

═══ FIX 3: Remove dead Zod schemas [FE-NEW-17 — HIGH] ═══
File: frontend/src/types/api.ts
Delete ALL Zod schema definitions (~160 lines of z.object(), z.string(), etc.)
Keep only the TypeScript interfaces/types.
Check if 'zod' is used elsewhere: grep -r "from 'zod'" frontend/src/
If not used anywhere else, run: cd frontend && npm uninstall zod

═══ FIX 4: Fix E2E tests — add data-testid attributes to components [TST-NEW-01 — HIGH] ═══
Search frontend/tests/e2e/ for all data-testid references used in tests.
Add matching data-testid attributes to the corresponding components:
- RoomList.tsx: add data-testid="room-card" to each room card wrapper
- BookingForm.tsx: add data-testid="booking-modal" to form container
- Add data-testid="success-message" to booking success display
- Add data-testid="booking-reference" to reference number element
- Add data-testid="error-message" to error display components

═══ FIX 5: Fix README.dev.md remaining MySQL references [DOC-NEW-01 — HIGH] ═══
File: README.dev.md
Search for any remaining "MySQL" or "mysql" references and replace with PostgreSQL.
Run: grep -in "mysql" README.dev.md — should return 0 results after fix.

Run: cd frontend && npx tsc --noEmit && npx vitest run
Then: git add -A && git commit -m "fix(frontend): batch 6 — refresh mutex, User type, Zod cleanup, data-testid, MySQL refs [FE-NEW-01,06,17,TST-NEW-01,DOC-NEW-01]"
```

---

## BATCH 7 — Backend Code Quality (Models + Dead Code + Controllers + Routes)

**Scope:** Model fixes, dead code deletion, controller consistency, route cleanup
**Issues:** BE-NEW-06,07,08,09,10,16,17,29,30,36,18,19,20,21,22,23,40,41,42,43 + SEC-NEW-06,07
**Files (12+):** Models (Booking, ContactMessage, Room, Review), dead controllers/middleware, BookingController, ContactController, HealthController, Auth/AuthController, HttpOnlyTokenController, routes

### Copy this prompt:

```
Execute all 4 fix groups below. Read files before editing. Delete dead code files after verifying no routes/imports reference them.

═══ GROUP 1: Model scope & type fixes [BE-NEW-06,07,08,09,10] ═══
1. Models/Booking.php — scopeByStatus: change param from string to BookingStatus enum
2. Models/Booking.php — selectColumns(): add missing columns:
   'amount', 'payment_intent_id', 'refund_amount', 'refund_status', 'cancelled_at', 'cancelled_by', 'cancellation_reason'
3. Models/ContactMessage.php — add `use Purifiable;` trait with:
   protected $purifiable = ['name', 'subject', 'message'];
4. Models/Room.php — standardize status: make scopeActive and scopeAvailableBetween use same status convention. Check what DB actually stores.
5. Models/Review.php — change default approved from true to false; add 'approved' to $fillable

═══ GROUP 2: Delete dead code [BE-NEW-16,17,36,29,30] ═══
Verify each is unused (grep routes + imports), then delete:
1. backend/app/Http/Controllers/Security/CspViolationReportController.php (duplicate)
2. backend/app/Http/Controllers/Api/BookingCancellationController.php (no route)
3. backend/app/Http/Middleware/VerifyBookingOwnership.php (not registered)
4. Check RateLimitService.php: grep -r "RateLimitService" backend/app/ backend/routes/
   If dead code, add @deprecated docblock (don't delete — AdvancedRateLimitMiddleware may use it)
5. Check BookingService::cancelBooking(): grep -r "cancelBooking" backend/app/
   If unused, remove the method

═══ GROUP 3: Controller consistency [BE-NEW-18,19,21,22,23 + SEC-NEW-06] ═══
1. BookingController — add `use ApiResponse;` trait, replace raw response()->json() calls
2. ContactController::store() — mask email in log context:
   'email' => Str::mask($validated['email'], '*', 3)
   Also purify name/subject inputs (SEC-NEW-06)
3. Auth\AuthController — should already extend Controller from Batch 4; verify
4. HttpOnlyTokenController CSRF — replace Str::random(64) with Session::token()
5. Replace Vietnamese/Indonesian error messages with __() helper throughout auth controllers
   Create backend/resources/lang/en/auth.php if needed with English translations

═══ GROUP 4: Route cleanup [BE-NEW-40,41,42,43 + SEC-NEW-07] ═══
File: backend/routes/api.php + routes/api/legacy.php
1. BE-NEW-42: /api/auth/csrf-token — update to return Session::token() (ties to GROUP 3 #4)
2. BE-NEW-40: Check if ReviewController exists. If so, add: Route::apiResource('reviews', ReviewController::class);
   If not, add TODO comment in routes
3. BE-NEW-41: Deduplicate legacy routes in routes/api/legacy.php — either delete duplicates or prefix with /v1/
4. BE-NEW-43: Remove /api/ping if /health/live exists
5. SEC-NEW-07: Add throttle middleware to authenticated booking/admin endpoints

Run: cd backend && php artisan route:list && php artisan test
Then: git add -A && git commit -m "fix(backend): batch 7 — model types, dead code, controller consistency, route cleanup [BE-NEW-06..10,16..23,29,30,36,40..43,SEC-NEW-06,07]"
```

---

## BATCH 8 — Backend Middleware + Frontend Features & Cleanup

**Scope:** Middleware fixes + frontend feature fixes, dead code cleanup, dependency removal
**Issues:** BE-NEW-33,34,35,37,38 + SEC-NEW-08 + FE-NEW-02,03,04,07,08,09,10,11,18,19,20,21,22,23,24,25
**Files (12+):** Middleware files, frontend source files, frontend/package.json

### Copy this prompt:

```
Execute all 4 fix groups. Read files before editing.

═══ GROUP 1: Backend middleware fixes [BE-NEW-33,34,35,37,38 + SEC-NEW-08] ═══
1. CheckHttpOnlyTokenValid.php — throttle last_used_at to 1-min intervals:
   if (!$token->last_used_at || $token->last_used_at->diffInMinutes(now()) >= 1) { update }
2. Check if custom Middleware/Cors.php should be deleted:
   If Laravel's HandleCors is in bootstrap/app.php, delete custom Cors.php
   If not, fix: don't fallback to $allowedOrigins[0] on mismatch — omit header entirely (BE-NEW-38)
3. AdvancedRateLimitMiddleware — replace $user->subscription_tier ?? 'free' with just 'free'
4. SecurityHeaders.php — change COEP:
   FROM: 'Cross-Origin-Embedder-Policy' => 'require-corp'
   TO:   'Cross-Origin-Embedder-Policy' => 'credentialless'

═══ GROUP 2: Frontend feature fixes [FE-NEW-02,03,04,07,08,09] ═══
1. api.ts: fix public route regex: FROM url?.match(/\/(rooms|$)/) TO /^\/(rooms)?$/.test(url || '')
2. RegisterPage.tsx: remove sanitizeInput() from API submissions — server handles sanitization
3. CSRF: add comment documenting that HttpOnly cookie auth provides inherent CSRF protection (FE-NEW-04)
4. LoginPage.tsx: change min password from 6 to 8 chars (align with RegisterPage's min 8)
5. BookingForm.tsx: read URL query params and pre-fill room_id:
   const [searchParams] = useSearchParams(); const roomId = searchParams.get('room_id');
6. RoomList.tsx: add onClick to "Book Now" button: navigate(`/booking?room_id=${room.id}`)

═══ GROUP 3: Frontend dead code cleanup [FE-NEW-10,11,18,19,20,21] ═══
1. Delete src/app/providers.tsx if it's a no-op passthrough
2. ToastContainer: mount in App.tsx/main.tsx OR remove toast utility entirely
3. Delete src/features/auth/auth.api.ts if AuthContext reimplements everything (check Batch 6 first)
4. Standardize Booking type field: pick 'number_of_guests' (matches backend), update all files
5. Standardize Room status: pick 'booked' or 'occupied', use consistently
6. Delete dead API functions: getMyBookings, getBookingById, cancelBooking (booking.api.ts),
   getRoomById (room.api.ts), checkAvailability (location.api.ts)

═══ GROUP 4: Frontend dependency & config cleanup [FE-NEW-22,23,24,25] ═══
Run these commands:
  cd frontend
  npm uninstall react-datepicker @types/react-datepicker  (unused — FE-NEW-22)
  npm uninstall framer-motion  (unused, ~30KB gzipped — FE-NEW-23)
Add to eslint.config.js: import and include eslint-config-prettier (FE-NEW-24)
Add to package.json scripts: "test": "vitest run", "test:watch": "vitest" (FE-NEW-25)

Run: cd frontend && npx tsc --noEmit && npm run test
Run: cd backend && php artisan test
Then: git add -A && git commit -m "fix(mixed): batch 8 — middleware fixes, frontend features, dead code, deps [BE-NEW-33..38,FE-NEW-02..25,SEC-NEW-08]"
```

---

## BATCH 9 — Docker Hardening + Test Coverage + Documentation

**Scope:** Docker dev env, test expansion, documentation accuracy
**Issues:** DV-NEW-07,08,09,10 + TST-NEW-03,04,05,06 + DOC-NEW-02,03,04,05,07,08,09
**Files (10+):** `docker-compose.yml`, test files, docs/

### Copy this prompt:

```
Execute all 4 fix groups.

═══ GROUP 1: Docker dev environment hardening [DV-NEW-07,08,09,10] ═══
File: docker-compose.yml
1. Bind dev ports to localhost:
   "8000:8000" → "127.0.0.1:8000:8000"
   "5173:5173" → "127.0.0.1:5173:5173"
2. Add named volume for node_modules:
   frontend volumes: add `- frontend_node_modules:/app/node_modules`
   Add at bottom: volumes: frontend_node_modules:
3. Make migration deliberate: replace `php artisan migrate --force &&`
   with `echo "Run: docker compose exec backend php artisan migrate" &&`
4. Add resource limits to services:
   deploy: resources: limits: memory: 512M, cpus: '1.0'

═══ GROUP 2: Test coverage expansion [TST-NEW-03,04,05,06] ═══
1. Create frontend tests for untested components (TST-NEW-03):
   - tests/unit/AuthContext.test.tsx (login/logout flow)
   - tests/unit/RegisterPage.test.tsx (form validation)
   - tests/unit/BookingForm.test.tsx (date selection)
   - tests/unit/HomePage.test.tsx (render, navigation)
2. Create backend password reset tests (TST-NEW-04):
   test('user can request password reset')
   test('user can reset with valid token')
3. Create backend CSRF tests (TST-NEW-05):
   test('CSRF token endpoint returns token')
4. Enable foreign keys in tests (TST-NEW-06):
   phpunit.xml: change DB_FOREIGN_KEYS from false to true
   Fix any tests that break.

═══ GROUP 3: Documentation updates [DOC-NEW-03,04,05,07,08] ═══
1. docs/DATABASE.md: reconcile migration/table counts — run php artisan migrate:status and count
   Replace MySQL types: LONGTEXT → TEXT, MEDIUMTEXT → TEXT (DOC-NEW-08)
2. docs/OPERATIONAL_PLAYBOOK.md:
   Replace Nginx references with actual setup (php artisan serve / Docker) (DOC-NEW-04)
   Replace "TBD" contacts with "[To be assigned]" (DOC-NEW-05)
3. Standardize PostgreSQL version to 16 everywhere (DOC-NEW-07):
   docs/PERFORMANCE_BASELINE.md: change "PostgreSQL 15" → "PostgreSQL 16"
   Any other docs referencing version 15

═══ GROUP 4: Misc doc fixes [DOC-NEW-02,09] ═══
1. docs/README.md: verify test counts are current (DOC-NEW-02) — should be 698+ tests
2. Fix any remaining typos/artifacts in README.dev.md (DOC-NEW-09)

Run all tests: cd backend && php artisan test && cd ../frontend && npx vitest run
Then: git add -A && git commit -m "fix(mixed): batch 9 — Docker hardening, test coverage, docs [DV-NEW-07..10,TST-NEW-03..06,DOC-NEW-02..09]"
```

---

## BATCH 10 — All Low Priority Cleanup

**Scope:** All remaining LOW severity issues
**Issues:** All 29 LOW items (BE-NEW-11,12,13,24,25,31,46,48,49 + FE-NEW-05,12,13,14,15,16,26,27 + DV-NEW-11,12,13,17,18,19,21,22,23 + BE-NEW-45 + SEC-NEW-09 + TST-NEW-07,08 + DOC-NEW-10)
**Files (20+):** Scattered across entire project

### Copy this prompt:

```
Execute all 3 fix groups. These are all LOW priority — quick fixes and cleanup.

═══ GROUP 1: Backend low-priority [BE-NEW-11,12,13,24,25,31,45,46,48,49 + SEC-NEW-09] ═══
1. Models/Booking.php: delete redundant scopeOnlyTrashed, scopeWithTrashed, isTrashed() — SoftDeletes provides these
2. Models/Location.php: remove lock_version cast/accessor if unused; fix selectColumns to narrow scope
3. RoomController: use route model binding (Room $room) instead of raw $id
4. BookingController: add proper `use` imports for Log and BookingCreated event
5. Replace Vietnamese error messages in service layer with __() helper
6. config/cors.php: set max_age to 86400 (not 0)
7. config/sanctum.php expiration: already set in Batch 3 — verify
8. Evaluate config/session.php database driver necessity for stateless API
9. Consider squashing 28+ migrations (or just document as tech debt)

═══ GROUP 2: Frontend low-priority [FE-NEW-05,12,13,14,15,16,26,27] ═══
1. api.ts: add production guard — throw error if VITE_API_URL missing in production
2. ProtectedRoute.tsx: preserve URL for redirect-after-login using useLocation()
3. NotFoundPage.tsx: replace <a href="/"> with React Router <Link to="/">
4. Extract shared SkeletonCard component from RoomList and LocationList
5. booking.validation.ts: remove Math.abs from calculateNights, validate date order explicitly
6. Use shared UI components (Input, Label) in actual forms instead of raw <input>
7. vite.config.ts: use env variable for proxy target instead of hardcoded hostname
8. vite-plugin-csp-nonce.js: add comment documenting Blade dependency

═══ GROUP 3: DevOps low-priority [DV-NEW-11,12,13,17,18,19,21,22,23 + TST-NEW-07,08 + DOC-NEW-10] ═══
1. docker-compose.yml: remove `version: "3.8"` line
2. backend/Dockerfile: add HEALTHCHECK instruction
3. redis.conf: add rename-command for FLUSHALL, FLUSHDB, DEBUG, CONFIG
4. tests.yml: make N+1 report step conditional
5. Standardize package manager: pick pnpm or npm, use everywhere (CI + Docker)
6. Update GitHub Action versions: docker/login-action@v3, codecov@v4
7. deploy.php: update SQLite ref to PostgreSQL, use dynamic test count
8. deploy-forge.sh: remove `curl | bash` suggestion
9. deploy.yml: add mutual exclusion for deploy targets
10. LoginPage.test.tsx: use MemoryRouter instead of mocking react-router-dom
11. playwright.config.ts: align package manager reference
12. docs/ADR.md: add ADR for multi-location architecture decision

Run full test suite. Then: git add -A && git commit -m "fix(cleanup): batch 10 — all low priority items [29 issues]"
```

---

## Quick Reference: Issue → Batch Mapping

| Issue ID   | Batch | Issue ID  | Batch | Issue ID   | Batch |
| ---------- | ----- | --------- | ----- | ---------- | ----- |
| BE-NEW-01  | 3     | BE-NEW-26 | 4     | FE-NEW-06  | 6     |
| BE-NEW-02  | 5     | BE-NEW-27 | 5     | FE-NEW-07  | 8     |
| BE-NEW-03  | 5     | BE-NEW-28 | 5     | FE-NEW-08  | 8     |
| BE-NEW-04  | 4     | BE-NEW-29 | 7     | FE-NEW-09  | 8     |
| BE-NEW-05  | 4     | BE-NEW-30 | 7     | FE-NEW-10  | 8     |
| BE-NEW-06  | 7     | BE-NEW-31 | 10    | FE-NEW-11  | 8     |
| BE-NEW-07  | 7     | BE-NEW-32 | 3     | FE-NEW-12  | 10    |
| BE-NEW-08  | 7     | BE-NEW-33 | 8     | FE-NEW-13  | 10    |
| BE-NEW-09  | 7     | BE-NEW-34 | 8     | FE-NEW-14  | 10    |
| BE-NEW-10  | 7     | BE-NEW-35 | 8     | FE-NEW-15  | 10    |
| BE-NEW-11  | 10    | BE-NEW-36 | 7     | FE-NEW-16  | 10    |
| BE-NEW-12  | 10    | BE-NEW-37 | 8     | FE-NEW-17  | 6     |
| BE-NEW-13  | 10    | BE-NEW-38 | 8     | FE-NEW-18  | 8     |
| BE-NEW-14  | 4     | BE-NEW-39 | 5     | FE-NEW-19  | 8     |
| BE-NEW-15  | 4     | BE-NEW-40 | 7     | FE-NEW-20  | 8     |
| BE-NEW-16  | 7     | BE-NEW-41 | 7     | FE-NEW-21  | 8     |
| BE-NEW-17  | 7     | BE-NEW-42 | 7     | FE-NEW-22  | 8     |
| BE-NEW-18  | 7     | BE-NEW-43 | 7     | FE-NEW-23  | 8     |
| BE-NEW-19  | 7     | BE-NEW-44 | 3     | FE-NEW-24  | 8     |
| BE-NEW-20  | 7     | BE-NEW-45 | 10    | FE-NEW-25  | 8     |
| BE-NEW-21  | 7     | BE-NEW-46 | 10    | FE-NEW-26  | 10    |
| BE-NEW-22  | 7     | BE-NEW-47 | 5     | FE-NEW-27  | 10    |
| BE-NEW-23  | 7     | BE-NEW-48 | 10    | SEC-NEW-01 | 3     |
| BE-NEW-24  | 10    | BE-NEW-49 | 10    | SEC-NEW-02 | 1     |
| BE-NEW-25  | 10    |           |       | SEC-NEW-03 | 4     |
| SEC-NEW-04 | 4     | DV-NEW-06 | 1     | SEC-NEW-08 | 8     |
| SEC-NEW-05 | 4     | DV-NEW-07 | 9     | SEC-NEW-09 | 10    |
| SEC-NEW-06 | 7     | DV-NEW-08 | 9     | TST-NEW-01 | 6     |
| SEC-NEW-07 | 7     | DV-NEW-09 | 9     | TST-NEW-02 | 2     |
| DV-NEW-01  | 1     | DV-NEW-10 | 9     | TST-NEW-03 | 9     |
| DV-NEW-02  | 2     | DV-NEW-11 | 10    | TST-NEW-04 | 9     |
| DV-NEW-03  | 1     | DV-NEW-12 | 10    | TST-NEW-05 | 9     |
| DV-NEW-04  | 1     | DV-NEW-13 | 10    | TST-NEW-06 | 9     |
| DV-NEW-05  | 1     | DV-NEW-14 | 2     | TST-NEW-07 | 10    |
| DOC-NEW-01 | 6     | DV-NEW-15 | 2     | TST-NEW-08 | 10    |
| DOC-NEW-02 | 9     | DV-NEW-16 | 2     | DOC-NEW-09 | 9     |
| DOC-NEW-03 | 9     | DV-NEW-17 | 10    | DOC-NEW-10 | 10    |
| DOC-NEW-04 | 9     | DV-NEW-18 | 10    |            |       |
| DOC-NEW-05 | 9     | DV-NEW-19 | 10    |            |       |
| DOC-NEW-07 | 9     | DV-NEW-20 | 2     |            |       |
| DOC-NEW-08 | 9     | DV-NEW-21 | 10    |            |       |
| DOC-NEW-11 | 2     | DV-NEW-22 | 10    |            |       |

---

_Generated February 10, 2026. 10 execution batches covering all 98 issues from AUDIT_REPORT.md v2._
_Each batch is sized to fit one AI context window. Copy the "### Copy this prompt:" section and send for execution._
