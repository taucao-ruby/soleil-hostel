# Soleil Hostel Documentation

> **Last Updated:** March 9, 2026 | **Tests:** 885 backend tests (2487 assertions) + 226 frontend unit tests | **Status:** Phases 0-5 Complete + DevSecOps + Quality Hardening

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
| **Migrate from API v1 to v2**         | [API v1→v2 Migration](./backend/guides/API_MIGRATION_V1_TO_V2.md)   |
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
├── README.md                         # This file (documentation index)
├── ADR.md                            # Architecture Decision Records
├── KNOWN_LIMITATIONS.md              # System constraints & tech debt
├── OPERATIONAL_PLAYBOOK.md           # Incident runbooks
├── API_DEPRECATION.md                # API versioning & deprecation
├── DATABASE.md                       # Database schema & indexes
├── PERFORMANCE_BASELINE.md           # Performance benchmarks & SLA targets
├── api/                              # API documentation
│   ├── index.html                    # Interactive API docs (Redoc)
│   └── openapi.yaml                  # OpenAPI 3.1 specification
├── backend/                          # Backend documentation
│   ├── README.md                     # Backend index
│   ├── architecture/                 # System design
│   ├── features/                     # Feature documentation
│   ├── guides/                       # How-to guides
│   └── security/                     # Security documentation
└── frontend/                         # Frontend documentation
    ├── README.md                     # Frontend overview
    ├── ARCHITECTURE.md               # Main architecture document
    ├── FEATURES_LAYER.md             # Feature modules
    ├── SERVICES_LAYER.md             # API services
    ├── TESTING.md                    # Frontend testing
    └── ...                           # Additional layer docs
```

---

## Project Status

See [PROJECT_STATUS.md](../PROJECT_STATUS.md) for full status snapshot with gate results and roadmap.

**Current baselines** (verified March 6, 2026): 885 backend tests, 226 frontend tests, 283 Pint files, 0 open critical/high findings.

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

- **Authentication**: Bearer Token + HttpOnly Cookie dual mode, token rotation, unified endpoints
- **Booking System**: Pessimistic locking, soft deletes with audit trail, half-open intervals
- **Room Management**: Optimistic locking, real-time availability cache
- **RBAC**: 3 roles (USER, MODERATOR, ADMIN), type-safe enum, 6 authorization gates
- **Security**: A+ security headers, HTML Purifier XSS, multi-tier rate limiting, CSRF
- **Performance**: Redis caching with event-driven invalidation, N+1 prevention, parallel testing
- **Monitoring**: Correlation ID tracing, health probes, Sentry, structured JSON logging

---

## Change History

See [WORKLOG.md](./WORKLOG.md) for chronological change log.

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
| [FINDINGS_BACKLOG.md](./FINDINGS_BACKLOG.md) | Code issues backlog           |

---

## Support

- **Issues**: GitHub Issues
- **API Docs**: [Interactive API Reference (Redoc)](./api/index.html) | [OpenAPI Spec](./api/openapi.yaml) | Postman collection in `/backend/postman/`
