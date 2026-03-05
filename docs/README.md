# Soleil Hostel Documentation

> **Last Updated:** March 5, 2026 | **Tests:** 871 backend tests (2449 assertions) + 226 frontend unit tests | **Status:** Phases 0-5 Complete + DevSecOps + Quality Hardening

## Quick Navigation

| I want to...                          | Go to                                                               |
| ------------------------------------- | ------------------------------------------------------------------- |
| **Get started quickly**               | [Quick Start](#quick-start)                                         |
| **Understand architecture decisions** | [ADR (Decision Log)](./ADR.md)                                      |
| **Handle an incident**                | [Operational Playbook](./OPERATIONAL_PLAYBOOK.md)                   |
| **Know system limitations**           | [Known Limitations](./KNOWN_LIMITATIONS.md)                         |
| **Deprecate an API**                  | [API Deprecation](./API_DEPRECATION.md)                             |
| **Database schema**                   | [Database Docs](./DATABASE.md)                                      |
| **DB invariants & constraints**       | [DB Facts (Invariants & Constraints)](./DB_FACTS.md)                |
| **Backend documentation**             | [Backend Docs](./backend/README.md)                                 |
| **Frontend documentation**            | [Frontend Docs](./frontend/README.md)                               |
| **Set up development environment**    | [Setup Guide](./backend/guides/ENVIRONMENT_SETUP.md)                |
| **Run tests**                         | [Testing Guide](./backend/guides/TESTING.md)                        |
| **Set up Git hooks**                  | [Git Hooks](./HOOKS.md)                                             |
| **Migrate to unified auth endpoints** | [Auth Migration Guide](./backend/guides/AUTH_MIGRATION.md)          |
| **Migrate from API v1 to v2**         | [API v1â†’v2 Migration](./backend/guides/API_MIGRATION_V1_TO_V2.md) |
| **Browse interactive API docs**       | [API Reference (Redoc)](./api/index.html)                           |
| **Download OpenAPI spec**             | [openapi.yaml](./api/openapi.yaml)                                  |
| **Review performance baselines**      | [Performance Baseline](./PERFORMANCE_BASELINE.md)                   |
| **Run load tests**                    | [Performance Tests](../tests/performance/README.md)                 |

---

## For AI Agents

Start here if you are an AI coding agent:

- [AGENTS.md](../AGENTS.md) — onboarding + conventions
- [Agent Framework](./agents/README.md) — CONTRACT, ARCHITECTURE_FACTS, COMMANDS
- [AI Governance](./AI_GOVERNANCE.md) — operational checklists
- [Skills](../skills/README.md) — task-specific guardrails
- [COMPACT](./COMPACT.md) — current session state
- [MCP Server](./MCP.md) — tool server + safety policy
- [Hooks](./HOOKS.md) — local enforcement

## High-Risk Areas

These domains have critical invariants. Read docs before making changes:

- **Booking overlap constraint** — [DB_FACTS.md](./DB_FACTS.md), [ARCHITECTURE_FACTS](./agents/ARCHITECTURE_FACTS.md)
- **Auth tokens** — [AUTHENTICATION.md](./backend/features/AUTHENTICATION.md)
- **Migrations** — [DB_FACTS.md](./DB_FACTS.md) Section 6

## Project Memory

- [COMPACT](./COMPACT.md)
- [WORKLOG](./WORKLOG.md)
- [skills/](../skills/)
- [AGENTS.md](../AGENTS.md)

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
pnpm install

# Start frontend dev server (Vite)
pnpm dev
# Frontend running at: http://localhost:5173

# 4. Run tests
cd backend && php artisan test
```

---

## Documentation Structure

```text
docs/
â”œâ”€â”€ README.md                         # This file (documentation index)
â”œâ”€â”€ ADR.md                            # Architecture Decision Records
â”œâ”€â”€ KNOWN_LIMITATIONS.md              # System constraints & tech debt
â”œâ”€â”€ OPERATIONAL_PLAYBOOK.md           # Incident runbooks
â”œâ”€â”€ API_DEPRECATION.md                # API versioning & deprecation
â”œâ”€â”€ DATABASE.md                       # Database schema & indexes
â”œâ”€â”€ PERFORMANCE_BASELINE.md           # Performance benchmarks & SLA targets
â”œâ”€â”€ api/                              # API documentation
â”‚   â”œâ”€â”€ index.html                    # Interactive API docs (Redoc)
â”‚   â””â”€â”€ openapi.yaml                  # OpenAPI 3.1 specification
â”œâ”€â”€ backend/                          # Backend documentation
â”‚   â”œâ”€â”€ README.md                     # Backend index
â”‚   â”œâ”€â”€ architecture/                 # System design
â”‚   â”‚   â”œâ”€â”€ API.md                    # Complete API reference
â”‚   â”‚   â”œâ”€â”€ SERVICES.md               # Service layer architecture
â”‚   â”‚   â”œâ”€â”€ REPOSITORIES.md           # Repository pattern
â”‚   â”‚   â”œâ”€â”€ MIDDLEWARE.md             # Middleware pipeline
â”‚   â”‚   â”œâ”€â”€ EVENTS.md                 # Events & listeners
â”‚   â”‚   â”œâ”€â”€ POLICIES.md               # Authorization policies
â”‚   â”‚   â”œâ”€â”€ JOBS.md                   # Queue jobs
â”‚   â”‚   â””â”€â”€ TRAITS_EXCEPTIONS.md      # Traits & exceptions
â”‚   â”œâ”€â”€ features/                     # Feature documentation
â”‚   â”‚   â”œâ”€â”€ AUTHENTICATION.md         # Auth (Bearer + HttpOnly)
â”‚   â”‚   â”œâ”€â”€ BOOKING.md                # Booking system
â”‚   â”‚   â”œâ”€â”€ EMAIL_TEMPLATES.md        # Branded email templates
â”‚   â”‚   â”œâ”€â”€ ROOMS.md                  # Room management
â”‚   â”‚   â”œâ”€â”€ REVIEWS.md                # Reviews + XSS protection
â”‚   â”‚   â”œâ”€â”€ RBAC.md                   # Role-based access
â”‚   â”‚   â”œâ”€â”€ CACHING.md                # Redis cache layer
â”‚   â”‚   â””â”€â”€ OPTIMISTIC_LOCKING.md     # Concurrency control
â”‚   â”œâ”€â”€ guides/                       # How-to guides
â”‚   â”‚   â”œâ”€â”€ ENVIRONMENT_SETUP.md      # Dev environment
â”‚   â”‚   â”œâ”€â”€ TESTING.md                # Testing guide
â”‚   â”‚   â”œâ”€â”€ PERFORMANCE.md            # Octane & N+1
â”‚   â”‚   â”œâ”€â”€ DEPLOYMENT.md             # Docker & deployment
â”‚   â”‚   â”œâ”€â”€ COMMANDS.md               # Artisan commands
â”‚   â”‚   â”œâ”€â”€ MONITORING_LOGGING.md     # Observability & logging
â”‚   â”‚   â”œâ”€â”€ EMAIL_NOTIFICATIONS.md    # Email & verification
â”‚   â”‚   â”œâ”€â”€ AUTH_MIGRATION.md         # Auth endpoint migration guide
â”‚   â”‚   â””â”€â”€ API_MIGRATION_V1_TO_V2.md # API v1â†’v2 migration guide
â”‚   â””â”€â”€ security/                     # Security documentation
â”‚       â”œâ”€â”€ HEADERS.md                # Security headers
â”‚       â”œâ”€â”€ XSS_PROTECTION.md         # HTML Purifier
â”‚       â”œâ”€â”€ RATE_LIMITING.md          # Rate limiting
â”‚       â””â”€â”€ README.md                 # Security overview
â””â”€â”€ frontend/                         # Frontend documentation
    â”œâ”€â”€ README.md                     # Frontend overview
    â”œâ”€â”€ ARCHITECTURE.md               # Main architecture document
    â”œâ”€â”€ APP_LAYER.md                  # App configuration layer
    â”œâ”€â”€ FEATURES_LAYER.md             # Feature modules
    â”œâ”€â”€ SERVICES_LAYER.md             # API services
    â”œâ”€â”€ SHARED_LAYER.md               # Shared components
    â”œâ”€â”€ TYPES_LAYER.md                # TypeScript types
    â”œâ”€â”€ UTILS_LAYER.md                # Utility functions
    â”œâ”€â”€ CONFIGURATION.md              # Build & dev config
    â”œâ”€â”€ TESTING.md                    # Frontend testing
    â”œâ”€â”€ PERFORMANCE_SECURITY.md       # Performance & security
    â””â”€â”€ DEPLOYMENT.md                 # Frontend deployment
```

---

## Project Status

| Component             | Status                | Tests         |
| --------------------- | --------------------- | ------------- |
| Authentication        | ✅ Complete           | 44 tests      |
| Booking System        | ✅ Complete           | 60 tests      |
| Booking Notifications | ✅ Complete           | 23 tests      |
| Email Templates       | ✅ Complete           | 13 tests      |
| Room Management       | ✅ Complete           | 151 tests     |
| RBAC                  | ✅ Complete           | 47 tests      |
| Security Headers      | ✅ Complete           | 14 tests      |
| XSS Protection        | ✅ Complete           | 48 tests      |
| Rate Limiting         | ✅ Complete           | 29 tests      |
| Caching               | ✅ Complete           | 6 tests       |
| Monitoring & Health   | ✅ Complete           | 30 tests      |
| Optimistic Locking    | ✅ Complete           | 24 tests      |
| Repository Layer      | ✅ Complete           | 53 tests      |
| Email Verification    | ✅ Complete           | 26 tests      |
| Stripe/Cashier        | ✅ Bootstrap          | 14 tests      |
| Backend i18n          | ✅ Complete           | 9 tests       |
| PHPStan/Larastan      | ✅ Installed          | Baseline 151  |
| **Backend Total**     | **✅ All 17 systems** | **871 tests** |
| Frontend (Phase 0-5)  | ✅ Complete           | 226 tests     |

## Tech Stack

| Layer    | Technology                                 |
| -------- | ------------------------------------------ |
| Frontend | React 19 + TypeScript + Vite + TailwindCSS |
| Backend  | Laravel 12 + PHP 8.2+                      |
| Database | PostgreSQL 16                              |
| Cache    | Redis 7                                    |
| Testing  | PHPUnit + Vitest + Playwright              |

---

## Key Features

### ðŸ” Authentication

- Bearer Token + HttpOnly Cookie dual mode
- Token expiration & rotation
- Multi-device support
- Suspicious activity detection
- **Unified auth endpoints** (mode-agnostic, auto-detect Bearer/Cookie)
- Legacy endpoints deprecated (sunset July 2026)

### ðŸ“… Booking System

- Pessimistic locking (prevents double-booking)
- Soft deletes with audit trail
- Admin restore/force-delete
- Half-open interval logic

  ### ðŸ¨ Room Management

- Optimistic locking (prevents lost updates)
- Real-time availability cache
- Status management

  ### ðŸ‘¥ RBAC

- 3 roles: USER, MODERATOR, ADMIN
- Type-safe enum implementation
- 6 authorization gates

  ### ðŸ›¡ï¸ Security

- A+ security headers (CSP, HSTS, etc.)
- HTML Purifier XSS protection
- Multi-tier rate limiting
- CSRF protection

  ### âš¡ Performance

- Redis caching with event-driven invalidation
- N+1 query prevention
- Optimized database indexes
- Parallel testing

  ### ðŸ“Š Monitoring & Logging

- Correlation ID request tracing (X-Correlation-ID)
- Performance logging (duration, memory)
- Kubernetes-style health probes
- Sentry error tracking
- Structured JSON logging
- Sensitive data masking

---

## Recent Updates (March 2026)

### Backend Quality + Frontend Hardening (March 2, 2026)

- **Batch 3**: HealthService extraction (464→~80 lines), 4 FormRequests extracted, PHPStan/Larastan installed (Level 5, baseline 151), Contact+Review tests (+19)
- **Batch 4**: AbortController cleanup (RoomList, LocationList, BookingForm), vi.hoisted() auth mocks, no-console ESLint rule, RoomList tests (+8)
- **Test counts**: Backend 790 → 857 (+67 tests), Frontend 218 → 226 (+8 tests, +1 suite)

### DevSecOps + Backend Hardening (March 1, 2026)

- **OPS-001**: Production Docker Compose, `.env.production.example`, frontend prod Dockerfile (nginx), Caddy reverse proxy
- **DevSecOps Batch 1**: Redis `protected-mode`, Caddy security headers, non-root Docker, CI typecheck gate
- **PAY-001**: Laravel Cashier `^16.3` bootstrap, Stripe webhook handlers (3 events), 14 tests
- **I18N-001**: Backend i18n — 47 translation keys (en + vi), `__()` in 5 controllers, 9 tests
- **TD-003**: BookingFactory helper methods (`expired`, `cancelledByAdmin`, `multiDay`)
- **Batch 2 Fixes**: Review purification crash (C-01/C-02), Booking `$fillable` (H-01), Stripe webhooks (H-03), 21 tests
- **Security**: minimatch `>=10.2.3` override (GHSA-7r86, GHSA-23c5)
- **Test counts**: Backend 769 → 790 (+21 tests), Frontend 218 (unchanged)

### Updates (February 2026)

### Frontend Phases 0-4 Complete (February 25, 2026)

- **Phase 0**: Lazy-loaded `DashboardPage` with `ProtectedRoute` + role-based routing
- **Phase 1**: Guest Dashboard — booking list with filter tabs (All/Upcoming/Past), cancel with confirm modal, skeleton/empty/error states, toast notifications
- **Phase 2**: SearchCard wired to live locations API (`GET /v1/locations`); navigates to LocationDetail with URL params
- **Phase 3**: Admin Dashboard — 3 tabs (Đặt phòng / Đã xóa / Liên hệ), lazy-fetch per tab, trashed metadata, unread badge on contacts
- **Phase 4**: BookingForm polish — URL params pre-fill (`check_in`, `check_out`, `guests`), Vietnamese UI, deprecated `/bookings` → `/v1/bookings`, deprecated `/rooms` → `/v1/rooms`, removed dead `AvailabilityResponse` type
- **Test suite**: 19 files, 194 tests (was 145 / 13 files before)
- **All pre-commit and pre-push hooks pass**: ESLint, Prettier, TypeScript typecheck, full test suite
- See [FEATURES_LAYER.md](./frontend/FEATURES_LAYER.md) for full feature documentation

---

## Recent Updates (January 2026)

### Performance Benchmarking & API Documentation (January 2026)

- **Performance Test Suite**: k6 load tests for availability queries, booking creation, auth flows, and mixed workloads
  - See [Performance Tests README](../tests/performance/README.md) for quick start
- **Performance Baseline**: Documented SLA targets, alerting thresholds, and bottleneck analysis
  - See [PERFORMANCE_BASELINE.md](./PERFORMANCE_BASELINE.md)
- **OpenAPI 3.1 Specification**: Complete API spec covering all endpoints, schemas, and security schemes
  - Interactive docs: [API Reference (Redoc)](./api/index.html)
  - Raw spec: [openapi.yaml](./api/openapi.yaml)
- **API v1â†’v2 Migration Guide**: Step-by-step client migration with code examples
  - See [API_MIGRATION_V1_TO_V2.md](./backend/guides/API_MIGRATION_V1_TO_V2.md)
- **Updated Postman Collection**: v2 collection with test scripts and E2E flows
  - See `/backend/postman/Soleil_Hostel_v2.postman_collection.json`

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

## All Docs Index

### Root-level

| File                                                 | Purpose                        |
| ---------------------------------------------------- | ------------------------------ |
| [COMPACT.md](./COMPACT.md)                           | Session memory / current state |
| [WORKLOG.md](./WORKLOG.md)                           | Work log                       |
| [ADR.md](./ADR.md)                                   | Architecture Decision Records  |
| [DATABASE.md](./DATABASE.md)                         | Database schema & indexes      |
| [DB_FACTS.md](./DB_FACTS.md)                         | DB invariants & constraints    |
| [KNOWN_LIMITATIONS.md](./KNOWN_LIMITATIONS.md)       | System constraints & tech debt |
| [OPERATIONAL_PLAYBOOK.md](./OPERATIONAL_PLAYBOOK.md) | Incident runbooks              |
| [PERFORMANCE_BASELINE.md](./PERFORMANCE_BASELINE.md) | Performance benchmarks         |
| [API_DEPRECATION.md](./API_DEPRECATION.md)           | API versioning & deprecation   |
| [DEVELOPMENT_HOOKS.md](./DEVELOPMENT_HOOKS.md)       | Redirect to HOOKS.md           |
| [MIGRATION_GUIDE.md](./MIGRATION_GUIDE.md)           | Migration guide                |

### Agent & Governance

| File                                                           | Purpose                  |
| -------------------------------------------------------------- | ------------------------ |
| [../CLAUDE.md](../CLAUDE.md)                                   | Claude Code entry point  |
| [agents/README.md](./agents/README.md)                         | Agent framework index    |
| [agents/CONTRACT.md](./agents/CONTRACT.md)                     | Definition of Done       |
| [agents/ARCHITECTURE_FACTS.md](./agents/ARCHITECTURE_FACTS.md) | Domain invariants        |
| [agents/COMMANDS.md](./agents/COMMANDS.md)                     | Verified commands        |
| [AI_GOVERNANCE.md](./AI_GOVERNANCE.md)                         | AI agent workflow        |
| [COMMANDS_AND_GATES.md](./COMMANDS_AND_GATES.md)               | Full commands + CI gates |
| [MCP.md](./MCP.md)                                             | MCP server docs          |
| [HOOKS.md](./HOOKS.md)                                         | Hook enforcement         |

### Audits

| File                                         | Purpose                       |
| -------------------------------------------- | ----------------------------- |
| [AUDIT_2026_02_21.md](./AUDIT_2026_02_21.md) | Full repo audit (2026-02-21)  |
| [FINDINGS_BACKLOG.md](./FINDINGS_BACKLOG.md) | Code issues found (not fixed) |

---

## Support

- **Issues**: GitHub Issues
- **API Docs**: [Interactive API Reference (Redoc)](./api/index.html) | [OpenAPI Spec](./api/openapi.yaml) | Postman collection in `/backend/postman/`
