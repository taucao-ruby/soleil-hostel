# Docker Compose Skill

Use this skill when editing or validating local multi-service runtime behavior in `docker-compose.yml`.

## When to Use This Skill

- You modify service definitions for backend, frontend, PostgreSQL, or Redis.
- You change environment variables, health checks, ports, or startup commands.
- You need local dev parity with production assumptions.

## Non-negotiables

- Keep `docker compose config` valid after every compose change.
- Preserve core service topology:
  - `db` (PostgreSQL 16)
  - `redis` (Redis 7)
  - `backend` (Laravel)
  - `frontend` (React/Vite)
- Keep security hygiene in env wiring.
  - No real secrets in committed compose defaults.
  - Keep Redis/password and app keys env-driven.
- Preserve APP_KEY behavior.
  - Backend startup command should not regenerate `APP_KEY` when already set.
- Maintain expected local network/port bindings unless intentionally changed.

Service-specific safety checks:

- `db`: healthcheck uses `pg_isready` with expected user.
- `redis`: auth command and healthcheck password expression stay aligned.
- `backend`: env uses pgsql + redis defaults; startup command remains non-destructive.
- `frontend`: Vite API URL points to backend API path.

## Implementation Checklist

1. Inspect current compose service contracts and dependencies.
2. Apply minimal change for the requested behavior.
3. Re-check env variable fallbacks and secret handling.
4. Validate backend startup command safety (especially key generation logic).
5. Validate health checks for db and redis after changes.
6. Run compose config validation and document any runtime caveats.
7. Confirm no accidental secret hardcoding was introduced.

## Verification / DoD

```bash
# Required
docker compose config

# Common follow-up checks (if runtime behavior changed)
docker compose up --build
docker compose ps
docker compose logs backend
docker compose logs redis
```

Optional parity checks:

```bash
docker compose exec backend php artisan --version
docker compose exec backend php artisan migrate --pretend
docker compose exec frontend node -v
```

## Common Failure Modes

- Compose file syntax/quoting errors that break config parsing.
- Startup command changes that regenerate `APP_KEY` unexpectedly.
- Redis password mismatch between service command and app env values.
- Local ports exposed too broadly without intent.
- Diverging from PostgreSQL-first assumptions used by backend behavior and CI.
- Breaking bind mounts and causing stale container code to run unexpectedly.
- Editing compose env defaults without updating docs or `.env.example` guidance.

## References

- `../../AGENTS.md`
- `../../docker-compose.yml`
- `../../redis.conf`
- `../../PROJECT_STATUS.md`
- `../../.github/workflows/tests.yml`
