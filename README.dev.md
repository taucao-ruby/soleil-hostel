# Development README — Running frontend + backend

This file documents how to run the project locally (Windows-optimized) and with Docker Compose. It also lists common troubleshooting steps.

## Summary

- Frontend: `frontend/` — React + TypeScript + Vite
- Backend: `backend/` — Laravel (PHP 8.x)
- DB: PostgreSQL (optional when using Docker)

Two main approaches:

- Local (no Docker): install PHP/Composer/PostgreSQL locally and run both servers
- Docker Compose: single command to start DB, backend and frontend

---

## Prerequisites (Windows)

- Node.js (16+/18+/20+)
- npm (comes with Node.js) or yarn
- PHP 8.x and Composer (unless using Docker)
- PostgreSQL (unless using Docker)
- (Optional but recommended) WSL2
- (If using Docker) Docker Desktop with WSL2 integration

---

## 1) Quick local setup (no Docker)

### Backend

```powershell
cd backend
composer install
copy .env.example .env  # or copy on Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
# ensure DB settings in .env are correct and PostgreSQL database exists
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

### Frontend

```powershell
cd frontend
npm install
npm run dev -- --host
# or with yarn
# yarn
# yarn dev --host
```

Access frontend (Vite) at `http://localhost:5173` and backend at `http://localhost:8000`.

---

## 2) Run both with one command (concurrently)

From repo root:

```powershell
npm install --save-dev concurrently
npm run dev
```

`npm run dev` uses the root `package.json` script and will run both the Laravel dev server and Vite.

---

## 3) Docker Compose (recommended if you want a reproducible environment)

From repo root:

```powershell
docker compose up --build
```

This starts PostgreSQL, Redis, backend and frontend. Backend listens on `8000`, frontend on `5173`.

Note about Dockerfiles and networking:

- This repository includes simple development `Dockerfile`s for `backend` and `frontend`. Compose is configured to build those images and mount your local source so you get live edits during development.
- When running the frontend inside Docker on Windows, `host.docker.internal` is the easiest way for the frontend container to contact a service running on the host (for example if you run Laravel with `php artisan serve` locally). We set `VITE_API_URL` in `frontend/.env` to `http://host.docker.internal:8000/api` by default. If you run the backend as a Compose service, use `http://backend:8000/api` instead.
- **Note:** `host.docker.internal` is Windows/Mac Docker-specific and won't work in Linux Docker without extra config (`--add-host`).

---

## 4) Common troubleshooting

### 404 on `/api/rooms`

- Ensure backend server is running: `php artisan serve --host=127.0.0.1 --port=8000`
- Check `php artisan route:list` from `backend/` and look for `api/rooms`.
- Confirm `backend/routes/api.php` contains the resource route and `RoomController` exists under `app/Http/Controllers` with namespace `App\Http\Controllers`.

### CORS errors

- Update `backend/config/cors.php` allowed origins to include `http://localhost:5173`.

### Port conflicts

- Find the PID using the port: `netstat -ano | Select-String ":8000"` and then `Stop-Process -Id <PID> -Force`.

### php/composer not found

- Add PHP and Composer to PATH or use WSL2 or Docker.

---

## Detailed troubleshooting checklist

If you're still stuck, follow this checklist in order — it covers the common root causes for 404/CORS/port issues on Windows + Laravel + Vite setups.

1. Confirm servers are running and ports
   - Backend (Laravel dev server):

     ```powershell
     cd backend
     php artisan serve --host=127.0.0.1 --port=8000
     ```

     Open `http://127.0.0.1:8000` in the browser — you should see the Laravel welcome page.

   - Frontend (Vite):
     ```powershell
     cd frontend
     npm run dev -- --host
     ```
     Vite usually serves at `http://localhost:5173`.

2. If API returns 404 for `http://127.0.0.1:8000/api/rooms`
   - Run `php artisan route:list` from `backend/` and look for `api/rooms`.
   - If the route is missing:
     - Open `backend/routes/api.php` and confirm you have the route registration, for example:

     ```php
     use App\Http\Controllers\RoomController;
     Route::apiResource('rooms', RoomController::class);
     ```

     - Confirm `backend/app/Http/Controllers/RoomController.php` exists and the namespace at top is `namespace App\Http\Controllers;`.
     - If controller was added recently, run `composer dump-autoload`.

3. Clear caches (very common fix for route/controller issues)

   ```powershell
   cd backend
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   composer dump-autoload
   php artisan route:list
   ```

4. Check Laravel logs for exceptions
   - `backend/storage/logs/laravel.log`
   - If there are permission issues on Windows with mounted volumes, ensure files are writable by the PHP process (when using Docker)

5. Port conflicts on Windows
   - Find who uses the port (example for 8000):
     ```powershell
     netstat -ano | Select-String ":8000"
     ```
   - Kill the blocking PID if it's safe:
     ```powershell
     Stop-Process -Id <PID> -Force
     ```

6. CORS from browser
   - If the browser console shows CORS, temporarily allow origins in `config/cors.php` during development:
     ```php
     'paths' => ['api/*'],
     'allowed_methods' => ['*'],
     'allowed_origins' => ['http://localhost:5173'],
     'allowed_headers' => ['*'],
     'supports_credentials' => false,
     ```
   - After changing config, run `php artisan config:clear`.

7. Vite + API base URL mismatch
   - Ensure `frontend/.env` or Vite environment has `VITE_API_URL` set (ex: `VITE_API_URL=http://localhost:8000/api`).
   - We updated `frontend/src/services/api.ts` to read `import.meta.env.VITE_API_URL` with a fallback. If you change `.env`, restart Vite.

8. Using Docker — container troubleshooting
   - `docker compose ps` to see containers
   - `docker compose logs backend` to see Laravel output
   - If migrations fail in container startup, run them manually inside the container:
     ```powershell
     docker compose exec backend bash
     php artisan migrate
     ```

9. If all else fails
   - Try reproducing the exact request with `curl` or Postman to isolate browser/CORS vs server 404.
   - Share `php artisan route:list` output and the top 50 lines of `storage/logs/laravel.log` and I can help pinpoint the issue.

---

## 5) Project resources

- Full audit report: [AUDIT_REPORT.md](./AUDIT_REPORT.md)
- Project status: [PROJECT_STATUS.md](./PROJECT_STATUS.md)
- Documentation index: [docs/README.md](./docs/README.md)
- API documentation (Redoc): [docs/api/index.html](./docs/api/index.html)
