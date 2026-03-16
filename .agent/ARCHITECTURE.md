# AI Operating Layer — Soleil Hostel

## Layer Model

| Layer | Location | Purpose |
|-------|----------|---------|
| Claude Code execution | `.claude/` | Claude Code-specific agents, commands, hooks, output styles |
| Model-agnostic operations | `.agent/` | Portable rules, workflows, scripts — usable by any AI tool or human |
| Implementation guidance | `skills/` | Reusable domain skill files loaded on demand by agents and commands |
| Canonical truth | `docs/agents/ARCHITECTURE_FACTS.md`, `docs/PERMISSION_MATRIX.md` | Authoritative — never overridden |

`.agent/` does NOT contain a second `agents/` or `skills/` folder. Those live in `.claude/` and `skills/` respectively.

## Source-of-Truth Priority

```
1. docs/agents/ARCHITECTURE_FACTS.md     ← domain invariants, highest authority
2. docs/PERMISSION_MATRIX.md             ← RBAC canonical baseline
3. .agent/rules/*.md                     ← fast-load summaries (derived, not authoritative)
4. skills/laravel/*.md  skills/react/*.md  skills/ops/*.md
5. .claude/commands/*.md
6. docs/COMPACT.md                       ← volatile session state only
```

If a rule file and ARCHITECTURE_FACTS.md disagree: **the rule is stale, ARCHITECTURE_FACTS.md wins.**

## `.agent/` Contents

### `rules/`
Fast-load assertion files. Each summarizes invariants from one canonical source.
- State what MUST be true and what are STOP conditions
- Do not contain verbatim multi-line SQL or full column tables (→ read canonical doc)
- Do not contain implementation checklists (→ skills)
- Do not contain ordered steps (→ workflows)
- Required frontmatter: `verified-against`, `section`, `last-verified`, `maintained-by`

### `workflows/`
Portable, operator-readable procedures.
- Ordered steps using `READ / LOAD / USE / INVOKE / RUN` verbs
- Define STOP conditions and expected outputs
- Portable outside Claude Code — a human can follow them
- Do not contain canonical facts verbatim
- Do not contain agent persona language or Claude-specific prompt formatting

### `scripts/`
Mechanical, read-only bash scripts.
- Grep-based checks against actual source files
- Exit codes: `0` PASS / `1` FAIL / `2` UNKNOWN
- Never silently pass on missing files or evidence
- Never modify files, never require DB or network access
- CI-safe and Git Bash compatible

## Relation to `skills/`

Skills contain implementation guidance, review checklists, failure modes, and verification commands.
Workflows and agents LOAD skills — they do not copy their content.
Skills do NOT store canonical facts verbatim — they reference ARCHITECTURE_FACTS.md for exact invariants.

## Load Order for a Fresh Session

```
Auto-loaded (CLAUDE.md @imports):
  → docs/agents/ARCHITECTURE_FACTS.md
  → docs/agents/CONTRACT.md

Agent-selected on invocation:
  → .agent/rules/<relevant>.md         (fast entry point, ~30-50 lines)
  → skills/<area>/<skill>.md           (1-3 max per session)

Load only when needed:
  → docs/PERMISSION_MATRIX.md          (RBAC-touching tasks)
  → docs/COMPACT.md                    (session continuity only)
  → specific source files directly     (when exact SQL or evidence-grade precision required)
```

## Anti-Duplication Rules

- Invariant facts → ARCHITECTURE_FACTS.md only; rules summarize, skills reference, agents load
- RBAC matrix → PERMISSION_MATRIX.md only; do not redefine elsewhere
- Implementation patterns → skills only; do not copy into rules, workflows, or agents
- Ordered procedures → workflows only; commands may reference workflows but do not duplicate steps
- `docs-sync` agent must verify rule files against their `verified-against` source on every audit run
