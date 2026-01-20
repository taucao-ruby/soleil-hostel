# 📚 Soleil Hostel Documentation

> **Last Updated:** January 19, 2026 | **Tests:** 609 tests (1657 assertions) | **Status:** Production Ready ✅

## Quick Navigation

| I want to...                          | Go to                                                      |
| ------------------------------------- | ---------------------------------------------------------- |
| **Get started quickly**               | [Quick Start](#quick-start)                                |
| **Understand architecture decisions** | [ADR (Decision Log)](./ADR.md)                             |
| **Handle an incident**                | [Operational Playbook](./OPERATIONAL_PLAYBOOK.md)          |
| **Know system limitations**           | [Known Limitations](./KNOWN_LIMITATIONS.md)                |
| **Deprecate an API**                  | [API Deprecation](./API_DEPRECATION.md)                    |
| **Database schema**                   | [Database Docs](./DATABASE.md)                             |
| **Backend documentation**             | [Backend Docs](./backend/README.md)                        |
| **Frontend documentation**            | [Frontend Docs](./frontend/README.md)                      |
| **Set up development environment**    | [Setup Guide](./backend/guides/ENVIRONMENT_SETUP.md)       |
| **Run tests**                         | [Testing Guide](./backend/guides/TESTING.md)               |
| **Migrate to unified auth endpoints** | [Auth Migration Guide](./backend/guides/AUTH_MIGRATION.md) |

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
├── README.md                         # This file (documentation index)
├── ADR.md                            # Architecture Decision Records
├── KNOWN_LIMITATIONS.md              # System constraints & tech debt
├── OPERATIONAL_PLAYBOOK.md           # Incident runbooks
├── API_DEPRECATION.md                # API versioning & deprecation
├── DATABASE.md                       # Database schema & indexes
├── backend/                          # Backend documentation
│   ├── README.md                     # Backend index
│   ├── architecture/                 # System design
│   │   ├── API.md                    # Complete API reference
│   │   ├── SERVICES.md               # Service layer architecture
│   │   ├── REPOSITORIES.md           # Repository pattern
│   │   ├── MIDDLEWARE.md             # Middleware pipeline
│   │   ├── EVENTS.md                 # Events & listeners
│   │   ├── POLICIES.md               # Authorization policies
│   │   ├── JOBS.md                   # Queue jobs
│   │   └── TRAITS_EXCEPTIONS.md      # Traits & exceptions
│   ├── features/                     # Feature documentation
│   │   ├── AUTHENTICATION.md         # Auth (Bearer + HttpOnly)
│   │   ├── BOOKING.md                # Booking system
│   │   ├── EMAIL_TEMPLATES.md        # Branded email templates
│   │   ├── ROOMS.md                  # Room management
│   │   ├── REVIEWS.md                # Reviews + XSS protection
│   │   ├── RBAC.md                   # Role-based access
│   │   ├── CACHING.md                # Redis cache layer
│   │   └── OPTIMISTIC_LOCKING.md     # Concurrency control
│   ├── guides/                       # How-to guides
│   │   ├── ENVIRONMENT_SETUP.md      # Dev environment
│   │   ├── TESTING.md                # Testing guide
│   │   ├── PERFORMANCE.md            # Octane & N+1
│   │   ├── DEPLOYMENT.md             # Docker & deployment
│   │   ├── COMMANDS.md               # Artisan commands
│   │   ├── MONITORING_LOGGING.md     # Observability & logging
│   │   ├── EMAIL_NOTIFICATIONS.md    # Email & verification
│   │   └── AUTH_MIGRATION.md         # Auth endpoint migration guide
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

| Component             | Status                  | Tests         |
| --------------------- | ----------------------- | ------------- |
| Authentication        | ✅ Complete             | 44 tests      |
| Booking System        | ✅ Complete             | 60 tests      |
| Booking Notifications | ✅ Complete             | 23 tests      |
| Email Templates       | ✅ Complete             | 13 tests      |
| Room Management       | ✅ Complete             | 151 tests     |
| RBAC                  | ✅ Complete             | 47 tests      |
| Security Headers      | ✅ Complete             | 14 tests      |
| XSS Protection        | ✅ Complete             | 48 tests      |
| Rate Limiting         | ✅ Complete             | 29 tests      |
| Caching               | ✅ Complete             | 6 tests       |
| Monitoring & Health   | ✅ Complete             | 30 tests      |
| Optimistic Locking    | ✅ Complete             | 24 tests      |
| Repository Layer      | ✅ Complete             | 53 tests      |
| Email Verification    | ✅ Complete             | 26 tests      |
| **Total**             | **✅ Production Ready** | **609 tests** |

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
- **Unified auth endpoints** (mode-agnostic, auto-detect Bearer/Cookie)
- Legacy endpoints deprecated (sunset July 2026)

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

  ### 📊 Monitoring & Logging

- Correlation ID request tracing (X-Correlation-ID)
- Performance logging (duration, memory)
- Kubernetes-style health probes
- Sentry error tracking
- Structured JSON logging
- Sensitive data masking

---

## Recent Updates (January 2026)

### Auth Endpoint Consolidation (January 15, 2026)

- **New Unified Endpoints** (mode-agnostic, auto-detect Bearer/Cookie):
  - `GET /api/auth/unified/me` - Get current user
  - `POST /api/auth/unified/logout` - Logout current session
  - `POST /api/auth/unified/logout-all` - Logout all devices
- **Legacy Endpoints Deprecated** (sunset July 2026):
  - `/api/auth/login`, `/api/auth/logout`, `/api/auth/refresh`, `/api/auth/me`
- **RFC 8594 Deprecation Headers**: `Deprecation`, `Sunset`, `Link`, `X-Deprecation-Notice`
- See [Auth Migration Guide](./backend/guides/AUTH_MIGRATION.md) for client migration

### Branded Email Templates (January 12, 2026)

- Professional Markdown email templates for booking notifications
- Configurable branding via `config/email-branding.php`

### Documentation Upgrade (January 14, 2026)

- ADR with 12 documented architecture decisions
- Known Limitations with 10 documented constraints
- Operational Playbook with 13 incident runbooks

---

## Contributing

1. Read the [Environment Setup Guide](./backend/guides/ENVIRONMENT_SETUP.md)
2. Run tests before submitting: `php artisan test`
3. Follow PSR-12 coding standards
4. Update documentation for new features

---

## Support

- **Issues**: GitHub Issues
- **API Docs**: Postman collection in `/backend/postman/`
