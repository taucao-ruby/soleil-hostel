# Repo Truth — Soleil Hostel

Authoritative source hierarchy and stack facts. Verified 2026-04-07.

## Stable Memory

### Source Hierarchy (conflict resolution order)
1. `docs/agents/ARCHITECTURE_FACTS.md` — canonical domain invariants
2. `docs/PERMISSION_MATRIX.md` — RBAC single source of truth
3. `.agent/rules/*.md` — fast-load derived assertions (8 files)
4. `skills/**/*.md` — implementation guidance (17 files across 3 domains)
5. `.claude/commands/*.md` — execution playbooks (6 commands)
6. `docs/COMPACT.md` — volatile session state only
7. README / prose — lowest authority; never treat as runtime proof

### Stack Truth (from lockfiles/config)
- **Backend**: Laravel `^12.0`, PHP `^8.2` — `backend/composer.json`
- **Frontend**: React `^19.0.0`, Vite `^4.3.4` (plugin), pnpm `9.15.9` — `frontend/package.json`
- **Database**: PostgreSQL `16-alpine` — `docker-compose.yml`
- **Cache**: Redis `7-alpine` — `docker-compose.yml`
- **Architecture**: Controller → Service → Repository (backend); feature-sliced (frontend)

### Quality Gates (required before merge)
```bash
cd backend && php artisan test          # 0 failures
cd frontend && npx tsc --noEmit         # 0 errors
cd frontend && npx vitest run           # 0 failures
docker compose config                   # valid
```
Source: `docs/agents/CONTRACT.md`, `docs/agents/COMMANDS.md`

### Anti-Drift Rules
- Never treat README prose as authoritative when contradicted by ARCHITECTURE_FACTS.md
- Never treat `docs/COMPACT.md` as policy — it is ephemeral session state
- Migrations are source-of-truth for schema; not model `$fillable` or docs
- `rooms.status` is DEPRECATED — canonical column is `rooms.readiness_status` (see `docs/DB_FACTS.md` § Deprecation)

## Learned Patterns

- Agent learnings system exists (`docs/agents/AGENT_LEARNINGS*.md`) but has zero active entries — schema is defined, entries are added only after real failures with evidence
- Task bundles in `docs/agents/TASK_BUNDLES.md` map common task types to skill+rule combinations — use before manually selecting skills

## Revalidation Notes

- After Laravel or React major version bumps: re-verify stack truth from lockfiles
- After adding new quality gates: update CONTRACT.md and COMMANDS.md simultaneously
- `rooms.status` deprecation phase: track progress in DB_FACTS.md § Deprecation
