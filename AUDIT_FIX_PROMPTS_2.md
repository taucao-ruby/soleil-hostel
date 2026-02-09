# Soleil Hostel — Audit Fix Prompts (Phase 2)

> **Scope:** 22 remaining unfixed issues from the original 61-issue audit.  
> **Prerequisites:** All 16 prompts from [AUDIT_FIX_PROMPTS.md](./AUDIT_FIX_PROMPTS.md) have been completed.  
> **Status:** 0/22 fixed

> **How to use:** Copy each prompt below and send it to an AI model to execute one at a time.  
> Each prompt is **self-contained** — it does not depend on any other prompt.  
> Execution order: P1 (high) → P2 → P3.  
> After each prompt, run backend tests (`php artisan test`) and frontend tests (`npx vitest run`) to verify.

---

## Table of Contents

- [PROMPT 1 — P1: Fix Middleware Bugs (BE-027, BE-028)](#prompt-1)
- [PROMPT 2 — P1: Consolidate Health System & Fix Routes (BE-031, BE-032, BE-033, TST-007)](#prompt-2)
- [PROMPT 3 — P1: Consolidate Room Availability Services (BE-019)](#prompt-3)
- [PROMPT 4 — P1: ContactController Persistence (BE-013)](#prompt-4)
- [PROMPT 5 — P1: Consolidate CI/CD Workflows (DV-013)](#prompt-5)
- [PROMPT 6 — P2: Backend Controller, Service & Config Cleanup (BE-016, BE-021, BE-036, BE-039)](#prompt-6)
- [PROMPT 7 — P2: Frontend Architecture Fixes (FE-011, FE-012, FE-016)](#prompt-7)
- [PROMPT 8 — P2: DevOps Hardening (DV-007, DV-017, DV-018)](#prompt-8)
- [PROMPT 9 — P2: Test Coverage — Auth & Security (TST-003, TST-004, TST-005)](#prompt-9)
- [PROMPT 10 — P2: Test Coverage — Validation & Profile (TST-006, TST-008)](#prompt-10)
- [PROMPT 11 — P3: Fix Migration File Ordering (BE-037)](#prompt-11)

---

<a id="prompt-1"></a>

## PROMPT 1 — P1: Fix Middleware Bugs (BE-027, BE-028)

> **Issues:** BE-027 (MEDIUM), BE-028 (MEDIUM)  
> **Risk:** BE-027 will throw `TypeError` at runtime in PHP 8+; BE-028 partially defeats CSP nonce protection  
> **Estimated effort:** 30 minutes

### Context

**BE-027 — ThrottleApiRequests passes wrong argument counts**

File: `backend/app/Http/Middleware/ThrottleApiRequests.php`

The middleware calls Laravel's `RateLimiter` methods with extra arguments that Laravel doesn't accept:

- `tooManyAttempts($limit, $maxAttempts, $request)` — 3 args, Laravel accepts 2: `tooManyAttempts($key, $maxAttempts)`
- `hit($limit, $period, $request)` — 3 args, Laravel accepts 2: `hit($key, $decaySeconds)`
- `remaining($limit, $maxAttempts, $request)` — 3 args, Laravel accepts 2: `remaining($key, $maxAttempts)`
- `availableIn($limit, $request)` — 2 args, Laravel accepts 1: `availableIn($key)`
- `buildException()` returns a `JsonResponse`, but the code does `throw $this->buildException(...)` — a `JsonResponse` is not `Throwable`, so this causes a `TypeError` in PHP 8+.

**BE-028 — SecurityHeaders exposes CSP nonce via X-CSP-Nonce response header**

File: `backend/app/Http/Middleware/SecurityHeaders.php`

Line ~46: `$response->headers->set('X-CSP-Nonce', $this->nonce);`

The nonce is correctly stored in request attributes (for server-side template use), but ALSO exposed as a response header. An XSS payload could read `X-CSP-Nonce` from response headers and inject scripts with the valid nonce, partially defeating CSP protection.

### Task

1. **Fix `ThrottleApiRequests.php`:**
   - Fix all `RateLimiter` method calls to use correct argument counts per Laravel 11 API
   - The first argument should be `$key` (string), not `$limit` — check that you're resolving the cache key correctly
   - Fix `buildException()` to either: (a) return a proper `Throwable` (e.g., `HttpException`), or (b) change the calling code to use `return $this->buildException(...)` instead of `throw`
   - Ensure the middleware correctly implements throttling: check attempts, hit counter on pass, return 429 with `Retry-After` on exceed

2. **Fix `SecurityHeaders.php`:**
   - Remove the line `$response->headers->set('X-CSP-Nonce', $this->nonce);`
   - Keep the existing `$request->attributes->set('csp_nonce', $this->nonce);` — this is the correct way to pass nonce to views
   - Verify that any Blade templates or responses that need the nonce get it from `$request->attributes->get('csp_nonce')`, not from the response header

### Verification

```bash
cd backend && php artisan test
```

All 698 tests must pass. Test specifically:

- Rate limiting still works (if rate limit tests exist)
- Security headers still present in responses (minus `X-CSP-Nonce`)

---

<a id="prompt-2"></a>

## PROMPT 2 — P1: Consolidate Health System & Fix Routes (BE-031, BE-032, BE-033, TST-007)

> **Issues:** BE-031 (MEDIUM), BE-032 (MEDIUM), BE-033 (MEDIUM), TST-007 (LOW)  
> **Risk:** Duplicate health endpoints confuse monitoring; inconsistent deprecation; CSP reports may fail  
> **Estimated effort:** 2 hours

### Context

**BE-031 — Duplicate health controllers**

Two controllers serve overlapping health-check purposes:

- `app/Http/Controllers/HealthCheckController.php` (~115 lines) — simpler, older implementation. Serves `/health` and `/health/detailed`.
- `app/Http/Controllers/HealthController.php` (~423 lines) — comprehensive with failure semantics, liveness/readiness probes. Serves `/health/live`, `/health/ready`, `/health/full`, `/health/db`, `/health/cache`, `/health/queue`.

Routes in `routes/api.php` register both controllers:

```php
// ~L43-L61:
Route::get('/health', [HealthCheckController::class, 'check']);
Route::get('/health/detailed', [HealthCheckController::class, 'detailed']);
Route::get('/health/live', [HealthController::class, 'liveness']);
Route::get('/health/ready', [HealthController::class, 'readiness']);
// ... etc.
```

**BE-032 — Legacy `/auth/register` missing deprecation middleware**

In `routes/api.php`:

```php
Route::post('/auth/login', ...)->middleware(['throttle:10,1', 'deprecated:2026-07-01,/api/auth/login-v2']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
// ↑ Missing `deprecated:...` middleware — inconsistent with /auth/login
```

**BE-033 — CSP report route strips all `api` middleware**

In `routes/api.php`:

```php
Route::post('/csp-violation-report', [CspViolationReportController::class, 'report'])
    ->withoutMiddleware(['api']);
```

`withoutMiddleware(['api'])` removes the ENTIRE api middleware group including CORS. Browser CSP violation reports are cross-origin POST requests — they need CORS headers to succeed.

**TST-007 — Duplicate HealthCheck test files**

Two test files exist:

- `tests/Feature/HealthCheck/HealthControllerTest.php`
- `tests/Feature/HealthCheck/HealthCheckControllerTest.php`

### Task

1. **Consolidate health controllers (BE-031):**
   - Keep `HealthController.php` as the canonical health controller (it's more comprehensive)
   - Move any unique functionality from `HealthCheckController` into `HealthController` (if any)
   - Update `routes/api.php`: redirect all health routes to `HealthController`
   - The basic `/health` endpoint should return a simple `{"status": "ok"}` response (merge from HealthCheckController)
   - Delete `HealthCheckController.php`

2. **Fix `/auth/register` deprecation (BE-032):**
   - Add the deprecation middleware to the register route:
     ```php
     Route::post('/auth/register', [AuthController::class, 'register'])
         ->middleware(['throttle:5,1', 'deprecated:2026-07-01,/api/v2/auth/register']);
     ```

3. **Fix CSP report route (BE-033):**
   - Instead of removing ALL api middleware, only remove the specific middleware that causes issues (e.g., CSRF, throttle for reports):
     ```php
     Route::post('/csp-violation-report', [CspViolationReportController::class, 'report'])
         ->withoutMiddleware(['throttle:api']);
     ```
   - Keep CORS middleware so browser CSP reports can actually reach the endpoint

4. **Consolidate health tests (TST-007):**
   - Merge tests from `HealthCheckControllerTest.php` into `HealthControllerTest.php`
   - Update assertions to match the consolidated controller
   - Delete `HealthCheckControllerTest.php`

### Verification

```bash
cd backend && php artisan test
# Check health endpoints:
curl http://localhost:8000/api/health
curl http://localhost:8000/api/health/live
curl http://localhost:8000/api/health/ready
```

All existing tests must pass. Health endpoints must still respond correctly.

---

<a id="prompt-3"></a>

## PROMPT 3 — P1: Consolidate Room Availability Services (BE-019)

> **Issues:** BE-019 (HIGH)  
> **Risk:** Two services with conflicting cache strategies cause stale/inconsistent availability data  
> **Estimated effort:** 3 hours

### Context

Two services manage room availability with **different caching strategies**:

**`app/Services/RoomService.php`** has:

- `getRoomDetailWithBookings()` — loads room with active bookings
- `checkOverlappingBookings()` — checks booking conflicts
- `invalidateAvailability()` — invalidates cache with tags: `availability`, `availability-room-{id}` (30-second TTL)

**`app/Services/RoomAvailabilityService.php`** has:

- `getAllRoomsWithAvailability()` — lists all rooms with availability for date range
- `getRoomAvailability()` — single room availability check
- `isRoomAvailable()` — boolean availability check
- Uses cache tag `room-availability` with **1-hour TTL**

**The problem:** Cache invalidation in one service doesn't clear the other's cache. When a booking is created:

- `RoomService` invalidates `availability` + `availability-room-{id}` tags
- `RoomAvailabilityService`'s `room-availability` tag is NOT invalidated
- Result: `RoomAvailabilityService` shows stale data for up to 1 hour

### Task

1. **Consolidate into `RoomAvailabilityService`** as the single source of truth for all room availability logic:
   - Move `checkOverlappingBookings()` from `RoomService` into `RoomAvailabilityService`
   - Move `invalidateAvailability()` logic into `RoomAvailabilityService`
   - Unify cache tags to ONE consistent set (e.g., `room-availability` and `room-availability-{id}`)
   - Use a single, consistent TTL (recommend 300 seconds / 5 minutes as a balance)

2. **Slim down `RoomService`:**
   - Remove all availability-related methods from `RoomService`
   - `RoomService` should only handle CRUD operations for rooms
   - If `RoomService` needs availability data, inject `RoomAvailabilityService` and delegate

3. **Update all callers:**
   - Search the entire codebase for references to `RoomService::checkOverlappingBookings`, `RoomService::invalidateAvailability`, `RoomService::getRoomDetailWithBookings`
   - Update them to use `RoomAvailabilityService` methods
   - Check controllers, other services, event listeners, and observers

4. **Fix cache invalidation:**
   - Ensure `CreateBookingService`, `BookingService`, `CancellationService`, and any booking observers call `RoomAvailabilityService::invalidateAvailability()` (not `RoomService`)
   - All invalidation must use the unified cache tags

### Verification

```bash
cd backend && php artisan test
```

All 698 tests must pass. Pay special attention to:

- Room availability tests
- Booking creation tests (concurrent booking tests)
- Cache-related tests

---

<a id="prompt-4"></a>

## PROMPT 4 — P1: ContactController Persistence (BE-013)

> **Issues:** BE-013 (HIGH)  
> **Risk:** All contact form messages are silently lost — only written to log file  
> **Estimated effort:** 1.5 hours

### Context

File: `backend/app/Http/Controllers/ContactController.php`

The `store()` method validates input and logs it, but never persists:

```php
\Log::info('Contact message received', $validated);
// Future enhancement: save to database or send email notification
```

Contact messages from users are silently discarded. In production, there is no way to retrieve or respond to them.

### Task

1. **Create a `ContactMessage` model and migration:**

   ```bash
   php artisan make:model ContactMessage -m
   ```

   Migration columns:
   - `id` (bigIncrements)
   - `name` (string, 255)
   - `email` (string, 255)
   - `subject` (string, 255, nullable)
   - `message` (text)
   - `read_at` (timestamp, nullable) — for admin to mark as read
   - `timestamps`

2. **Update `ContactController::store()`:**
   - After validation, create a `ContactMessage` record
   - Keep the logging as an additional audit trail
   - Return the created message with `201 Created` status
   - Use the `ApiResponse` trait for consistent response format

3. **Create an admin endpoint to list contact messages:**
   - Add `index()` method to `ContactController` (or a new `AdminContactController`)
   - Paginate results, sorted by newest first
   - Add `markAsRead()` method
   - Register routes in `routes/api.php` under admin middleware group

4. **Optional: Send email notification when contact message is received:**
   - Create a `ContactMessageReceived` notification
   - Send to admin email (configurable via `config('mail.admin_address')`)
   - Use queued notification to not block the response

### Verification

```bash
cd backend && php artisan test
# Test the endpoint manually:
curl -X POST http://localhost:8000/api/contact \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@example.com","subject":"Hello","message":"Test message"}'
```

All existing tests must pass. New record should appear in `contact_messages` table.

---

<a id="prompt-5"></a>

## PROMPT 5 — P1: Consolidate CI/CD Workflows (DV-013)

> **Issues:** DV-013 (HIGH)  
> **Risk:** 4 overlapping workflows waste CI minutes and may cause conflicting deployments  
> **Estimated effort:** 3 hours

### Context

Four workflow files all trigger on push to `main`/`develop`, running redundant test suites:

| File                                       | Lines | Triggers              | What it does                                                    |
| ------------------------------------------ | ----- | --------------------- | --------------------------------------------------------------- |
| `.github/workflows/ci-cd.yml`              | 855   | push main/develop, PR | Full pipeline: lint, test backend, test frontend, build, deploy |
| `.github/workflows/tests.yml`              | 181   | push main/develop, PR | Tests: backend, frontend, lint, security scan                   |
| `.github/workflows/deploy.yml`             | 244   | push main, manual     | Tests + deploy to staging/production                            |
| `.github/workflows/nplusone-detection.yml` | 115   | push main/develop     | N+1 query detection                                             |

**Overlap:** Backend tests run in `ci-cd.yml`, `tests.yml`, AND `deploy.yml` simultaneously on every push. This wastes ~3x CI minutes.

### Task

1. **Design a consolidated workflow structure:**
   - **`tests.yml`** — Runs on PR and push to develop. Contains: lint, backend tests, frontend tests, security scan, N+1 detection. This is the "CI" part.
   - **`deploy.yml`** — Runs on push to main (or manual trigger). Requires tests to pass first (use `workflow_run` or `needs`). Contains: build, deploy to staging, health check, optional deploy to production.
   - Remove or archive `ci-cd.yml` and `nplusone-detection.yml`.

2. **Merge N+1 detection into `tests.yml`:**
   - Add N+1 detection as a job in `tests.yml` instead of a separate workflow
   - Keep the same detection logic, just move it

3. **Fix `deploy.yml` health check:**
   - Replace the `echo` statements with actual HTTP health checks:
     ```yaml
     - name: Health Check
       run: |
         for i in {1..10}; do
           STATUS=$(curl -s -o /dev/null -w "%{http_code}" ${{ env.APP_URL }}/api/health)
           if [ "$STATUS" = "200" ]; then echo "Health check passed"; exit 0; fi
           echo "Attempt $i: status $STATUS, retrying..."
           sleep 5
         done
         echo "Health check failed after 10 attempts"
         exit 1
     ```

4. **Keep workflow triggers clean:**
   - `tests.yml`: `on: [pull_request, push: branches: [develop]]`
   - `deploy.yml`: `on: [push: branches: [main], workflow_dispatch]`
   - No duplicate triggers on the same branch

5. **Preserve all existing test/build logic** — just reorganize, don't remove any checks.

### Verification

- Validate YAML syntax: `yamllint .github/workflows/*.yml` or use an online validator
- Ensure all jobs from the original 4 files are represented in the new 2 files
- Trigger a test push to verify workflows run correctly

---

<a id="prompt-6"></a>

## PROMPT 6 — P2: Backend Controller, Service & Config Cleanup (BE-016, BE-021, BE-036, BE-039)

> **Issues:** BE-016 (MEDIUM), BE-021 (MEDIUM), BE-036 (MEDIUM), BE-039 (MEDIUM)  
> **Risk:** Inline validation inconsistency; cached bookings missing payment data; unclear business rules; silent mail failures  
> **Estimated effort:** 2 hours

### Context

**BE-016 — RoomController uses inline validation**

File: `backend/app/Http/Controllers/RoomController.php`

The `index()` method uses inline `$request->validate([...])` while all other room endpoints use `RoomRequest` FormRequest. This is inconsistent.

**BE-021 — BookingService select() missing payment/refund fields**

File: `backend/app/Services/BookingService.php`

`getUserBookings()` and `getBookingById()` have explicit `select()` calls:

```php
->select(['id', 'room_id', 'user_id', 'check_in', 'check_out', 'status',
          'guest_name', 'guest_email', 'created_at', 'updated_at'])
```

Missing columns added by migration `2026_01_11_000001_add_payment_fields_to_bookings.php`:
`amount`, `payment_intent_id`, `refund_id`, `refund_status`, `refund_amount`, `refund_error`, `cancelled_by`, `cancelled_at`, `cancellation_reason`.

This means any booking list/detail response shows `null` for all payment and refund fields, even when data exists.

**BE-036 — Reviews `booking_id` is nullable + unique (ambiguous business rule)**

The reviews migration adds `booking_id` as nullable with a unique constraint. This means:

- A review CAN exist without a booking (nullable)
- But if it has a booking_id, it must be unique (one review per booking)
- Business question: Should ALL reviews require a booking? (Probably yes for a hostel platform)

**BE-039 — Mail driver defaults to `log`**

File: `backend/config/mail.php` line 17:

```php
'default' => env('MAIL_MAILER', 'log'),
```

If `MAIL_MAILER` is not set in production `.env`, users never receive any emails.

### Task

1. **BE-016 — Create `ListRoomsRequest` FormRequest:**

   ```bash
   php artisan make:request ListRoomsRequest
   ```

   - Move the validation rules from `RoomController::index()` into the FormRequest
   - Update `index()` to type-hint `ListRoomsRequest` instead of `Request`

2. **BE-021 — Fix BookingService select():**
   - Add the missing payment/refund columns to BOTH `select()` calls in `getUserBookings()` and `getBookingById()`
   - Add: `'amount', 'payment_intent_id', 'refund_id', 'refund_status', 'refund_amount', 'refund_error', 'cancelled_by', 'cancelled_at', 'cancellation_reason'`
   - Alternatively, remove the explicit `select()` entirely if all columns should be returned

3. **BE-036 — Make reviews `booking_id` non-nullable:**
   - Create a new migration that makes `booking_id` non-nullable (with a default for existing null records if any)
   - Only apply if this matches the business logic — if reviews without bookings ARE valid, add a code comment explaining why

4. **BE-039 — Add mail configuration guard:**
   - Change default to `smtp` (or just `env('MAIL_MAILER', 'smtp')`) — forces explicit configuration
   - Add a `MailConfigurationHealthCheck` or add a check in the existing health controller
   - At minimum, add a `.env.example` comment warning about `MAIL_MAILER=log`

### Verification

```bash
cd backend && php artisan test
```

All 698 tests must pass. Test that BookingService returns payment fields.

---

<a id="prompt-7"></a>

## PROMPT 7 — P2: Frontend Architecture Fixes (FE-011, FE-012, FE-016)

> **Issues:** FE-011 (MEDIUM), FE-012 (MEDIUM), FE-016 (LOW)  
> **Risk:** AuthProvider can't use React Router hooks; hard redirects lose all React state; localStorage.clear() wipes unrelated data  
> **Estimated effort:** 2 hours

### Context

**FE-011 — AuthProvider outside RouterProvider**

File: `frontend/src/app/App.tsx`

Current component tree: `ErrorBoundary → Providers(AuthProvider) → Router(RouterProvider)`.

AuthProvider wraps RouterProvider, meaning AuthProvider **cannot use** `useNavigate()`, `useLocation()`, or any React Router hooks. If AuthProvider needs to redirect on auth state changes (token expiry, logout), it cannot use the router.

**FE-012 — `window.location.href = '/login'` hard redirect**

File: `frontend/src/shared/lib/api.ts` line ~135:

```typescript
window.location.href = "/login";
```

This is in the axios 401 response interceptor. A full page reload loses all React component state, in-memory data, form inputs, etc. Should use React Router navigation.

**Note:** This is tricky because the axios interceptor runs outside the React component tree. You need a navigation service pattern — a module-level `navigate` function that the Router sets up.

**FE-016 — `localStorage.clear()` wipes ALL localStorage**

File: `frontend/src/shared/lib/api.ts` lines ~128-129:

```typescript
sessionStorage.clear();
localStorage.clear();
```

This clears EVERYTHING in both storages, including any user preferences, cached UI state, or data from other features. Should only clear auth-related keys.

### Task

1. **FE-011 — Move AuthProvider inside RouterProvider:**
   - Create a layout component that wraps children with `AuthProvider`
   - Use React Router's layout routes so AuthProvider is inside the router tree
   - AuthProvider can now use `useNavigate()` if needed
   - Example structure:

     ```tsx
     // App.tsx
     <ErrorBoundary>
       <RouterProvider router={router} />  // Router is outermost
     </ErrorBoundary>

     // In router config, use a layout route:
     {
       element: <AuthProvider><Outlet /></AuthProvider>,
       children: [/* all routes */]
     }
     ```

2. **FE-012 — Create a navigation service for programmatic navigation outside React:**
   - Create `frontend/src/shared/lib/navigation.ts`:

     ```typescript
     import type { NavigateFunction } from "react-router-dom";

     let _navigate: NavigateFunction | null = null;

     export function setNavigate(nav: NavigateFunction) {
       _navigate = nav;
     }

     export function appNavigate(to: string) {
       if (_navigate) {
         _navigate(to);
       } else {
         // Fallback only if router not yet initialized
         window.location.href = to;
       }
     }
     ```

   - In a root-level component inside the Router, call `setNavigate(useNavigate())` via `useEffect`
   - Replace `window.location.href = '/login'` in `api.ts` with `appNavigate('/login')`

3. **FE-016 — Replace `localStorage.clear()` with specific key removal:**
   - Replace:
     ```typescript
     sessionStorage.clear();
     localStorage.clear();
     ```
   - With:
     ```typescript
     const AUTH_KEYS = ["auth_token", "user", "refresh_token"];
     AUTH_KEYS.forEach((key) => {
       localStorage.removeItem(key);
       sessionStorage.removeItem(key);
     });
     ```
   - Adjust key names to match what your app actually stores

### Verification

```bash
cd frontend && npx vitest run && npx tsc --noEmit
```

All 90 tests must pass. TypeScript must compile cleanly. Manual test:

- Login → navigate around → simulate 401 → should redirect to login without full page reload
- Check that non-auth localStorage data survives a logout

---

<a id="prompt-8"></a>

## PROMPT 8 — P2: DevOps Hardening (DV-007, DV-017, DV-018)

> **Issues:** DV-007 (MEDIUM), DV-017 (LOW), DV-018 (LOW)  
> **Risk:** Silent failures in Docker and CI hide real problems  
> **Estimated effort:** 1 hour

### Context

**DV-007 — `|| true` swallows failures in docker-compose.yml**

File: `docker-compose.yml`

Backend command (~L83):

```yaml
command: bash -lc "composer install || true; php artisan key:generate || true; php artisan migrate --force || true; php artisan serve --host=0.0.0.0 --port=8000"
```

Frontend command (~L95):

```yaml
command: bash -lc "npm install || true; npm run dev -- --host 0.0.0.0"
```

If `composer install` or `npm install` fails (e.g., network issue, incompatible package), the container silently continues with broken dependencies.

**DV-017 — `npm audit || true` suppresses all audit failures**

File: `.github/workflows/ci-cd.yml` line ~290:

```yaml
run: npm audit --audit-level=moderate || true
```

Additionally, the job has `continue-on-error: true` (L268). Vulnerable dependencies never block builds.

**DV-018 — Post-deployment health check is just `echo`**

File: `.github/workflows/deploy.yml` lines ~222-228:

```yaml
- name: ✅ Post-deployment Health Check
  run: |
    echo "Verifying Redis connectivity..."
    echo "Verifying database connectivity..."
    echo "All checks passed!"
```

No actual HTTP requests — deployment is marked "successful" without verification.

### Task

1. **DV-007 — Remove `|| true` from docker-compose.yml:**
   - Backend: use `set -e` and proper error handling:
     ```yaml
     command: bash -lc "set -e; composer install --no-interaction --prefer-dist; php artisan key:generate --force; php artisan migrate --force; php artisan serve --host=0.0.0.0 --port=8000"
     ```
   - Frontend:
     ```yaml
     command: bash -lc "set -e; npm install; npm run dev -- --host 0.0.0.0"
     ```
   - This way, if any step fails, the container stops and reports the error

2. **DV-017 — Make npm audit meaningful:**
   - Remove `|| true` from the npm audit command
   - Remove `continue-on-error: true` from the job (or keep it as a warning, not silent)
   - Use `--audit-level=high` to only fail on high/critical vulnerabilities:
     ```yaml
     run: npm audit --audit-level=high
     ```
   - Alternatively, allow the audit to fail but make it visible:
     ```yaml
     continue-on-error: true # Keep as warning
     # But remove || true so the step itself shows failure status
     run: npm audit --audit-level=moderate
     ```

3. **DV-018 — Add real health check to deploy.yml:**
   - Replace echo statements with actual HTTP verification:
     ```yaml
     - name: Post-deployment Health Check
       run: |
         APP_URL="${{ env.APP_URL || 'http://localhost:8000' }}"
         echo "Checking $APP_URL/api/health..."
         for i in $(seq 1 10); do
           STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/api/health" 2>/dev/null || echo "000")
           if [ "$STATUS" = "200" ]; then
             echo "✅ Health check passed (attempt $i)"
             exit 0
           fi
           echo "⏳ Attempt $i: status $STATUS, retrying in 5s..."
           sleep 5
         done
         echo "❌ Health check failed after 10 attempts"
         exit 1
     ```

### Verification

- Test docker-compose locally: `docker-compose up --build`
- Verify containers fail properly if dependencies are broken
- Validate YAML syntax of workflow files

---

<a id="prompt-9"></a>

## PROMPT 9 — P2: Test Coverage — Auth & Security (TST-003, TST-004, TST-005)

> **Issues:** TST-003 (HIGH), TST-004 (MEDIUM), TST-005 (MEDIUM)  
> **Risk:** Untested auth flows; FK violations pass in tests but fail in production; CSRF not verified  
> **Estimated effort:** 3 hours

### Context

**TST-003 — No password reset / forgot password tests**

Zero tests exist for the password reset flow. Search for `password.*reset`, `PasswordReset`, `forgot.*password` in `backend/tests/` returns no results. This is a critical auth flow that is completely untested.

**TST-004 — `DB_FOREIGN_KEYS=false` in phpunit.xml**

File: `backend/phpunit.xml` line 29:

```xml
<env name="DB_FOREIGN_KEYS" value="false"/>
```

Foreign key constraints are disabled during tests. This means tests that create records with invalid foreign keys (e.g., booking with non-existent room_id) will pass during testing but fail in production (where the FK migration is applied).

**TST-005 — No CSRF protection verification tests**

File: `backend/tests/TestCase.php` line 17 globally disables `VerifyCsrfToken` middleware. While this is common for API testing (API uses token auth, not CSRF), there are no tests that specifically verify CSRF protection works for session-based endpoints.

### Task

1. **TST-003 — Write password reset tests:**
   - Create `backend/tests/Feature/Auth/PasswordResetTest.php`
   - Test cases:
     - `test_user_can_request_password_reset_email` — POST `/api/forgot-password` with valid email → 200
     - `test_password_reset_requires_valid_email` — POST with invalid email → 422
     - `test_password_reset_with_nonexistent_email` — POST with non-registered email → 200 (don't reveal if email exists)
     - `test_user_can_reset_password_with_valid_token` — POST `/api/reset-password` → 200
     - `test_password_reset_fails_with_invalid_token` — POST with bad token → 422
     - `test_password_reset_fails_with_expired_token` — Token older than timeout → fails
   - Note: Check what password reset routes exist in `routes/api.php` or `routes/auth.php` first. If none exist, note this as "endpoint not implemented" and create a placeholder test file.

2. **TST-004 — Enable foreign keys in test environment:**
   - Change `phpunit.xml`:
     ```xml
     <env name="DB_FOREIGN_KEYS" value="true"/>
     ```
   - Run full test suite: `php artisan test`
   - Fix any tests that fail due to FK violations:
     - Ensure factories create parent records before children
     - Use `Room::factory()->create()` before `Booking::factory()->create()`
     - Check that `setUp()` methods create required dependencies
   - If SQLite FK support is limited, consider using `RefreshDatabase` with proper ordering or adding a separate test suite for FK validation

3. **TST-005 — Add CSRF verification test:**
   - Create `backend/tests/Feature/Security/CsrfProtectionTest.php`
   - Test cases:
     - `test_web_routes_require_csrf_token` — POST to a web route without CSRF token → 419
     - `test_api_routes_use_token_auth_not_csrf` — POST to API route with Bearer token works without CSRF
     - `test_csrf_token_endpoint_returns_token` — GET `/sanctum/csrf-cookie` sets XSRF-TOKEN cookie
   - **Important:** These tests MUST NOT use `WithoutMiddleware` or `$this->withoutMiddleware(VerifyCsrfToken::class)`. They need to test WITH the middleware active.

### Verification

```bash
cd backend && php artisan test
```

All existing tests must still pass. New tests should add 10-15 additional test cases.

---

<a id="prompt-10"></a>

## PROMPT 10 — P2: Test Coverage — Validation & Profile (TST-006, TST-008)

> **Issues:** TST-006 (MEDIUM), TST-008 (LOW)  
> **Risk:** Edge cases and profile features untested  
> **Estimated effort:** 2 hours

### Context

**TST-006 — No API validation edge-case tests**

Room validation tests exist (`RoomValidationTest.php`), but no dedicated tests for:

- Malformed JSON bodies (invalid JSON syntax)
- Wrong `Content-Type` headers (sending form data to JSON endpoint)
- Oversized payloads
- Booking creation validation (invalid dates, missing fields, past dates)
- Contact form validation edge cases
- Auth registration validation (weak passwords, duplicate emails, missing fields)

**TST-008 — No user profile / account management tests**

There are model-level email change tests in `EmailVerificationTest.php`, but no HTTP endpoint tests for:

- Viewing own profile
- Updating profile information
- Changing password (authenticated)
- Deleting account

### Task

1. **TST-006 — Write API validation edge-case tests:**
   - Create `backend/tests/Feature/Validation/ApiValidationTest.php`
   - Test cases:
     - `test_malformed_json_returns_400` — POST with `{invalid json` → 400
     - `test_wrong_content_type_returns_error` — POST with `text/plain` to JSON endpoint
     - `test_booking_requires_future_dates` — check_in in the past → 422
     - `test_booking_check_out_must_be_after_check_in` — check_out before check_in → 422
     - `test_registration_requires_strong_password` — weak password → 422
     - `test_registration_rejects_duplicate_email` — existing email → 422
     - `test_contact_form_requires_valid_email` — invalid email format → 422
     - `test_empty_request_body_returns_422` — POST with empty body → 422
   - Check existing routes in `routes/api.php` to know which endpoints to test

2. **TST-008 — Write user profile endpoint tests:**
   - Create `backend/tests/Feature/User/ProfileTest.php`
   - First check which profile endpoints exist in routes. If endpoints exist:
     - `test_user_can_view_own_profile` — GET `/api/user` or similar
     - `test_authenticated_user_required_for_profile` — Without auth → 401
     - `test_user_can_update_name` — PUT with new name
     - `test_user_can_change_password` — POST with old+new password
     - `test_password_change_requires_current_password` — Without old password → 422
   - If profile endpoints DON'T exist, document this as "endpoint not implemented" and write tests for what IS available (e.g., the auth response that includes user data)

### Verification

```bash
cd backend && php artisan test
```

All existing tests must pass. New tests should add 10-15 additional test cases.

---

<a id="prompt-11"></a>

## PROMPT 11 — P3: Fix Migration File Ordering (BE-037)

> **Issues:** BE-037 (LOW)  
> **Risk:** Confusing for developers; works only because FK constraints were historically disabled  
> **Estimated effort:** 30 minutes

### Context

Migration file dates are chronologically backwards:

- `2025_05_09_074429_create_bookings_table.php` — bookings table created first
- `2025_08_19_120000_create_rooms_table.php` — rooms table created 3 months LATER

Bookings depends on rooms (`room_id` FK), but the rooms migration runs AFTER the bookings migration. This historically worked because `DB_FOREIGN_KEYS=false` was set, but with FK constraints now enabled (from the `2026_02_09_000000_add_foreign_key_constraints.php` migration), this ordering could cause issues on a fresh database setup.

**Warning:** Renaming migration files is risky if the database already has a `migrations` table tracking which files have run. This prompt should only be applied to **new/fresh** deployments, or must also update the `migrations` table.

### Task

1. **Check the full migration file listing:**

   ```bash
   ls backend/database/migrations/
   ```

   Identify all files with incorrect date ordering relative to their dependencies.

2. **Option A: Rename files (preferred for new projects)**
   - Ensure `create_locations_table` comes before `create_rooms_table`
   - Ensure `create_rooms_table` comes before `create_bookings_table`
   - Ensure `create_users_table` comes before everything that references `user_id`
   - Use date prefixes that reflect correct dependency order

3. **Option B: Create a migration that verifies order (safer for existing DBs)**
   - Create a migration `2026_02_10_000000_verify_migration_order.php` that:
     - Checks if all required tables exist
     - Documents the intended order in comments
     - Skips if tables already exist

4. **Test with fresh database:**
   ```bash
   php artisan migrate:fresh --seed
   php artisan test
   ```

### Verification

```bash
cd backend && php artisan migrate:fresh && php artisan test
```

All tests must pass on a completely fresh database. No migration errors about missing tables or FK constraints.

---

## Summary

| Prompt    | Priority | Issues                          | Description                                                 | Effort          |
| --------- | -------- | ------------------------------- | ----------------------------------------------------------- | --------------- |
| 1         | P1       | BE-027, BE-028                  | Fix middleware bugs (ThrottleApiRequests + SecurityHeaders) | 30 min          |
| 2         | P1       | BE-031, BE-032, BE-033, TST-007 | Consolidate health system & fix routes                      | 2 hours         |
| 3         | P1       | BE-019                          | Consolidate room availability services                      | 3 hours         |
| 4         | P1       | BE-013                          | ContactController persistence                               | 1.5 hours       |
| 5         | P1       | DV-013                          | Consolidate CI/CD workflows                                 | 3 hours         |
| 6         | P2       | BE-016, BE-021, BE-036, BE-039  | Backend controller, service & config cleanup                | 2 hours         |
| 7         | P2       | FE-011, FE-012, FE-016          | Frontend architecture fixes                                 | 2 hours         |
| 8         | P2       | DV-007, DV-017, DV-018          | DevOps hardening                                            | 1 hour          |
| 9         | P2       | TST-003, TST-004, TST-005       | Test coverage — auth & security                             | 3 hours         |
| 10        | P2       | TST-006, TST-008                | Test coverage — validation & profile                        | 2 hours         |
| 11        | P3       | BE-037                          | Fix migration file ordering                                 | 30 min          |
| **Total** |          | **22 issues**                   |                                                             | **~20.5 hours** |

### Issues NOT included (already fixed but mislabeled in Section 4 of AUDIT_REPORT.md)

The following were previously listed as unfixed but are **confirmed fixed** during verification:

- **DV-014** — `backend/.github/workflows/laravel.yml` was deleted
- **DV-015** — Redundant `composer update` was removed from `tests.yml`
- **SEC-003 through SEC-010** — All 8 security config issues were fixed in Prompts 8, 14, and 16
