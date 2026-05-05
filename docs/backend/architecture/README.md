# Backend Architecture

> Architecture references for backend design, runtime behavior, and code organization.
>
> Last Updated: May 5, 2026

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

- `app/AiHarness` — AI harness (DTOs, Services, Middleware, Providers, Enums, Exceptions, ToolRegistry, PromptRegistry)
- `app/Console/Commands`
- `app/Enums`
- `app/Events`
- `app/Exceptions`
- `app/Helpers`
- `app/Http`
- `app/Jobs`
- `app/Listeners`
- `app/Logging`
- `app/Models`
- `app/Policies`
- `app/Providers`
- `app/Repositories`
- `app/Services`
- `app/Traits`
- `config`
- `database`
- `database/factories`
- `database/migrations`
- `database/seeders`

> If a folder appears in this list but is missing from `backend/app/` on disk, treat the on-disk tree as authoritative — `app/Macros`, `app/Directives`, `app/Notifications`, `app/Observers`, `app/Octane` are listed in legacy reference docs but are not currently present. Verify with `ls backend/app/` or check `[FOLDER_REFERENCE.md](./FOLDER_REFERENCE.md)`.
