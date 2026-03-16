#!/usr/bin/env bash
# PreToolUse hook: block dangerous bash commands
# Exit 2 = block, Exit 0 = allow

# jq fail-open policy: if jq is missing, hook protection is degraded
if ! command -v jq &>/dev/null; then
  echo "WARNING: jq not found — dangerous-bash hook protection degraded" >&2
  exit 0
fi

cmd=$(cat | jq -r '.tool_input.command // empty')

if [ -z "$cmd" ]; then
  exit 0
fi

# Block destructive commands
case "$cmd" in
  *"rm -rf"*|*"git push --force"*|*"git reset --hard"*|*"git checkout -- ."*)
    echo '{"decision":"block","reason":"Destructive command blocked by hook. Use a safer alternative."}' >&2
    exit 2 ;;
  *"artisan tinker"*)
    echo '{"decision":"block","reason":"artisan tinker blocked — bypasses service/repository boundaries."}' >&2
    exit 2 ;;
  *APP_KEY*|*DB_PASSWORD*|*REDIS_PASSWORD*|*SECRET*|*private_key*)
    echo '{"decision":"block","reason":"Command references sensitive credentials — blocked by hook."}' >&2
    exit 2 ;;
esac

exit 0
