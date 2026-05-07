# Backend Architecture

> Architecture references for backend design, runtime behavior, and code organization.
>
> Last Updated: May 8, 2026

## Architecture Index

| Document | Description |
| --- | --- |
| [API.md](./API.md) | API contracts and endpoint behavior |
| [../../DATABASE.md](../../DATABASE.md) | Schema, indexes, and migration history |
| [FOLDER_REFERENCE.md](./FOLDER_REFERENCE.md) | Current folder-level inventory for `backend/app`, `backend/config`, and `backend/database` |
| [SERVICES.md](./SERVICES.md) | Service layer responsibilities |
| [REPOSITORIES.md](./REPOSITORIES.md) | Repository pattern and contracts |
| [MIDDLEWARE.md](./MIDDLEWARE.md) | HTTP middleware pipeline |
| [EVENTS.md](./EVENTS.md) | Event-driven workflows |
| [POLICIES.md](./POLICIES.md) | Authorization policy model |
| [JOBS.md](./JOBS.md) | Queue job behavior |
| [TRAITS_EXCEPTIONS.md](./TRAITS_EXCEPTIONS.md) | Traits, macros, directives, and exception types |
| [BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md](./BOOKING_CANCELLATION_REFUND_ARCHITECTURE.md) | Cancellation and refund flow |

## Backend Folder Coverage

The folder reference covers every directory currently present under `backend/app/`, `backend/config/`, and `backend/database/`. As of HEAD (`6372d7f`, May 8, 2026), `backend/app/` contains:

- `AiHarness` — AI harness (DTOs, Services, Middleware, Providers, Enums, Exceptions, ToolRegistry, PromptRegistry)
- `Booking` — booking value objects and domain primitives
- `Console/Commands` — artisan commands (incl. `ai:eval`, `cache:warmup`)
- `Database` — custom DB-layer extensions
- `Directives` — Blade directives
- `Enums` — type-safe enums (UserRole, BookingStatus, ProposalActionType, …)
- `Events` — domain events
- `Exceptions` — typed exceptions (OptimisticLockException, ProposalLifecycle, …)
- `Helpers` — `SecurityHelpers.php` and friends
- `Http` — Controllers, Middleware, Requests, Resources
- `Jobs` — queue jobs (incl. `ReconcileRefundsJob`)
- `Listeners` — event listeners
- `Logging` — custom log processors (PII redaction)
- `Macros` — Eloquent / collection macros
- `Models` — Eloquent models
- `Notifications` — notification classes
- `Observers` — model observers
- `Octane` — Octane-specific bindings
- `Policies` — authorization policies
- `Providers` — service providers
- `Repositories` — data-access layer (paired unit tests under `tests/Unit/Repositories`)
- `Services` — business-logic layer
- `Traits` — reusable traits

`backend/database/` contains `factories/`, `migrations/`, and `seeders/`.

> Treat the on-disk tree as authoritative. If anything in [`FOLDER_REFERENCE.md`](./FOLDER_REFERENCE.md) drifts from `ls backend/app/`, update the doc — do not work from the doc.
