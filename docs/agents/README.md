# AI Agent Framework — Soleil Hostel

This directory contains the structured governance framework for AI coding agents working in this repository.

## Files

| File | Purpose |
|------|---------|
| [CONTRACT.md](./CONTRACT.md) | Definition of Done for all task types |
| [ARCHITECTURE_FACTS.md](./ARCHITECTURE_FACTS.md) | Domain invariants verified against code |
| [COMMANDS.md](./COMMANDS.md) | Verified command reference |
| [AGENT_LEARNINGS.md](./AGENT_LEARNINGS.md) | Operational failure patterns (active entries + proposed staging) |
| [AGENT_LEARNINGS_OPERATING_RULES.md](./AGENT_LEARNINGS_OPERATING_RULES.md) | Read/write/rejection rules for the learning system |
| [AGENT_LEARNINGS_SCHEMA.md](./AGENT_LEARNINGS_SCHEMA.md) | Field definitions and entry format |
| [AGENT_LEARNINGS_EXAMPLES.md](./AGENT_LEARNINGS_EXAMPLES.md) | Illustrative entries for schema training (do not cite as real failures) |

## How to Use (agent workflow)

1. **Read** `../../AGENTS.md` first (root-level onboarding)
2. **Read** `CONTRACT.md` to understand what "done" means
3. **Read** `ARCHITECTURE_FACTS.md` to know what invariants to preserve
4. **Read** `COMMANDS.md` for verified commands to run
5. **Read** `../COMPACT.md` for current session state
6. **Select skills** from `../../skills/` relevant to the task
7. **Execute** — discover paths via MCP, implement, run gates
8. **Update** `../COMPACT.md` when done

## Related Docs

- [CLAUDE.md](../../CLAUDE.md) — Claude Code CLI master context (start here)
- [AI Governance](../AI_GOVERNANCE.md) — operational checklists
- [MCP Server](../MCP.md) — tool server + safety policy
- [Hooks](../HOOKS.md) — local enforcement
- [Commands & Gates](../COMMANDS_AND_GATES.md) — full command reference
- [Skills](../../skills/README.md) — task-specific guardrails
