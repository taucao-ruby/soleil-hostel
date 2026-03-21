# Backend Architecture

> Architecture references for backend design, runtime behavior, and code organization.
>
> Last Updated: March 21, 2026

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

The folder reference includes all requested folders:

- `app/Console/Commands`
- `app/Database`
- `app/Directives`
- `app/Enums`
- `app/Events`
- `app/Exceptions`
- `app/Helpers`
- `app/Http`
- `app/Jobs`
- `app/Listeners`
- `app/Logging`
- `app/Macros`
- `app/Models`
- `app/Notifications`
- `app/Observers`
- `app/Octane`
- `app/Octane/Tables`
- `app/Policies`
- `app/Providers`
- `app/Repositories`
- `app/Services`
- `app/Traits`
- `config`
- `database`
- `database/backups`
- `database/factories`
- `database/migrations`
- `database/seeders`
