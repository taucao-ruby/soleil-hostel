#!/usr/bin/env bash
# PreToolUse hook: block edits to sensitive files
# Exit 2 = block, Exit 0 = allow

# jq fail-open policy: if jq is missing, hook protection is degraded
if ! command -v jq &>/dev/null; then
  echo "WARNING: jq not found — sensitive-file guard protection degraded" >&2
  exit 0
fi

path=$(cat | jq -r '.tool_input.file_path // .tool_input.path // empty')

if [ -z "$path" ]; then
  exit 0
fi

# Block edits to secret/credential files
case "$path" in
  *.env*|*.key|*.pem|*.secret|*id_rsa*)
    echo '{"decision":"block","reason":"Editing sensitive file blocked by hook: '"$path"'"}' >&2
    exit 2 ;;
esac

exit 0
