# AGENTS.md — Soleil Hostel

Agent onboarding index. CLAUDE.md is auto-loaded every session and imports
ARCHITECTURE_FACTS.md and CONTRACT.md. Consult these additional layers as needed:

| Layer | File | Purpose |
|-------|------|---------|
| Commands + setup | `docs/agents/COMMANDS.md` | Quality gates, MCP targets, setup, dev servers |
| Session state | `docs/COMPACT.md` | Current snapshot, active work, handoff log |
| Slash commands | `.claude/commands/` | Invocable execution playbooks |
| Subagents | `.claude/agents/` | Specialist reasoning (security, DB, docs) |
| Skills | `skills/README.md` | Task-specific guardrails (16 skill files) |
| Governance | `docs/AI_GOVERNANCE.md` | Full agent workflow framework |

## Core Domains

Locations, Rooms, Bookings, Reviews, Contact Messages, Authentication.

## Primary Business Risks

- Double-booking prevention (half-open intervals + PostgreSQL exclusion constraint)
- Token/session security (dual-mode Sanctum + custom token columns)
- Cancellation/refund state machine integrity

## Repo Layout

```
./backend/          Laravel API — controllers, services, repositories, migrations, tests
./frontend/         React SPA — src/app/, src/features/, src/pages/, src/shared/
./docs/             Architecture, guides, operations
./skills/           Task-specific skill files (laravel/, react/, ops/)
./mcp/soleil-mcp/   MCP server (read-only + allowlisted verify commands)
./.claude/          Commands, agents, hooks, settings
```
