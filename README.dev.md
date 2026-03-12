# Development README - Soleil Hostel

This file documents the day-to-day development workflow for the monorepo. It reflects the current repo scripts, package managers, Docker services, and health endpoints.

## Environment Files

Use the right env template for the right layer:

- Root `.env.example`: Docker Compose variables for `docker-compose.yml` and `docker-compose.prod.yml`
- `backend/.env.example`: Laravel application configuration
- `frontend/.env.example`: Vite environment variables

Do not copy the root `.env.example` into `backend/` or `frontend/`. Each layer has its own contract.

## Prerequisites

- Node.js 20+
- npm for root tooling
- pnpm for the frontend workspace
- PHP 8.2+ (platform pinned to 8.3 in composer.json)
- Composer 2.x
- PostgreSQL 16+ for local backend work
- Redis 7+ if you want the local stack to match production more closely
- Docker Desktop if you prefer the Compose workflow

## Local Development

### 1. Install root tooling

From the repository root:

```powershell
npm install
```

This installs root tooling such as Husky and the `npm run dev` orchestration script.

### 2. Backend setup

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Notes:

- `backend/.env.example` defaults to PostgreSQL on `127.0.0.1:5432`.
- Tests and health checks also assume Redis is available on `127.0.0.1:6379` when you want a closer CI-like setup.
- Backend health endpoints live under `/api/health/*`, including `/api/health/live` and `/api/health/ready`.

### 3. Frontend setup

```powershell
cd frontend
pnpm install
Copy-Item .env.example .env
pnpm dev
```

Notes:

- In local development, `VITE_API_URL` can stay empty.
- Vite proxies `/api` requests to `http://127.0.0.1:8000` via `frontend/vite.config.ts`.
- `VITE_API_URL` is required for production builds, but not for normal local development.

### 4. Run both apps from the repo root

```powershell
npm run dev
```

This starts the Laravel development server from `backend/` and the Vite development server from `frontend/`.

## Docker Compose Development

Use the root env template when working with Docker Compose:

```powershell
Copy-Item .env.example .env
docker compose up --build
```

This starts the current development stack:

- `db` - PostgreSQL on `127.0.0.1:5432`
- `redis` - Redis on `127.0.0.1:6379`
- `backend` - Laravel app on `127.0.0.1:8000`
- `frontend` - Vite app on `127.0.0.1:5173`

Useful commands:

```powershell
docker compose ps
docker compose logs backend
docker compose exec backend php artisan migrate
docker compose config
```

## Verification Commands

These are the repo-aligned checks worth running before shipping changes:

```powershell
cd backend
php artisan route:list --path=health

cd ..\frontend
npx tsc --noEmit
pnpm run test:unit
pnpm run build
pnpm run lint

cd ..
docker compose config
```

## Troubleshooting

### API requests fail in local development

- Make sure Laravel is running on `http://127.0.0.1:8000`.
- Confirm the frontend is calling `/api`, not a stale hard-coded host.
- Check `frontend/src/shared/lib/api.ts` and confirm `VITE_API_URL` is empty or correct for your environment.

### Docker stack uses the wrong database settings

- The root `.env` controls Docker Compose only.
- The Laravel app inside `backend/` still reads `backend/.env` when you run Artisan locally outside Compose.
- If Compose resolves to unexpected values, run `docker compose config` and inspect the expanded environment.

### Health endpoints do not respond

- Run `cd backend && php artisan route:list --path=health` to confirm the routes are registered.
- Check `/api/health/live` first, then `/api/health/ready`.
- If readiness fails, verify PostgreSQL and Redis are reachable.

### PowerShell blocks pnpm or npx

- If PowerShell blocks `pnpm` or `npx` with an execution-policy error, use `pnpm.cmd` or `npx.cmd` instead.
- You can also adjust your execution policy if that matches your machine policy.

### Port conflicts on Windows

```powershell
netstat -ano | Select-String ":8000"
netstat -ano | Select-String ":5173"
Stop-Process -Id <PID> -Force
```

## Related Files

- `package.json`
- `frontend/package.json`
- `backend/.env.example`
- `frontend/.env.example`
- `docker-compose.yml`
- `docker-compose.prod.yml`
