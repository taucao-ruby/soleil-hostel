# Control Plane Ownership — Soleil Hostel

> **Single canonical source for AI governance control-plane ownership.**
> Do not duplicate this matrix into other files — link to it.
>
> Created: 2026-04-04 (Harness Hardening Wave 2)

## Ownership Matrix

| Component | Owner Role | Maintainer | Review Cadence | Escalation |
|-----------|-----------|------------|----------------|------------|
| `CLAUDE.md` | Tech Lead | Manual | Per-constitutional-change | Stop + human review |
| `.agent/rules/*.md` | Tech Lead | docs-sync agent | Per-PR + monthly | docs-sync flags drift |
| `.claude/hooks/*.sh` | Platform Engineer | Manual | Quarterly | Verify via `scripts/verify-control-plane.sh` |
| `.claude/settings.json` | Platform Engineer | Manual | Per-PR | JSON validation in verify script |
| `.claude/settings.local.json` | Individual Developer | Manual | N/A (gitignored) | — |
| `.claude/commands/*.md` | Tech Lead | Manual | Per-feature | Review against CONTRACT.md DoD |
| `.claude/agents/*.md` | Tech Lead | Manual | Per-feature | Review tool access against least-privilege |
| `mcp/soleil-mcp/policy.json` | Platform Engineer | Manual | Per-release | No arbitrary command additions without review |
| `mcp/soleil-mcp/src/index.ts` | Platform Engineer | Manual | Per-release | Output redaction patterns must cover new secrets |
| `skills/**/*.md` | Domain Lead | docs-sync agent | Per-feature | Skill selection guide in `skills/README.md` |
| `AGENT_LEARNINGS*.md` | AI Engineer | R-07 guardrail | Continuous | See `AGENT_LEARNINGS_OPERATING_RULES.md` |
| `PERMISSION_MATRIX.md` | Security Lead | Manual | Per-role-change | Full permission re-audit on hierarchy changes |
| `docs/agents/CONTRACT.md` | Tech Lead | Manual | Per-gate-change | DoD changes require team agreement |
| `docs/agents/COMMANDS.md` | Tech Lead | Manual | Per-command-change | Prerequisite list must stay current |
| `docs/agents/TASK_BUNDLES.md` | Tech Lead | Manual | Per-skill-addition | Bundle map updated when skills are added/removed |
| `scripts/verify-control-plane.sh` | Platform Engineer | Manual | Quarterly | Must pass on fresh clone before release |

## Review Triggers

Certain changes require immediate review by the component owner:

| Change Type | Triggered Review |
|-------------|------------------|
| New role added to `UserRole` enum | Security Lead: full PERMISSION_MATRIX re-audit |
| New hook added to `.claude/hooks/` | Platform Engineer: verify fail-open behavior + audit logging |
| New MCP tool or `allowed_commands` entry | Platform Engineer: safety boundary review |
| New skill file in `skills/` | Domain Lead: bundle map update in TASK_BUNDLES.md |
| Rule `last-verified` > 90 days | docs-sync agent: re-verification required |
| `verify-control-plane.sh` reports FAIL | Platform Engineer: fix before next release |

## Unowned Component Policy

If a component has no assigned owner:
1. Flag as `UNOWNED` in this matrix
2. Add to `FINDINGS_BACKLOG.md` as a governance gap
3. Assign a temporary owner within one sprint
4. Temporary owner inherits review cadence until permanent assignment
