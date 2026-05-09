#!/usr/bin/env bash
# soleil-engineering / SessionStart
# Read-only reminder. Emits a single JSON object on stdout per Claude Code hook contract.
# JSON is built via Node (already required for mcp/soleil-mcp); jq is not assumed.
# All diagnostics go to stderr to keep stdout protocol-clean.

set -euo pipefail

if ! command -v node >/dev/null 2>&1; then
  echo "soleil-engineering hook: node not on PATH; skipping reminder" >&2
  exit 0
fi

REMINDER='soleil-engineering plugin active. Available:
  /soleil-engineering:release-gates           wraps scripts/ship.sh
  /soleil-engineering:static-analysis-triage  triage Pint/PHPStan/Psalm/tsc/vitest
  /soleil-engineering:security-review         harness + MCP + CI security pass
Agents:
  @agent-soleil-engineering:release-reviewer  read-only release readiness
  @agent-soleil-engineering:security-reviewer read-only security audit
MCP server "soleil-review" is read-only; respect its allowed_commands.'

REMINDER="$REMINDER" node -e '
const out = {
  hookSpecificOutput: {
    hookEventName: "SessionStart",
    additionalContext: process.env.REMINDER || ""
  }
};
process.stdout.write(JSON.stringify(out));
'
