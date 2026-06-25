# 🖥️ Soleil Hostel Backend (Laravel 12)

> **Last Updated:** June 24, 2026 | **Laravel:** 12.x | **PHP:** 8.2+ (platform pinned to 8.3 in `composer.json`)

## 🎯 Overview

The Soleil Hostel backend is a REST API built with Laravel 12, implementing clean architecture principles with comprehensive test coverage, security hardening, and performance optimization. The backend booking core is production-quality; the integrated product is not yet production-ready (March 2026).

### Key Features

- ✅ **Authentication**: Dual-mode (Bearer Token + HttpOnly Cookie); OTP email verification (race-hardened — AUTH-004)
- ✅ **Booking System**: Pessimistic + optimistic (`lock_version`) locking; PostgreSQL `EXCLUDE` constraint enforces no-overlap; immutable actor snapshots; payment-hold lifecycle; deposit FSM (CONC-005/006); stay-cancellation propagation (OPS-004)
- ✅ **Room Management**: Optimistic locking, real-time availability cache
- ✅ **Repository Pattern**: Data access abstraction with focused unit tests
- ✅ **RBAC**: Enum-based role system (USER, MODERATOR, ADMIN); see [`docs/PERMISSION_MATRIX.md`](../docs/PERMISSION_MATRIX.md)
- ✅ **AI Harness (Phases 0–4)**: 7-layer safety pipeline, kill switch, canary routing, proposal-confirmation flow under `app/AiHarness/`; eval gate `php artisan ai:eval --all-phases`
- ✅ **Payments**: Stripe Cashier (signed-webhook idempotency via `stripe_refund_events` UNIQUE) and MoMo wallet (VN e-wallet, sandbox AIO v2; in-app QR; IPN-confirmed via `momo_payments` authoritative order + `momo_webhook_events` idempotency ledger)
- ✅ **Booking Notifications**: Event-driven queued emails (confirm, update, cancel)
- ✅ **Security**: XSS (HTML Purifier), CSRF (Sanctum), security headers, multi-tier rate limiting, PII redaction across log channels + Sentry
- ✅ **Performance**: Redis caching with event-driven invalidation, N+1 prevention, parallel testing
- ✅ **Monitoring**: Correlation IDs, structured JSON logging, health probes (admin-gated detail per OBS-002)
- ✅ **Testing**: Comprehensive PHPUnit suite (Feature + Unit + Repositories) — counts in [PROJECT_STATUS.md](../PROJECT_STATUS.md)

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer
- PostgreSQL 16+
- Redis 7+ (required — used for cache, session, and queue)

### Installation

```bash
# 1. Install dependencies
composer install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Configure database in .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=soleil_hostel
DB_USERNAME=your_username
DB_PASSWORD=your_password

# 4. Run migrations and seeders
php artisan migrate:fresh --seed

# 5. Start development server
php artisan serve
# Or use built-in PHP server:
php -S 127.0.0.1:8000 -t public public/index.php
```

Backend will be available at: <http://127.0.0.1:8000>

---

## 📂 Project Structure

```bash
backend/
├── app/
│   ├── Console/         # Artisan commands
│   ├── Enums/           # Type-safe enums (UserRole, BookingStatus, etc.)
│   ├── Events/          # Event classes
│   ├── Exceptions/      # Custom exceptions (OptimisticLockException, etc.)
│   ├── Helpers/         # Helper functions (SecurityHelpers.php)
│   ├── Http/
│   │   ├── Controllers/ # API controllers
│   │   ├── Middleware/  # Custom middleware
│   │   ├── Requests/    # Form request validation
│   │   └── Resources/   # API resources
│   ├── Jobs/            # Queue jobs
│   ├── Listeners/       # Event listeners
│   ├── Logging/         # Custom log processors
│   ├── Models/          # Eloquent models
│   ├── Policies/        # Authorization policies
│   ├── Providers/       # Service providers
│   ├── Repositories/    # Repository pattern (data access layer)
│   ├── Services/        # Business logic layer
│   └── Traits/          # Reusable traits
├── config/              # Configuration files
├── database/
│   ├── factories/       # Model factories
│   ├── migrations/      # Database migrations
│   └── seeders/         # Database seeders
├── docs/                # (See main docs/ folder)
├── routes/
│   ├── api.php          # API routes
│   └── web.php          # Web routes
├── storage/             # File storage & logs
├── tests/
│   ├── Feature/         # Feature tests
│   └── Unit/            # Unit tests
│       ├── Repositories/ # Repository unit tests (zero DB)
└── vendor/              # Composer dependencies
```

---

## 🧪 Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suites

```bash
# Authentication tests
php artisan test --filter=Auth

# Room tests (including optimistic locking)
php artisan test --filter=Room

# Booking tests
php artisan test --filter=Booking

# Security tests
php artisan test --filter=Security
```

### Parallel Testing

```bash
php artisan test --parallel
```

### Test Coverage

```bash
php artisan test --coverage --min=80
```

---

## 📋 API Endpoints

> Versioned under `/api/v1/*` (current stable). `/api/v2/*` reserved for development. Unprefixed legacy paths (`/api/auth/login`, `/api/auth/register`) are deprecated with `Sunset: 2026-07-01` headers. Authoritative sources: [`backend/routes/api.php`](./routes/api.php) (loader), [`backend/routes/api/v1.php`](./routes/api/v1.php) (v1 stable), [`backend/routes/api/v1_ai.php`](./routes/api/v1_ai.php) (AI Harness, kill-switch gated), [`backend/routes/api/v2.php`](./routes/api/v2.php) (v2 in development), [`backend/routes/api/legacy.php`](./routes/api/legacy.php) (deprecated, sunset-tagged). Full surface: [`docs/api/openapi.yaml`](../docs/api/openapi.yaml).

### Authentication (unprefixed — Sunset 2026-07-01)

| Method | Endpoint                    | Description                                        |
| ------ | --------------------------- | -------------------------------------------------- |
| POST   | /api/auth/register          | Register (legacy AuthController response shape)    |
| POST   | /api/auth/login             | Login (deprecated — use `-v2` or `-httponly`)      |
| POST   | /api/auth/login-v2          | Login (Bearer token, current)                      |
| POST   | /api/auth/login-httponly    | Login (HttpOnly cookie, current)                   |
| POST   | /api/auth/refresh-httponly  | Refresh HttpOnly cookie token                      |
| POST   | /api/auth/refresh-v2        | Refresh Bearer token                               |
| POST   | /api/auth/logout-v2         | Logout single device                               |
| POST   | /api/auth/logout-all-v2     | Logout all devices                                 |
| GET    | /api/auth/me-v2             | Get current user (token-aware)                     |
| POST   | /api/email/send-code        | Send 6-digit OTP verification code                 |
| POST   | /api/email/verify-code      | Verify OTP code                                    |
| GET    | /api/email/verification-status | Verification status + cooldown                  |
| GET    | /api/auth/unified/me        | Mode-agnostic identity (Bearer or Cookie)          |
| POST   | /api/auth/unified/logout    | Mode-agnostic logout                               |
| POST   | /api/auth/unified/logout-all | Mode-agnostic logout-all                          |

### Locations (v1)

| Method | Endpoint                                  | Description              | Auth |
| ------ | ----------------------------------------- | ------------------------ | ---- |
| GET    | /api/v1/locations                         | List locations           | No   |
| GET    | /api/v1/locations/{slug}                  | Location detail          | No   |
| GET    | /api/v1/locations/{slug}/availability     | Availability per branch  | No   |

### Rooms (v1)

| Method | Endpoint                | Description                        | Auth        |
| ------ | ----------------------- | ---------------------------------- | ----------- |
| GET    | /api/v1/rooms           | List rooms (filters: `location_id`)| No          |
| GET    | /api/v1/rooms/{room}    | Room detail                        | No          |
| POST   | /api/v1/rooms           | Create room                        | Admin only  |
| PUT    | /api/v1/rooms/{room}    | Update room (requires lock_version)| Admin only  |
| DELETE | /api/v1/rooms/{room}    | Delete room                        | Admin only  |

### Bookings (v1, verified email required)

| Method | Endpoint                                | Description                  | Auth                |
| ------ | --------------------------------------- | ---------------------------- | ------------------- |
| GET    | /api/v1/bookings                        | List own bookings            | Yes                 |
| POST   | /api/v1/bookings                        | Create booking               | Yes (throttle 10/m) |
| GET    | /api/v1/bookings/{booking}              | Booking detail               | Yes                 |
| PUT    | /api/v1/bookings/{booking}              | Update booking               | Yes (throttle 10/m) |
| DELETE | /api/v1/bookings/{booking}              | Cancel booking               | Yes (throttle 10/m) |
| POST   | /api/v1/bookings/{booking}/confirm      | Admin confirm                | Admin               |
| POST   | /api/v1/bookings/{booking}/cancel       | Cancel (owner or admin)      | Yes                 |
| POST   | /api/v1/bookings/{booking}/momo/create  | Start MoMo wallet payment    | Yes (throttle 10/m) |

### Admin (v1)

| Method | Endpoint                                                  | Auth          |
| ------ | --------------------------------------------------------- | ------------- |
| GET    | /api/v1/admin/bookings                                    | Moderator+    |
| GET    | /api/v1/admin/bookings/trashed                            | Moderator+    |
| GET    | /api/v1/admin/bookings/trashed/{id}                       | Moderator+    |
| POST   | /api/v1/admin/bookings/{id}/restore                       | Admin         |
| POST   | /api/v1/admin/bookings/restore-bulk                       | Admin         |
| DELETE | /api/v1/admin/bookings/{id}/force                         | Admin         |
| GET    | /api/v1/admin/contact-messages                            | Admin         |
| PATCH  | /api/v1/admin/contact-messages/{id}/read                  | Admin         |
| GET    | /api/v1/admin/customers, /stats, /{email}, /{email}/bookings | Moderator+ |

### Reviews (v1)

| Method | Endpoint                          | Auth     |
| ------ | --------------------------------- | -------- |
| POST   | /api/v1/reviews                   | Yes      |
| PUT    | /api/v1/reviews/{review}          | Yes      |
| DELETE | /api/v1/reviews/{review}          | Yes      |

### AI Harness (v1) — gated by `AI_HARNESS_ENABLED` feature flag

| Method | Endpoint                                       | Description                                  |
| ------ | ---------------------------------------------- | -------------------------------------------- |
| POST   | /api/v1/ai/{task_type}                         | `faq_lookup`, `room_discovery`, `booking_status`, `admin_draft` (throttle 10/m) |
| POST   | /api/v1/ai/proposals/{hash}/shown              | Mark proposal as shown (idempotent)          |
| POST   | /api/v1/ai/proposals/{hash}/decide             | Confirm/decline proposal (throttle 5/m)      |
| GET    | /api/v1/ai/health                              | 200 if enabled, 404 if killed                |

### Health & Monitoring

| Method | Endpoint                | Description                                       | Auth        |
| ------ | ----------------------- | ------------------------------------------------- | ----------- |
| GET    | /api/ping               | Public liveness sentinel                          | Public      |
| GET    | /api/health/live        | Liveness probe (returns only `{"status":"ok"}`)   | Public      |
| GET    | /api/health             | Service breakdown                                 | Admin       |
| GET    | /api/health/ready       | Readiness probe                                   | Admin       |
| GET    | /api/health/detailed    | Full system health                                | Admin       |
| GET    | /api/health/{db,cache,queue} | Component-level checks                       | Admin       |

> OBS-002: Detailed health endpoints are gated behind admin auth to avoid leaking topology (driver names, queue stats, exception messages) to anonymous callers.

### Webhooks

| Method | Endpoint                | Description                                          |
| ------ | ----------------------- | ---------------------------------------------------- |
| POST   | /api/webhooks/stripe    | Cashier-signed webhook; replay-fenced via `stripe_refund_events` UNIQUE (`stripe_refund_id`) |
| POST   | /api/v1/payments/momo/ipn | Public MoMo IPN (server→server); HMAC-signed (fail-closed), rate-limited 120/min; replay-deduped via `momo_webhook_events` (`order_id`+`trans_id`) UNIQUE |
| POST   | /api/csp-violation-report | CSP report endpoint                                |

---

## 🏗️ Architecture

### Clean Architecture Layers

```bash
┌─────────────────────────────────────┐
│        Controllers (HTTP)           │  ← API Routes
├─────────────────────────────────────┤
│     Services (Business Logic)       │  ← Core Logic
├─────────────────────────────────────┤
│       Models (Data Access)          │  ← Eloquent ORM
├─────────────────────────────────────┤
│          Database (PostgreSQL)      │  ← Persistence
└─────────────────────────────────────┘
```

### Key Patterns

- **Service Layer**: Business logic isolated from controllers
- **Form Requests**: Validation separated from controller logic
- **Policies**: Authorization logic centralized
- **Events & Listeners**: Decoupled event-driven architecture
- **Optimistic Locking**: Prevents lost updates in Room model
- **Pessimistic Locking**: Prevents double-booking in Booking system

---

## 🔐 Security Features

### Implemented

- ✅ XSS Protection (HTML Purifier)
- ✅ CSRF Protection (Sanctum)
- ✅ SQL Injection Prevention (Eloquent ORM)
- ✅ Rate Limiting (3-tier: guest, user, admin)
- ✅ Security Headers (CSP, HSTS, X-Frame-Options, etc.)
- ✅ Token Expiration & Rotation
- ✅ HttpOnly Cookies for sensitive tokens
- ✅ Suspicious Activity Detection
- ✅ Password Hashing (bcrypt)
- ✅ Sensitive Data Masking in logs

### Security Headers

```php
Content-Security-Policy: default-src 'self'
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

## ⚡ Performance Optimizations

### Caching Strategy

```php
// Room availability cached for 5 minutes
Cache::remember("rooms:available:{$capacity}", 300, function() {
    return Room::active()->where('max_guests', '>=', $capacity)->get();
});
```

### N+1 Query Prevention

```php
// Eager load relationships
Room::with('activeBookings')->get();
```

### Database Indexes

```sql
-- Optimized composite indexes
CREATE INDEX idx_bookings_availability ON bookings(room_id, status, check_in, check_out);
CREATE INDEX idx_bookings_user_history ON bookings(user_id, created_at);
CREATE INDEX idx_bookings_status_period ON bookings(status, check_in);
```

---

## 📚 Documentation

Full documentation available in the `docs/` folder:

- [Architecture Overview](../docs/backend/README.md)
- [Backend Folder Reference](../docs/backend/architecture/FOLDER_REFERENCE.md)
- [Authentication Guide](../docs/backend/features/AUTHENTICATION.md)
- [Booking System](../docs/backend/features/BOOKING.md)
- [Room Management](../docs/backend/features/ROOMS.md)
- [Optimistic Locking](../docs/backend/features/OPTIMISTIC_LOCKING.md)
- [RBAC System](../docs/backend/features/RBAC.md)
- [Security Guide](../docs/backend/security/README.md)
- [Testing Guide](../docs/backend/guides/TESTING.md)
- [Environment Setup](../docs/backend/guides/ENVIRONMENT_SETUP.md)

---

## 🛠️ Development Commands

### Artisan Commands

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Fresh migration with seed data
php artisan migrate:fresh --seed

# Cache warmup (post-deployment)
php artisan cache:warmup              # Warm all caches
php artisan cache:warmup --dry-run    # Preview only
php artisan cache:warmup --group=rooms --group=config  # Specific groups
php artisan cache:warmup --force      # Override existing cache

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# View routes
php artisan route:list

# Dev-only: simulate a completed MoMo payment by firing a locally-signed IPN
php artisan momo:simulate-ipn                 # latest pending prepaid booking
php artisan momo:simulate-ipn 42 --replay     # target booking #42; prove idempotent dedup ack
```

### Cache Warmup

After deployment, run cache warmup to prevent cold-start latency spikes:

```bash
# Full warmup with progress
php artisan cache:warmup

# In deployment scripts (no progress bar)
php artisan cache:warmup --force --no-progress
```

See [Cache Warmup Strategy](../docs/backend/CACHE_WARMUP_STRATEGY.md) for full documentation.

### Database Commands

```bash
# Create new migration
php artisan make:migration create_rooms_table


# Create new seeder
php artisan make:seeder RoomSeeder

# Run specific seeder
php artisan db:seed --class=RoomSeeder
```

### Testing Commands

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run parallel tests
php artisan test --parallel

# Filter tests
php artisan test --filter=RoomOptimisticLocking
```

---

## 🚀 Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Configure production database
- [ ] Set strong `APP_KEY`
- [ ] Configure Redis for caching (recommended)
- [ ] Set up queue worker (if using queues)
- [ ] Configure Sentry for error tracking (optional)
- [ ] Run `composer install --optimize-autoloader --no-dev`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Run `php artisan migrate --force`

### Performance Optimization

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize Composer autoloader
composer install --optimize-autoloader --no-dev
```

---

## 📊 Test Coverage

Run `cd backend && php artisan test` for current counts. The Mar 31, 2026 baseline (1047 tests / 2875 assertions) requires re-verification — Apr–May added AI proposal lifecycle tests, OPS-004 cancellation propagation, CONC-005/006 deposit FSM, AUTH-004 OTP race tests, and Stripe webhook idempotency tests. See [PROJECT_STATUS.md](../PROJECT_STATUS.md) for the canonical, single-source baseline.

---

## 🤝 Contributing

1. Follow Laravel coding standards (PSR-12)
2. Write tests for new features
3. Update documentation
4. Ensure all tests pass before submitting PR

---

## 📄 License

MIT License - see main project README for details.

---

**Backend API**: <http://127.0.0.1:8000>
**Documentation**: [docs/README.md](../docs/README.md)
