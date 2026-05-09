#!/usr/bin/env bash
# soleil-engineering / PreCompact
# Read-only reminder injected before context compaction so the post-compact
# session preserves blocker / verification / changed-file context.
# JSON is built via Node (already required for mcp/soleil-mcp); jq is not assumed.
# stdout = JSON only; stderr = diagnostics.

set -euo pipefail

if ! command -v node >/dev/null 2>&1; then
  echo "soleil-engineering hook: node not on PATH; skipping reminder" >&2
  exit 0
fi

REMINDER='Before compacting, capture in the summary:
  - active blockers (failing gates, denied permissions, missing deps)
  - last verification status (which scripts/ship.sh gates passed/failed and exit codes)
  - changed file paths since last commit (git status --short)
  - any unresolved finding intended for docs/FINDINGS_BACKLOG.md
Do not drop these.'

REMINDER="$REMINDER" node -e '
const out = {
  hookSpecificOutput: {
    hookEventName: "PreCompact",
    additionalContext: process.env.REMINDER || ""
  }
};
process.stdout.write(JSON.stringify(out));
'
