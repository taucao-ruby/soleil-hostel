# Soleil Hostel — Audit Fix Prompts

> **Status: ✅ ALL 16 PROMPTS COMPLETED** — 54/61 issues fixed (89%).  
> 7 remaining items are deferred low-impact issues (BE-019 partial, BE-037, DV-013, etc.)

> **How to use:** Copy each prompt below and send it to an AI model to execute one at a time.  
> Each prompt is **self-contained** — it does not depend on any other prompt.  
> Execution order: P0 → P1 → P2 → P3.  
> After each prompt, run backend tests (`php artisan test`) and frontend tests (`npx vitest run`) to verify.

---

## Table of Contents

- [PROMPT 1 — P0: Fix env() → config() (CRITICAL)](#prompt-1)
- [PROMPT 2 — P0: Fix Database Mismatch (CRITICAL)](#prompt-2)
- [PROMPT 3 — P0: Fix Redis & Docker Security (CRITICAL)](#prompt-3)
- [PROMPT 4 — P0: Fix CI/CD Security Job (CRITICAL)](#prompt-4)
- [PROMPT 5 — P0: Frontend Cleanup — Remove Bogus Deps & Consolidate API (CRITICAL)](#prompt-5)
- [PROMPT 6 — P1: Delete Dead Code & Fix Routes](#prompt-6)
- [PROMPT 7 — P1: Fix AdminBookingController & Auth Issues](#prompt-7)
- [PROMPT 8 — P1: Consolidate Duplicate Services](#prompt-8)
- [PROMPT 9 — P1: Fix Frontend — 404 Route, process.env, sanitizeInput, CSP](#prompt-9)
- [PROMPT 10 — P1: Security Hardening — Session, Dockerfiles, .gitignore](#prompt-10)
- [PROMPT 11 — P2: Fix Models — Casts, Relationships, Fillable](#prompt-11)
- [PROMPT 12 — P2: Standardize API Response Format](#prompt-12)
- [PROMPT 13 — P2: Frontend Cleanup — Duplicate Files, Types, Temp Files](#prompt-13)
- [PROMPT 14 — P2: Frontend Unit Tests](#prompt-14)
- [PROMPT 15 — P2: Docker Multi-Stage Builds & CI Consolidation](#prompt-15)
- [PROMPT 16 — P3: Low-Priority Cleanup](#prompt-16)

---

<a id="prompt-1"></a>

## PROMPT 1 — P0: Fix env() → config() (CRITICAL)

**Issue IDs:** BE-023, BE-024, BE-025, SEC-001  
**Risk:** `env()` returns `null` when config is cached (`php artisan config:cache`). This breaks CORS and authentication in production.

````
In the Laravel project at `backend/`, there are 7 places using `env()` directly in middleware/controllers instead of `config()`. When running `php artisan config:cache` in production, `env()` returns `null` → breaks CORS and authentication.

### Requirements:

**Step 1:** Add new config key to `backend/config/sanctum.php`:
- Add `'cookie_name' => env('SANCTUM_COOKIE_NAME', 'soleil_token'),` to the config array

**Step 2:** Add new config key to `backend/config/cors.php` (create the file if it doesn't exist):
- Create file `backend/config/cors.php` with content:
```php
<?php
return [
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'),
];
````

**Step 3:** Fix the following 7 files, replacing `env()` with `config()`:

1. `backend/app/Http/Middleware/Cors.php` line 14:
   - FROM: `env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')`
   - TO: `config('cors.allowed_origins', 'http://localhost:5173')`

2. `backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php` line 33:
   - FROM: `env('SANCTUM_COOKIE_NAME', 'soleil_token')`
   - TO: `config('sanctum.cookie_name', 'soleil_token')`

3. `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` lines 127, 224, 258:
   - FROM: `env('SANCTUM_COOKIE_NAME', 'soleil_token')` (3 occurrences)
   - TO: `config('sanctum.cookie_name', 'soleil_token')` (all 3 occurrences)

4. `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` lines 113, 140:
   - FROM: `env('SANCTUM_COOKIE_NAME', 'soleil_token')` (2 occurrences)
   - TO: `config('sanctum.cookie_name', 'soleil_token')` (both occurrences)

**Step 4:** After fixing, run `php artisan test` to verify no tests are broken.

**Notes:**

- DO NOT modify `env()` in `config/*.php` files — that is the correct place to use `env()`.
- ONLY fix `env()` in middleware, controllers, and services.
- Keep the same default values when switching to `config()`.

```

---

{{{{{{{{{<a id="prompt-2"></a>
## PROMPT 2 — P0: Fix Database Mismatch (CRITICAL)

**Issue ID:** BE-034
**Risk:** Docker uses MySQL, config defaults to SQLite, code/migrations assume PostgreSQL. DB-level protections are silently absent.

```

The project has a critical database mismatch issue:

- `docker-compose.yml` uses `mysql:8.0`
- `backend/config/database.php` defaults to `sqlite`
- Code & migrations assume PostgreSQL (exclusion constraints, btree-gist, jsonb)

### Requirements:

**Step 1:** Fix `docker-compose.yml` — Switch MySQL to PostgreSQL:

Replace the `db:` service from:

```yaml
db:
  image: mysql:8.0
  restart: unless-stopped
  environment:
    MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-root}
    MYSQL_DATABASE: ${MYSQL_DATABASE:-homestay}
    MYSQL_PASSWORD: ${MYSQL_PASSWORD:-root}
  ports:
    - "3306:3306"
  volumes:
    - dbdata:/var/lib/mysql
```

To:

```yaml
db:
  image: postgres:16-alpine
  restart: unless-stopped
  environment:
    POSTGRES_USER: ${DB_USERNAME:-soleil}
    POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    POSTGRES_DB: ${DB_DATABASE:-homestay}
  ports:
    - "127.0.0.1:5432:5432"
  volumes:
    - dbdata:/var/lib/postgresql/data
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-soleil}"]
    interval: 5s
    timeout: 3s
    retries: 5
```

**Step 2:** Update backend service environment in `docker-compose.yml`:

- `DB_CONNECTION: ${DB_CONNECTION:-pgsql}`
- `DB_HOST: ${DB_HOST:-db}`
- `DB_PORT: ${DB_PORT:-5432}`

**Step 3:** Fix `backend/config/database.php`:

- Change `'default' => env('DB_CONNECTION', 'sqlite')` to `'default' => env('DB_CONNECTION', 'pgsql')`

**Step 4:** Update `backend/.env.example`:

- Change `DB_CONNECTION=mysql` to `DB_CONNECTION=pgsql`
- Change `DB_PORT=3306` to `DB_PORT=5432`
- Change `DB_USERNAME=root` to `DB_USERNAME=soleil`
- Change `DB_PASSWORD=` to `DB_PASSWORD=secret`

**Step 5:** Rename volume `dbdata` if needed (or keep as is).

**Notes:**

- Keep SQLite for the test environment (phpunit.xml already configures `DB_CONNECTION=sqlite`)
- PostgreSQL-specific migrations (exclusion constraints, btree-gist, jsonb) will work correctly after switching
- DO NOT modify migration files

```

---

<a id="prompt-3"></a>
## PROMPT 3 — P0: Fix Redis & Docker Security (CRITICAL)

}}}}}}}}}**Issue IDs:** DV-001, DV-002, DV-003, DV-009, DV-010, SEC-002
**Risk:** Redis has no authentication, ports are exposed to the network, default password is root.

```

Fix security issues in Docker and Redis configuration:

### Requirements:

**Step 1:** Fix `redis.conf`:

a) Change `bind 0.0.0.0` to `bind 127.0.0.1` (or remove the bind directive since Docker network will self-limit)

b) Add the following line (replace with a strong password):

```
requirepass ${REDIS_PASSWORD:-soleil_redis_secret_2026}
```

Note: Redis conf does not support env variable interpolation. Use instead:

```
requirepass soleil_redis_secret_2026
```

c) Remove the duplicate `always-show-logo` line:

- Remove 1 of the 2 `always-show-logo` lines (keep `always-show-logo no`)

**Step 2:** Fix `docker-compose.yml`:

a) Redis service — Change port binding from:

```yaml
ports:
  - "6379:6379"
```

To:

```yaml
ports:
  - "127.0.0.1:6379:6379"
```

b) Backend service — Add `REDIS_PASSWORD` env:

```yaml
REDIS_PASSWORD: ${REDIS_PASSWORD:-soleil_redis_secret_2026}
```

c) Redis healthcheck — Add password:

```yaml
healthcheck:
  test: ["CMD", "redis-cli", "-a", "soleil_redis_secret_2026", "ping"]
```

**Step 3:** Update `backend/config/database.php` — redis config section:

- Ensure `'password' => env('REDIS_PASSWORD', null),` exists in the redis config

**Step 4:** Update `backend/.env.example`:

- Add `REDIS_PASSWORD=soleil_redis_secret_2026`

**Notes:**

- Do not change application logic
- If local dev doesn't use Docker, you can set REDIS_PASSWORD=null in the local .env

```

---

<a id="prompt-4"></a>
## PROMPT 4 — P0: Fix CI/CD Security Job (CRITICAL)

**Issue ID:** DV-012
**Risk:** The `security:` job in `.github/workflows/tests.yml` is not under `jobs:` → GitHub Actions completely ignores it. Gitleaks and composer audit never run.

```

The file `.github/workflows/tests.yml` has a YAML indentation error — the `security:` job is a top-level key instead of being nested under `jobs:`.

### Requirements:

**Step 1:** Open `.github/workflows/tests.yml`

**Step 2:** Find the following block (around lines 155-182):

```yaml
security:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    ...
```

This block is **outside** of `jobs:`. It needs to be indented inside `jobs:`:

```yaml
jobs:
  test:
    ...

  static-analysis:
    ...

  security:                          # ← must be indented 2 spaces inside jobs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      ...
```

**Step 3:** Ensure the ENTIRE content of the `security:` job is indented by an additional 2 spaces compared to its current state.

**Step 4:** Validate YAML syntax after fixing (use an online tool or `yamllint`).

**Notes:**

- Only fix indentation, DO NOT change logic or step content
- The `static-analysis:` job above may also have the same issue — check it as well
- Goal: all jobs must be nested under the `jobs:` key

```

---

<a id="prompt-5"></a>
## PROMPT 5 — P0: Frontend — Remove Bogus Deps & Consolidate API Client (CRITICAL)

**Issue IDs:** FE-001, FE-005, FE-006
**Risk:** Bogus npm packages, 3 different API clients, legacy auth uses localStorage.

```

The frontend has 3 critical issues to fix simultaneously:

### Requirements:

**Step 1:** Remove bogus npm packages:

```bash
cd frontend
npm uninstall dom route
```

The packages `dom` (0.0.3) and `route` (0.2.5) are abandoned packages and are not imported anywhere in the source code.

**Step 2:** Identify the canonical API client:

- **KEEP:** `frontend/src/shared/lib/api.ts` — this is the canonical client (httpOnly cookies, CSRF, correct base URL)
- **DELETE:** `frontend/src/services/api.ts` — duplicate, missing Content-Type header
- **DELETE:** `frontend/src/lib/api.ts` — uses `process.env` (broken in Vite), old Bearer token strategy

**Step 3:** Delete the legacy auth service:

- **DELETE:** `frontend/src/services/auth.ts` — contains legacy methods that store tokens in localStorage (XSS risk)

**Step 4:** After deleting the 3 files above, find all imports in `frontend/src/` pointing to:

- `@/services/api` or `../services/api` → change to `@/shared/lib/api`
- `@/lib/api` or `../lib/api` → change to `@/shared/lib/api`
- `@/services/auth` or `../services/auth` → remove import, use the `useAuth()` hook from AuthContext instead

Use grep/search to find all affected files before making changes.

**Step 5:** Verify build:

```bash
cd frontend
npx tsc --noEmit
npm run build
```

**Notes:**

- `frontend/src/shared/lib/api.ts` is the only client to keep
- If any file imports from `services/auth.ts` using functions like `login()`, `register()`, check whether `features/auth/auth.api.ts` already has a replacement
- Goal: a single axios instance, no tokens in localStorage

```

---

<a id="prompt-6"></a>
## PROMPT 6 — P1: Delete Dead Code & Fix Routes

**Issue IDs:** BE-009, BE-017, BE-030, FE-008, BE-029

```

Delete dead code and fix route issues in the project.

### Requirements:

**Step 1:** Delete dead backend files:

- `backend/app/Http/Controllers/ReviewController.php` — controller returns Blade views but has no web routes, column name mismatch
- `backend/app/Http/Controllers/BookingControllerExample.php` — example controller, not routed
- `backend/test_sanctum_find.php` — debug script

**Step 2:** Delete dead frontend files:

- `frontend/src/pages/Auth/LoginPage.tsx` — legacy login page using old Bearer token API, not in the router
- Delete the `frontend/src/pages/Auth/` directory if empty after deletion

**Step 3:** Fix unified auth routes — Add authentication middleware:

Open `backend/routes/api.php`, find the unified auth routes block (around lines 137-141):

```php
Route::prefix('auth/unified')->group(function () {
    Route::get('/me', [UnifiedAuthController::class, 'me']);
    Route::post('/logout', [UnifiedAuthController::class, 'logout']);
    Route::post('/logout-all', [UnifiedAuthController::class, 'logoutAll']);
});
```

Add `auth:sanctum` middleware:

```php
Route::prefix('auth/unified')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UnifiedAuthController::class, 'me']);
    Route::post('/logout', [UnifiedAuthController::class, 'logout']);
    Route::post('/logout-all', [UnifiedAuthController::class, 'logoutAll']);
});
```

**Step 4:** Run `php artisan test` to verify nothing is broken.

**Notes:**

- Do not create new review routes — the review feature is not ready yet
- The unified auth routes were previously accessible to anyone without authentication

```

---

<a id="prompt-7"></a>
## PROMPT 7 — P1: Fix AdminBookingController & Auth Issues

**Issue IDs:** BE-011, BE-012, BE-013, BE-014, BE-026

```

Fix controller issues: pagination, validation, security.

### Requirements:

**Step 1:** Fix AdminBookingController pagination:

File: `backend/app/Http/Controllers/AdminBookingController.php`

In the `index()` method, replace `->get()` with `->paginate(50)`:

```php
// FROM:
->orderBy('created_at', 'desc')
->get();

// TO:
->orderBy('created_at', 'desc')
->paginate(50);
```

Update the return response accordingly to handle paginated data.

**Step 2:** Fix AdminBookingController restoreBulk() — add validation:

Instead of using `request()->input('ids', [])` without validation, add validation:

```php
public function restoreBulk(Request $request): JsonResponse
{
    $validated = $request->validate([
        'ids' => 'required|array|min:1',
        'ids.*' => 'integer|exists:bookings,id',
    ]);

    // Use $validated['ids'] instead of $request->input('ids')
    ...
}
```

**Step 3:** Fix VerifyBookingOwnership middleware — add admin bypass:

File: `backend/app/Http/Middleware/VerifyBookingOwnership.php`

Add admin bypass condition:

```php
// FROM:
if ($booking->user_id !== auth()->id()) {
    abort(403, 'You do not own this booking.');
}

// TO:
if ($booking->user_id !== auth()->id() && !auth()->user()?->isAdmin()) {
    abort(403, 'You do not own this booking.');
}
```

**Step 4:** AuthController — Do not return the full User model:

File: `backend/app/Http/Controllers/AuthController.php` (legacy controller)

Replace `'user' => $user` with explicit field selection:

```php
'user' => [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'role' => $user->role,
]
```

Or create a `UserResource` if one doesn't exist.

**Step 5:** Run `php artisan test` to verify.

```

---

<a id="prompt-8"></a>
## PROMPT 8 — P1: Consolidate Duplicate Services

**Issue IDs:** BE-018, BE-019, BE-020

```

The backend has 3 groups of duplicate business logic that need consolidation.

### Requirements:

**Step 1:** Fix duplicate refund calculation:

Two places compute the same refund policy:

- `backend/app/Models/Booking.php` — method `calculateRefundAmount()`
- `backend/app/Services/CancellationService.php` — method `calculateRefundAmount()`

**Action:** Remove `calculateRefundAmount()` from `CancellationService`. Replace with delegation:

```php
// In CancellationService, instead of computing locally:
$refundAmount = $booking->calculateRefundAmount();
```

**Step 2:** Fix duplicate availability services:

Two services both implement `getAllRoomsWithAvailability()`:

- `backend/app/Services/RoomService.php`
- `backend/app/Services/RoomAvailabilityService.php`

**Action:**

- `RoomService` should delegate availability logic to `RoomAvailabilityService`
- Remove methods `getAllRoomsWithAvailability()` and `isRoomAvailable()` from `RoomService`
- Inject `RoomAvailabilityService` into `RoomService` if needed

**Step 3:** Fix duplicate cancellation paths:

`BookingService::cancelBooking()` exists in parallel with `CancellationService::cancel()`:

- `BookingService::cancelBooking()` does NOT handle refunds
- `CancellationService::cancel()` has a complete refund + idempotency flow

**Action:** In `BookingService::cancelBooking()`, delegate to `CancellationService`:

```php
public function cancelBooking(Booking $booking): bool
{
    return app(CancellationService::class)->cancel($booking);
}
```

Or remove `BookingService::cancelBooking()` entirely if it has no callers.
Check all callers by grepping `cancelBooking` before deciding.

**Step 4:** Fix null pointer in `RoomAvailabilityService`:

File: `backend/app/Services/RoomAvailabilityService.php` around line 99:

```php
// FROM:
Room::find($roomId)->activeBookings()

// TO:
$room = Room::find($roomId);
if (!$room) {
    return false; // or throw an exception depending on context
}
$room->activeBookings()
```

**Step 5:** Run `php artisan test` to verify — pay special attention to tests related to booking, cancellation, and availability.

```

---

<a id="prompt-9"></a>
## PROMPT 9 — P1: Fix Frontend — 404 Route, process.env, sanitizeInput, CSP

**Issue IDs:** FE-002, FE-007, FE-020, FE-021, SEC-004

```

Fix 4 important frontend issues.

### Requirements:

**Step 1:** Add a 404 catch-all route:

File: `frontend/src/app/router.tsx`

Create a simple `NotFoundPage` component (or in `frontend/src/pages/NotFoundPage.tsx`):

```tsx
export default function NotFoundPage() {
  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh]">
      <h1 className="text-4xl font-bold text-gray-900 mb-4">404</h1>
      <p className="text-gray-600 mb-8">
        The page you are looking for does not exist.
      </p>
      <a href="/" className="text-blue-600 hover:underline">
        Go to homepage
      </a>
    </div>
  );
}
```

In the router config, add a catch-all route at the end:

```tsx
{ path: '*', element: <NotFoundPage /> }
```

**Step 2:** Fix `process.env` → `import.meta.env`:

File: `frontend/src/lib/api.ts` (if not already deleted in Prompt 5):

- Line 46: Change `process.env.REACT_APP_API_URL` → `import.meta.env.VITE_API_URL`

File: `frontend/src/utils/webVitals.ts`:

- Change `process.env.NODE_ENV` → `import.meta.env.DEV` (or `import.meta.env.MODE`)

**Step 3:** Remove `sanitizeInput()` from API submissions:

File: `frontend/src/features/auth/LoginPage.tsx` around line 72:

- Remove the `sanitizeInput()` wrapper on email/password before sending to the API
- Reason: `sanitizeInput()` HTML-encodes data before submission → the DB stores `&amp;` instead of `&`
- React auto-escapes when rendering, and server-side validation handles XSS

File: `frontend/src/features/booking/BookingForm.tsx` around lines 101-106:

- Similarly, remove `sanitizeInput()` from form submission data

**Step 4:** Fix CSP plugin — Remove `unsafe-inline` fallback:

File: `frontend/vite-plugin-csp-nonce.js` around lines 40-47:

Remove or comment out the CSP meta tag injection block:

```javascript
// DELETE THIS BLOCK:
const cspMeta = `
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';">
`;

if (html.includes("</head>")) {
  html = html.replace("</head>", cspMeta + "</head>");
}
```

CSP should be set via HTTP headers (backend middleware already handles this), not via a meta tag with unsafe-inline.

**Step 5:** Verify:

```bash
cd frontend
npx tsc --noEmit
npm run build
```

```

---

<a id="prompt-10"></a>
## PROMPT 10 — P1: Security Hardening

**Issue IDs:** SEC-003, SEC-004, DV-004, DV-019, BE-035, BE-038

```

Perform security hardening for the project.

### Requirements:

**Step 1:** Enable session encryption:

File: `backend/config/session.php` line ~51:

- FROM: `'encrypt' => env('SESSION_ENCRYPT', false),`
- TO: `'encrypt' => env('SESSION_ENCRYPT', true),`

**Step 2:** Set session secure cookie default:

File: `backend/config/session.php` line ~183:

- FROM: `'secure' => env('SESSION_SECURE_COOKIE'),`
- TO: `'secure' => env('SESSION_SECURE_COOKIE', true),`

**Step 3:** Add non-root user to backend Dockerfile:

File: `backend/Dockerfile`

Add before CMD/ENTRYPOINT:

```dockerfile
# Create non-root user
RUN groupadd -r soleil && useradd -r -g soleil -d /var/www -s /bin/bash soleil
RUN chown -R soleil:soleil /var/www
USER soleil
```

**Step 4:** Add non-root user to frontend Dockerfile:

File: `frontend/Dockerfile`

Add before CMD:

```dockerfile
RUN addgroup -S soleil && adduser -S soleil -G soleil
RUN chown -R soleil:soleil /app
USER soleil
```

(Use addgroup/adduser for Alpine-based images, useradd/groupadd for Debian-based)

**Step 5:** Fix .gitignore — commit composer.lock:

File: `.gitignore` (root)

Remove the line: `composer.lock`

Then:

```bash
cd backend
git add composer.lock
```

**Step 6:** Create migration for foreign key constraints (production):

Create a new migration: `backend/database/migrations/2026_02_09_000000_add_foreign_key_constraints.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add FK when NOT using SQLite (test environment)
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['room_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['room_id']);
        });
    }
};
```

**Step 7:** Run `php artisan test` to verify.

```

---

<a id="prompt-11"></a>
## PROMPT 11 — P2: Fix Models — Casts, Relationships, Fillable

**Issue IDs:** BE-001, BE-002, BE-003, BE-004, BE-005, BE-006, BE-007, BE-008

```

Fix all model issues found in the audit.

### Requirements:

**Step 1:** Fix Review model:

File: `backend/app/Models/Review.php`

a) Add `'booking_id'` to the `$fillable` array
b) Add casts:

```php
protected $casts = [
    'rating' => 'integer',
    'approved' => 'boolean',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];
```

**Step 2:** Fix User model — add reviews() relationship:

File: `backend/app/Models/User.php`

Add method:

```php
public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Review::class);
}
```

(Import `App\Models\Review` if needed)

**Step 3:** Fix Room model — add reviews() relationship:

File: `backend/app/Models/Room.php`

Add method:

```php
public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Review::class);
}
```

**Step 4:** Fix Location and Room models — remove `$guarded`:

Files: `backend/app/Models/Location.php` and `backend/app/Models/Room.php`

Remove the property `protected $guarded = [...]`. Keep `$fillable`.
Laravel docs: use `$fillable` OR `$guarded`, not both.

**Step 5:** Fix Booking model — remove deprecated string constants:

File: `backend/app/Models/Booking.php`

Remove the deprecated constants:

```php
// DELETE THESE LINES:
const STATUS_PENDING = 'pending';
const STATUS_CONFIRMED = 'confirmed';
const STATUS_CANCELLED = 'cancelled';
const ACTIVE_STATUSES = [...];
```

Find all references to `Booking::STATUS_*` and `Booking::ACTIVE_STATUSES` in the codebase.
Replace with `BookingStatus::Pending`, `BookingStatus::Confirmed`, `BookingStatus::Cancelled` respectively.

Check scopes in the Booking model: `scopeCancelled()`, `scopeActive()` — ensure they use the enum.

**Step 6:** Run `php artisan test` after each step to catch regressions early.

```

---

<a id="prompt-12"></a>
## PROMPT 12 — P2: Standardize API Response Format

**Issue ID:** BE-015

```

Standardize the API response format across all auth controllers.

### Current State:

- Legacy AuthController: `{ "data": { "access_token": "..." } }`
- Auth v2 Controller: `{ "token": "...", "user": {...} }`
- HttpOnly Controller: `{ "success": true, "message": "..." }`
- Admin Controller: `{ "success": true, "data": {...} }`

### Requirements:

**Step 1:** Define the standard format:

Success responses:

```json
{
  "success": true,
  "message": "...",
  "data": { ... }
}
```

Error responses (already handled via exception handler):

```json
{
  "success": false,
  "message": "...",
  "errors": { ... }
}
```

**Step 2:** Create a helper trait if one doesn't exist:

File: `backend/app/Traits/ApiResponse.php`

```php
<?php
namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message = 'Error', int $code = 400, $errors = null)
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ]), $code);
    }
}
```

**Step 3:** Apply the trait to the auth controllers:

- `App\Http\Controllers\Auth\AuthController`
- `App\Http\Controllers\Auth\HttpOnlyTokenController`
- `App\Http\Controllers\Auth\UnifiedAuthController`
- `App\Http\Controllers\AuthController` (legacy)
- `App\Http\Controllers\AdminBookingController`

**Step 4:** Refactor each controller to use `$this->success()` and `$this->error()`.

**Notes:**

- Maintain backward compatibility for legacy endpoints (keep the `access_token` key in data)
- Test all auth tests after changes
- If the frontend expects the old format, update frontend response handling accordingly

```

---

<a id="prompt-13"></a>
## PROMPT 13 — P2: Frontend Cleanup — Duplicate Files, Types, Temp Files

**Issue IDs:** FE-009, FE-010, FE-017, FE-018, FE-019

```

Clean up the frontend: delete duplicate files, consolidate types, remove temp files.

### Requirements:

**Step 1:** Delete temp files:

```bash
cd frontend
rm -f temp_app.js temp_app2.js temp_app3.js temp_main.tsx temp_main2.tsx temp_main3.tsx
```

Add to `frontend/.gitignore`:

```
temp_*
```

**Step 2:** Delete duplicate utility files:

```bash
rm -f src/utils/csrf.ts src/utils/security.ts
```

Find all imports pointing to `@/utils/csrf` or `../utils/csrf`:

- Change to `@/shared/utils/csrf`

Find all imports pointing to `@/utils/security` or `../utils/security`:

- Change to `@/shared/utils/security`

If the `src/utils/` directory is empty after deletion, delete the directory too (unless other files like `webVitals.ts` remain).

**Step 3:** Consolidate `Room` type:

Two files define the Room type differently:

- `frontend/src/types/api.ts` — `status: 'available' | 'booked' | 'maintenance'`
- `frontend/src/features/rooms/room.types.ts` — `status: 'available' | 'occupied' | 'maintenance'`

**Action:** Check the backend Room model/migration to determine the correct status values.
Choose 1 source of truth, delete the other. Recommendation: keep `types/api.ts` and import from there.

**Step 4:** Consolidate `AuthResponse` type:

3 places define `AuthResponse`:

- `src/services/auth.ts` (will be deleted in Prompt 5)
- `src/types/api.ts`
- `src/features/auth/auth.api.ts`

**Action:** Keep the definition in `src/features/auth/auth.api.ts` (closest to actual usage).
Update `src/types/api.ts` to re-export or remove the duplicate definition.

**Step 5:** Extract `amenityIcons` shared constant:

Create `frontend/src/features/locations/constants.ts`:

```typescript
export const amenityIcons: Record<string, string> = {
  // Copy from LocationCard.tsx
  wifi: "📶",
  parking: "🅿️",
  // ... all icons
};
```

Update `LocationCard.tsx` and `LocationDetail.tsx` to import from `./constants`.

**Step 6:** Verify: `npx tsc --noEmit && npm run build`

```

---

<a id="prompt-14"></a>
## PROMPT 14 — P2: Frontend Unit Tests

**Issue IDs:** TST-001, TST-002

```

The frontend currently has 0 unit tests. Vitest + @testing-library/react are installed but there are no test files.

### Requirements:

**Step 1:** Set up test infrastructure:

Create `frontend/src/test/setup.ts`:

```typescript
import "@testing-library/jest-dom";
```

Ensure `vite.config.ts` has test config:

```typescript
test: {
  globals: true,
  environment: 'jsdom',
  setupFiles: './src/test/setup.ts',
  exclude: ['**/tests/e2e/**', '**/node_modules/**'],
}
```

Install additional dependencies if missing: `npm install -D @testing-library/jest-dom jsdom`

**Step 2:** Write unit tests for utility functions (highest priority since they are stateless and easy to test):

- `frontend/src/shared/utils/csrf.test.ts` — test getCsrfToken, setCsrfToken
- `frontend/src/shared/utils/security.test.ts` — test escapeHtml, sanitizeInput, etc.

**Step 3:** Write unit tests for booking validation:

Find the booking validation schema file (likely at `features/booking/booking.validation.ts` or `types/api.ts` with Zod schemas).
Write tests for rules: required fields, date validation, capacity limits.

**Step 4:** Write component tests:

- `frontend/src/features/auth/LoginPage.test.tsx` — test render, form submission, error display
- `frontend/src/shared/components/ui/Button.test.tsx` — test variants, disabled state, click handler
- `frontend/src/shared/components/ui/Input.test.tsx` — test value change, error state, label association

**Step 5:** Write API service tests:

- `frontend/src/shared/lib/api.test.ts` — test CSRF token handling, error interceptor, auth redirect

**Step 6:** Verify all tests pass:

```bash
cd frontend
npx vitest run
```

**Minimum target:** 15-20 unit tests covering utilities, validation, and at least 2 components.

```

---

<a id="prompt-15"></a>
## PROMPT 15 — P2: Docker Multi-Stage Builds & CI Consolidation

**Issue IDs:** DV-006, DV-013, DV-014, DV-015

```

Improve Docker builds and the CI/CD pipeline.

### Requirements:

**Step 1:** Backend Dockerfile — Multi-stage build:

Rewrite `backend/Dockerfile`:

```dockerfile
# Stage 1: Install dependencies
FROM php:8.3-cli AS builder
WORKDIR /var/www
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize

# Stage 2: Production image
FROM php:8.3-cli
WORKDIR /var/www
RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*
RUN groupadd -r soleil && useradd -r -g soleil -d /var/www soleil
COPY --from=builder --chown=soleil:soleil /var/www /var/www
USER soleil
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

**Step 2:** Frontend Dockerfile — Multi-stage build:

Rewrite `frontend/Dockerfile`:

```dockerfile
# Stage 1: Dependencies
FROM node:20-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

# Stage 2: Dev server (for docker-compose dev)
FROM node:20-alpine
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN addgroup -S soleil && adduser -S soleil -G soleil
RUN chown -R soleil:soleil /app
USER soleil
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
```

**Step 3:** Create `.dockerignore` for both:

`backend/.dockerignore`:

```
.git
.env
.env.*
node_modules
tests
storage/logs
vendor
```

`frontend/.dockerignore`:

```
.git
node_modules
dist
coverage
playwright-report
test-results
temp_*
```

**Step 4:** CI Consolidation:

Consider merging the 4 overlapping workflows into 2:

- `ci.yml` — Runs on all pushes: lint + test + security + build
- `deploy.yml` — Runs only on `main`: deploy to production

Or at minimum:

- Delete `backend/.github/workflows/laravel.yml` (old PHP 8.1, redundant with tests.yml)
- In `tests.yml`, remove the redundant `composer update` (keep only `composer install`)

**Step 5:** Fix Playwright port mismatch:

File: `frontend/playwright.config.ts`

- Change `baseURL` to port `4173` (match the webServer preview port)
- OR change the webServer command to `npm run dev` port `5173`

```

---

<a id="prompt-16"></a>
## PROMPT 16 — P3: Low-Priority Cleanup

**Issue IDs:** BE-040, FE-015, FE-003, FE-004, FE-014, FE-022, FE-023, BE-037, DV-008, DV-020, SEC-006, SEC-007, SEC-009, SEC-010

```

Clean up remaining minor issues.

### Requirements:

**1. Fix app name defaults:**

- `backend/config/app.php`: Change `env('APP_NAME', 'Laravel')` → `env('APP_NAME', 'Soleil Hostel')`
- `frontend/index.html`: Change `<title>Vite + React + TS</title>` → `<title>Soleil Hostel</title>`

**2. Clean up frontend package.json:**

```bash
cd frontend
npm uninstall @types/react-router-dom react-i18next
```

- `@types/react-router-dom@5.3.3` conflicts with react-router-dom v7 (already ships types)
- `react-i18next` is installed but not used — remove or implement i18n

- Fix lint script: `"lint": "eslint ."` (remove deprecated `--ext` flag)

**3. Accessibility — Fix Input component:**
File: `frontend/src/shared/components/ui/Input.tsx`

- Replace `Math.random().toString(36).substr(2, 9)` with `React.useId()`

```tsx
const autoId = React.useId();
const inputId = id || autoId;
```

**4. Lazy loading images:**
Files: `frontend/src/features/rooms/RoomList.tsx` and `frontend/src/pages/HomePage.tsx`

- Add `loading="lazy"` to all `<img>` tags displaying room images

**5. Update frontend .gitignore:**
File: `frontend/.gitignore`
Add:

```
playwright-report/
test-results/
coverage/
temp_*
```

**6. Security configs (production):**

- `backend/config/sanctum.php`:
  - Change `'token_prefix' => ''` → `'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'soleil_')`
  - Change `'verify_device_fingerprint' => false` → `'verify_device_fingerprint' => env('SANCTUM_VERIFY_FINGERPRINT', true)`
- `backend/config/auth.php`:
  - Change `'password_timeout' => 10800` → `'password_timeout' => env('PASSWORD_TIMEOUT', 1800)` (30 min, OWASP)
- `backend/config/security-headers.php`:
  - Change `'CSP_REPORTING' => false` → `'CSP_REPORTING' => env('CSP_REPORTING', true)`

**7. Full verification:**

```bash
cd backend && php artisan test
cd ../frontend && npx vitest run && npm run build
```

```

---

## Summary

| Prompt | Phase | Estimated Effort | Focus |
|--------|-------|-----------------|-------|
| 1 | P0 🔴 | 2 hours | `env()` → `config()` |
| 2 | P0 🔴 | 3 hours | Database mismatch |
| 3 | P0 🔴 | 30 min | Redis & Docker security |
| 4 | P0 🔴 | 10 min | CI security job |
| 5 | P0 🔴 | 3 hours | Frontend API consolidation |
| 6 | P1 🟠 | 1 hour | Dead code & routes |
| 7 | P1 🟠 | 2 hours | Controller fixes |
| 8 | P1 🟠 | 4 hours | Service consolidation |
| 9 | P1 🟠 | 2 hours | Frontend fixes |
| 10 | P1 🟠 | 3 hours | Security hardening |
| 11 | P2 🟡 | 2 hours | Model fixes |
| 12 | P2 🟡 | 3 hours | API response format |
| 13 | P2 🟡 | 2 hours | Frontend cleanup |
| 14 | P2 🟡 | 2-3 days | Frontend unit tests |
| 15 | P2 🟡 | 4 hours | Docker & CI |
| 16 | P3 🟢 | 2 hours | Low-priority cleanup |

**Total estimated effort:** ~30-35 hours (excluding frontend test writing)

---

*Generated from AUDIT_REPORT.md — February 9, 2026*
```
