# Agent Commands Reference — Soleil Hostel

Verified against code on 2026-02-21. Full details in [COMMANDS_AND_GATES.md](../COMMANDS_AND_GATES.md).

## Quality Gates (must pass before merge)

```bash
# Backend tests
cd backend && php artisan test

# Frontend typecheck
cd frontend && npx tsc --noEmit

# Frontend unit tests
cd frontend && npx vitest run

# Docker compose validation
docker compose config
```

## MCP run_verify Targets

If using MCP server, these are the allowlisted verify targets:

| Target                  | Equivalent command                     |
| ----------------------- | -------------------------------------- |
| `backend_tests`         | `cd backend && php artisan test`       |
| `frontend_typecheck`    | `cd frontend && npx tsc --noEmit`      |
| `frontend_unit_tests`   | `cd frontend && npx vitest run`        |
| `docker_compose_config` | `docker compose config`                |
| `backend_lint`          | `cd backend && vendor/bin/pint --test` |
| `frontend_lint`         | `cd frontend && pnpm lint`             |

## Additional Checks (useful but not blocking)

```bash
# Backend static analysis
cd backend && vendor/bin/phpstan analyse      # Level 5
cd backend && vendor/bin/psalm                 # Level 1

# Backend code style
cd backend && vendor/bin/pint --test

# Frontend lint + format
cd frontend && pnpm lint
cd frontend && pnpm format

# Security audits
cd backend && composer audit
cd frontend && pnpm audit --audit-level=high
```

## Setup Commands

```bash
# Backend
cd backend && composer install
cd backend && cp .env.example .env
cd backend && php artisan key:generate
cd backend && php artisan migrate:fresh --seed

# Frontend
cd frontend && pnpm install

# Hooks
npm install && npm run hooks:install

# MCP server
cd mcp/soleil-mcp && npm install && npm run build
```

## Dev Servers

```bash
# Both (from root)
npm run dev

# Backend only
cd backend && php artisan serve --host=127.0.0.1 --port=8000

# Frontend only
cd frontend && pnpm dev
# → http://localhost:5173

# Docker stack
docker compose up --build
```
