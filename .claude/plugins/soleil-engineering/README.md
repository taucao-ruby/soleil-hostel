# soleil-engineering

Reusable Claude Code workflows for the Soleil Hostel monorepo.

This plugin packages thin orchestration around existing repo assets — `scripts/ship.sh`, `mcp/soleil-mcp`, and the docs in `docs/agents/`. It does not duplicate the project-level `.claude/` control plane; it complements it.

## Layout

```
.claude/plugins/soleil-engineering/
  .claude-plugin/plugin.json        plugin manifest
  skills/
    release-gates/SKILL.md          wraps scripts/ship.sh
    static-analysis-triage/SKILL.md Pint/PHPStan/Psalm/tsc/vitest triage
    security-review/SKILL.md        harness + MCP + CI security pass
  agents/
    release-reviewer.md             read-only release readiness
    security-reviewer.md            read-only security audit (harness scope)
  hooks/
    hooks.json                      hook bindings
    session-start-reminder.sh       reminder of available skills/agents
    pre-compact-reminder.sh         preserve blockers/verification on compact
  README.md                         this file
```

## Local development

This plugin lives under `.claude/plugins/`, the per-project plugin location. Discovery depends on your Claude Code version:

- **Auto-discovered** (recent versions) — opening Claude Code in the repo root surfaces the plugin's skills and agents automatically.
- **Manual** (older versions) — load it explicitly:

  ```bash
  claude --plugin-dir ./.claude/plugins/soleil-engineering
  ```

After editing any skill, agent, or hook:

```
/reload-plugins
```

## Skills

Each skill is `disable-model-invocation: true` — call it explicitly.

| Slash command | Purpose |
|---|---|
| `/soleil-engineering:release-gates` | Run `scripts/ship.sh` and report each gate outcome |
| `/soleil-engineering:static-analysis-triage` | Triage Pint/PHPStan/Psalm/TypeScript/Vitest failures with minimal patches |
| `/soleil-engineering:security-review` | Read-only security pass scoped to the harness, MCP, hooks, and CI |

## Agents

| Invocation | Purpose |
|---|---|
| `@agent-soleil-engineering:release-reviewer` | Pre-merge readiness review of gates, migrations, healthchecks, CI drift |
| `@agent-soleil-engineering:security-reviewer` | Pre-merge security audit of the harness surface (auth, secrets, MCP, hooks, CI) |

The plugin's `security-reviewer` complements the project-level `.claude/agents/security-reviewer.md` (which focuses on application code paths) by adding harness/MCP/CI coverage.

## MCP server

This plugin assumes the existing `soleil-review` MCP server is registered via the project-root `.mcp.json` (created alongside this plugin):

```jsonc
{
  "mcpServers": {
    "soleil-review": {
      "command": "node",
      "args": ["${SOLEIL_REPO_ROOT:-.}/mcp/soleil-mcp/dist/index.js"],
      "env": { "NODE_ENV": "development" }
    }
  }
}
```

Before first use:

```bash
export SOLEIL_REPO_ROOT="$PWD"     # bash / zsh
$env:SOLEIL_REPO_ROOT = (Get-Location).Path   # PowerShell

cd mcp/soleil-mcp
npm install
npm run build
npm run self-test                  # optional sanity check
```

The server is **read-only by design**. Its allowlist lives in `mcp/soleil-mcp/policy.json`. Do not extend the allowlist from this plugin.

## Hooks

| Event | Behavior |
|---|---|
| `SessionStart` | Inject a reminder of the plugin's skills, agents, and MCP boundary |
| `PreCompact` | Inject a reminder to preserve blockers/verification status across compaction |

Both hooks are read-only, never read secrets, never auto-commit, and degrade silently if `node` is unavailable on `PATH`. JSON output is built via Node so no external `jq` dependency is required.

## Security posture

- No skill, agent, or hook in this plugin reads `.env*`, `.git/`, `storage/oauth-*.key`, `*.pem`, or `*.key`.
- No skill, agent, or hook auto-formats, auto-commits, auto-pushes, or installs dependencies.
- All shell scripts use `set -euo pipefail` and route diagnostics to stderr to keep stdout protocol-clean for hook JSON.
- The `.mcp.json` registration uses `${SOLEIL_REPO_ROOT:-.}` so no machine-specific absolute path is committed.
- Project-level `.claude/settings.json` allow/deny lists remain authoritative — this plugin does not weaken them.

## Why a plugin (and not loose files in `.claude/`)

The rest of `.claude/` (`commands/`, `agents/`, `hooks/`, `skills/generated/`) is the always-on project control plane. This plugin under `.claude/plugins/soleil-engineering/` is a **packaged, versioned bundle** with its own manifest, namespaced slash commands (`/soleil-engineering:*`), and agent prefix (`@agent-soleil-engineering:*`). That makes it independently shippable to a marketplace or sibling repos without leaking the project-private memory, settings, or generated domain skills that live elsewhere in `.claude/`.
