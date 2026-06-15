# Frontend Deployment

The frontend is a Vite-built React SPA served as static assets behind nginx. Its production
container is a multi-stage build (Node build stage → nginx serve stage).

## At a glance

- **Build**: `pnpm run build` → static bundle in `dist/`
- **Image**: multi-stage `frontend/Dockerfile` (Node build → nginx static serve)
- **Serve**: nginx serves the SPA and proxies `/api` to the backend
- **Build/tooling config**: see [CONFIGURATION.md](./CONFIGURATION.md) (Vite, tsconfig, env)

## Full production deployment

The complete production deployment configuration — the frontend Dockerfile, the backend
Dockerfile, `docker-compose.prod.yml`, nginx reverse proxy, environment management, the
GitHub Actions CI/CD pipeline, monitoring, backup, and security hardening — lives in:

➡️ **[docs/ops/PRODUCTION_DEPLOYMENT.md](../ops/PRODUCTION_DEPLOYMENT.md)**

Operational procedure and rollback: **[docs/backend/DEPLOYMENT.md](../backend/DEPLOYMENT.md)** (runbook).
