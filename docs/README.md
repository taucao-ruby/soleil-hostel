# 📚 Soleil Hostel Documentation

> **Last Updated:** December 18, 2025 | **Tests:** 296 passed | **Status:** Production Ready ✅

## Quick Navigation

| I want to...                       | Go to                                        |
| ---------------------------------- | -------------------------------------------- |
| **Get started quickly**            | [Quick Start](#quick-start)                  |
| **Set up development environment** | [Setup Guide](./guides/ENVIRONMENT_SETUP.md) |
| **Run tests**                      | [Testing Guide](./guides/TESTING.md)         |
| **Understand the architecture**    | [Architecture](./architecture/README.md)     |
| **Implement a feature**            | [Feature Docs](./features/README.md)         |
| **Check security**                 | [Security Docs](./security/README.md)        |

---

## Quick Start

```bash
# 1. Clone & Install
git clone <repo>
cd soleil-hostel

# 2. Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# 3. Frontend (new terminal)
cd frontend
npm install
npm run dev

# 4. Run tests
cd backend && php artisan test
```

---

## Documentation Structure

```
docs/
├── README.md                    # This file
├── guides/                      # How-to guides
│   ├── ENVIRONMENT_SETUP.md     # Dev environment setup
│   ├── TESTING.md               # Testing guide
│   └── DEPLOYMENT.md            # Deployment guide
├── architecture/                # System design
│   ├── README.md                # Architecture overview
│   ├── DATABASE.md              # Database schema & indexes
│   └── API.md                   # API reference
├── features/                    # Feature documentation
│   ├── README.md                # Feature index
│   ├── AUTHENTICATION.md        # Auth (Bearer + HttpOnly Cookie)
│   ├── BOOKING.md               # Booking system (double-booking prevention, soft deletes)
│   ├── ROOMS.md                 # Room management (optimistic locking)
│   ├── RBAC.md                  # Role-based access control
│   └── CACHING.md               # Redis cache layer
├── security/                    # Security documentation
│   ├── README.md                # Security overview
│   ├── HEADERS.md               # Security headers (CSP, HSTS, etc.)
│   ├── XSS_PROTECTION.md        # HTML Purifier
│   └── RATE_LIMITING.md         # Rate limiting system
└── api/                         # API documentation
    └── postman/                 # Postman collections
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
