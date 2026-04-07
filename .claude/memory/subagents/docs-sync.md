# Docs Sync — Subagent Memory

Role-scoped memory for code-vs-docs drift detection and canonical source alignment.

## Stable Memory

### Canonical Docs to Verify
1. `CLAUDE.md` — constitutional; must not contain stale stack versions or wrong file paths
2. `AGENTS.md` — onboarding index; must list current agents, commands, skills
3. `docs/agents/ARCHITECTURE_FACTS.md` — domain invariants; verify against migrations and service layer
4. `docs/agents/CONTRACT.md` — DoD checklists; verify gate commands match actual tooling
5. `docs/agents/COMMANDS.md` — command reference; verify slash commands match `.claude/commands/`
6. `docs/PERMISSION_MATRIX.md` — RBAC; verify against actual route middleware and policies
7. `docs/COMPACT.md` — session state; section 1 must stay under 12 lines
8. `.agent/rules/*.md` — verify each against its `verified-against` frontmatter source

### Cross-Reference Sources
- `backend/database/migrations/` — schema truth
- `backend/routes/api.php`, `backend/routes/api/` — route definitions
- `backend/app/Http/Controllers/` — middleware assignments
- `frontend/src/features/` — feature-sliced structure
- `frontend/src/shared/lib/api.ts` — API client config
- `backend/composer.json`, `frontend/package.json` — dependency versions
- `docker-compose.yml` — infrastructure versions

### Known Drift Areas (from PERMISSION_MATRIX.md)
- FU-1: Legacy cancellation tests still on `/api/bookings/` not `/api/v1/`
- FU-5: Room CUD tests still on `/api/rooms/` not `/api/v1/rooms/`
- `rooms.status` deprecated in favor of `rooms.readiness_status` (DB_FACTS.md)

## Learned Patterns

- Update docs only from current code truth — never preserve stale prose because it sounds reasonable
- Explicitly mark unverified runtime claims in docs with `[UNVERIFIED]` or `[NEEDS RUNTIME CONFIRMATION]`
- Line-level edits only — do not rewrite entire documents
- Do not invent facts — only surface discrepancies found by reading actual source files
- COMPACT.md section 1 creep is a recurring issue — enforce 12-line limit

## Revalidation Notes

- After any route or middleware change: immediately verify PERMISSION_MATRIX.md and ARCHITECTURE_FACTS.md
- After dependency version bumps: verify stack versions in any doc that mentions them
- After new agent or command added: verify AGENTS.md and COMMANDS.md reflect the addition
- After FU-* items are resolved: confirm PERMISSION_MATRIX.md follow-up section is updated
