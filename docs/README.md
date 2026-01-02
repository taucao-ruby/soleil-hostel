# 📚 Soleil Hostel Documentation

> **Last Updated:** January 2, 2026 | **Tests:** 296 passed (890 assertions) | **Status:** Production Ready & Running ✅

## Quick Navigation

| I want to...                       | Go to                                                |
| ---------------------------------- | ---------------------------------------------------- |
| **Get started quickly**            | [Quick Start](#quick-start)                          |
| **Database schema**                | [Database Docs](./DATABASE.md)                       |
| **Backend documentation**          | [Backend Docs](./backend/README.md)                  |
| **Frontend documentation**         | [Frontend Docs](./frontend/README.md)                |
| **Set up development environment** | [Setup Guide](./backend/guides/ENVIRONMENT_SETUP.md) |
| **Run tests**                      | [Testing Guide](./backend/guides/TESTING.md)         |

---

## Quick Start

```bash
# 1. Clone & Install
git clone <repo>
cd soleil-hostel

# 2. Backend Setup
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed

# Start backend server (PHP built-in dev server)
php -S 127.0.0.1:8000 -t public public/index.php
# Backend running at: http://127.0.0.1:8000

# 3. Frontend Setup (new terminal)
cd frontend
npm ci

# Start frontend dev server (Vite)
npx vite --port 5173
# Frontend running at: http://localhost:5173

# 4. Run tests
cd backend && php artisan test
```

---

## Documentation Structure

```
docs/
├── README.md                         # This file
├── DATABASE.md                       # Database schema & indexes
├── backend/                          # Backend documentation
│   ├── README.md                     # Backend index
│   ├── architecture/                 # System design
│   │   ├── API.md                    # Complete API reference
│   │   ├── MIDDLEWARE.md             # Middleware pipeline
│   │   ├── EVENTS.md                 # Events & listeners
│   │   ├── POLICIES.md               # Authorization policies
│   │   ├── JOBS.md                   # Queue jobs
│   │   └── TRAITS_EXCEPTIONS.md      # Traits & exceptions
│   ├── features/                     # Feature documentation
│   │   ├── AUTHENTICATION.md         # Auth (Bearer + HttpOnly)
│   │   ├── BOOKING.md                # Booking system
│   │   ├── ROOMS.md                  # Room management
│   │   ├── REVIEWS.md                # Reviews + XSS protection
│   │   ├── RBAC.md                   # Role-based access
│   │   └── CACHING.md                # Redis cache layer
│   ├── guides/                       # How-to guides
│   │   ├── ENVIRONMENT_SETUP.md      # Dev environment
│   │   ├── TESTING.md                # Testing guide
│   │   ├── PERFORMANCE.md            # Octane & N+1
│   │   ├── DEPLOYMENT.md             # Docker & deployment
│   │   └── COMMANDS.md               # Artisan commands
│   └── security/                     # Security documentation
│       ├── HEADERS.md                # Security headers
│       ├── XSS_PROTECTION.md         # HTML Purifier
│       ├── RATE_LIMITING.md          # Rate limiting
│       └── README.md                 # Security overview
└── frontend/                         # Frontend documentation
    ├── README.md                     # Frontend overview
    ├── ARCHITECTURE.md               # Main architecture document
    ├── APP_LAYER.md                  # App configuration layer
    ├── FEATURES_LAYER.md             # Feature modules
    ├── SERVICES_LAYER.md             # API services
    ├── SHARED_LAYER.md               # Shared components
    ├── TYPES_LAYER.md                # TypeScript types
    ├── UTILS_LAYER.md                # Utility functions
    ├── CONFIGURATION.md              # Build & dev config
    ├── TESTING.md                    # Frontend testing
    ├── PERFORMANCE_SECURITY.md       # Performance & security
    └── DEPLOYMENT.md                 # Frontend deployment
```

---

## Project Status

| Component        | Status                  | Tests         |
| ---------------- | ----------------------- | ------------- |
| Authentication   | ✅ Complete             | 26 tests      |
| Booking System   | ✅ Complete             | 60 tests      |
| Room Management  | ✅ Complete             | 24 tests      |
| RBAC             | ✅ Complete             | 47 tests      |
| Security Headers | ✅ Complete             | 14 tests      |
| XSS Protection   | ✅ Complete             | 48 tests      |
| Rate Limiting    | ✅ Complete             | 15 tests      |
| Caching          | ✅ Complete             | 6 tests       |
| **Total**        | **✅ Production Ready** | **296 tests** |

---

## Tech Stack

| Layer    | Technology                                 |
| -------- | ------------------------------------------ |
| Frontend | React 19 + TypeScript + Vite + TailwindCSS |
| Backend  | Laravel 11 + PHP 8.3                       |
| Database | PostgreSQL 15                              |
| Cache    | Redis 7                                    |
| Testing  | PHPUnit + Playwright                       |

---

## Key Features

### 🔐 Authentication

- Bearer Token + HttpOnly Cookie dual mode
- Token expiration & rotation
- Multi-device support
- Suspicious activity detection

### 📅 Booking System

- Pessimistic locking (prevents double-booking)
- Soft deletes with audit trail
- Admin restore/force-delete
- Half-open interval logic

### 🏨 Room Management

- Optimistic locking (prevents lost updates)
- Real-time availability cache
- Status management

### 👥 RBAC

- 3 roles: USER, MODERATOR, ADMIN
- Type-safe enum implementation
- 6 authorization gates

### 🛡️ Security

- A+ security headers (CSP, HSTS, etc.)
- HTML Purifier XSS protection
- Multi-tier rate limiting
- CSRF protection

### ⚡ Performance

- Redis caching with event-driven invalidation
- N+1 query prevention
- Optimized database indexes
- Parallel testing

---

## Contributing

1. Read the [Environment Setup Guide](./guides/ENVIRONMENT_SETUP.md)
2. Run tests before submitting: `php artisan test`
3. Follow PSR-12 coding standards
4. Update documentation for new features

---

## Support

- **Issues**: GitHub Issues
- **API Docs**: Postman collection in `/docs/api/postman/`
