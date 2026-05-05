# Backend Documentation

> Laravel 12 + PHP 8.2+ (platform 8.3) + PostgreSQL 16 + Redis 7
>
> Last Updated: May 5, 2026

## Quick Navigation

| Section | Description |
| --- | --- |
| [Architecture](./architecture/) | System design, backend layers, folder map |
| [Features](./features/) | Feature-level behavior and flows |
| [Guides](./guides/) | Setup, operations, and runbooks |
| [Security](./security/) | Security controls and policies |

## Documentation Index

### Architecture

| Document | Description |
| --- | --- |
| [API.md](./architecture/API.md) | API reference |
| [DATABASE.md](../DATABASE.md) | Schema, constraints, and indexes |
| [FOLDER_REFERENCE.md](./architecture/FOLDER_REFERENCE.md) | Current backend folder inventory (`app`, `config`, `database`) |
| [SERVICES.md](./architecture/SERVICES.md) | Service layer |
| [REPOSITORIES.md](./architecture/REPOSITORIES.md) | Repository layer |
| [MIDDLEWARE.md](./architecture/MIDDLEWARE.md) | HTTP middleware pipeline |
| [EVENTS.md](./architecture/EVENTS.md) | Events and listeners |
| [POLICIES.md](./architecture/POLICIES.md) | Authorization policies |
| [JOBS.md](./architecture/JOBS.md) | Queue jobs |
| [TRAITS_EXCEPTIONS.md](./architecture/TRAITS_EXCEPTIONS.md) | Traits, macros, directives, exceptions |
| [BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md](./architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md) | Cancellation/refund architecture |

### Features

| Document | Description |
| --- | --- |
| [AUTHENTICATION.md](./features/AUTHENTICATION.md) | Authentication modes and flow (incl. OTP email verification — AUTH-004) |
| [BOOKING.md](./features/BOOKING.md) | Booking lifecycle (FSM, cancellation, payment-hold, deposit) |
| [ROOMS.md](./features/ROOMS.md) | Room management |
| [REVIEWS.md](./features/REVIEWS.md) | Reviews and moderation |
| [RBAC.md](./features/RBAC.md) | Role model and permissions |
| [CACHING.md](./features/CACHING.md) | Caching strategy |
| [OPTIMISTIC_LOCKING.md](./features/OPTIMISTIC_LOCKING.md) | Concurrency protection |
| [EMAIL_TEMPLATES.md](./features/EMAIL_TEMPLATES.md) | Notification templates |
| [HEALTH_CHECK.md](./features/HEALTH_CHECK.md) | Health endpoints |

> **Cross-cutting domains** documented at top-level (not under `backend/features/`):
> - **AI Harness (Phases 0–4)** — [`../HARNESS_ENGINEERING.md`](../HARNESS_ENGINEERING.md), [`../THREAT_MODEL_AI.md`](../THREAT_MODEL_AI.md), [`../EVAL_STRATEGY.md`](../EVAL_STRATEGY.md), [`../ROLLOUT_AND_KILL_SWITCH.md`](../ROLLOUT_AND_KILL_SWITCH.md). Code under `backend/app/AiHarness/`.
> - **Operational Stay Domain** — [`../DOMAIN_LAYERS.md`](../DOMAIN_LAYERS.md) (stays, room_assignments, service_recovery_cases).
> - **Cancellation & Refund** — [`./architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md`](./architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md).
> - **Permissions** — [`../PERMISSION_MATRIX.md`](../PERMISSION_MATRIX.md) is the RBAC source of truth.

### Guides

| Document | Description |
| --- | --- |
| [ENVIRONMENT_SETUP.md](./guides/ENVIRONMENT_SETUP.md) | Local environment setup |
| [TESTING.md](./guides/TESTING.md) | Test execution patterns |
| [PERFORMANCE.md](./guides/PERFORMANCE.md) | Performance and N+1 guidance |
| [DEPLOYMENT.md](./guides/DEPLOYMENT.md) | Deployment steps |
| [COMMANDS.md](./guides/COMMANDS.md) | Custom artisan commands |
| [MONITORING_LOGGING.md](./guides/MONITORING_LOGGING.md) | Monitoring and logging |
| [EMAIL_NOTIFICATIONS.md](./guides/EMAIL_NOTIFICATIONS.md) | Email/notification behavior |
| [AUTH_MIGRATION.md](./guides/AUTH_MIGRATION.md) | Auth endpoint migration |
| [API_MIGRATION_V1_TO_V2.md](./guides/API_MIGRATION_V1_TO_V2.md) | API v1 to v2 migration |

### Security

| Document | Description |
| --- | --- |
| [README.md](./security/README.md) | Security overview |
| [HEADERS.md](./security/HEADERS.md) | Security headers |
| [XSS_PROTECTION.md](./security/XSS_PROTECTION.md) | Sanitization/XSS controls |
| [RATE_LIMITING.md](./security/RATE_LIMITING.md) | Throttling and abuse controls |
