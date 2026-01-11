# 🔧 Backend Documentation

> Laravel 11 + PHP 8.3 + PostgreSQL + Redis

## Quick Navigation

| Section                         | Description                |
| ------------------------------- | -------------------------- |
| [Architecture](./architecture/) | System design & API        |
| [Features](./features/)         | Feature implementations    |
| [Guides](./guides/)             | Setup, testing, deployment |
| [Security](./security/)         | Security implementations   |

---

## Documentation Index

### Architecture

| Document                                                                                                  | Description                 |
| --------------------------------------------------------------------------------------------------------- | --------------------------- |
| [API.md](./architecture/API.md)                                                                           | Complete API reference      |
| [DATABASE.md](../DATABASE.md)                                                                             | Schema, indexes, migrations |
| [REPOSITORIES.md](./architecture/REPOSITORIES.md)                                                         | Repository pattern          |
| [MIDDLEWARE.md](./architecture/MIDDLEWARE.md)                                                             | Middleware pipeline         |
| [EVENTS.md](./architecture/EVENTS.md)                                                                     | Events & listeners          |
| [POLICIES.md](./architecture/POLICIES.md)                                                                 | Authorization policies      |
| [JOBS.md](./architecture/JOBS.md)                                                                         | Queue jobs                  |
| [TRAITS_EXCEPTIONS.md](./architecture/TRAITS_EXCEPTIONS.md)                                               | Traits, macros, exceptions  |
| [BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md](./architecture/BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md) | Cancellation & refund flow  |

### Features

| Document                                                  | Description                   |
| --------------------------------------------------------- | ----------------------------- |
| [AUTHENTICATION.md](./features/AUTHENTICATION.md)         | Auth (Bearer + HttpOnly)      |
| [BOOKING.md](./features/BOOKING.md)                       | Booking system                |
| [ROOMS.md](./features/ROOMS.md)                           | Room management               |
| [REVIEWS.md](./features/REVIEWS.md)                       | Reviews + XSS protection      |
| [RBAC.md](./features/RBAC.md)                             | Role-based access             |
| [CACHING.md](./features/CACHING.md)                       | Redis cache layer             |
| [OPTIMISTIC_LOCKING.md](./features/OPTIMISTIC_LOCKING.md) | Concurrency control for rooms |

### Guides

| Document                                                  | Description             |
| --------------------------------------------------------- | ----------------------- |
| [ENVIRONMENT_SETUP.md](./guides/ENVIRONMENT_SETUP.md)     | Dev environment         |
| [TESTING.md](./guides/TESTING.md)                         | Testing guide           |
| [PERFORMANCE.md](./guides/PERFORMANCE.md)                 | Octane & N+1            |
| [DEPLOYMENT.md](./guides/DEPLOYMENT.md)                   | Docker & deployment     |
| [COMMANDS.md](./guides/COMMANDS.md)                       | Artisan commands        |
| [MONITORING_LOGGING.md](./guides/MONITORING_LOGGING.md)   | Observability & logging |
| [EMAIL_NOTIFICATIONS.md](./guides/EMAIL_NOTIFICATIONS.md) | Email & verification    |

### Security

| Document                                          | Description      |
| ------------------------------------------------- | ---------------- |
| [HEADERS.md](./security/HEADERS.md)               | Security headers |
| [XSS_PROTECTION.md](./security/XSS_PROTECTION.md) | HTML Purifier    |
| [RATE_LIMITING.md](./security/RATE_LIMITING.md)   | Rate limiting    |

---

## Tech Stack

| Component | Technology      |
| --------- | --------------- |
| Framework | Laravel 11      |
| Language  | PHP 8.3         |
| Database  | PostgreSQL 15   |
| Cache     | Redis 7         |
| Auth      | Laravel Sanctum |
| Server    | Laravel Octane  |
| Queue     | Redis Queue     |

---

## Quick Start

```bash
cd backend

# Install dependencies
composer install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Run tests
php artisan test

# Start server
php artisan serve
```
