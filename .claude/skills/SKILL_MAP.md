# Skill map

Routing reference for Claude sessions. Discovery (`description` matching) does not auto-fire any skill in this tree — every skill here is `disable-model-invocation: true` or invoked explicitly.

## When to use which surface

| Need | Use this | Not this |
|---|---|---|
| Find code by concept (auth flow, booking lifecycle) | `soleil-ai-review-engine_query` MCP tool | `.claude/skills/generated/<area>/SKILL.md` (those are static snapshots, MCP returns fresh data) |
| 360° view of a symbol (callers, callees, processes) | `soleil-ai-review-engine_context` MCP tool | grep |
| Blast radius before editing | `soleil-ai-review-engine_impact` MCP tool | manual call-site search |
| Pre-commit scope check | `soleil-ai-review-engine_detect_changes` MCP tool | git diff alone |
| Safe rename across files | `soleil-ai-review-engine_rename` MCP tool | find/replace |
| Index, status, clean, wiki | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-cli/SKILL.md` | shell guesses |

## Generated skills (`.claude/skills/generated/*`)

Snapshots produced by the indexer. Useful as a name-only directory of the codebase areas. Each is `disable-model-invocation: true`, so they will not auto-fire on description match.

When to read one:

- You want to know which files cluster under a given area name without running an MCP query.
- You are offline from the soleil-ai-review-engine MCP server.

When NOT to read one:

- The file count or symbol count matters — those are point-in-time and the MCP server has fresh data.
- You need callers/callees — use `soleil-ai-review-engine_context`.

| Area | File |
|---|---|
| admin | `.claude/skills/generated/admin/SKILL.md` |
| auth | `.claude/skills/generated/auth/SKILL.md` |
| authorization | `.claude/skills/generated/authorization/SKILL.md` |
| booking (backend) | `.claude/skills/generated/booking/SKILL.md` |
| bookings (frontend) | `.claude/skills/generated/bookings/SKILL.md` |
| cache | `.claude/skills/generated/cache/SKILL.md` |
| controllers | `.claude/skills/generated/controllers/SKILL.md` |
| database | `.claude/skills/generated/database/SKILL.md` |
| enums | `.claude/skills/generated/enums/SKILL.md` |
| feature (cross-cutting) | `.claude/skills/generated/feature/SKILL.md` |
| listeners | `.claude/skills/generated/listeners/SKILL.md` |
| middleware | `.claude/skills/generated/middleware/SKILL.md` |
| models | `.claude/skills/generated/models/SKILL.md` |
| notifications | `.claude/skills/generated/notifications/SKILL.md` |
| policies | `.claude/skills/generated/policies/SKILL.md` |
| repositories | `.claude/skills/generated/repositories/SKILL.md` |
| requests | `.claude/skills/generated/requests/SKILL.md` |
| room | `.claude/skills/generated/room/SKILL.md` |
| services | `.claude/skills/generated/services/SKILL.md` |
| stays | `.claude/skills/generated/stays/SKILL.md` |

Regenerate with `npx soleil-engine-cli analyze` (preserve embeddings with `--embeddings`).

## Curated skills

| Area | File | When to invoke |
|---|---|---|
| soleil-ai-review-engine — exploring | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-exploring/SKILL.md` | "How does X work?" |
| soleil-ai-review-engine — impact analysis | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-impact-analysis/SKILL.md` | "What breaks if I change X?" |
| soleil-ai-review-engine — debugging | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-debugging/SKILL.md` | "Why is X failing?" |
| soleil-ai-review-engine — refactoring | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-refactoring/SKILL.md` | rename/extract/split/move |
| soleil-ai-review-engine — guide | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-guide/SKILL.md` | tools/resources/schema reference |
| soleil-ai-review-engine — CLI | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-cli/SKILL.md` | index/status/clean/wiki commands |

## Plugin skills

`.claude/plugins/soleil-engineering/skills/` — manual invocation only:

- `/soleil-engineering:release-gates` — wraps `scripts/ship.sh`
- `/soleil-engineering:static-analysis-triage` — Pint/PHPStan/Psalm/tsc/vitest
- `/soleil-engineering:security-review` — harness/MCP/CI/hook security pass
