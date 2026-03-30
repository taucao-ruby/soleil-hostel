# AGENTS.md — Soleil Hostel

Agent onboarding index. CLAUDE.md is auto-loaded every session and imports
ARCHITECTURE_FACTS.md and CONTRACT.md. Consult these additional layers as needed:

| Layer | File | Purpose |
|-------|------|---------|
| Commands + setup | `docs/agents/COMMANDS.md` | Quality gates, MCP targets, setup, dev servers |
| Session state | `docs/COMPACT.md` | Current snapshot, active work, handoff log |
| Slash commands | `.claude/commands/` | Invocable execution playbooks |
| Subagents | `.claude/agents/` | Specialist reasoning (security, DB, docs) |
| Skills | `skills/README.md` | Task-specific guardrails (17 skill files) |
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

<!-- soleil-ai-review-engine:start -->
# soleil-ai-review-engine — Code Intelligence

This project is indexed by soleil-ai-review-engine as **soleil-hostel** (4479 symbols, 11693 relationships, 178 execution flows). Use the soleil-ai-review-engine MCP tools to understand code, assess impact, and navigate safely.

> If any soleil-ai-review-engine tool warns the index is stale, run `npx soleil-engine-cli analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `soleil-ai-review-engine_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `soleil-ai-review-engine_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `soleil-ai-review-engine_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `soleil-ai-review-engine_context({name: "symbolName"})`.

## When Debugging

1. `soleil-ai-review-engine_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `soleil-ai-review-engine_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ soleil-ai-review-engine://repo/soleil-hostel/process/{processName}` — trace the full execution flow step by step
4. For regressions: `soleil-ai-review-engine_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `soleil-ai-review-engine_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `soleil-ai-review-engine_context({name: "target"})` to see all incoming/outgoing refs, then `soleil-ai-review-engine_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `soleil-ai-review-engine_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `soleil-ai-review-engine_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `soleil-ai-review-engine_rename` which understands the call graph.
- NEVER commit changes without running `soleil-ai-review-engine_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `soleil-ai-review-engine_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `soleil-ai-review-engine_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `soleil-ai-review-engine_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `soleil-ai-review-engine_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `soleil-ai-review-engine_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `soleil-ai-review-engine_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `soleil-ai-review-engine://repo/soleil-hostel/context` | Codebase overview, check index freshness |
| `soleil-ai-review-engine://repo/soleil-hostel/clusters` | All functional areas |
| `soleil-ai-review-engine://repo/soleil-hostel/processes` | All execution flows |
| `soleil-ai-review-engine://repo/soleil-hostel/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `soleil-ai-review-engine_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `soleil-ai-review-engine_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the soleil-ai-review-engine index becomes stale. Re-run analyze to update it:

```bash
npx soleil-engine-cli analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx soleil-engine-cli analyze --embeddings
```

To check whether embeddings exist, inspect `.soleil-ai-review-engine/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/soleil-ai-review-engine/soleil-ai-review-engine-cli/SKILL.md` |

<!-- soleil-ai-review-engine:end -->
