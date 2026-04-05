#!/usr/bin/env bash
# PostToolUse hook: remind to run validation after frontend edits
# Exit 0 always (non-blocking)

# jq fail-open policy: if jq is missing, emit structured warning and skip
if ! command -v jq &>/dev/null; then
  echo "SOLEIL-HOOK-DEGRADED: hook=remind-frontend-validation reason=jq-missing timestamp=$(date -u +%Y-%m-%dT%H:%M:%SZ) severity=INFO" >&2
  audit_log="$(dirname "$0")/../hook-audit.log"
  echo "{\"event\":\"degraded\",\"hook\":\"remind-frontend-validation\",\"reason\":\"jq-missing\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" >> "$audit_log" 2>/dev/null
  exit 0
fi

path=$(cat | jq -r '.tool_input.file_path // .tool_input.path // empty')

if [ -z "$path" ]; then
  exit 0
fi

# Inject reminder context for frontend source edits
case "$path" in
  *frontend/src/*)
    echo '{"additionalContext":"Frontend source edited. Run `cd frontend && npx tsc --noEmit` and `cd frontend && npx vitest run` before marking complete."}'
    ;;
esac

exit 0
