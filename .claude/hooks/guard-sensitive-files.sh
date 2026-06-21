#!/usr/bin/env bash
# PreToolUse hook: block edits to sensitive files
# Exit 2 = block, Exit 0 = allow

# jq fail-open policy: if jq is missing, emit structured warning and degrade
if ! command -v jq &>/dev/null; then
  echo "SOLEIL-HOOK-DEGRADED: hook=guard-sensitive-files reason=jq-missing timestamp=$(date -u +%Y-%m-%dT%H:%M:%SZ) severity=WARNING" >&2
  audit_log="$(dirname "$0")/../hook-audit.log"
  echo "{\"event\":\"degraded\",\"hook\":\"guard-sensitive-files\",\"reason\":\"jq-missing\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" >> "$audit_log" 2>/dev/null
  exit 0
fi

path=$(cat | jq -r '.tool_input.file_path // .tool_input.path // empty')

if [ -z "$path" ]; then
  exit 0
fi

# Audit log path
audit_log="$(dirname "$0")/../hook-audit.log"

# Block edits to secret/credential files.
# .env.example is a tracked template (public placeholders, no real credentials),
# so it is exempted to match the documented policy in docs/HOOKS.md, which blocks
# ".env* (except .env.example)". The exempt arm must precede the *.env* arm below.
case "$path" in
  *.env.example)
    exit 0 ;;
  *.env*|*.key|*.pem|*.secret|*id_rsa*)
    echo '{"decision":"block","reason":"Editing sensitive file blocked by hook: '"$path"'"}' >&2
    echo "{\"event\":\"block\",\"hook\":\"guard-sensitive-files\",\"path\":\"$(echo "$path" | head -c 200)\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" >> "$audit_log" 2>/dev/null
    exit 2 ;;
esac

exit 0
