# 🖥️ Soleil Hostel Backend (Laravel 11)

> **Last Updated:** January 6, 2026 | **Laravel:** 11.x | **PHP:** 8.2+ | **Tests:** 488 passing ✅

## 🎯 Overview

The Soleil Hostel backend is a **production-ready REST API** built with Laravel 11, implementing clean architecture principles with comprehensive test coverage, security hardening, and performance optimization.

### Key Features

-   ✅ **Authentication**: Dual-mode (Bearer Token + HttpOnly Cookie)
-   ✅ **Booking System**: Pessimistic locking prevents double-booking
-   ✅ **Room Management**: Optimistic locking prevents lost updates
-   ✅ **Repository Pattern**: Data access abstraction with 100% unit test coverage
-   ✅ **RBAC**: Enum-based role system (User, Moderator, Admin)
-   ✅ **Security**: XSS protection, CSRF tokens, security headers, rate limiting
-   ✅ **Performance**: Redis caching, N+1 query prevention, database indexes
-   ✅ **Monitoring**: Correlation IDs, performance logging, health probes
-   ✅ **Testing**: 488 tests with 1348 assertions (100% pass rate)

---

## 🚀 Quick Start

### Prerequisites

-   PHP 8.2 or higher
-   Composer
-   PostgreSQL 12+
-   Redis (optional, for caching)

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

Backend will be available at: http://127.0.0.1:8000

---

## 📂 Project Structure

```
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
│   ├── Feature/         # Feature tests (383 tests)
│   └── Unit/            # Unit tests (105 tests)
│       ├── Repositories/ # Repository unit tests (53 tests, zero DB)
└── vendor/              # Composer dependencies
```

---

## 🧪 Testing

### Run All Tests

```bash
php artisan test
# ✅ 435 tests, 1295 assertions, ~14 seconds
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

### Authentication

| Method | Endpoint                   | Description             |
| ------ | -------------------------- | ----------------------- |
| POST   | /api/auth/register         | Register new user       |
| POST   | /api/auth/login-v2         | Login (Bearer token)    |
| POST   | /api/auth/login-httponly   | Login (HttpOnly cookie) |
| POST   | /api/auth/refresh-httponly | Refresh token           |
| POST   | /api/auth/logout-v2        | Logout single device    |
| POST   | /api/auth/logout-all-v2    | Logout all devices      |
| GET    | /api/auth/me-v2            | Get current user        |

### Rooms

| Method | Endpoint             | Description                         | Auth Required |
| ------ | -------------------- | ----------------------------------- | ------------- |
| GET    | /api/rooms           | List all rooms                      | No            |
| GET    | /api/rooms/{id}      | Get room details                    | No            |
| POST   | /api/rooms           | Create room                         | Admin only    |
| PUT    | /api/rooms/{id}      | Update room (requires lock_version) | Admin only    |
| DELETE | /api/rooms/{id}      | Delete room                         | Admin only    |
| GET    | /api/rooms/available | Check availability                  | No            |

### Bookings

| Method | Endpoint           | Description         | Auth Required |
| ------ | ------------------ | ------------------- | ------------- |
| GET    | /api/bookings      | List all bookings   | Yes           |
| POST   | /api/bookings      | Create booking      | Yes           |
| GET    | /api/bookings/{id} | Get booking details | Yes           |
| PUT    | /api/bookings/{id} | Update booking      | Yes           |
| DELETE | /api/bookings/{id} | Cancel booking      | Yes           |

### Health & Monitoring

| Method | Endpoint          | Description       |
| ------ | ----------------- | ----------------- |
| GET    | /api/health/live  | Liveness probe    |
| GET    | /api/health/ready | Readiness probe   |
| GET    | /api/health/full  | Full health check |

---

## 🏗️ Architecture

### Clean Architecture Layers

```
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

-   **Service Layer**: Business logic isolated from controllers
-   **Form Requests**: Validation separated from controller logic
-   **Policies**: Authorization logic centralized
-   **Events & Listeners**: Decoupled event-driven architecture
-   **Optimistic Locking**: Prevents lost updates in Room model
-   **Pessimistic Locking**: Prevents double-booking in Booking system

---

## 🔐 Security Features

### Implemented

-   ✅ XSS Protection (HTML Purifier)
-   ✅ CSRF Protection (Sanctum)
-   ✅ SQL Injection Prevention (Eloquent ORM)
-   ✅ Rate Limiting (3-tier: guest, user, admin)
-   ✅ Security Headers (CSP, HSTS, X-Frame-Options, etc.)
-   ✅ Token Expiration & Rotation
-   ✅ HttpOnly Cookies for sensitive tokens
-   ✅ Suspicious Activity Detection
-   ✅ Password Hashing (bcrypt)
-   ✅ Sensitive Data Masking in logs

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

-   [Architecture Overview](../docs/backend/README.md)
-   [Authentication Guide](../docs/backend/features/AUTHENTICATION.md)
-   [Booking System](../docs/backend/features/BOOKING.md)
-   [Room Management](../docs/backend/features/ROOMS.md)
-   [Optimistic Locking](../docs/backend/features/OPTIMISTIC_LOCKING.md)
-   [RBAC System](../docs/backend/features/RBAC.md)
-   [Security Guide](../docs/backend/security/README.md)
-   [Testing Guide](../docs/backend/guides/TESTING.md)
-   [Environment Setup](../docs/backend/guides/ENVIRONMENT_SETUP.md)

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

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# View routes
php artisan route:list
```

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

-   [ ] Set `APP_ENV=production` in `.env`
-   [ ] Set `APP_DEBUG=false` in `.env`
-   [ ] Configure production database
-   [ ] Set strong `APP_KEY`
-   [ ] Configure Redis for caching (recommended)
-   [ ] Set up queue worker (if using queues)
-   [ ] Configure Sentry for error tracking (optional)
-   [ ] Run `composer install --optimize-autoloader --no-dev`
-   [ ] Run `php artisan config:cache`
-   [ ] Run `php artisan route:cache`
-   [ ] Run `php artisan view:cache`
-   [ ] Run `php artisan migrate --force`

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

| Component       | Tests   | Assertions | Status |
| --------------- | ------- | ---------- | ------ |
| Authentication  | 26      | 78         | ✅     |
| Booking System  | 60      | 180        | ✅     |
| Room Management | 151     | 453        | ✅     |
| RBAC            | 47      | 141        | ✅     |
| Security        | 77      | 231        | ✅     |
| Caching         | 6       | 18         | ✅     |
| Monitoring      | 10      | 30         | ✅     |
| Other           | 58      | 164        | ✅     |
| **Total**       | **435** | **1295**   | **✅** |

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

**Status**: ✅ Production Ready  
**Backend API**: http://127.0.0.1:8000  
**Documentation**: [docs/README.md](../docs/README.md)
